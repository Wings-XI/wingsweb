<?php

/**
 *	@file index.php
 *	Main program, this is the only URL which the user should access directly.
 *	(C) 2020-2022 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
// Assume stuff loaded by composer
require_once("vendor/autoload.php");
 
require_once("configuration.php");
require_once("output.php");
require_once("staticpage.php");
require_once("logging.php");

if (array_key_exists("page", $_REQUEST)) {
	$page = $_REQUEST["page"];
}
else {
	$page = "home";
}

$debug = WGWConfig::$debug;

if ($debug) {
	WGWOutput::$out->write("<p><b>Warning! Debug mode enabled!</b><br>Page name: $page</p>");
}

if (WGWRequestContainsTrackedWords())
{
	WGWLogQuery();
}

$not_found = false;
switch ($page) {
	case "home":
		WGWShowStaticPage("home");
		break;
	case "play":
		WGWShowStaticPage("play");
		break;
	case "rules":
		WGWShowStaticPage("rules");
		break;
	case "server":
		WGWShowStaticPage("server");
		break;
	case "faqs":
		WGWShowStaticPage("faqs");
		break;
	case "support":
		WGWShowStaticPage("support");
		break;
	case "addons":
		WGWShowStaticPage("addons");
		break;
	case "mytickets":
		require_once("callgm.php");
		WGWHandleMyTickets();
		break;
	case "gmtickets":
		require_once("gmtickets.php");
		WGWHandleGMTickets();
		break;
	case "onlineusers":
		require_once("onlineusers.php");
		WGWShowOnlineUsersPage();
		break;
	case "onlinementors":
		require_once("onlinementors.php");
		WGWShowOnlineMentorsPage();
		break;
	case "login":
		require_once("login.php");
		WGWProcessLogin();
		break;
	case "logout":
		require_once("login.php");
		WGWProcessLogout();
		break;
	case "signup":
		require_once("signup.php");
		WGWProcessSignup();
		break;
	case "activate":
		require_once("activation.php");
		WGWProcessActivation();
		break;
	case "resend":
		require_once("activation.php");
		WGWResendActivationMail();
		break;
	case "profile":
		require_once("profile.php");
		WGWShowMainProfilePage();
		break;
	case "changemail":
		require_once("profile.php");
		WGWProcessEmailChange();
		break;
	case "changepassword":
		require_once("profile.php");
		WGWProcessPasswordChange();
		break;
	case "passwordreset":
		require_once("passwordreset.php");
		WGWProcessPasswordReset();
		break;
	case "dopasswordreset":
		require_once("passwordreset.php");
		WGWProcessDoPasswordReset();
		break;
	case "character":
		require_once("character.php");
		WGWShowCharacter();
		break;
	case "playersearch":
		require_once("playersearch.php");
		WGWProcessPlayerSearch();
		break;
	case "item":
		require_once("item.php");
		WGWShowItemInfo();
		break;
	case "itemsearch":
		require_once("itemsearch.php");
		WGWProcessItemSearch();
		break;
	case "migrate":
		require_once("migrate.php");
		WGWDoMigration();
		break;
	case "changerace":
		require_once("changerace.php");
		WGWRaceChange();
		break;
	case "lanparty":
		require_once("lanparty.php");
		WGWShowLANPartyModeForm();
		break;
	case "mfa":
		require_once("mfa.php");
		WGWShowMFAForm();
		break;
	case "countries":
		require_once("countries.php");
		DoCountryStats();
		break;
	case "testmail":
		require_once("email.php");
		WGWDoEmailTest();
		break;
	default:
		$not_found = true;
}

if ($not_found and $debug) {
	$not_found = false;
	switch ($page) {
		case "env":
			WGWOutput::$out->write("<pre>" . var_export($_SERVER, true) . "</pre>");
			break;
		default:
			$not_found = true;
	}
}

if ($not_found) {
	WGWOutput::$out->write("The requestd page was not found.");
}

?>
