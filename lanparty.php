<?php

/**
 *	@file lanparty.php
 *	Grants temporary IP exceptions to play with friends
 *	(C) 2022 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("output.php");
require_once("login.php");
require_once("lists.php");
require_once("user.php");
require_once("profile.php");
require_once("database.php");
require_once("configuration.php");

function WGWGrantTempException($accid)
{
	$sql = "UPDATE " . WGWConfig::$db_prefix . "accounts SET temp_exempt = DATE_ADD(UTC_TIMESTAMP(), INTERVAL " . strval(WGWConfig::$temp_ip_exception_length) . " SECOND) WHERE id = $accid";
	WGWDB::$con->query($sql);
	WGWDB::$con->query("COMMIT");
}

function WGWGetNextTempExceptionTime($accid)
{
	$result = WGWDB::$con->query("SELECT temp_exempt FROM " . WGWConfig::$db_prefix . "accounts WHERE id = $accid");
	if ((!$result) or ($result->num_rows == 0)) {
		return null;
	}
	$row = $result->fetch_row();
	if (!$row[0]) {
		// NULL value means they never used LAN party mode before so they are allowed
		$endtime = 0;
	}
	else {
		$endtime = strtotime($row[0]);
	}
	return array($endtime, $endtime + WGWConfig::$temp_ip_exception_cooldown);
}

function WGWShowLANPartyModeForm()
{
	global $g_base;
	
	WGWForceLogin();
	
	WGWOutput::$out->title = "LAN Party Mode";
	WGWOutput::$out->write("<h2>Enable temporary IP exception (LAN Party mode)</h2>");
	
	$accid = WGWUser::$user->id;
	$nexttime = WGWGetNextTempExceptionTime($accid);
	$now = time();
	if (!$nexttime) {
		// Should never happen
		WGWOutput::$out->write("An internal error has occurred.<br>");
		die(0);
	}
	if (array_key_exists("action", $_REQUEST) && $_REQUEST["action"] == "grant" && $nexttime[1] < $now) {
		// Actually grant the exception (if allowed)
		WGWGrantTempException($accid);
		$nexttime = WGWGetNextTempExceptionTime($accid);
		WGWOutput::$out->write("<p>Temporary IP exception granted!</p>");
	}
	if ($nexttime[0] >= $now) {
		// Exception is already enabled
		WGWOutput::$out->write("Temporary IP exception is <span style=\"color: green\">enabled</span> until " . date("D Y/m/d H:i:s", $nexttime[0]) . " UTC.<br>");
	}
	else if ($nexttime[1] >= $now) {
		// They already used it recently
		WGWOutput::$out->write("Temporary IP exception had already been used recently. It will be available again on " . date("D Y/m/d H:i:s", $nexttime[1]) . " UTC.<br>");
	}
	else {
		// Show the grant button
		WGWOutput::$out->write("<FORM METHOD=\"POST\" ONSUBMIT=\"return confirm('If you enable a temporary IP exception now you will not be able to use it again until the indicated time.\\nDo you wish to continue?');\">");
		WGWOutput::$out->write("<INPUT TYPE=\"HIDDEN\" NAME=\"page\" VALUE=\"lanparty\"><INPUT TYPE=\"HIDDEN\" NAME=\"action\" VALUE=\"grant\">");
		WGWOutput::$out->write("By clicking the button below you will receive a temporary IP exception until " . date("D Y/m/d H:i:s", $now + WGWConfig::$temp_ip_exception_length) . " UTC.<br>");
		WGWOutput::$out->write("Please note that once enabled the exception cannot be disabled until it expires. Once expired you will not be able to enable it again until " . date("D Y/m/d H:i:s", $now + WGWConfig::$temp_ip_exception_length + WGWConfig::$temp_ip_exception_cooldown) . " UTC.<br><br>");
		WGWOutput::$out->write("<INPUT TYPE=\"SUBMIT\" VALUE=\"Enable\"></FORM>");
	}
}

?>
