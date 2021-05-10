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
require_once("database.php");
require_once("zones.php");
require_once("jobs.php");
require_once("skills.php");

function WGWShowCharacterBasicInfo($charname, $worldid=100)
{
	global $g_base;
	
	$char_esc = htmlspecialchars($charname);
	$char_sql = WGWDB::$con->real_escape_string($charname);
	WGWOutput::$out->title = "Character: $char_esc";
	WGWOutput::$out->write("<h2>$char_esc</h2>");
	$result = WGWQueryCharactersBy("charname = '$char_sql'", $worldid);
	if ((!$result) or ($result->num_rows == 0)) {
		WGWOutput::$out->write("There is no character named $char_esc on this server.<br>");
		die(0);
	}
	$basic_info = $result->fetch_assoc();
	// We will use this a lot
	$charid = $basic_info["id"];
	WGWOutput::$out->write("<p>Server: " . WGWDB::$maps[$worldid]["name"] . "</p>");
	$result = WGWDB::$maps[$worldid]["db"]->query("SELECT * FROM accounts_sessions WHERE charid=$charid");
	$isonline = $result->num_rows ? true : false;
	if ($isonline) {
		WGWOutput::$out->write("<p style=\"color: green\">Online</p>");
	}
	else {
		WGWOutput::$out->write("<p style=\"color: red\">Offline</p>");
	}
	// If GM then only other GMs can see info
	$result = WGWDB::$maps[$worldid]["db"]->query("SELECT * FROM chars WHERE charid=$charid");
	$chardetails = $result->fetch_assoc();
	$isgm = $chardetails["gmlevel"] > 0;
	if ($isgm) {
		WGWOutput::$out->write("<p><b style=\"color: red\">Game Master</b></p>");
		if (!WGWUser::$user->is_admin()) {
			return;
		}
	}
	$ismentor = ($chardetails["nnameflags"] & 0x2000000) != 0;
	if ($ismentor) {
		WGWOutput::$out->write("<p><b style=\"color: blue\">Mentor</b></p>");
	}
	// Check their rank
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
		WGWOutput::$out->write("<p>Nation: $nation, Rank $rank</p>");
	}
	
	WGWOutput::$out->write("<p>" . WGWGetFullJobString($basic_info["mjob"],$basic_info["mlvl"], $basic_info["sjob"], $basic_info["slvl"]) . "<br>" .
		"Current location: " . WGWGetZoneName($basic_info["pos_zone"]) . "</p>");
	// Top table (because we're going to split the screen to two columns for better usage)
	WGWOutput::$out->write("<table border=\"0\" style=\"width: 100%\"><tbody><tr><td style=\"width: 50%; vertical-align: top\">");
	// Job list
	$jobs = WGWGetJobListForChar($charid);
	WGWOutput::$out->write("<h3>Jobs</h3><table border=\"0\" style=\"width: 15%\"><tbody>");
	foreach ($jobs as $job => $joblevel) {
		if ($joblevel != 0) {
			WGWOutput::$out->write("<tr><td style=\"width: 10px;\">$job</td><td style=\"text-align: right\">$joblevel</td></tr>");
		}
	}
	WGWOutput::$out->write("</tbody></table>");
	WGWOutput::$out->write("</td><td style=\"width: 50%; vertical-align: top;\">");
	WGWOutput::$out->write("<h3>Crafts</h3><table border=\"0\" style=\"width: 35%;\"><tbody>");
	$skills = WGWGetSkillListForChar($charid);
	// Crafting skills are 48-57
	global $g_wgwSkills;
	$hascrafts = false;
	for ($i = 48; $i <= 57; $i++) {
		if (array_key_exists($i, $skills)) {
			WGWOutput::$out->write("<tr><td style=\"width: 10px;\">$g_wgwSkills[$i]</td><td style=\"text-align: right; width: 10px\">" . $skills[$i] / 10 . "</td></tr>");
			$hascrafts = true;
		}
	}
	WGWOutput::$out->write("</tbody></table>");
	if (!$hascrafts) {
		WGWOutput::$out->write("No crafts are leveled");
	}
	WGWOutput::$out->write("</td></tr></tbody></table>");
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