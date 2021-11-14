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

function WGWPrintOnlineUsers($worldid=100)
{
	global $g_base;
	WGWOutput::$out->title = "Online users";
	WGWOutput::$out->write("<p>World: <select name=\"world\" onchange=\"window.location.href='$g_base?page=onlineusers&worldid='+this.value\">\n");
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
	$where = "chars.charid IN (SELECT charid FROM accounts_sessions)";
	if (!WGWUser::$user->is_admin()) {
		$where .= " AND chars.gmlevel < " . strval(WGWConfig::$gm_threshold);
	}
	$result = WGWQueryCharactersBy($where, $worldid);	
	WGWOutput::$out->write("<p>$result->num_rows players currently online.</p>");
	WGWDisplayCharacterList($result, false, $worldid);
}

function WGWShowOnlineUsersPage()
{
	$worldid = 100;
	if (array_key_exists("worldid", $_REQUEST)) {
		$worldid = intval($_REQUEST["worldid"]);
	}
	WGWPrintOnlineUsers($worldid);
}

?>
