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

global $g_wgwPlayerSearchForm;
$g_wgwPlayerSearchForm = <<<EOS
<p>
<form>
<input type="hidden" name="page" value="playersearch">
<input type="text" name="name" size="30" placeholder="Enter character name or part of it">
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<input type="submit" value="Search">
</form>
</p>
EOS;

function WGWShowPlayerSearchForm()
{
	global $g_wgwPlayerSearchForm;
	WGWOutput::$out->title = "Player search";
	WGWOutput::$out->write($g_wgwPlayerSearchForm);
}

function WGWProcessPlayerSearch()
{
	WGWShowPlayerSearchForm();
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
			$where .= " AND gmlevel = 0";
		}
		$worldid = 100;
		if (array_key_exists("worldid", $_REQUEST)) {
			$worldid = intval($_REQUEST["worldid"]);
		}
		$result = WGWQueryCharactersBy($where, $worldid);
		if ($result->num_rows == 0) {
			WGWOutput::$out->write("<b>No characters matched the search criteria</b>");
			return;
		}
		WGWDisplayCharacterList($result);
	}
}

?>
