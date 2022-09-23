<?php

/**
 *	@file login.php
 *	Handles the login page and submitted form
 *	(C) 2020-2021 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("output.php");
require_once("user.php");
require_once("staticpage.php");

global $g_wgwLoginPage;
global $g_wgwOTPPage;

$g_wgwLoginPage = <<<EOS
	<center>
		<form method="POST">
			<input type="hidden" name="page" value="login">
			<table border="0">
				<tbody>
					<tr>
						<td>Username: </td>
						<td><input type="text" name="user" size="30"></td>
					</tr>
					<tr>
						<td>Password: </td>
						<td><input type="password" name="pass" size="30"></td>
					</tr>
					<tr>
						<td></td>
						<td><center><input type="submit" value="Login"></center></td>
					</tr>
					<tr>
						<td><br><br>&nbsp;</td>
						<td><center><a href="$g_base?page=passwordreset">Forgot your password?</a></center></td>
					</tr>
				</tbody>
			</table>
		</form>
	</center>
EOS;

$g_wgwOTPPage = <<<EOS
	<center>
		<form method="POST">
			<input type="hidden" name="page" value="login">
			This account is secured with two-factor authentication.<br>
			Please enter the one time code shown in the authenticaor app.<br>
			<table border="0">
				<tbody>
					<tr>
						<td>One time code: </td>
						<td><input type="text" name="otp" size="30"></td>
					</tr>
					<tr>
						<td></td>
						<td><center><input type="submit" value="Login"></center></td>
					</tr>
					<tr>
						<td><br><br>&nbsp;</td>
						<td><center><a href="$g_base?page=logout">Change user</a></center></td>
					</tr>
				</tbody>
			</table>
		</form>
	</center>
EOS;

function WGWShowLoginForm($msg = null)
{
	global $g_wgwLoginPage;
	if (WGWUser::$user->is_logged_in()) {
		WGWUser::$user->logout();
	}
	WGWOutput::$out->title = "Login";
	WGWOutput::$out->write($g_wgwLoginPage);
	if ($msg) {
		WGWOutput::$out->write("<br><center><b style=\"color: red\">$msg</b></center>");
	}
	die(0);
}

function WGWShowOTPForm($msg = null)
{
	global $g_wgwOTPPage;
	if (WGWUser::$user->is_logged_in()) {
		WGWUser::$user->logout();
	}
	WGWOutput::$out->title = "Two Factor Authentication";
	WGWOutput::$out->write($g_wgwOTPPage);
	if ($msg) {
		WGWOutput::$out->write("<br><center><b style=\"color: red\">$msg</b></center>");
	}
	die(0);
}

function WGWProcessLogin()
{
	global $g_base;
	
	$success = false;
	if (!WGWUser::$user->is_logged_in()) {
		if (WGWUser::$user->mfaid > 0) {
			// Actually logged in but needs to go through MFA
			if (WGWUser::$user->otp_secret == null) {
				// Should never happen
				WGWShowLoginForm("Two factor authentication intenal error");
			}
			else if (array_key_exists("otp", $_REQUEST)) {
				$success = WGWUser::$user->domfa($_REQUEST["otp"]);
				if (!$success) {
					WGWShowOTPForm("Incorrect one time code");
				}
			}
			else {
				// Still need to enter the OTP
				WGWShowOTPForm();
			}
		}
		else if ((array_key_exists("user", $_REQUEST)) and (array_key_exists("pass", $_REQUEST))) {
			if (WGWUser::$user->login($_REQUEST["user"], $_REQUEST["pass"])) {
				$success = true;
			}
			else if (WGWUser::$user->mfaid > 0) {
				// Recursive call will hit previous check, yielding
				// the OTP request page
				WGWProcessLogin();
			}
			else {
				WGWShowLoginForm("Unknown username or bad password");
			}
		}
		else {
			WGWShowLoginForm();
		}
	}
	else {
		// Already logged-in
		$success = true;
	}
	if ($success) {
		header("Location: $g_base");
		// Shouldn't actually be displayed because of the redirect but
		// better be safe than sorry.
		WGWOutput::$out->title = "Login";
		WGWOutput::$out->write("Successfully logged-in.<br>");
	}
}

function WGWProcessLogout()
{
	if (WGWUser::$user->is_logged_in()) {
		WGWUser::$user->logout();
	}
	else if (WGWUser::$user->mfaid > 0) {
		WGWUser::$user->mfaid = 0;
		WGWUser::$user->otp_secret = null;
	}
	WGWShowLoginForm();
}

/**
 *	Should be called by any page that makes use of user data.
 *	This verifies that the user is logged-in and redirects to the login page
 *	if not. This is guaranteed to return only if the user is logged-in
 */
function WGWForceLogin()
{
	// Make sure output.php is included
	global $g_base;
	
	if (!WGWUser::$user->is_logged_in()) {
		WGWShowLoginForm();
	}
	if (!WGWUser::$user->status) {
		WGWOutput::$out->title = "Login";
		WGWOutput::$out->write("The account must be activated before accessing this page.
								<br><a href=\"$g_base?page=resend\">Resend activation mail</a>
								<br>If you do not receive an activation email, please join the Wings discord and submit a GM ticket including your account name and email to have your account manually verified.</br>");
		die(0);
	}
}

/**
 *	Should be called by any page that is reserved for GMs and administrators.
 *	This verifies that the user has administrator privileges and outputs an error
 *	if not. This is guaranteed to return only if the user is an administrator.
 */
function WGWForceAdmin()
{
	// First check that they're even logged in
	WGWForceLogin();
	if (!WGWUser::$user->is_admin()) {
		WGWOutput::$out->title = "Login";
		WGWOutput::$out->write("This function is reserved for game masters and system administrators.<br>");
		die(0);
	}
}

?>
