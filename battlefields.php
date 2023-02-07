<?php

/**
 *	@file onlineusers.php
 *	Online users page, displays a list of currently connected users
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("database.php");
require_once("output.php");
require_once("lists.php");
require_once("user.php");

function WGWPrintBattlefields($worldid=100)
{
	global $g_base;
	WGWOutput::$out->title = "Battlefield records. An entry here implies the battlefield is enabled.";
	WGWOutput::$out->write("<p>World: <select name=\"world\" onchange=\"window.location.href='$g_base?page=battlefields&worldid='+this.value\">\n");
	foreach (WGWDB::$maps as $worldno => $worlddata) {
		if (WGWUser::$user->has_access_to_world($worldno)) {
			WGWOutput::$out->write("<option value=\"$worldno\"" . ($worldno == $worldid ? " selected" : "") . ">" . $worlddata["name"] . "</option>\n");
		}
	}
	WGWOutput::$out->write("</select></p>\n");
	if (!WGWUser::$user->has_access_to_world($worldid)) {
		WGWOutput::$out->write("The specified world does not exist or access is denied.<br>");
		die(0);
	}
	$where = "bcnm_info.fastestTime > 1 and bcnm_info.fastestTime < bcnm_info.timeLimit";
	$result = WGWQueryBattlefieldsBy($where, $worldid);
	WGWOutput::$out->write("<p>$result->num_rows battlefields currently enabled.</p>");
	WGWDisplayBattlefieldsList($result, false, $worldid);
}

function WGWShowBattlefields()
{
	$worldid = 100;
	if (array_key_exists("worldid", $_REQUEST)) {
		$worldid = intval($_REQUEST["worldid"]);
	}
	WGWPrintBattlefields($worldid);
}

?>
