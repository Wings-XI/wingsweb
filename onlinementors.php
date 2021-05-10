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

function WGWPrintOnlineMentors($worldid=100)
{
	$where = "chars.nnameflags & 0x2000000 != 0 AND chars.charid IN (SELECT charid FROM accounts_sessions)";
	if (!WGWUser::$user->is_admin()) {
		$where .= " AND chars.gmlevel = 0";
	}
	$result = WGWQueryCharactersBy($where, $worldid);	
	WGWOutput::$out->title = "Mentors";
	WGWOutput::$out->write("<p><b>Mentors</b> are volunteers who help assist new players to either Wings or FFXI in general.<br>" .
		"Please note that mentors are not game masters and do not have any special privileges other than the will to help.<br>" .
		"Mentors may be contacted via /tell in-game.</p>");
	WGWOutput::$out->write("<p>$result->num_rows mentors are currently available for help.</p>");
	WGWDisplayCharacterList($result);
}

?>
