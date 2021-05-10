<?php

/**
 *	@file callgm.php
 *	Functions for opening new GM tickets and tracking responses (player side)
 *	(C) 2021 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("database.php");
require_once("mqconnection.php");
require_once("user.php");
require_once("output.php");
require_once("login.php");

global $g_wgwMyTicketsBefore;
$g_wgwMyTicketsBefore = <<<EOS
<h1>My GM tickets</h1>

<form name="deletemsg">
<input type="hidden" name="page" value="mytickets">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="messageid">
</form>

<script type="text/javascript">
<!--

function DeleteMessage(messageid)
{
	if (confirm("Are you sure you wish to delete this message?")) {
		document.deletemsg.messageid.value = messageid;
		document.deletemsg.submit();
	}
}

// -->
</script>

<table border="1" width="100%" style="border-collapse: collapse;">
<tbody>
<tr>
<td width="10%" style="padding-left: 5px;"><b>Character</b></td>
<td width="35%" style="padding-left: 5px;"><b>Issue</b></td>
<td width="35%" style="padding-left: 5px;"><b>GM Response</b></td>
<td width="10%" style="padding-left: 5px;"><b>Status</b></td>
<td width="10%" style="padding-left: 5px;"><b>Delete</b></td>
</tr>
EOS;

global $g_wgwMyTicketsAfter;
$g_wgwMyTicketsAfter = <<<EOS
</tbody>
</table>
EOS;

global $g_wgwNewTicketBefore;
$g_wgwNewTicketBefore =	<<<EOS
<h1>New GM call</h1>
<form name="callgm" method="POST">
<input type="hidden" name="page" value="mytickets">
<input type="hidden" name="action" value="submit">
EOS;

global $g_wgwNewTicketMiddle;
$g_wgwNewTicketMiddle = <<<EOS
Character: <select name="char">
<option value="0" selected>None (account related)</option>
EOS;

global $g_wgwNewTicketAfter;
$g_wgwNewTicketAfter = <<<EOS
</select><br><br>
<textarea name="details" rows="5" cols="64" placeholder="Describe the issue here."></textarea><br><br>
<input type="submit">
</form>
<br><br><br>
EOS;


global $g_wgwTicketStatus;
$g_wgwTicketStatus = array(
	0 => "Pending",
	1 => "Received",
	2 => "Answered",
	3 => "Closed");
	
function WGWDeleteTicket($callid, $worldid=100)
{
	// Verify the user is not trying to delete other users' tickets
	$accid = WGWUser::$user->id;
	$sql = "SELECT messageid FROM char_gmmessage WHERE callid=$callid AND accid=$accid;";
	$result = WGWDB::$maps[$worldid]["db"]->query($sql);
	if ($result->num_rows == 0) {
		return;
	}
	$sql = "UPDATE char_gmmessage SET `read` = 1 WHERE callid=$callid AND accid=$accid;";
	WGWDB::WGWDB::$maps[$worldid]["db"]->query($sql);
	WGWDB::WGWDB::$maps[$worldid]["db"]->query("COMMIT");
	WGWUser::$user->update_gm_msg_count();
	WGWUser::$user->update_pending_ticket_count();
}


function WGWSubmitTicket()
{
	if (!array_key_exists("char", $_REQUEST) || !is_numeric($_REQUEST["char"])) {
		return;
	}
	if (!array_key_exists("details", $_REQUEST)) {
		return;
	}
	$worldid = 100;
	if (array_key_exists("worldid", $_REQUEST)) {
		$worldid = intval($_REQUEST["worldid"]);
	}
	$char = intval($_REQUEST["char"]);
	$charname = "";
	$accid = WGWUser::$user->id;
	$pos_x = 0.0;
	$pos_y = 0.0;
	$pos_z = 0.0;
	$pos_zone = 0;
	$harass = 0;
	$block = 0;
	$stuck = 0;
	$worldid = 100;
	if (array_key_exists("worldid", $_REQUEST)) {
		$worldid = intval($_REQUEST["worldid"]);
	}
	// Only if the request is character related, pull the character information
	if ($char) {
		$sql = "SELECT charname, pos_zone, pos_x, pos_y, pos_z FROM chars WHERE charid=$char AND accid=$accid;";
		$result = WGWDB::$maps[$worldid]["db"]->query($sql);
		if ($result->num_rows == 0) {
			// Don't allow users to submit tickets for characters not associated with their account
			return;
		}
		$row = $result->fetch_assoc();
		$charname = WGWDB::$con->real_escape_string($row["charname"]);
		$pos_zone = intval($row["pos_zone"]);
		$pos_x = floatval($row["pos_x"]);
		$pos_y = floatval($row["pos_y"]);
		$pos_z = floatval($row["pos_z"]);
	}
	if (array_key_exists("harass", $_REQUEST) && is_numeric($_REQUEST["harass"]) && $_REQUEST["harass"]) {
		$harass = 1;
	}
	if (array_key_exists("block", $_REQUEST) && is_numeric($_REQUEST["block"]) && $_REQUEST["block"]) {
		$block = 1;
	}
	if (array_key_exists("stuck", $_REQUEST) && is_numeric($_REQUEST["stuck"]) && $_REQUEST["stuck"]) {
		$stuck = 1;
	}
	$message = WGWDB::$maps[$worldid]["db"]->real_escape_string($_REQUEST["details"]);
	$sql = "INSERT INTO server_gmcalls (charid, charname, accid, zoneid, pos_x, pos_y, pos_z, version, message, harassment, stuck, blocked)
		VALUES ($char, '$charname', $accid, $pos_zone, $pos_x, $pos_y, $pos_z, 'Web', '$message', $harass, $stuck, $block);";
	WGWDB::$maps[$worldid]["db"]->query($sql);
	WGWDB::$maps[$worldid]["db"]->query("COMMIT;");
	WGWSendNotificationToGMs($message, $charname, $pos_zone);
	WGWUser::$user->update_gm_msg_count();
	WGWUser::$user->update_pending_ticket_count();
}

function WGWShowMyTickets($worldid=100)
{
	global $g_wgwMyTicketsBefore;
	global $g_wgwMyTicketsAfter;
	global $g_wgwTicketStatus;
	
	WGWForceLogin();
	
	WGWOutput::$out->write($g_wgwMyTicketsBefore);
	
	$accid = WGWUser::$user->id;
	$sql = "SELECT server_gmcalls.callid, server_gmcalls.charid, server_gmcalls.charname, server_gmcalls.message AS issue, char_gmmessage.message AS response, `status`
		FROM server_gmcalls
		LEFT JOIN char_gmmessage
		ON server_gmcalls.callid = char_gmmessage.callid
		AND server_gmcalls.charid = char_gmmessage.charid
		WHERE (server_gmcalls.charid IS NULL OR server_gmcalls.charid = 0 OR server_gmcalls.charid IN (SELECT charid FROM chars WHERE accid=$accid))
		AND server_gmcalls.accid=$accid AND (char_gmmessage.accid=$accid OR char_gmmessage.accid IS NULL)
		AND (`read` = 0 OR (`read` IS NULL AND `status` < 3));";
	$result = WGWDB::$maps[$worldid]["db"]->query($sql);
		
	while ($row = $result->fetch_assoc()) {
		WGWOutput::$out->write("<tr>");
		WGWOutput::$out->write("<td width=\"10%\" style=\"padding-left: 5px;\">" . (!empty($row["charname"]) ? htmlspecialchars($row["charname"]) : "&lt;N/A&gt;") . "</td>");
		WGWOutput::$out->write("<td width=\"35%\" style=\"padding-left: 5px;\">" . (!empty($row["issue"]) ? htmlspecialchars($row["issue"]) : "&lt;NULL&gt;") . "</td>");
		WGWOutput::$out->write("<td width=\"35%\" style=\"padding-left: 5px;\">" . (!empty($row["response"]) ? htmlspecialchars($row["response"]) : "&lt;Not answered yet&gt;") . "</td>");
		WGWOutput::$out->write("<td width=\"10%\" style=\"padding-left: 5px;\">" . $g_wgwTicketStatus[$row["status"]] . "</td>");
		if (!empty($row["response"])) {
			WGWOutput::$out->write("<td width=\"10%\" style=\"padding-left: 5px;\">" . "<a href=\"javascript:DeleteMessage(" . $row["callid"] . ");\">Delete</a>" . "</td>");
		}
		else {
			WGWOutput::$out->write("<td width=\"10%\" style=\"padding-left: 5px;\"></td>");
		}
		WGWOutput::$out->write("</tr>");
	}
	
	WGWOutput::$out->write($g_wgwMyTicketsAfter);
}

function WGWShowCallGMForm($isharass = false, $isblock = false, $isstuck = false, $worldid=100)
{
	global $g_wgwNewTicketBefore;
	global $g_wgwNewTicketMiddle;
	global $g_wgwNewTicketAfter;
	
	WGWForceLogin();
	
	WGWOutput::$out->write($g_wgwNewTicketBefore);
	WGWOutput::$out->write("<input type=\"hidden\" name=\"harass\" value=\"" . ($isharass ? "1" : "0") . "\">");
	WGWOutput::$out->write("<input type=\"hidden\" name=\"block\" value=\"" . ($isblock ? "1" : "0") . "\">");
	WGWOutput::$out->write("<input type=\"hidden\" name=\"stuck\" value=\"" . ($isstuck ? "1" : "0") . "\">");
	WGWOutput::$out->write($g_wgwNewTicketMiddle);
	$sql = "SELECT charid, charname FROM chars WHERE accid=" . WGWUser::$user->id . ";";
	$result = WGWDB::$maps[$worldid]["db"]->query($sql);
	while ($row = $result->fetch_assoc()) {
		WGWOutput::$out->write("<option value=\"" . strval($row["charid"]) . "\">" . htmlspecialchars($row["charname"]) . "</option>");
	}
	WGWOutput::$out->write($g_wgwNewTicketAfter);
}

function WGWHandleMyTickets()
{
	WGWForceLogin();
	
	$worldid = 100;
	if (array_key_exists("worldid", $_REQUEST)) {
		$worldid = intval($_REQEST["worldid"]);
	}
	
	if (array_key_exists("action", $_REQUEST)) {
		if ($_REQUEST["action"] == "new") {
			$harass = (array_key_exists("harass", $_REQUEST) && is_numeric($_REQUEST["harass"]) && intval($_REQUEST["harass"]));
			$block = (array_key_exists("block", $_REQUEST) && is_numeric($_REQUEST["block"]) && intval($_REQUEST["block"]));
			$stuck = (array_key_exists("stuck", $_REQUEST) && is_numeric($_REQUEST["stuck"]) && intval($_REQUEST["stuck"]));
			WGWShowCallGMForm($harass, $block, $stuck, $worldid);
		}
		elseif ($_REQUEST["action"] == "submit") {
			WGWSubmitTicket();
		}
		elseif ($_REQUEST["action"] == "delete") {
			if (array_key_exists("messageid", $_REQUEST) && is_numeric($_REQUEST["messageid"])) {
				WGWDeleteTicket(intval($_REQUEST["messageid"]), $worldid);
			}
		}
	}
	// Always show pending tickets
	WGWShowMyTickets($worldid);
}

?>
