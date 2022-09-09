<?php

/**
 *	@file email.php
 *	E-mail sending routines. Used for account activation and password reset.
 *	This file depends on the PHPMailer library, available under LGPL.
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once("PHPMailer/src/Exception.php");
require_once("PHPMailer/src/PHPMailer.php");
require_once("PHPMailer/src/SMTP.php");

require_once("database.php");
require_once("login.php");
require_once("output.php");
require_once("configuration.php");

function WGWGetMailer()
{
	$mail = new PHPMailer();
	// Will dump all SMTP traffic to the screen (INCLUDING PASSWORDS)
	// Leave commented out unless you're trying to debug mail problems.
	// $mail->SMTPDebug = SMTP::DEBUG_SERVER;
	$mail->isSMTP();
	$mail->Host = WGWConfig::$smtp_server;
	$mail->Port = WGWConfig::$smtp_port;
	switch (WGWConfig::$smtp_ssl) {
		case "starttls":
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
		break;
		case "smtps":
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
		break;
	}
	$mail->SMTPAuth = WGWConfig::$smtp_auth;
	$mail->Username = WGWConfig::$smtp_user;
	$mail->Password = WGWConfig::$smtp_pass;
	return $mail;
}

function WGWDropEmailTokens($account=null)
{
	// First of all, delete all expired tokens
	WGWDB::$con->query("DELETE FROM " . WGWConfig::$db_prefix . "web_tokens WHERE expiration <= NOW()");
	// Then delete the requested account (if provided)
	if ($account) {
		WGWDB::$con->query("DELETE FROM " . WGWConfig::$db_prefix . "web_tokens WHERE account=$account");
	}
	WGWDB::$con->query("COMMIT");
}

function WGWSendTestMail($target)
{
	$mail = WGWGetMailer();
	$mail->SMTPDebug = 3;
	if (!$mail) {
		return false;
	}
	$mail->setFrom(WGWConfig::$activation_fromemail, WGWConfig::$activation_fromname);
	$mail->addAddress($target);
	$mail->Subject = "WGW Test email";
	$timestr = strval(time());
	$mail->MsgHTML("<h1>It works!</h1>The email system is successfully set up (ts=$timestr).");
	if (!$mail->send()) {
		return false;
	}
	return true;
}

function WGWDoEmailTest()
{
	WGWForceAdmin();
	
	WGWOutput::$out->title = "E-mail Test";
	if (array_key_exists("target", $_REQUEST)) {
		$target = $_REQUEST["target"];
		if (!filter_var($target, FILTER_VALIDATE_EMAIL)) {
			WGWOutput::$out->write("<p>Not a valid email address!</p>");
		}
		else if (WGWSendTestMail($target)) {
			WGWOutput::$out->write("<p>Test mail successfully sent!</p>");
		}
		else {
			WGWOutput::$out->write("<p>Failed to send test mail!</p>");
		}
	}
	WGWOutput::$out->write("<h1>E-Mail test page</h1>");
	WGWOutput::$out->write("<form method=\"POST\"><input type=\"hidden\" name=\"page\" value=\"testmail\">");
	WGWOutput::$out->write("<p>Target address: <input type=\"text\" size=\"20\" name=\"target\"></p>");
	WGWOutput::$out->write("<input type=\"submit\" value=\"Send test mail\"></form>");
}

?>
