<?php

/**
 *	@file jobs.php
 *	Maps FFXI job ID to the proper job name
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("database.php");
 
$g_wgwjobs = array(
    0=>"NONE",
    1=>"WAR",
    2=>"MNK",
    3=>"WHM",
    4=>"BLM",
    5=>"RDM",
    6=>"THF",
    7=>"PLD",
    8=>"DRK",
    9=>"BST",
    10=>"BRD",
    11=>"RNG",
    12=>"SAM",
    13=>"NIN",
    14=>"DRG",
    15=>"SMN",
    16=>"BLU",
    17=>"COR",
    18=>"PUP",
    19=>"DNC",
    20=>"SCH",
    21=>"GEO",
    22=>"RUN"
);

function WGWGetJobListForChar($charid, $worldid=100)
{
	global $g_wgwjobs;
	$jobs_str = implode(",", array_slice($g_wgwjobs, 1, 20));
	$query = "SELECT $jobs_str FROM char_jobs WHERE charid=$charid";
	$result = WGWDB::$maps[$worldid]["db"]->query($query);
	if ($result->num_rows == 0) {
		// Must return an array by convention
		return array();
	}
	return $result->fetch_assoc();
}

function WGWGetJobName($jobid)
{
	global $g_wgwjobs;
	if (array_key_exists($jobid, $g_wgwjobs)) {
		return $g_wgwjobs[$jobid];
	}
	else {
		return "UNK";
	}
}

function WGWGetFullJobString($mainjob, $mainlevel, $subjob=0, $sublevel=0)
{
	$result = WGWGetJobName($mainjob) . intval($mainlevel);
	if ($subjob != 0) {
		$result = $result . "/" . WGWGetJobName($subjob) . intval($sublevel);
	}
	return $result;
}

?>