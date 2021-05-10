<?php

/**
 *	@file staticpage.php
 *	Allows displaying of static HTML pages
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("output.php");

// Map of all recognized static pages to files on the disk
global $g_wgwStaticPages; 
$g_wgwStaticPages = array(
	"home" => array("home.html", "Home"),
	"play" => array("play.html", "How to play"),
	"rules" => array("rules.html", "Rules"),
	"server" => array("server.html", "Server Information"),
	"faqs" => array("faqs.html", "Frequently Asked Questions"),
	"support" => array("support.html", "Support")
);

function WGWShowStaticPage($page)
{
	global $g_wgwStaticPages;
	if (array_key_exists($page, $g_wgwStaticPages)) {
		WGWOutput::$out->title = $g_wgwStaticPages[$page][1];
		WGWOutput::$out->write(file_get_contents($g_wgwStaticPages[$page][0]));
		die(0);
	}
	else {
		WGWOutput::$out->write("No such static page.");
		die(1);
	}
}

?>
