<?php

/**
 *	@file userutils.php
 *	Misc utilities for user management.
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("database.php");

function WGWAccountIDByName($user)
{
	$user_escaped = WGWDB::$con->real_escape_string($user);
	$result = WGWDB::$con->query("SELECT id FROM " . WGWConfig::$db_prefix . "accounts WHERE username='$user_escaped' LIMIT 1");
	if ($result->num_rows != 0) {
		$row = $result->fetch_row();
		return $row[0];
	}
	return false;
}

function WGWUserNameByID($account)
{
	$result = WGWDB::$con->query("SELECT username FROM " . WGWConfig::$db_prefix . "accounts WHERE id=$account LIMIT 1");
	if ($result->num_rows != 0) {
		$row = $result->fetch_row();
		return $row[0];
	}
	return false;
}

function WGWAccountIDOfChar($charid, $worldid)
{
	$sql = "SELECT account_id FROM " . WGWConfig::$db_prefix. "contents WHERE content_id = (SELECT content_id FROM " . WGWConfig::$db_prefix. "chars WHERE character_id = $charid AND world_id = $worldid) LIMIT 1";
	$result = WGWDB::$con->query($sql);
	if ($result and $result->num_rows != 0) {
		$row = $result->fetch_row();
		return $row[0];
	}
	return false;
}

?>
