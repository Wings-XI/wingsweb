<?php

/**
 *	@file lists.php
 *	Used to display lists from DB rows
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("output.php");
require_once("database.php");
require_once("zones.php");
require_once("jobs.php");
require_once("obfusutils.php");
require_once("user.php");

 
/**
 *	Query character list from the DB
 *	$where - WHERE clause for SQL statement
 */
function WGWQueryCharactersBy($where, $worldid=100, $debug=false)
{
	$query = "SELECT chars.charid AS id, charname, pos_zone, mjob, sjob, mlvl, slvl, nameflags
		FROM chars, char_stats
		WHERE chars.charid = char_stats.charid AND $where
		ORDER BY charname";
	if ($debug) {
		WGWOutput::$out->write("<p>Query: $query</p>");
	}
	return $result = WGWDB::$maps[$worldid]["db"]->query($query);
}
 
/**
 *	Displays a list of characters as a table
 *	$cursor - Result WGWQueryCharactersBy
 */
function WGWDisplayCharacterList($cursor, $withanon = false, $worldid=100)
{
	global $g_base;
	if (!$cursor) {
		WGWOutput::$out->write("Database query error<br>");
		return;
	}
	$clear_out = "<table border=\"0\"><tbody><tr>
		<td style=\"width: 150px\"><b>Name</b></td>
		<td style=\"width: 150px\"><b>Job</b></td>
		<td><b>Zone</b></td>
		</tr>";
	$is_admin = WGWUser::$user->is_admin();
	while ($row = $cursor->fetch_assoc()) {
		$tag_enter = "";
		$tag_end = "";
		if ($row["charname"][0] == ' ') {
			// Characters that begin with a space are deleted and will only
			// be shown to administrator
			if (!$is_admin) {
				continue;
			}
			$tag_enter = "<i>";
			$tag_end = "</i>";
		}
		$charname_esc = htmlspecialchars($row["charname"]);
		$charname_url = urlencode($row["charname"]);
		if ($row["nameflags"] & 0x1000 and !$withanon) {
			// Anonymous
			$job_str = "?/?";
		}
		else {
			$job_str = WGWGetFullJobString($row["mjob"],$row["mlvl"], $row["sjob"], $row["slvl"]);
		}
		$clear_out .= "<tr class=\"character\"><td>$tag_enter<a href=\"$g_base?page=character&worldid=$worldid&name=$charname_url\">$charname_esc</a>$tag_end</td>
		
			<td>$job_str</td>
			<td>" . WGWGetZoneName($row["pos_zone"]) . "</td>";
	}
	$clear_out .= "</tr></tbody></table>";
	WGWOutput::$out->write(WGWGetSelfDecodigStr($clear_out));
}


/**
 *	Query battlefield list from the DB
 *	$where - WHERE clause for SQL statement
 */
function WGWQueryBattlefieldsBy($where, $worldid=100, $debug=false)
{
	$query = "SELECT bcnm_info.bcnmid AS id, zoneId, name,
		fastestName, fastestPartySize, round(fastestTime / 60, 0) as fastest,
		previousName, previousPartySize, round(previousTime / 60, 0) as previous,
		round(timeLimit / 60, 0) as time, levelCap, partySize, isMission
		FROM bcnm_info
		WHERE $where
		ORDER BY zoneId, isMission, name";
	if ($debug) {
		WGWOutput::$out->write("<p>Query: $query</p>");
	}
	return $result = WGWDB::$maps[$worldid]["db"]->query($query);
}
 
/**
 *	Displays a list of battlefields as a table
 *	$cursor - Result WGWQueryBattlefieldsBy
 */
function WGWDisplayBattlefieldsList($cursor, $withanon = false, $worldid=100)
{
	global $g_base;
	if (!$cursor) {
		WGWOutput::$out->write("Database query error<br>");
		return;
	}
	$clear_out = "<table border=\"0\"><tbody><tr>
		<td><b>Name</b></td>
		<td><b>Zone</b></td>
		<td><b>Time</b></td>
		<td><b>Max<br>Plyr</b></td>
		<td><b>Max<br>Lvl</b></td>
		<td><b>Mission?</b></td>
		<td><b>Fastest</b></td>
		<td><b>Second</b></td>
		</tr>";
	while ($row = $cursor->fetch_assoc()) {
		$tag_enter = "";
		$tag_end = "";
		if ($row["isMission"] == 1) {
			$isMission = "Yes";
		}
		else {
			$isMission = "No";
		}
		$first = $row["fastestName"];
		if ($row["fastestPartySize"] == 1) {
			$first .= "<br>in " . $row["fastest"] . " mins";
		}
		if ($row["fastestPartySize"] > 1) {
			$first .= " and " . $row["fastestPartySize"] . " others";
			$first .= "<br>in " . $row["fastest"] . " mins";
		}
		$second = $row["previousName"];
		if ($row["previousPartySize"] == 1) {
			$second .= "<br>in " . $row["previous"] . " mins";
		}
		if ($row["previousPartySize"] > 1) {
			$second .= " and " . $row["previousPartySize"] . " others";
			$second .= "<br>in " . $row["previous"] . " mins";
		}
		$charname_esc = ucfirst(str_replace("_", " ", $row["name"]));
		$charname_url = urlencode($row["name"]);
		$clear_out .= "<tr class=\"character\"><td>$tag_enter<a href=\"/wangzthangz/bcnm.php?name=$charname_url\">$charname_esc</a>$tag_end</td>
			<td>" . WGWGetZoneName($row["zoneId"]) . "</td>
			<td>" . $row["time"] . "</td>
			<td>" . $row["partySize"] . "</td>
			<td>" . $row["levelCap"] . "</td>
			<td>" . $isMission . "</td>
			<td>" . $first . "</td>
			<td>" . $second . "</td>";
	}
	$clear_out .= "</tr></tbody></table>";
	WGWOutput::$out->write(WGWGetSelfDecodigStr($clear_out));
}

?>