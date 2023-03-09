<?php

/**
 *	@file skills.php
 *	Maps FFXI skill ID to the proper skill name
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("database.php");
 
$g_wgwSkills = array(
    0=>"Unknown",
    1=>"Hand to Hand",
    2=>"Daggger",
    3=>"Sword",
    4=>"Great Sword",
    5=>"Axe",
    6=>"Great Axe",
    7=>"Scythe",
    8=>"Polearm",
    9=>"Katana",
    10=>"Great Katana",
    11=>"Club",
    12=>"Staff",
    13=>"Reserved13",
    14=>"Reserved14",
    15=>"Reserved15",
    16=>"Reserved16",
    17=>"Reserved17",
    18=>"Reserved18",
    19=>"Reserved19",
    20=>"Reserved20",
    21=>"Reserved21",
    22=>"Automation: Melee",
    23=>"Automation: Ranged",
    24=>"Automation: Magic",
    25=>"Archery",
    26=>"Marksmanship",
    27=>"Throwing",
	28=>"Guarding",
	29=>"Evasion",
	30=>"Shield",
	31=>"Parrying",
	32=>"Divine",
	33=>"Healing",
	34=>"Enhancing",
	35=>"Enfeebling",
	36=>"Elemental",
	37=>"Dark",
	38=>"Summoning",
	39=>"Ninjutsu",
	40=>"Singing",
	41=>"String",
	42=>"Wind",
	43=>"Blue",
	44=>"Reserved44",
	45=>"Reserved45",
	46=>"Reserved46",
	47=>"Reserved47",
	48=>"Fishing",
	49=>"Woodworking",
	50=>"Smithing",
	51=>"Goldsmithing",
	52=>"Clothcraft",
	53=>"Leathercraft",
	54=>"Bonecraft",
	55=>"Alchemy",
	56=>"Cooking",
	57=>"Synergy",
	58=>"Riding",
	59=>"Digging",
	60=>"Reserved60",
	61=>"Reserved61",
	62=>"Reserved62",
	63=>"Unknown2"
);

function WGWGetSkillListForChar($charid, $worldid=100)
{
	$query = "SELECT skillid, concat(trim(round(value / 10,1)) + 0, ' (rank ', rank, ')') FROM char_skills WHERE charid=$charid";
	$result = WGWDB::$maps[$worldid]["db"]->query($query);
	if ($result->num_rows == 0) {
		// Must return an array by convention
		return array();
	}
	$skills = array();
	$row = $result->fetch_row();
	while ($row) {
		$skills[$row[0]] = $row[1];
		$row = $result->fetch_row();
	}
	return $skills;
}

function WGWGetSkillName($skillid)
{
	global $g_wgwSkills;
	if (array_key_exists($skillid, $g_wgwSkills)) {
		return $g_wgwSkills[$skillid];
	}
	else {
		return "Unknown";
	}
}

?>