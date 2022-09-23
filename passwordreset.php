<?php

/**
 *	@file passwordreet.php
 *	Allows users to reset forgotten passwords using links sent by E-mail
 *	(C) 2020-2022 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("userutils.php");
require_once("database.php");
require_once("configuration.php");
require_once("email.php");
require_once("user.php");
 
global $g_wgwPasswordResetTitle;
if (WGWConfig::$passreset_title) {
	$g_wgwPasswordResetTitle = WGWConfig::$passreset_title;
}
else {
	$g_wgwPasswordResetTitle = "Reset your password";
}
global $g_wgwPasswordResetMessage;
if (WGWConfig::$passreset_message) {
	$g_wgwPasswordResetMessage = WGWConfig::$passreset_message;
}
else {
	$g_wgwPasswordResetMessage = "You have requested to reset your account's password.<br>If you did not expect this message please ignore it.<br>Your username is: <b>%USER%</b><br>";
}
global $g_wgwPasswordResetBody;
$g_wgwPasswordResetBody = <<<EOS
<html>
<head>
<title>$g_wgwPasswordResetTitle</title>
</head>
<body>
$g_wgwPasswordResetMessage;
<br>
<a href="%PASSRESETLINK%">%PASSRESETLINK%</a><br>
</body>
</html>
EOS;

// Form that is displayed after the user clicks the mail link
global $g_wgwDoPassResetForm;
$g_wgwDoPassResetForm = <<<EOS
	<center>
		<form method="POST">
			<input type="hidden" name="page" value="dopasswordreset">
			<input type="hidden" name="user" value="%USER%">
			<input type="hidden" name="token" value="%TOKEN%">
			<table border="0">
				<tbody>
					<tr>
						<td>New Password: </td>
						<td><input type="password" name="pass" size="30"></td>
					</tr>
					<tr>
						<td>Verify Password: </td>
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

// Form that is displayed to request the reset link
global $g_wgwPassResetForm;
$g_wgwPassResetForm = <<<EOS
	<center>
		<form method="POST" onsubmit="document.getElementById('btn_submit').disabled=true;return true;">
			<input type="hidden" name="page" value="passwordreset">
			<table border="0">
				<tbody>
					<tr>
						<td>Enter the email address associated with your account: </td>
						<td><input type="text" name="email" size="30"></td>
					</tr>
					<tr>
						<td></td>
						<td>%CAPTCHA%</td>
					</tr>
					<tr>
						<td><br><br>&nbsp;</td>
						<td><center><input id="btn_submit" type="submit" value="Request Link"></center></td>
					</tr>
				</tbody>
			</table>
		</form>
	</center>
EOS;

function WGWSendPassResetMail($account, $user, $email)
{
	if ((!$account) and (!$user)) {
		return false;
	}
	if (!$account) {
		$account = WGWAccountIDByName($user);
		if (!$account) {
			return false;
		}
	}
	if (!$user) {
		$user = WGWUserNameByID($account);
		if (!$user) {
			return false;
		}
	}
	$mail = WGWGetMailer();
	$mail->setFrom(WGWConfig::$passreset_fromemail, WGWConfig::$passreset_fromname);
	// Get the mail address of this account from the DB if not already provided
	if (!$email) {
		$result = WGWDB::$con->query("SELECT email FROM " . WGWConfig::$db_prefix . "accounts WHERE id=$account LIMIT 1");
		if ($result->num_rows == 0) {
			return false;
		}
		$row = $result->fetch_row();
		$email = $row[0];
	}
	// Create new token for this account
	WGWDropEmailTokens($account);
	// Hex is guaranteed to be SQL safe so no need to escape
	$token = bin2hex(random_bytes(8));
	$result = WGWDB::$con->query("INSERT INTO " . WGWConfig::$db_prefix . "web_tokens (account, token, expiration, type) VALUES ($account, '$token', NOW() + INTERVAL 45 MINUTE, 2)");
	if (!$result) {
		// Shouldn't happen
		return false;
	}
	WGWDB::$con->query("COMMIT");
	$passreset_link = WGWConfig::$passreset_link_base . "?page=dopasswordreset&user=" . urlencode($user) . "&token=" . $token;
	global $g_wgwPasswordResetBody;
	$passreset_body = str_replace("%PASSRESETLINK%", $passreset_link, $g_wgwPasswordResetBody);
	$passreset_body = str_replace("%USER%", htmlspecialchars($user), $passreset_body);
	$mail->addAddress($email);
	global $g_wgwPasswordResetTitle;
	$mail->Subject = $g_wgwPasswordResetTitle;
	$mail->MsgHTML($passreset_body);
	if (!$mail->send()) {
		return false;
	}
	return true;
}

function WGWValidatePassResetToken($user, $token)
{
	WGWDropEmailTokens();
	WGWOutput::$out->title = "Reset password";
	if (array_key_exists("user", $_REQUEST)) {
		$account = WGWAccountIDByName($_REQUEST["user"]);
		if (!$account) {
			WGWOutput::$out->write("The given username does not exist<br>");
			die(0);
		}
		if (WGWUser::$user->is_admin()) {
			return true;
		}
		if (!$token) {
			WGWOutput::$out->write("No token given<br>");
			die(0);
		}
		$token_escaped = WGWDB::$con->real_escape_string($token);
		$result = WGWDB::$con->query("SELECT account FROM " . WGWConfig::$db_prefix . "web_tokens WHERE account=$account AND token='$token_escaped' AND type=2 LIMIT 1");
		if ($result->num_rows == 0) {
			WGWOutput::$out->write("Invalid password reset token<br>");
			die(0);
		}
		return true;
	}
	WGWOutput::$out->write("No user/token given<br>");
	die(0);
}

function WGWShowDoPasswordResetForm($msg=null)
{
	global $g_wgwDoPassResetForm;
	
	WGWOutput::$out->title = "Reset password";
	if (!array_key_exists("user", $_REQUEST)) {
		WGWOutput::$out->write("No username given<br>");
		die(0);
	}
	$token = "";
	if (array_key_exists("token", $_REQUEST)) {
		$token = $_REQUEST["token"];
	}
	else if (!WGWUser::$user->is_admin()) {
		WGWOutput::$out->write("No token given<br>");
		die(0);
	}
	$pwdresetform = str_replace("%USER%", addslashes($_REQUEST["user"]), $g_wgwDoPassResetForm);
	$pwdresetform = str_replace("%TOKEN%", addslashes($token), $pwdresetform);
	WGWOutput::$out->write($pwdresetform);
	if ($msg) {
		WGWOutput::$out->write("<br><center><b style=\"color: red\">$msg</b></center>");
	}
	die(0);
}

function WGWProcessDoPasswordReset()
{
	WGWOutput::$out->title = "Reset password";
	if (!array_key_exists("user", $_REQUEST)) {
		WGWOutput::$out->write("No username given<br>");
		die(0);
	}
	$token = "";
	if (array_key_exists("token", $_REQUEST)) {
		$token = $_REQUEST["token"];
	}
	else if (!WGWUser::$user->is_admin()) {
		WGWOutput::$out->write("No token given<br>");
		die(0);
	}
	if (WGWValidatePassResetToken($_REQUEST["user"], $token)) {
		if ((array_key_exists("pass", $_REQUEST)) and (array_key_exists("verify", $_REQUEST))) {
			// Final step, actually change the password
			$id = WGWAccountIDByName($_REQUEST["user"]);
			if (!$id) {
				WGWOutput::$out->write("Internal error<br>");
				die(0);
			}
			$result = WGWUser::$user->changepassword(null, $_REQUEST["pass"], $_REQUEST["verify"], $id, true);
			if ($result === true) {
				WGWUser::$user->disablemfa($id);
				WGWDropEmailTokens($id);
				WGWOutput::$out->write("Your password has been successfully reset!<br>");
				die(0);
			}
			else {
				WGWShowDoPasswordResetForm($result);
			}
		}
		WGWShowDoPasswordResetForm();
	}
	WGWOutput::$out->write("Invalid username or token<br>");
	die(0);
}

function WGWShowPasswordResetForm($msg=null)
{
	global $g_wgwPassResetForm;
	WGWOutput::$out->title = "Reset password";
	if (WGWConfig::$recaptcha_enabled) {
		WGWOutput::$out->write("<script src='https://www.google.com/recaptcha/api.js' async defer></script>");
	}
	if (WGWConfig::$recaptcha_enabled) {
		$captcha_key = WGWConfig::$recaptcha_site_key;
		$reset_form = str_replace("%CAPTCHA%", "<br><div class=\"g-recaptcha\" data-sitekey=\"$captcha_key\"></div>", $g_wgwPassResetForm);
	}
	else {
		$reset_form = str_replace("%CAPTCHA%", "", $g_wgwPassResetForm);
	}
	WGWOutput::$out->write($reset_form);
	if ($msg) {
		WGWOutput::$out->write("<br><center><b style=\"color: red\">$msg</b></center>");
	}
	die(0);
}

function WGWProcessPasswordReset()
{
	WGWOutput::$out->title = "Reset password";
	if (array_key_exists("email", $_REQUEST)) {
		$id = 0;
		$error_msg = "";
		$email_exists = false;
		if (!filter_var($_REQUEST["email"], FILTER_VALIDATE_EMAIL)) {
			$error_msg .= "Invalid e-mail address<br>";
		}
		$captcha_valid = true;
		if (WGWConfig::$recaptcha_enabled) {
			require_once("captcha.php");
			if (!WGWVerifyCaptcha()) {
				$error_msg .= "Please complete the captcha to verify that you're human<br>";
				$captcha_valid = false;
			}
		}
		if ($error_msg != "") {
			WGWShowPasswordResetForm($error_msg);
			die(0);
		}
		$email_escaped = WGWDB::$con->real_escape_string($_REQUEST["email"]);
		$result = WGWDB::$con->query("SELECT id FROM " . WGWConfig::$db_prefix . "accounts WHERE email='$email_escaped' LIMIT 1");
		if ($result->num_rows != 0) {
			$row = $result->fetch_row();
			$id = $row[0];
			WGWSendPassResetMail($id, null, null);
		}
		WGWOutput::$out->write("If the given email address matches one on our records, a reset link will be sent to that address.<br>Pleae click the link to continue the process.<br>");
		die(0);
	}
	WGWShowPasswordResetForm();
}

?>
