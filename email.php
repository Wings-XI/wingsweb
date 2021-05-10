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

?>
