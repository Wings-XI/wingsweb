<?php

/**
 *	@file playershearch.php
 *	Allows searching for players by name
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("output.php");
require_once("lists.php");
require_once("database.php");
require_once("user.php");

global $g_wgwPlayerSearchFormHead;
$g_wgwPlayerSearchFormHead = <<<EOS
<p>
<form>
<input type="hidden" name="page" value="playersearch">
<input type="text" name="name" size="30" placeholder="Enter character name or part of it">
&nbsp;
EOS;

global $g_wgwPlayerSearchFormTail;
$g_wgwPlayerSearchFormTail = <<<EOS
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<input type="submit" value="Search">
</form>
</p>
EOS;

function WGWShowPlayerSearchForm($worldid=100)
{
	global $g_wgwPlayerSearchFormHead;
	global $g_wgwPlayerSearchFormTail;
	WGWOutput::$out->title = "Player search";
	WGWOutput::$out->write($g_wgwPlayerSearchFormHead);
	WGWOutput::$out->write("<select name=\"worldid\">\n");
	foreach (WGWDB::$maps as $worldno => $worlddata) {
		if (WGWUser::$user->has_access_to_world($worldno)) {
			WGWOutput::$out->write("<option value=\"$worldno\"" . ($worldno == $worldid ? " selected" : "") . ">" . $worlddata["name"] . "</option>\n");
		}
	}
	WGWOutput::$out->write("</select>\n");
	WGWOutput::$out->write($g_wgwPlayerSearchFormTail);
}

function WGWProcessPlayerSearch()
{
	$worldid = 100;
	if (array_key_exists("worldid", $_REQUEST)) {
		$worldid = intval($_REQUEST["worldid"]);
	}
	WGWShowPlayerSearchForm($worldid);
	if (array_key_exists("name", $_REQUEST)) {
		$name = $_REQUEST["name"];
		WGWOutput::$out->title = "Player search - " . htmlspecialchars($name);
		if (strlen($name) < 3) {
			WGWOutput::$out->write("<b style=\"color: red\">Please enter a minimum of three characters</b>");
			return;
		}
		if (!ctype_alpha($name)) {
			WGWOutput::$out->write("<b style=\"color: red\">Character names can only contain latin letters</b>");
			return;
		}
		$name_esc = $result = WGWDB::$con->real_escape_string($name);
		$where = "charname LIKE '%$name_esc%'";
		if (!WGWUser::$user->is_admin()) {
			$where .= " AND gmlevel < " . strval(WGWConfig::$gm_threshold);
		}
		if (!WGWUser::$user->has_access_to_world($worldid)) {
			WGWOutput::$out->write("<b style=\"color: red\">The selected world does not exist or access is denied</b>");
			return;
		}
		$result = WGWQueryCharactersBy($where, $worldid);
		if ($result->num_rows == 0) {
			WGWOutput::$out->write("<b>No characters matched the search criteria</b>");
			return;
		}
		WGWDisplayCharacterList($result, false, $worldid);
	}
}

?>
