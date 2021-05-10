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
	$where = "chars.charid IN (SELECT charid FROM accounts_sessions)";
	if (!WGWUser::$user->is_admin()) {
		$where .= " AND chars.gmlevel = 0";
	}
	$result = WGWQueryCharactersBy($where, $worldid);	
	WGWOutput::$out->title = "Online users";
	WGWOutput::$out->write("<p>$result->num_rows players currently online.</p>");
	WGWDisplayCharacterList($result);
}

?>
