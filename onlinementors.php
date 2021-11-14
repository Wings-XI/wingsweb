<?php

/**
 *	@file onlinementors.php
 *	Online users page, displays a list of currently connected users
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("database.php");
require_once("output.php");
require_once("lists.php");
require_once("user.php");

function WGWPrintOnlineMentors($worldid=100)
{
	global $g_base;
	WGWOutput::$out->title = "Mentors";
	WGWOutput::$out->write("<p><b>Mentors</b> are volunteers who help assist new players to either Wings or FFXI in general.<br>" .
		"Mentors are not affiliated with Wings staff and any opinions and beliefs shared are their own.<br>" .
		"Please note that mentors are not game masters and do not have any special privileges other than the will to help.<br>" .
		"Mentors may be contacted via /tell in-game.</p>");
	WGWOutput::$out->write("<p>World: <select name=\"world\" onchange=\"window.location.href='$g_base?page=onlinementors&worldid='+this.value\">\n");
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
	$where = "chars.nnameflags & 0x2000000 != 0 AND chars.charid IN (SELECT charid FROM accounts_sessions)";
	if (!WGWUser::$user->is_admin()) {
		$where .= " AND chars.gmlevel < " . strval(WGWConfig::$gm_threshold);
	}
	$result = WGWQueryCharactersBy($where, $worldid);	
	WGWOutput::$out->write("<p>$result->num_rows mentors are currently available for help.</p>");
	WGWDisplayCharacterList($result, false, $worldid);
}

function WGWShowOnlineMentorsPage()
{
	$worldid = 100;
	if (array_key_exists("worldid", $_REQUEST)) {
		$worldid = intval($_REQUEST["worldid"]);
	}
	WGWPrintOnlineMentors($worldid);
}

?>
