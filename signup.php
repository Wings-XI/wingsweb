<?php

/**
 *	@file signup.php
 *	Handles the user registration page and form submission
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("output.php");
require_once("user.php");
require_once("configuration.php");
require_once("serverutils.php");
require_once("database.php");

global $g_wgwSignupPage;
$g_wgwSignupPage = <<<EOS




	<center>
		<form method="POST" onsubmit="document.getElementById('btn_submit').disabled=true;return true;">
			<input type="hidden" name="page" value="signup">
			<span id="topmsg" style="%TOPMSGSTYLE%">%TOPMSG%</span><br><br>
			<table border="0">
				<tbody>
					<tr>
						<td>Username: <span style="font-size: x-small;"><br>At least 3 characters</span></td>
						<td><input type="text" name="user" size="38"></td>
					</tr>
					<tr>
						<td>Password: <span style="font-size: x-small;"><br>At least 6 characters</span></td>
						<td><input type="password" name="pass" size="38"></td>
					</tr>
					<tr>
						<td>Verify password: </td>
						<td><input type="password" name="verify" size="38"></td>
					</tr>
					<tr>
						<td>Email address: </td>
						<td><input type="text" name="email" size="38"></td>
					</tr>
					%VERIFYWARN%
					<tr>
						<td></td>
						<td>%CAPTCHA%</td>
					</tr>
					<tr>
						<td><br><br>&nbsp;</td>
						<td><center><input id="btn_submit" type="submit" value="Sign up"></center></td>
				</tbody>
			</table>
		</form>
		<p>By signing up you agree to the <a href="$g_base?page=rules">rules, terms of service and data collection policy</a>.</p>
	</center>

EOS;
global $g_wgwSignupSuccess;
$g_wgwSignupSuccess = <<<EOS
<h1>Registration Completed</h1>
You may now log-in.<br>
EOS;

global $g_wgwSignupAdminVerification;
$g_wgwSignupAdminVerification = <<<EOS
<h1>Admin / GM approval required</h1>
Currently new accounts require verification by an administrator or a GM.<br>
Please open a GM call on discord and ask for verification.<br>
EOS;

global $g_base;
global $g_wgwSignupNeedActivation;
$g_wgwSignupNeedActivation = <<<EOS
<h1>Just one more step</h1>
Befre you can use your new account, you will need to verify your e-mail address.<br>
We have sent a message to %EMAIL%.<br>
Please click the link in the message to activate your account.<br>
If you did not receive the mail, please check your spam folder. Gmail users, please check the social and promitions tabs as well.<br>
Please note that activation links are valid for 10 minutes.<br>
<a href="$g_base?page=resend">Resend activation mail</a><br><br>
If you do not receive an activation email, please join the Wings discord and submit a GM ticket including your account name and email to have your account manually verified.</br>
EOS;

function WGWIsThrottleBlocked()
{
	if (WGWConfig::$signup_threshold_accounts == 0 || WGWConfig::$signup_threshold_period == 0) {
		// Throttling disabled.
		return 0;
	}
	$num_acc = WGWConfig::$signup_threshold_accounts;
	$sql = "SELECT TIMESTAMPDIFF(SECOND, MIN(timecreated), NOW()) AS timethreshold FROM (SELECT timecreated FROM ww_accounts ORDER BY timecreated DESC LIMIT $num_acc) AS creatime;";
	$result = WGWDB::$con->query($sql);
	if (!$result || $result->num_rows == 0) {
		return 0;
	}
	$row = $result->fetch_row();
	if ($row[0] < WGWConfig::$signup_threshold_period) {
		return WGWConfig::$signup_threshold_period - $row[0];
	}
	return 0;
}

function WGWShowSignupForm($error_msg)
{
	global $g_wgwSignupPage;
	WGWOutput::$out->title = "Signup";
	if (WGWConfig::$recaptcha_enabled) {
		WGWOutput::$out->write("<script src='https://www.google.com/recaptcha/api.js' async defer></script>");
	}
	if ($error_msg) {
		$signup_page = str_replace("%TOPMSG%", $error_msg, $g_wgwSignupPage);
		$signup_page = str_replace("%TOPMSGSTYLE%", "color: red", $signup_page);
	}
	else {
		$signup_page = str_replace("%TOPMSG%", "Please fill the following details (all fields are mandatory)<br>Please note: Your account name will also be your name in the forums.", $g_wgwSignupPage);
		$signup_page = str_replace("%TOPMSGSTYLE%", "", $signup_page);
	}
	if (WGWConfig::$recaptcha_enabled) {
		$captcha_key = WGWConfig::$recaptcha_site_key;
		$signup_page = str_replace("%CAPTCHA%", "<br><div class=\"g-recaptcha\" data-sitekey=\"$captcha_key\"></div>", $signup_page);
	}
	else {
		$signup_page = str_replace("%CAPTCHA%", "", $signup_page);
	}
	if (WGWConfig::$verify_email) {
		$signup_page = str_replace("%VERIFYWARN%", "<tr><td colspan=\"2\">A verification link will be sent to the email address provided.<br>Verification of the address is required before the account can be used.</td></tr>", $signup_page);
	}
	else {
		$signup_page = str_replace("%VERIFYWARN%", "", $signup_page);
	}
	WGWOutput::$out->write($signup_page);
	die(0);
}

function WGWProcessSignup()
{
	global $g_wgwSignupSuccess;
	global $g_wgwSignupNeedActivation;
	$error_msg = "";
	WGWOutput::$out->title = "Signup";
	if (WGWUser::$user->is_logged_in()) {
		WGWUser::$user->logout();
	}
	if (!WGWConfig::$signup_allowed) {
		WGWOutput::$out->write("New user registrations are currently not available.");
		die(0);
	}
	$next_signup = WGWIsThrottleBlocked();
	if ($next_signup > 0) {
		$next_signup_min = ceil($next_signup / 60);
		WGWOutput::$out->write("Too many recent registrations. Please try again in $next_signup_min minutes.");
		die(0);
	}
	$msg = "";
	if ((array_key_exists("user", $_REQUEST)) and (array_key_exists("pass", $_REQUEST)) and
		(array_key_exists("verify", $_REQUEST)) and (array_key_exists("email", $_REQUEST))) {
		$msg = WGWUser::$user->signup($_REQUEST["user"], $_REQUEST["pass"], $_REQUEST["verify"], $_REQUEST["email"], WGWGetRemoteIPAddress());
		if ($msg === true) {
			// Registration was successful
			if (WGWConfig::$verify_email) {
				$outmsg = str_replace("%EMAIL%", htmlspecialchars($_REQUEST["email"]), $g_wgwSignupNeedActivation);
			}
			else if (WGWConfig::$signup_admin_verify_required) {
				$outmsg = $g_wgwSignupAdminVerification;
			}
			else {
				$outmsg = $g_wgwSignupSuccess;
			}
			WGWOutput::$out->write($outmsg);
			die(0);
		}
	}
	WGWShowSignupForm($msg);
} 

?>
