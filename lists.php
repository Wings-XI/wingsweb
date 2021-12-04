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
 
?>