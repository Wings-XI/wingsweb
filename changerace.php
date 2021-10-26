<?php

/**
 *	@file changerace.php
 *	Allow changing characters' race and looks
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("output.php");
require_once("login.php");
require_once("lists.php");
require_once("user.php");
require_once("database.php");
//require_once("zones.php");
//require_once("jobs.php");
//require_once("skills.php");
//require_once("obfusutils.php");

global $g_WGWRaceMap;
$g_WGWRaceMap = array(1 => "Hume (Male)",
					  2 => "Hume (Female)",
					  3 => "Elvaan (Male)",
					  4 => "Elvaan (Female)",
					  5 => "Tarutaru (Male)",
					  6 => "Tarutaru (Female)",
					  7 => "Mithra (Female)",
					  8 => "Galka (Male)");
					  
global $g_WGWRaceChangeOnceWarn;
$g_WGWRaceChangeOnceWarn = <<<EOS
<script type="text/javascript">

function ConfirmRaceChange()
{
	return confirm("Once done, the change is permanent and cannot be changed again or reverted.\\nAre you sure you wish to continue?");
}
</script>

EOS;

function WGWSetRaceLook($charid, $worldid, $newdata)
{
	if (!is_array($new_data)) {
		return "No data to set.";
	}
	if (!array_key_exists("race", $newdata) || $newdata["race"] < 1 || $newdata["race"] > 8) {
		return "Invalid race value.";
	}
	$race = $newdata["race"];
	if (!array_key_exists("face", $newdata) || $newdata["face"] < 0 || $newdata["face"] > 8) {
		return "Invalid face value.";
	}
	if (!array_key_exists("hair", $newdata) || $newdata["hair"] < 0 || $newdata["hair"] > 1) {
		return "Invalid hair value.";
	}
	$face = $newdata["face"] * 2 + $newdata["hair"];
	if (!array_key_exists("size", $newdata) || $newdata["size"] < 0 || $newdata["size"] > 2) {
		return "Invalid size value.";
	}
	$size = $newdata["size"];
	// Set the character var that indicates that the name has already been changed.
	// Whether this is actually checked or not is part of the form logic.
	WGWDB::$maps[$worldid]["db"]->query("INSERT INTO char_vars (charid, varname, value) VALUES ($charid, \"RACE_CHANGED\", 1)");
	// Update in world DB
	WGWDB::$maps[$worldid]["db"]->query("UPDATE char_look SET race = $race, face = $face, size = $size WHERE charid = $charid");
	WGWDB::$maps[$worldid]["db"]->query("COMMIT");
	// Update in login DB (not really needed since it's copied on connect but better be safe than sorry)
	WGWDB::$con->query("UPDATE " . WGWConfig::$db_prefix . "chars SET race = $race, face = $face, size = $size WHERE character_id = $charid AND world_id = $worldid");
	WGWDB::$con->query("COMMIT");
}

function WGWShowChangeRaceForm($charname, $worldid=100, $newdata = null)
{
	global $g_WGWRaceMap;
	global $g_WGWRaceChangeOnceWarn;
	$char_esc = htmlspecialchars($charname);
	$char_sql = WGWDB::$con->real_escape_string($charname);
	WGWOutput::$out->title = "Change race";
	WGWOutput::$out->write("<h2>Change race of $char_esc</h2>");
	if (!WGWUser::$user->has_access_to_world($worldid)) {
		WGWOutput::$out->write("The specified world does not exist or access is denied.<br>");
		die(0);
	}
	$result = WGWQueryCharactersBy("charname = '$char_sql'", $worldid);
	if ((!$result) or ($result->num_rows == 0)) {
		WGWOutput::$out->write("There is no character named $char_esc on this server.<br>");
		die(0);
	}
	$basic_info = $result->fetch_assoc();
	$charid = $basic_info["id"];
	$characcount = WGWAccountIDOfChar($charid, $worldid);
	$mychar = ($characcount == WGWUser::$user->id or WGWUser::$user->is_admin());
	$result = WGWDB::$maps[$worldid]["db"]->query("SELECT * FROM chars WHERE charid=$charid");
	$chardetails = $result->fetch_assoc();
	$isgm = $chardetails["gmlevel"] > 0;
	if ($isgm) {
		if (!WGWUser::$user->is_admin() && !$mychar) {
			WGWOutput::$out->write("Cannot display information on Game Master characters.<br>");
			return;
		}
	}
	$result = WGWDB::$maps[$worldid]["db"]->query("SELECT * FROM accounts_sessions WHERE charid=$charid");
	$isonline = $result->num_rows ? true : false;

	$result = WGWDB::$maps[$worldid]["db"]->query("SELECT * FROM char_look WHERE charid=$charid");
	if ((!$result) or ($result->num_rows == 0)) {
		WGWOutput::$out->write("Race query failed.<br>");
		die(0);
	}
	$char_look = $result->fetch_assoc();
	
	// Check if we're allowed to change race
	$disable = " disabled";
	$change_allowed = WGWConfig::$allow_race_change;
	if (!$mychar) {
		// Never allow changing of other people's characters
		WGWOutput::$out->write("<p>You can only change the race of your own characters.</p>");
	}
	else if ($isonline) {
		WGWOutput::$out->write("<p>You are currently logged-in. Please log-out before changing your race.</p>");
	}
	else if (WGWUser::$user->is_admin()) {
		// Admins not subject to change policies
		$disable = "";
	}
	else if ($change_allowed == 2) {
		// Always allowed
		$disable = "";
	}
	else if ($change_allowed == 1) {
		// Allowed only once, test if already set
		$result = WGWDB::$maps[$worldid]["db"]->query("SELECT * FROM char_vars WHERE charid=$charid AND varname=\"RACE_CHANGED\"");
		if (!$result) {
			WGWOutput::$out->write("Change permission query failed.<br>");
			die(0);
		}
		$already_changed = false;
		if ($result->num_rows != 0) {
			$change_perms = $result->fetch_assoc();
			if ($change_perms["value"] != 0) {
				$already_changed = true;
			}
		}
		if ($already_changed) {
			WGWOutput::$out->write("<p>You have already changed your race; cannot change again.</p>");
		}
		else {
			WGWOutput::$out->write("<p>You can only change your race once so choose wisely.</p>");
			$disable = "";
		}
	}
	else {
		WGWOutput::$out->write("<p>Race changes are currently disabled.</p>");
	}
	
	$change_warn = "";
	if ($change_allowed == 1) {
		WGWOutput::$out->write($g_WGWRaceChangeOnceWarn);
		$change_warn = " onsubmit=\"return ConfirmRaceChange();\"";
	}
	WGWOutput::$out->write("<form method=\"POST\" $change_warn><table border=\"0\"><tbody>\n");
	WGWOutput::$out->write("<input type=\"hidden\" name=\"page\" value=\"changerace\">\n");
	WGWOutput::$out->write("<tr><td>Race:</td><td><select name=\"race\" $disable>\n");
	for ($i = 1; $i <= 8; $i++) {
		WGWOutput::$out->write("<option value=\"$i\"". (($char_look["race"] == $i) ? " selected" : "") . ">" . $g_WGWRaceMap[$i] . "</option>\n");
	}
	WGWOutput::$out->write("</select></td></tr>\n");
	// Face and hair are actually compbined in the DB into one vale
	$face = floor($char_look["face"] / 2);
	$hair = $char_look["face"] % 2;
	WGWOutput::$out->write("<tr><td>Face:</td><td><select name=\"face\" $disable>\n");
	for ($i = 0; $i <= 7; $i++) {
		WGWOutput::$out->write("<option value=\"$i\"" . (($face == $i) ? " selected" : "") . ">" . $i + 1 . "</option>\n");
	}
	WGWOutput::$out->write("</select></td></tr>\n");
	WGWOutput::$out->write("<tr><td>Hair:</td><td><select name=\"hair\" $disable>\n");
	WGWOutput::$out->write("<option value=\"0\"" . (($hair == 0) ? " selected" : "") . ">A</option>\n");
	WGWOutput::$out->write("<option value=\"1\"" . (($hair == 1) ? " selected" : "") . ">B</option>\n");
	WGWOutput::$out->write("</select></td></tr>\n");
	WGWOutput::$out->write("<tr><td>Size:</td><td><select name=\"size\" $disable>\n");
	WGWOutput::$out->write("<option value=\"0\"" . (($char_look["size"] == 0) ? " selected" : "") . ">Small</option>\n");
	WGWOutput::$out->write("<option value=\"1\"" . (($char_look["size"] == 1) ? " selected" : "") . ">Medium</option>\n");
	WGWOutput::$out->write("<option value=\"2\"" . (($char_look["size"] == 2) ? " selected" : "") . ">Large</option>\n");
	WGWOutput::$out->write("</select></td></tr>\n");
	WGWOutput::$out->write("</tbody></table>\n");
	WGWOutput::$out->write("<p><b>Do not change your race if you have any RSE pieces equipped!</b></p>\n");
	WGWOutput::$out->write("<p><input type=\"submit\" value=\"Change\">&nbsp;&nbsp;<input type=\"reset\" value=\"Reset\"></p></form>\n");
}

function WGWRaceChange()
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
	$newdata = null;
	// Check if we actually do the name change now
	// (form has been submitted)
	if (array_key_exists("race", $_REQUEST) &&
		array_key_exists("face", $_REQUEST) &&
		array_key_exists("hair", $_REQUEST) &&
		array_key_exists("size", $_REQUEST)) {
		$newdata = array("race" => intval($_REQUEST["race"]),
						 "face" => intval($_REQUEST["face"]),
						 "hair" => intval($_REQUEST["hair"]),
						 "size" => intval($_REQUEST["size"]));
	}
	WGWShowChangeRaceForm($charname, $worldid, $newdata);
}

?>
