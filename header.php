<?php

/**
 *	@file header.php
 *	Page header and footer globals
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("user.php");

global $g_base;
$g_base = $_SERVER["PHP_SELF"];

global $g_wgwHeaderBefore;
$g_wgwHeaderBefore = <<<EOS
<!DOCTYPE html>
<html>
  <head>
  <link rel="stylesheet" href="">
    <title>Wings%TITLE%</title>
  </head>
  <body bgcolor=#cccccc>
    <table style="width: 100%;" border="0">
      <tbody>
        <tr>
          <td rowspan="1" colspan="2" style="background-color: #cccccc; padding: 0px;"><b
              style="font-size: 72px;"><img src="WingsXI.png"></b><!--<span style="background-color: red; color: white; vertical-align: 230%; font-size: 14px; font-weight: bold;">BETA</span>-->
            <br>
          </td>
        </tr>
        <tr>
          <td style="width: 187px; background-color: #cccccc; padding-left: 10px; padding-top: 20px; vertical-align: top"><p>
		    <br>
EOS;

global $g_wgwHeaderAfter;
$g_wgwHeaderAfter = <<<EOS
			</p></td>
          <td style="padding: 20px; vertical-align: top"><br>
EOS;

global $g_wgwMenu;
$g_wgwMenu = <<<EOS
			<br><b>Info</b><br>
            <a href="$g_base">Home</a><br>
			<a href="$g_base?page=rules">Rules</a><br>
			<a href="$g_base?page=server">Server Information</a><br>
            <a href="$g_base?page=play">How to connect</a><br>
			<a href="$g_base?page=faqs">FAQs</a><br>
			<a href="$g_base?page=addons">Supported Ashita Addons and Plugins</a><br>
			<a href="$g_base?page=support">Support</a><br>
			
			<br><b>Tools</b><br>
			<a href="$g_base?page=onlineusers">Who's Online</a><br>
			<a href="$g_base?page=onlinementors">Mentors</a><br>
			<a href="$g_base?page=playersearch">Player Search</a><br>
			<a href="$g_base?page=itemsearch">Item Search</a><br>

			<br><b>Community</b><br>
			<!-- Old discord now archived <a href="https://discord.gg/StXNpgXZtV">Discord</a><br> -->
			<a href="https://discord.gg/wNpVm35wbz">Our Discord</a><br>
			<a href="https://gitlab.com/ffxiwings/wings">Our Source Code</a>
EOS;

global $g_wgwLoggedInMenu;
$g_wgwLoggedInMenu = <<<EOS
<br><b>Account Management</b><br>
<a href="$g_base?page=profile">My Account</a><br>
<a href="$g_base?page=logout">Log out</a><br>
EOS;

global $g_wgwNotLoggedInMenu;
$g_wgwNotLoggedInMenu = <<<EOS
<br><b>Account Management</b><br>
<a href="$g_base?page=login">Log in</a><br>
<a href="$g_base?page=signup">Sign up</a><br>
EOS;

global $g_wgwFooter;
$g_wgwFooter = <<<EOS
          </td>
        </tr>
      </tbody>
    </table>
<footer>
      <p style="text-align:center" class="copyright">Website by Twilight and Gweivyth, &copy; 2019-<script>document.write(new Date().getFullYear())</script></p>
</footer>
  </body>
</html>
EOS;

function WGWGetPageHeader($title = null)
{
	global $g_base;
	global $g_wgwHeaderBefore;
	global $g_wgwHeaderAfter;
	global $g_wgwMenu;
	global $g_wgwLoggedInMenu;
	global $g_wgwNotLoggedInMenu;
	$header = str_replace("%TITLE%", $title ? " - $title" : "", $g_wgwHeaderBefore);
	if (WGWUser::$user->is_logged_in()) {
		$header = $header . "<b>" . htmlspecialchars(WGWUser::$user->name) . "</b><br>";
		$unreads = WGWUser::$user->unread_gm_messages;
		if ($unreads) {
			$header = $header . "<a style=\"color: red\" href=\"$g_base?page=mytickets\"><b>GM messages ($unreads)</b></a><br>";
		}
		if (WGWUser::$user->is_admin()) {
			$pending = WGWUser::$user->gm_pending_tickets;
			if ($pending) {
				$header = $header . "<a style=\"color: red\" href=\"$g_base?page=gmtickets\"><b>Pending tickets ($pending)</b></a><br>";
			}
		}
		$header = $header . $g_wgwLoggedInMenu;
	}
	else {
		$header = $header . $g_wgwNotLoggedInMenu;
	}
	$header = $header . $g_wgwMenu . $g_wgwHeaderAfter;
	return $header;
}

function WGWGetPageFooter()
{
	global $g_wgwFooter;
	return $g_wgwFooter;
}

?>
