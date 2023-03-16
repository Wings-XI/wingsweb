<?php

/**
 *	@file mfa.php
 *	Multi factor authentication enable/disable
 *	(C) 2022 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("output.php");
require_once("login.php");
require_once("user.php");
require_once("profile.php");
require_once("database.php");
require_once("configuration.php");

global $g_wgwMFADisableForm;
global $g_wgwMFAEnableForm;

$g_wgwMFADisableForm = <<<EOS
	<FORM METHOD="POST" ONSUBMIT="return confirm('Are you sure you wish to disable two factor authentication?');">
	<p><b>WARNING!</b><br>
	If you disable two factor authentication you will lose access to Mog Satchel until you enabe it again.<br>
	<b>Rare items left in your Mog Satchel cannot be reobtained!</b></p>
	%COOLDOWN%
	<INPUT TYPE="hidden" NAME="page" VALUE="mfa">
	<INPUT TYPE="hidden" NAME="action" VALUE="disable">
	<INPUT TYPE="submit" VALUE="Disable">
	</FORM>
EOS;

$g_wgwMFAEnableForm = <<<EOS
	<FORM METHOD="POST">
	<p>To enable two factor authentication please scan the following code with your authenticator app:<br><br>
	%QRCODE%
	<br><br>
	Alternatively, add the service manually by specifying the following code: %OTPSECRET%<br>
	</p>
	<INPUT TYPE="hidden" NAME="page" VALUE="mfa">
	<INPUT TYPE="hidden" NAME="action" VALUE="enable">
	<p>To prevent account lockout, please enter the current code being displayed in the app: <INPUT TYPE="TEXT" NAME="otp" SIZE="30"></p>
	<INPUT TYPE="submit" VALUE="Enable">
	</FORM>
EOS;

function WGWShowMFAForm()
{
	global $g_base;
	global $g_wgwMFADisableForm;
	global $g_wgwMFAEnableForm;
	
	WGWForceLogin();
	
	WGWOutput::$out->title = "Two factor authentication";
	WGWOutput::$out->write("<h2>Two factor authentication</h2>");
	
	$accid = WGWUser::$user->id;
	$now = time();
	
	$enabled = (WGWUser::$user->features & 0x01) ? true : false;
	$msg = null;
	
	if (array_key_exists("action", $_REQUEST)) {
		if ($_REQUEST["action"] == "disable") {
			WGWUser::$user->disablemfa();
		}
		else if ($_REQUEST["action"] == "enable" && array_key_exists("otp", $_REQUEST)) {
			$result = WGWUser::$user->enablemfa($_REQUEST["otp"]);
			if ($result !== true) {
				$msg = $result;
			}
		}
		$enabled = (WGWUser::$user->features & 0x01) ? true : false;
	}
	
	WGWOutput::$out->write("<p>Two factor authentication is <span style=\"color: " . ($enabled ? "green" : "red") . "\">" . ($enabled ? "enabled" : "disabled") . "</span> on your account.</p>");
	
	if ($msg) {
		WGWOutput::$out->write("<p style=\"color: red\">$msg</p>");
	}
	
	$abuse_msg = "";
	if (WGWConfig::$mfa_cooldown) {
		$abuse_msg = "To prevent abuse, once 2FA is disabled you will not be able to enable it again until " . date("D Y/m/d H:i:s", $now + WGWConfig::$mfa_cooldown) . " UTC.<br><br>";
	}
	
	if ($enabled) {
		// Show disable form
		WGWOutput::$out->write(str_replace("%COOLDOWN%", $abuse_msg, $g_wgwMFADisableForm));
	}
	else if (WGWConfig::$mfa_cooldown && WGWUser::$user->otp_change && WGWUser::$user->otp_change + WGWConfig::$mfa_cooldown >= $now) {
		WGWOutput::$out->write("Two factor authntication has been recently disabled. It can be enabled again on " . date("D Y/m/d H:i:s", WGWUser::$user->otp_change + WGWConfig::$mfa_cooldown) . " UTC.<br>");
	}
	else {
		// Show enable form
		$qrprovider = new RobThree\Auth\Providers\Qr\EndroidQrCodeProvider();
		$tfa = new RobThree\Auth\TwoFactorAuth('Wings', qrcodeprovider: $qrprovider);
		
		if (!WGWUser::$user->otp_secret || WGWUser::$user->otp_secret == "") {
			// Generate new secret
			WGWUser::$user->otp_secret = $tfa->createSecret();
		}
		$enableform = str_replace("%QRCODE%", "<img src=\"" . $tfa->getQRCodeImageAsDataUri('Wings', WGWUser::$user->otp_secret) . "\">", $g_wgwMFAEnableForm);
		$enableform = str_replace("%OTPSECRET%", WGWUser::$user->otp_secret, $enableform);
		WGWOutput::$out->write($enableform);
	}
}

?>
