<?php

/**
 *	@file activation.php
 *	Internal functions for account activation using links sent by E-mail
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("configuration.php");
require_once("database.php");
require_once("email.php");
require_once("userutils.php");
require_once("output.php");
require_once("user.php");

global $g_wgwActivationMailTitle;
if (WGWConfig::$activation_title) {
	$g_wgwActivationMailTitle = WGWConfig::$activation_title;
}
else {
	$g_wgwActivationMailTitle = "Activate your account";
}
global $g_wgwActivationMailMessgage;
if (WGWConfig::$activation_message) {
	$g_wgwActivationMailMessgage = WGWConfig::$activation_message;
}
else {
	$g_wgwActivationMailMessgage = "Please click or copy-paste the link below to activate your account.<br>If you did not expect this message please ignore it.<br>";
}
global $g_wgwActivationMailBody;
$g_wgwActivationMailBody = <<<EOS
<html>
<head>
<title>$g_wgwActivationMailTitle</title>
</head>
<body>
$g_wgwActivationMailMessgage;
<br>
<a href="%ACTIVATIONLINK%">%ACTIVATIONLINK%</a><br>
</body>
</html>
EOS;

function WGWSendActivationMail($account, $user, $email)
{
	if ((!$account) and (!$user)) {
		return false;
	}
	if (!$account) {
		$account = WGWAccountIDByName($user);
	}
	if (!$user) {
		$user = WGWUserNameByID($account);
	}
	$mail = WGWGetMailer();
	if (!WGWConfig::$verify_email) {
		// Activation not enabled
		return false;
	}	
	$mail->setFrom(WGWConfig::$activation_fromemail, WGWConfig::$activation_fromname);
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
	$result = WGWDB::$con->query("INSERT INTO " . WGWConfig::$db_prefix . "web_tokens (account, token, expiration, type) VALUES ($account, '$token', NOW() + INTERVAL 45 MINUTE, 1)");
	if (!$result) {
		// Shouldn't happen
		return false;
	}
	WGWDB::$con->query("COMMIT");
	$activation_link = WGWConfig::$activation_link_base . "?page=activate&user=" . urlencode($user) . "&token=" . $token;
	global $g_wgwActivationMailBody;
	$activation_body = str_replace("%ACTIVATIONLINK%", $activation_link, $g_wgwActivationMailBody);
	$mail->addAddress($email);
	global $g_wgwActivationMailTitle;
	$mail->Subject = $g_wgwActivationMailTitle;
	$mail->MsgHTML($activation_body);
	if (!$mail->send()) {
		return false;
	}
	return true;
}

function WGWProcessActivation()
{
	WGWDropEmailTokens();
	WGWOutput::$out->title = "Account activation";
	if ((array_key_exists("user", $_REQUEST)) and (array_key_exists("token", $_REQUEST))) {
		$account = WGWAccountIDByName($_REQUEST["user"]);
		if (!$account) {
			WGWOutput::$out->write("The given username does not exist<br>");
			die(0);
		}
		$token_escaped = WGWDB::$con->real_escape_string($_REQUEST["token"]);
		$result = WGWDB::$con->query("SELECT account FROM " . WGWConfig::$db_prefix . "web_tokens WHERE account=$account AND token='$token_escaped' AND type=1 LIMIT 1");
		if ($result->num_rows == 0) {
			WGWOutput::$out->write("Invalid activation token<br>");
			die(0);
		}
		$result = WGWDB::$con->query("SELECT status FROM " . WGWConfig::$db_prefix . "accounts WHERE id=$account LIMIT 1");
		if ($result->num_rows == 0) {
			WGWOutput::$out->write("Internal error (code=1)<br>");
			die(1);
		}
		$row = $result->fetch_row();
		if ($row[0] != 0) {
			WGWOutput::$out->write("This account is already activated.");
			die(0);
		}
		if (WGWConfig::$signup_admin_verify_required) {
			$new_status = 3;
		}
		else {
			$new_status = 1;
		}
		$result = WGWDB::$con->query("UPDATE " . WGWConfig::$db_prefix . "accounts SET status=$new_status WHERE id=$account");
		if (!$result) {
			WGWOutput::$out->write("Internal error (code=2)<br>");
			die(1);
		}
		WGWDropEmailTokens($account);
		if ((WGWUser::$user->is_logged_in()) and (WGWUser::$user->id == $account)) {
			WGWUser::$user->status = $new_status;
		}
		if ($new_status == 3) {
			WGWOutput::$out->write("Currently new accounts must be verified by an adiministrator or GM. Please open a GM call on discord and ask for verification.<br>");
		}
		else {
			WGWOutput::$out->write("Account successfully activated!<br>");
		}
		die(0);
	}
	WGWOutput::$out->write("Activation parameters missing<br>");
	die(0);
}

function WGWResendActivationMail($user=null)
{
	$account = 0;
	
	if ($user) {
		$account = WGWAccountIDByName($user);
	}
	else if (array_key_exists("user", $_REQUEST)) {
		$user = $_REQUEST["user"];
		$account = WGWAccountIDByName($user);
	}
	else if (WGWUser::$user->is_logged_in()) {
		$account = WGWUser::$user->id;
		$user = WGWUser::$user->name;
	}
	if (!$account) {
		WGWOutput::$out->write("Invalid user name<br>");
		die(0);
	}
	$result = WGWDB::$con->query("SELECT status FROM " . WGWConfig::$db_prefix . "accounts WHERE id=$account LIMIT 1");
	if ($result->num_rows == 0) {
		WGWOutput::$out->write("Internal error (code=1)<br>");
		die(1);
	}
	$row = $result->fetch_row();
	if ($row[0] != 0) {
		WGWOutput::$out->write("This account is already activated.");
		die(0);
	}
	WGWSendActivationMail($account, $user, null);
	WGWOutput::$out->write("A new activation link has been sent to your E-mail address.<br>Please click the link to activate your account.<br>");
	die(0);
}

?>