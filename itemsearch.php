<?php

/**
 *	@file itemsearch.php
 *	Allows searching for items on auction or bazaar
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("output.php");
require_once("lists.php");
require_once("database.php");

global $g_wgwItemSearchFormHead;
global $g_wgwItemSearchFormTail;
$g_wgwItemSearchFormHead = <<<EOS
<p>
<form>
<input type="hidden" name="page" value="itemsearch">
<input type="text" name="name" size="30" placeholder="Enter item name or part of it">
&nbsp;
EOS;

$g_wgwItemSearchFormTail = <<<EOS
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<input type="submit" value="Search">
</form>
</p>
EOS;

function WGWShowItemSearchForm($worldid=100)
{
	global $g_wgwItemSearchFormHead;
	global $g_wgwItemSearchFormTail;
	WGWOutput::$out->title = "Item search";
	WGWOutput::$out->write($g_wgwItemSearchFormHead);
	WGWOutput::$out->write("<select name=\"worldid\">\n");
	foreach (WGWDB::$maps as $worldno => $worlddata) {
		if (WGWUser::$user->has_access_to_world($worldno)) {
			WGWOutput::$out->write("<option value=\"$worldno\"" . ($worldno == $worldid ? " selected" : "") . ">" . $worlddata["name"] . "</option>\n");
		}
	}
	WGWOutput::$out->write("</select>\n");
	WGWOutput::$out->write($g_wgwItemSearchFormTail);
}

function WGWProcessItemSearch()
{
	global $g_base;
	
	$worldid = 100;
	if (array_key_exists("worldid", $_REQUEST)) {
		$worldid = intval($_REQUEST["worldid"]);
	}
	WGWShowItemSearchForm($worldid);
	if (array_key_exists("name", $_REQUEST)) {
		$name = $_REQUEST["name"];
		WGWOutput::$out->title = "Item search - " . htmlspecialchars($name);
		if (strlen($name) < 3) {
			WGWOutput::$out->write("<b style=\"color: red\">Please enter a minimum of three characters</b>");
			return;
		}
		$name_esc = $result = WGWDB::$con->real_escape_string(str_replace(" ", "_", $name));
		$result = WGWDB::$maps[$worldid]["db"]->query("SELECT itemid, name FROM item_basic WHERE name LIKE '%$name_esc%' OR sortname LIKE '%$name_esc%'");
		if ($result->num_rows == 0) {
			WGWOutput::$out->write("<b>No items matched the search criteria</b>");
			return;
		}
		WGWOutput::$out->write("<p>Found $result->num_rows items.</p>");
		$row = $result->fetch_row();
		while ($row) {
			$item_name = htmlspecialchars(ucfirst(str_replace("_", " ", $row[1])));
			WGWOutput::$out->write("<a class=\"character\" href=\"$g_base?page=item&worldid=$worldid&id=$row[0]\">$item_name</a><br>");
			$row = $result->fetch_row();
		}
	}
}

?>
