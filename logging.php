<?php

/**
 *	@file logging.php
 *	Logging and tracking routines
 *	(C) 2022 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */

require_once("configuration.php");
require_once("user.php");

function WGWRequestContainsTrackedWords()
{
	$tracked_lwr = array_map('strtolower', WGWConfig::$word_log);
	
	foreach ($tracked_lwr as $i) {
		foreach ($_GET as $k => $v) {
			if (strpos(strtolower($k), $i) !== false) {
				return true;
			}
			if (strpos(strtolower($v), $i) !== false) {
				return true;
			}
		}
		foreach ($_POST as $k => $v) {
			if (strpos(strtolower($k), $i) !== false) {
				return true;
			}
			if (strpos(strtolower($v), $i) !== false) {
				return true;
			}
		}
	}
	
	return false;
}

function WGWLogQuery()
{
	$hlog = fopen("logs/query_track.txt", "a");
	if ($hlog == false) {
		// Silent fail but proceed with processing the query.
		return false;
	}
	fputs($hlog, "Date: " . gmdate("M d Y H:i:s") . "\n");
	fputs($hlog, "IP Address: " . $_SERVER["REMOTE_ADDR"] . "\n");
	fputs($hlog, "User logged in: " . ((WGWUser::$user->is_logged_in()) ? "Yes" : "No") . "\n");
	if (WGWUser::$user->is_logged_in()) {
		fputs($hlog, "Logged in user: " . WGWUser::$user->name . "\n");
	}
	fputs($hlog, "GET:\n");
	if ($_GET != null) {
		fputs($hlog, var_export($_GET, true));
	}
	else {
		fputs($hlog, "null");
	}
	fputs($hlog, "\n");
	fputs($hlog, "POST:\n");
	if ($_POST != null) {
		fputs($hlog, var_export($_POST, true));
	}
	else {
		fputs($hlog, "null");
	}
	fputs($hlog, "\n\n");
	fclose($hlog);
}

?>
