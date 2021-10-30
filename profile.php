<?php

/**
 *	@file profile.php
 *	Account management
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("output.php");
require_once("login.php");
require_once("lists.php");
require_once("user.php");

global $g_wgwChangeMailForm;
$g_wgwChangeMailForm = <<<EOS
	<center>
		<form method="POST">
			<input type="hidden" name="page" value="changemail">
			<table border="0">
				<tbody>
					<tr>
						<td>Email: </td>
						<td><input type="text" name="email" size="30" value="%EMAIL%"></td>
					</tr>
					<tr>
						<td></td>
						<td><center><input type="submit" value="Save"></center></td>
					</tr>
				</tbody>
			</table>
		</form>
	</center>
EOS;

global $g_wgwPasswordChangeForm;
$g_wgwPasswordChangeForm = <<<EOS
	<center>
		<form method="POST">
			<input type="hidden" name="page" value="changepassword">
			<table border="0">
				<tbody>
					<tr>
						<td><center>Old Password: </center></td>
						<td><input type="password" name="old" size="30"></td>
					</tr>
					<tr>
						<td><center>New Password: </center></td>
						<td><input type="password" name="new" size="30"></td>
					</tr>
					<tr>
						<td><center>Verify Password: </center></td>
						<td><input type="password" name="verify" size="30"></td>
					</tr>
					<tr>
						<td></td>
						<td><center><input type="submit" value="Save"></center></td>
					</tr>
				</tbody>
			</table>
		</form>
	</center>
EOS;

function WGWShowWorldUserChars($worldid=100)
{
	$contents = WGWGetAccountContentIDs(WGWUser::$user->id);
	if (!$contents) {
		return 0;
	}
	$content_list = implode(", ", array_map('strval', $contents));
	// $result = WGWQueryCharactersBy("chars.accid = " . WGWUser::$user->id, $worldid);
	$result = WGWQueryCharactersBy("chars.content_id IN ($content_list)", $worldid);
	if (!$result || $result->num_rows == 0) {
		return 0;
	}
	$num_chars = $result->num_rows;
	WGWOutput::$out->write("<p>World: " . WGWDB::$maps[$worldid]["name"] . "<br>");
	WGWDisplayCharacterList($result, true, $worldid);
	WGWOutput::$out->write("</p>");
	return $num_chars;
}

function WGWShowMainProfilePage()
{
	global $g_base;
	
	WGWForceLogin();
	$acct_esc = htmlspecialchars(WGWUser::$user->name);
	WGWOutput::$out->title = "Account - $acct_esc";
	WGWOutput::$out->write("<h2>$acct_esc</h2>");
	if (WGWUser::$user->status == 2) {
		// User is banned
		WGWOutput::$out->write("<p style=\"color: red\"><b>You are no longer welcome on this server</b></p>");
	}
	if (WGWUser::$user->status == 3) {
		// Need an admin to verify
		WGWOutput::$out->write("<p style=\"color: red\"><b>Please wait for an administrator to verify your account</b></p>");
	}
	WGWOutput::$out->write("Email: " . htmlspecialchars(WGWUser::$user->email) . "<br>");
	WGWOutput::$out->write("<a href=\"$g_base?page=changemail\">Change E-mail</a><br>");
	WGWOutput::$out->write("<a href=\"$g_base?page=changepassword\">Change Password</a><br><br>");
	WGWOutput::$out->write("<h3>My Characters:</h3>");
	$num_chars = 0;
	foreach (WGWDB::$maps as $worldid => $worlddata) {
		if (!WGWUser::$user->has_access_to_world($worldid)) {
			continue;
		}
		$num_chars = $num_chars + WGWShowWorldUserChars($worldid);
	}
	if ($num_chars == 0) {
		WGWOutput::$out->write("No characters are associated with this account.<br>");
	}
}

function WGWShowChangeMailForm($msg=null)
{
	global $g_wgwChangeMailForm;
	
	WGWForceLogin();
	WGWOutput::$out->title = "Change E-mail";
	$changemailform = str_replace("%EMAIL%", addslashes(WGWUser::$user->email), $g_wgwChangeMailForm);
	WGWOutput::$out->write($changemailform);
	if ($msg) {
		WGWOutput::$out->write("<br><center><b style=\"color: red\">$msg</b></center>");
	}
	die(0);
}
 
function WGWProcessEmailChange()
{
	global $g_base;
	 
	WGWForceLogin();
	WGWOutput::$out->title =  "Change E-mail";
	if (array_key_exists("email", $_REQUEST)) {
		$result = WGWUser::$user->changemail($_REQUEST["email"]);
		if ($result === true) {
			// Succeeded
			header("Location: $g_base?page=profile");
			WGWOutput::$out->write("Email changed successfully<br>");
		}
		else {
			WGWShowChangeMailForm($result);
		}
	}
	WGWShowChangeMailForm();
}
 
function WGWShowPasswodChangeForm($msg=null)
{
	global $g_wgwPasswordChangeForm;
	
	WGWForceLogin();
	WGWOutput::$out->title = "Change password";
	WGWOutput::$out->write($g_wgwPasswordChangeForm);
	if ($msg) {
		WGWOutput::$out->write("<br><center><b style=\"color: red\">$msg</b></center>");
	}
	die(0);
}

function WGWProcessPasswordChange()
{
	global $g_base;
	 
	WGWForceLogin();
	WGWOutput::$out->title = "Change password";
	if ((array_key_exists("old", $_REQUEST)) and (array_key_exists("new", $_REQUEST)) and (array_key_exists("verify", $_REQUEST))) {
		$result = WGWUser::$user->changepassword($_REQUEST["old"], $_REQUEST["new"], $_REQUEST["verify"], WGWUser::$user->id);
		if ($result === true) {
			// Succeeded
			WGWOutput::$out->write("Password changed successfully!<br>");
			die(0);
		}
		else {
			WGWShowPasswodChangeForm($result);
		}
	}
	WGWShowPasswodChangeForm();
}

 ?>
 