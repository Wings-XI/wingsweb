<?php

/**
 *	@file gmtickets.php
 *	Functions for viewing and responding to GM tickets (GM side)
 *	(C) 2021 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("database.php");
require_once("user.php");
require_once("callgm.php");
require_once("zones.php");

// List of tickets

global $g_wgwGMTickersBefore;
$g_wgwGMTickersBefore = <<<EOS
<h1>GM Tickets</h1>

<form name="gmaction">
<input type="hidden" name="page" value="gmtickets">
<input type="hidden" name="action" value="">
<input type="hidden" name="callid">
</form>

<script type="text/javascript">
<!--

function CloseTicket(callid)
{
	if (confirm("Are you sure you wish to close this ticket?")) {
		document.gmaction.action.value = "close";
		document.gmaction.callid.value = callid;
		document.gmaction.submit();
	}
}

// -->
</script>

<table border="1" width="100%" style="border-collapse: collapse;">
<tbody>
<tr>
<td width="10%" style="padding-left: 5px;"><b>Character</b></td>
<td width="10%" style="padding-left: 5px;"><b>Account</b></td>
<td width="10%" style="padding-left: 5px;"><b>Time Opened</b></td>
<td width="10%" style="padding-left: 5px;"><b>Client Version</b></td>
<td width="10%" style="padding-left: 5px;"><b>Zone</b></td>
<td width="10%" style="padding-left: 5px;"><b>Issue</b></td>
<td width="10%" style="padding-left: 5px;"><b>Assignee</b></td>
<td width="10%" style="padding-left: 5px;"><b>Status</b></td>
<td width="10%" style="padding-left: 5px;"><b>GM Response</b></td>
<td width="10%" style="padding-left: 5px;"><b>Action</b></td>
</tr>
EOS;

global $g_wgwGMTickersAfter;
$g_wgwGMTickersAfter = <<<EOS
</tbody>
</table>
EOS;

/**
 *	Assign a ticket to a GM
 *	@param $callid Ticket to assign
 *	@param $gmid Char ID of the GM to assign to
 */
function WGWAssignTicket($callid, $gmid, $worldid=100)
{
	// Sanity
	if (!is_numeric($callid) || !is_numeric($gmid)) {
		return "Call ID and GM ID must be numeric.";
	}
	// Check that the ticket is not already assigned
	$sql = "SELECT assignee, `status` FROM server_gmcalls WHERE callid=$callid";
	$result = WGWDB::$maps[$worldid]["db"]->query($sql);
	if (!$result || $result->num_rows <= 0) {
		return "Ticket not found.";
	}
	$row = $result->fetch_assoc();
	if (($row["assignee"] && $row["assignee"] != "") || ($row["status"] >= 2)) {
		return "Ticket is already assigned.";
	}
	$status = $row["status"];
	// GMs can only assign tickets to themselves
	$accid = WGWUser::$user->id;
	$sql = "SELECT charname FROM chars WHERE charid=$gmid AND accid=$accid AND gmlevel > 0";
	$result = WGWDB::$maps[$worldid]["db"]->query($sql);
	if ($result->num_rows <= 0) {
		return "You can only assign tickets to your own GM characters.";
	}
	$row = $result->fetch_assoc();
	$gmname = $row["charname"];
	// Do the actual assignment
	$sql = "UPDATE server_gmcalls SET assignee='" . WGWDB::$con->real_escape_string($gmname) . "'";
	if ($status <= 0) {
		$sql = $sql . ", `status`=1";
	}
	$sql = $sql . " WHERE callid=$callid;";
	WGWDB::$maps[$worldid]["db"]->query($sql);
	WGWDB::$maps[$worldid]["db"]->query("COMMIT");
	WGWUser::$user->update_pending_ticket_count();
	return "";
}

function WGWReplyToTicket($callid, $gmid, $message, $close=false, $worldid=100)
{
	// Sanity
	if (!is_numeric($callid) || !is_numeric($gmid)) {
		return "Call ID and GM ID must be numeric.";
	}
	if (!$message || $message=="") {
		return "Reply message cannot be empty.";
	}
	// Check that the ticket is not already assigned
	$sql = "SELECT charid, accid, assignee, `status` FROM server_gmcalls WHERE callid=$callid";
	$result = WGWDB::$maps[$worldid]["db"]->query($sql);
	if (!$result) {
		return "Ticket not found.";
	}
	$row = $result->fetch_assoc();
	if ($row["status"] >= 2) {
		return "This ticket has already been answered or has been closed.";
	}
	$charid = $row["charid"];
	$accid = $row["accid"];
	$sql = "SELECT messageid FROM char_gmmessage WHERE callid=$callid";
	$result = WGWDB::$maps[$worldid]["db"]->query($sql);
	if ($result && $result->num_rows > 0) {
		return "This ticket has already been answered or has been closed.";
	}
	// GMs can only reply as themselves
	$accid = WGWUser::$user->id;
	$sql = "SELECT charname FROM chars WHERE charid=$gmid AND accid=$accid AND gmlevel > 0";
	$result = WGWDB::$maps[$worldid]["db"]->query($sql);
	if ($result->num_rows <= 0) {
		return "You can only reply as one of your own GM characters.";
	}
	// Reply now
	$sql = "INSERT INTO char_gmmessage (callid, charid, accid, gmid, datetime, message, `read`) VALUES ($callid, $charid, $accid, $gmid, NOW(), '" . WGWDB::$con->real_escape_string($message) . "', 0);";
	WGWDB::$maps[$worldid]["db"]->query($sql);
	WGWDB::$maps[$worldid]["db"]->query("COMMIT");
	// Set status
	$sql = "UPDATE server_gmcalls SET `status`=" . ($close ? 3 : 2) . " WHERE callid=$callid;";
	WGWDB::$maps[$worldid]["db"]->query($sql);
	WGWDB::$maps[$worldid]["db"]->query("COMMIT");
	WGWUser::$user->update_pending_ticket_count();
	return "";
}

/**
 *	Show a list of ticket according to a given filter
 *	@param $filter If true, will only show new tickets and unclosed tickets assigned to the user
 */
function WGWShowTicketList($filter, $worldid=100)
{
	global $g_wgwGMTickersBefore;
	global $g_wgwGMTickersAfter;
	global $g_wgwTicketStatus;
	global $g_base;
	
	WGWForceAdmin();
	
	WGWOutput::$out->write($g_wgwGMTickersBefore);
	
	$accid = WGWUser::$user->id;
	$sql = "SELECT server_gmcalls.callid, server_gmcalls.charid, server_gmcalls.charname, accounts.login,
			timesubmit, zoneid, version, harassment, stuck, blocked, assignee,
			server_gmcalls.message AS issue, chars.charname AS responder, char_gmmessage.message AS response,
			char_gmmessage.datetime AS resposnetime, server_gmcalls.`status`, `read`
		FROM server_gmcalls
		LEFT JOIN char_gmmessage
		ON server_gmcalls.callid = char_gmmessage.callid
		AND server_gmcalls.charid = char_gmmessage.charid
		LEFT JOIN chars
		ON gmid = chars.charname
		LEFT JOIN accounts
		ON server_gmcalls.accid = accounts.id";
	if ($filter) {
		$sql = $sql . " WHERE (server_gmcalls.`status` = 0) OR (assignee IN (SELECT charname FROM chars WHERE accid = $accid AND server_gmcalls.`status` < 3))";
	}
	$sql = $sql . ";";
	$result = WGWDB::$maps[$worldid]["db"]->query($sql);
	
	while ($row = $result->fetch_assoc()) {
		WGWOutput::$out->write("<tr>");
		WGWOutput::$out->write("<td width=\"10%\" style=\"padding-left: 5px;\">" . (!empty($row["charname"]) ? htmlspecialchars($row["charname"]) : "&lt;N/A&gt;") . "</td>");
		WGWOutput::$out->write("<td width=\"10%\" style=\"padding-left: 5px;\">" . (!empty($row["login"]) ? htmlspecialchars($row["login"]) : "&lt;N/A&gt;") . "</td>");
		WGWOutput::$out->write("<td width=\"10%\" style=\"padding-left: 5px;\">" . (!empty($row["timesubmit"]) ? htmlspecialchars($row["timesubmit"]) : "&lt;N/A&gt;") . "</td>");
		WGWOutput::$out->write("<td width=\"10%\" style=\"padding-left: 5px;\">" . (!empty($row["version"]) ? htmlspecialchars($row["version"]) : "&lt;N/A&gt;") . "</td>");
		WGWOutput::$out->write("<td width=\"10%\" style=\"padding-left: 5px;\">" . (!empty($row["zoneid"]) ? htmlspecialchars(WGWGetZoneName($row["zoneid"])) : "&lt;N/A&gt;") . "</td>");
		WGWOutput::$out->write("<td width=\"10%\" style=\"padding-left: 5px;\">" . (!empty($row["issue"]) ? htmlspecialchars($row["issue"]) : "&lt;NULL&gt;") . "</td>");
		WGWOutput::$out->write("<td width=\"10%\" style=\"padding-left: 5px;\">" . (!empty($row["assignee"]) ? htmlspecialchars($row["assignee"]) : "&lt;N/A&gt;") . "</td>");
		WGWOutput::$out->write("<td width=\"10%\" style=\"padding-left: 5px;\">" . $g_wgwTicketStatus[$row["status"]] . "</td>");
		WGWOutput::$out->write("<td width=\"10%\" style=\"padding-left: 5px;\">" . (!empty($row["response"]) ? htmlspecialchars($row["response"]) : "&lt;Not answered yet&gt;") . "</td>");
		// Action depends on the current status
		WGWOutput::$out->write("<td width=\"10%\" style=\"padding-left: 5px;\">");
		$status = $row["status"];
		if ($status <= 0) {
			WGWOutput::$out->write("<a href=\"$g_base?page=gmtickets&action=showassign&callid=" . $row["callid"] . "\">Assign</a><br>");
		}
		if ($status <= 1) {
			WGWOutput::$out->write("<a href=\"$g_base?page=gmtickets&action=showreply&callid=" . $row["callid"] . "\">Reply</a><br>");
		}
		if ($status <= 2) {
			WGWOutput::$out->write("<a href=\"javascript:CloseTicket(" . $row["callid"] . ");\">Close</a><br>");
		}
		WGWOutput::$out->write("</td></tr>");
	}
	
	WGWOutput::$out->write($g_wgwGMTickersAfter);
}

/**
 *	Show a select box with the GM characters on the current account.
 */
function DisplayGMCharSelect($worldid=100)
{
	WGWForceAdmin();
	
	$accid = WGWUser::$user->id;
	$sql = "SELECT charid, charname FROM chars WHERE accid=$accid AND gmlevel > 0";
	$result = WGWDB::$maps[$worldid]["db"]->query($sql);
	if ($result->num_rows <= 0) {
		// No GM chars
		return;
	}
	WGWOutput::$out->write("<select name=\"char\">");
	// First row is marked as selected
	$row = $result->fetch_assoc();
	WGWOutput::$out->write("<option value=\"" . $row["charid"] . "\" selected>" . htmlspecialchars($row["charname"]) . "</option>");
	while ($row = $result->fetch_assoc()) {
		WGWOutput::$out->write("<option value=\"" . $row["charid"] . "\">" . htmlspecialchars($row["charname"]) . "</option>");
	}
	WGWOutput::$out->write("</select>");
}

/**
 *	Display the selected ticket details (assignment and reply screen)
 *	@param $callid ID of the ticket to display
 */
function WGWDisplayTicketDetails($callid, $worldid=100)
{
	global $g_wgwTicketStatus;
	
	$sql = "SELECT server_gmcalls.callid, server_gmcalls.charid, server_gmcalls.charname, accounts.login,
			timesubmit, zoneid, version, harassment, stuck, blocked, assignee,
			server_gmcalls.message AS issue, server_gmcalls.`status`
		FROM server_gmcalls
		LEFT JOIN accounts ON server_gmcalls.accid  = accounts.id
		WHERE callid = $callid";
	$result = WGWDB::$maps[$worldid]["db"]->query($sql);
	if (!$result) {
		WGWOutput::$out->write("Invalid call ID!");
		return false;
	}
	$row = $result->fetch_assoc();
	WGWOutput::$out->write("<table><tbody>");
	WGWOutput::$out->write("<tr><td style=\"padding-left: 5px;\"><b>Caller:</b></td><td style=\"padding-left: 5px;\">" . (!empty($row["charname"]) ? htmlspecialchars($row["charname"]) : "&lt;N/A&gt;") . "</td></tr>");
	WGWOutput::$out->write("<tr><td style=\"padding-left: 5px;\"><b>Caller Account:</b></td><td style=\"padding-left: 5px;\">" . (!empty($row["login"]) ? htmlspecialchars($row["login"]) : "&lt;N/A&gt;") . "</td></tr>");
	WGWOutput::$out->write("<tr><td style=\"padding-left: 5px;\"><b>Time Submitted:</b></td><td style=\"padding-left: 5px;\">" . (!empty($row["timesubmit"]) ? htmlspecialchars($row["timesubmit"]) : "&lt;N/A&gt;") . "</td></tr>");
	WGWOutput::$out->write("<tr><td style=\"padding-left: 5px;\"><b>Zone:</b></td><td style=\"padding-left: 5px;\">" . (!empty($row["zoneid"]) ? htmlspecialchars(WGWGetZoneName($row["zoneid"])) : "&lt;N/A&gt;") . "</td></tr>");
	WGWOutput::$out->write("<tr><td style=\"padding-left: 5px;\"><b>Client Version:</b></td><td style=\"padding-left: 5px;\">" . (!empty($row["version"]) ? htmlspecialchars($row["version"]) : "&lt;N/A&gt;") . "</td></tr>");
	WGWOutput::$out->write("<tr><td style=\"padding-left: 5px;\"><b>Harassment:</b></td><td style=\"padding-left: 5px;\">" . (!empty($row["harassment"]) ? htmlspecialchars($row["harassment"]) : "&lt;N/A&gt;") . "</td></tr>");
	WGWOutput::$out->write("<tr><td style=\"padding-left: 5px;\"><b>Stuck:</b></td><td style=\"padding-left: 5px;\">" . (!empty($row["stuck"]) ? htmlspecialchars($row["stuck"]) : "&lt;N/A&gt;") . "</td></tr>");
	WGWOutput::$out->write("<tr><td style=\"padding-left: 5px;\"><b>Blocked:</b></td><td style=\"padding-left: 5px;\">" . (!empty($row["blocked"]) ? htmlspecialchars($row["blocked"]) : "&lt;N/A&gt;") . "</td></tr>");
	WGWOutput::$out->write("<tr><td style=\"padding-left: 5px;\"><b>Current Assignee:</b></td><td style=\"padding-left: 5px;\">" . (!empty($row["assignee"]) ? htmlspecialchars($row["assignee"]) : "&lt;N/A&gt;") . "</td></tr>");
	WGWOutput::$out->write("<tr><td style=\"padding-left: 5px;\"><b>Current Status:</b></td><td style=\"padding-left: 5px;\">" . $g_wgwTicketStatus[$row["status"]] . "</td></tr>");
	WGWOutput::$out->write("<tr><td style=\"padding-left: 5px;\"><b>Ticket Message:</b></td><td style=\"padding-left: 5px;\">" . (!empty($row["issue"]) ? htmlspecialchars($row["issue"]) : "&lt;N/A&gt;") . "</td></tr>");
	WGWOutput::$out->write("</tbody></table>");
	return true;
}

/**
 *	Assign tickets to GMs
 */
function WGWDoAssignTicket()
{
	if (!array_key_exists("callid", $_REQUEST) || !array_key_exists("char", $_REQUEST) ||
		!is_numeric($_REQUEST["callid"]) || !is_numeric($_REQUEST["char"])) {
		WGWOutput::$out->write("Invalid callid or GM id.");
		return;
	}
	$worldid = 100;
	if (array_key_exists("worldid", $_REQUEST)) {
		$worldid = intval($_REQUEST["worldid"]);
	}
	$result = WGWAssignTicket(intval($_REQUEST["callid"]), intval($_REQUEST["char"]), $worldid);
	if ($result != "") {
		// Result is the error to display
		WGWOutput::$out->write($result);
	}
}

/**
 *	Display the ticket assignment screen, where GMs can assign
 *	tickets to themselves.
 */
function WGWDisplayAssignScreen()
{
	WGWForceAdmin();
	
	WGWOutput::$out->write("<h1>Assign Ticket</h1><h2>Ticket Details</h2>");
	if (!array_key_exists("callid", $_REQUEST)) {
		WGWOutput::$out->write("No call ID specified.");
		return;
	}
	if (!is_numeric($_REQUEST["callid"])) {
		WGWOutput::$out->write("Call ID must be a number.");
		return;
	}
	$callid = intval($_REQUEST["callid"]);
	if (!WGWDisplayTicketDetails($callid)) {
		// Already displays an error so no need to print another
		return;
	}
	$worldid=100;
	if (array_key_exists("worldid", $_REQUEST)) {
		$worldid = intval($_REQUEST["worldid"]);
	}
	WGWOutput::$out->write("<h2>Assignment</h2>");
	WGWOutput::$out->write("<form name=\"assignment\"><input type=\"hidden\" name=\"page\" value=\"gmtickets\"><input type=\"hidden\" name=\"action\" value=\"assign\"><input type=\"hidden\" name=\"callid\" value=\"" . $_REQUEST["callid"] . "\">");
	WGWOutput::$out->write("Assign the ticket to the following GM: ");
	DisplayGMCharSelect($worldid);
	WGWOutput::$out->write("<br><br><input type=\"submit\" value=\"Assign\"><br></form>");
}

function WGWDisplayReplyScreen()
{
	WGWForceAdmin();

	WGWOutput::$out->write("<h1>Assign Ticket</h1><h2>Ticket Details</h2>");
	if (!array_key_exists("callid", $_REQUEST)) {
		WGWOutput::$out->write("No call ID specified.");
		return;
	}
	if (!is_numeric($_REQUEST["callid"])) {
		WGWOutput::$out->write("Call ID must be a number.");
		return;
	}
	$callid = intval($_REQUEST["callid"]);
	if (!WGWDisplayTicketDetails($callid)) {
		// Already displays an error so no need to print another
		return;
	}
	$worldid = 100;
	if (array_key_exists("worldid", $_REQUEST)) {
		$worldid = intval($_REQUEST["worldid"]);
	}
	$submitjs = <<<EOS
<script type="text/javascript">
<!--
function verifyReply()
{
	if (document.reply.message.value == "") {
		alert("You must write a response message.");
		return false;
	}
	return true;
}
// -->
</script>
EOS;
	WGWOutput::$out->write("<h2>Reply</h2>");
	WGWOutput::$out->write($submitjs);
	WGWOutput::$out->write("<form name=\"reply\" method=\"post\" onsubmit=\"return verifyReply();\"><input type=\"hidden\" name=\"page\" value=\"gmtickets\"><input type=\"hidden\" name=\"action\" value=\"reply\"><input type=\"hidden\" name=\"callid\" value=\"" . $_REQUEST["callid"] . "\">");
	WGWOutput::$out->write("Response Message:<br>");
	WGWOutput::$out->write("<textarea name=\"message\" rows=\"5\" cols=\"64\" placeholder=\"Enter reply message here.\"></textarea><br><br>");
	WGWOutput::$out->write("Reply as: ");
	DisplayGMCharSelect($worldid);
	WGWOutput::$out->write("<br><br><input type=\"checkbox\" name=\"close\" checked> Clock ticket<br><br>");
	WGWOutput::$out->write("<input type=\"submit\"><br></form>");
}

/**
 *	Reply to tickets
 */
function WGWDoReplyToTicket()
{
	if (!array_key_exists("callid", $_REQUEST) || !array_key_exists("char", $_REQUEST) ||
		!is_numeric($_REQUEST["callid"]) || !is_numeric($_REQUEST["char"])) {
		WGWOutput::$out->write("Invalid callid or GM id.");
		return;
	}
	$worldid = 100;
	if (array_key_exists("worldid", $_REQUEST)) {
		$worldid = intval($_REQUEST["worldid"]);
	}
	if (!array_key_exists("message", $_REQUEST) || $_REQUEST["message"] == "") {
		WGWOutput::$out->write("The reply message cannot be empty.");
		return;
	}
	$close = false;
	if (array_key_exists("close", $_REQUEST)  && $_REQUEST["close"]) {
		$close = true;
	}
	$result = WGWReplyToTicket(intval($_REQUEST["callid"]), intval($_REQUEST["char"]), $_REQUEST["message"], $close, $worldid);
	if ($result != "") {
		// Result is the error to display
		WGWOutput::$out->write($result);
	}
}

function WGWCloseTicket()
{
	if (!array_key_exists("callid", $_REQUEST) || !is_numeric($_REQUEST["callid"])) {
		WGWOutput::$out->write("Call ID is not valid.");
		return;
	}
	$callid = intval($_REQUEST["callid"]);
	// Check that they're not trying to close a ticket assigned to someone else
	$worldid = 100;
	if (array_key_exists("worldid", $_REQUEST)) {
		$worldid = intval($_REQUEST["worldid"]);
	}
	$sql = "SELECT assignee, `status` FROM server_gmcalls WHERE callid=$callid;";
	$result = WGWDB::$maps[$worldid]["db"]->query($sql);
	if (!$result || $result->num_rows <= 0) {
		WGWOutput::$out->write("Ticket not found.");
		return;
	}
	$row = $result->fetch_assoc();
	if ($row["status"] >= 3) {
		WGWOutput::$out->write("This ticket has already been closed.");
		return;
	}
	if ($row["assignee"] && $row["assignee"] != "") {
		$sql = "SELECT accid FROM chars WHERE charname='" . WGWDB::$con->real_escape_string($row["assignee"]) . "';";
		$result = WGWDB::$maps[$worldid]["db"]->query($sql);
		if ($result->num_rows > 0) {
			$row = $result->fetch_assoc();
			if ($row["accid"] != WGWUser::$user->id) {
				WGWOutput::$out->write("This ticket is assigned to someone else.");
				return;
			}
		}
	}
	// Do close
	$sql = "UPDATE server_gmcalls SET `status`=3 WHERE callid=$callid;";
	WGWDB::$maps[$worldid]["db"]->query($sql);
	WGWDB::$maps[$worldid]["db"]->query("COMMIT");
	WGWUser::$user->update_pending_ticket_count();
}

function WGWHandleGMTickets()
{
	WGWForceAdmin();
	
	$action = "";
	if (array_key_exists("action", $_REQUEST)) {
		$action = $_REQUEST["action"];
	}
	if ($action == "showassign") {
		WGWDisplayAssignScreen();
		return;
	}
	elseif ($action == "showreply") {
		WGWDisplayReplyScreen();
		return;
	}
	elseif ($action == "assign") {
		WGWDoAssignTicket();
		// No need to return because we go back to the ticket display
	}
	elseif ($action == "reply") {
		WGWDoReplyToTicket();
	}
	elseif ($action == "close") {
		WGWCloseTicket();
	}
	$filter = true;
	if (array_key_exists("all", $_REQUEST) && $_REQUEST["all"]) {
		$filter = false;
	}
	$worldid = 100;
	if (array_key_exists("worldid", $_REQUEST)) {
		$worldid = intval($_REQUEST["worldid"]);
	}
	WGWShowTicketList($filter, $worldid);
}
