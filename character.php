<?php

/**
 *	@file character.php
 *	Display character information
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("output.php");
require_once("login.php");
require_once("lists.php");
require_once("user.php");
require_once("userutils.php");
require_once("database.php");
require_once("zones.php");
require_once("jobs.php");
require_once("skills.php");
require_once("obfusutils.php");
require_once("configuration.php");

function WGWShowCharacterBasicInfo($charname, $worldid=100)
{
	global $g_base;
	
	$char_esc = htmlspecialchars($charname);
	$char_sql = WGWDB::$con->real_escape_string($charname);
	WGWOutput::$out->title = "Character: $char_esc";
	WGWOutput::$out->write("<h2>$char_esc</h2>");
	if (!WGWUser::$user->has_access_to_world($worldid)) {
		WGWOutput::$out->write("The specified world does not exist or access is denied.<br>");
		die(0);
	}
	$deleted = false;
	if ($charname[0] == ' ') {
		if (WGWUser::$user->is_admin()) {
			$deleted = true;
		}
		else {
			WGWOutput::$out->write("There is no character named $char_esc on this server.<br>");
			die(0);
		}
	}
	$result = WGWQueryCharactersBy("charname = '$char_sql'", $worldid);
	if ((!$result) or ($result->num_rows == 0)) {
		WGWOutput::$out->write("There is no character named $char_esc on this server.<br>");
		die(0);
	}
	$basic_info = $result->fetch_assoc();
	// We will use this a lot
	$charid = $basic_info["id"];
	$characcount = WGWAccountIDOfChar($charid, $worldid);
	$nameflags = $basic_info["nameflags"];
	$is_anon = $nameflags & 0x1000 ? true : false;
	$full_info = ($characcount == WGWUser::$user->id or WGWUser::$user->is_admin());
	WGWOutput::$out->write("<p>Server: " . WGWDB::$maps[$worldid]["name"] . "</p>");
	if ($deleted) {
		WGWOutput::$out->write("<p><b>Deleted</b></p>");
	}
	$result = WGWDB::$maps[$worldid]["db"]->query("SELECT * FROM accounts_sessions WHERE charid=$charid");
	$isonline = $result->num_rows ? true : false;
	$clear_out = "";
	if ($isonline) {
		$clear_out .= "<p style=\"color: green\">Online</p>";
	}
	else {
		$clear_out .= "<p style=\"color: red\">Offline</p>";
	}
	// If GM then only other GMs can see info
	$result = WGWDB::$maps[$worldid]["db"]->query("SELECT * FROM chars WHERE charid=$charid");
	$chardetails = $result->fetch_assoc();
	if ($full_info) {
		$create_time = $chardetails["timecreated"];
		$last_play = $chardetails["lastupdate"];
		$clear_out .= "<p>Creation time: $create_time<br>Last login: $last_play</p>";
	}
	$isgm = $chardetails["gmlevel"] >= WGWConfig::$gm_threshold;
	if ($isgm) {
		$clear_out .= "<p><b style=\"color: red\">Game Master</b></p>";
		if (!$full_info) {
			WGWOutput::$out->write(WGWGetSelfDecodigStr($clear_out));
			return;
		}
	}
	$ismentor = ($chardetails["nnameflags"] & 0x2000000) != 0;
	if ($ismentor) {
		$clear_out .= "<p><b style=\"color: blue\">Mentor</b></p>";
	}
	
	// Check their rank
	if (!$is_anon or $full_info) {
		$nation = "";
		$rank_column = "";
		$rank = 0;
		if ($chardetails["nation"] == 0) {
			$nation = "San d'Oria";
			$rank_column = "rank_sandoria";
		}
		else if ($chardetails["nation"] == 1) {
			$nation = "Bastok";
			$rank_column = "rank_bastok";
		}
		else if ($chardetails["nation"] == 2) {
			$nation = "Windurst";
			$rank_column = "rank_windurst";
		}
		if ($rank_column) {
			$result = WGWDB::$maps[$worldid]["db"]->query("SELECT $rank_column FROM char_profile WHERE charid=$charid");
			if ($result->num_rows) {
				$row = $result->fetch_row();
				$rank = $row[0];
			}
		}
		if (($nation) and ($rank)) {
			$clear_out .= "<p>Nation: $nation, Rank $rank</p>";
		}
		$job_str = WGWGetFullJobString($basic_info["mjob"],$basic_info["mlvl"], $basic_info["sjob"], $basic_info["slvl"]);
	}
	else {
		// Anonymous
		$job_str = "?/?";
	}
	
	$clear_out .= "<p>$job_str<br>" .
		"Current location: " . WGWGetZoneName($basic_info["pos_zone"]) . "</p>";
	// Top table (because we're going to split the screen to two columns for better usage)
	$clear_out .= "<table border=\"0\" style=\"width: 100%\"><tbody><tr><td style=\"width: 50%; vertical-align: top\">";
	if (!$is_anon or $full_info) {
		// Job list
		$jobs = WGWGetJobListForChar($charid, $worldid);
		$clear_out .= "<h3>Jobs</h3><table border=\"0\" style=\"width: 15%\"><tbody>";
		foreach ($jobs as $job => $joblevel) {
			if ($joblevel != 0) {
				$clear_out .= "<tr><td style=\"width: 10px;\">$job</td><td style=\"text-align: right\">$joblevel</td></tr>";
			}
		}
	}
	$clear_out .= "</tbody></table>";
	$clear_out .= "</td><td style=\"width: 40%; vertical-align: top;\">";
	if (!$is_anon) {
		$clear_out .= "<h3>Crafts</h3><table border=\"0\" style=\"width: 55%;\"><tbody>";
		$skills = WGWGetSkillListForChar($charid, $worldid);
		// Crafting skills are 48-57, 59 is digging
		global $g_wgwSkills;
		$hascrafts = false;
		for ($i = 48; $i <= 59; $i++) {
			if (array_key_exists($i, $skills) and $i != 58) {
				$clear_out .= "<tr><td style=\"\">$g_wgwSkills[$i]</td><td style=\"text-align: right;\">" . $skills[$i] . "</td></tr>";
				$hascrafts = true;
			}
		}
		$clear_out .= "</tbody></table>";
		if (!$hascrafts) {
			$clear_out .= "No crafts are leveled";
		}
	}
	$clear_out .= "</td></tr></tbody></table>";
	
	if ($full_info) {
		// Options menu shown only to the character owner and GMs
		$clear_out .= "<p>";
		if (WGWUser::$user->is_admin()) {
			$accid = WGWAccountIDOfChar($charid, $worldid);
			$accname = WGWUserNameByID($accid);
			$accname_escaped = htmlspecialchars($accname);
			$clear_out .= "Account: <a href=\"$g_base?page=profile&account=$accname_escaped\">$accname_escaped</a><br>";
		}
		$clear_out .= "<a href=\"$g_base?page=changerace&name=$char_esc&worldid=$worldid\">Change race and appearance</a>";
		$clear_out .= "</p>";
	}
	
	WGWOutput::$out->write(WGWGetSelfDecodigStr($clear_out));
}

function WGWShowCharacter()
{
	if (!array_key_exists("name", $_REQUEST)) {
		WGWOutput::$out->write("No character name was specified");
		die(0);
	}
	$worldid = 100;
	if (array_key_exists("worldid", $_REQUEST)) {
		$worldid = intval($_REQUEST["worldid"]);
	}
	$charname = $_REQUEST["name"];
	WGWShowCharacterBasicInfo($charname, $worldid);
}
 
?>