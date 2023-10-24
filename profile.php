<?php

/**
 *	@file profile.php
 *	Account management
 *	(C) 2020-2022 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("output.php");
require_once("login.php");
require_once("lists.php");
require_once("user.php");
require_once("userutils.php");

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

function WGWShowWorldUserChars($worldid, $accountid)
{
	$contents = WGWGetAccountContentIDs($accountid);
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
	
	$accid = WGWUser::$user->id;
	$accname = WGWUser::$user->name;
	if (array_key_exists("account", $_REQUEST)) {
		if ($_REQUEST["account"] != $accname) {
			WGWForceAdmin();
		}
		$accname = $_REQUEST["account"];
		$accid = WGWAccountIDByName($accname);
	}
	
	$acct_esc = htmlspecialchars($accname);
	WGWOutput::$out->title = "Account - $acct_esc";
	WGWOutput::$out->write("<h2>$acct_esc</h2>");
	$acc_escaped = WGWDB::$con->real_escape_string($accname);
	$result = WGWDB::$con->query("SELECT *,(select concat(value,' (',chars.charname,')') from ffxiwings.char_vars left join ffxiwings.chars using (charid) where varname = '[NomadBon]Ticket' and chars.accid = id limit 1) as bonanzaticket FROM  " . WGWConfig::$db_prefix . "accounts WHERE username='$acc_escaped' LIMIT 1;");
	if (!$result || $result->num_rows == 0) {
		WGWOutput::$out->write("No such account!");
		return;
	}
	$acc_info = $result->fetch_assoc();
	$email = $acc_info["email"];
	$bonanzaticket = $acc_info["bonanzaticket"];
	$acc_status = $acc_info["status"];
	$create_time = $acc_info["timecreated"];
	if ($acc_status == 2) {
		// User is banned
		WGWOutput::$out->write("<p style=\"color: red\"><b>You are no longer welcome on this server</b></p>");
	}
	if ($acc_status == 3) {
		// Need an admin to verify
		WGWOutput::$out->write("<p style=\"color: red\"><b>Please wait for an administrator to verify your account</b></p>");
	}
	WGWOutput::$out->write("Creation time: $create_time<br>");
	WGWOutput::$out->write("Email: " . htmlspecialchars($email) . "<br>");
	WGWOutput::$out->write("&nbsp;<a href='https://www.bg-wiki.com/ffxi/Nomad_Mog_Bonanza_I'>Ticket Number</a>: $bonanzaticket<br>");
	if (WGWUser::$user->id == $accid) {
		// TODO: Support admin change, currently too tied to the current user
		WGWOutput::$out->write("<a href=\"$g_base?page=changemail\">Change E-mail</a><br>");
		WGWOutput::$out->write("<a href=\"$g_base?page=changepassword\">Change Password</a><br>");
		WGWOutput::$out->write("<a href=\"$g_base?page=lanparty\">Enable temporary IP exception (LAN party mode)</a><br>");
		WGWOutput::$out->write("<a href=\"$g_base?page=mfa\">Two factor authentication</a><br><br>");
		if (WGWUser::$user->is_admin()) {
			WGWOutput::$out->write("<a href=\"/gimmedbaccess/\">phpMyAdmin (only accessible when logged in via admin account)</a><br><br>");
		}
	}
	WGWOutput::$out->write("<h3>My Characters:</h3>");
	$num_chars = 0;
	foreach (WGWDB::$maps as $worldid => $worlddata) {
		// Can rely on the currently logged-in user since we wouldn't want to display
		// test world characters to a user who does not have access and a user without
		// test access wouldn't have any characters on that world.
		if (!WGWUser::$user->has_access_to_world($worldid)) {
			continue;
		}
		$num_chars = $num_chars + WGWShowWorldUserChars($worldid, $accid);
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
	WGWOutput::$out->title = "Change E-mail";
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
 