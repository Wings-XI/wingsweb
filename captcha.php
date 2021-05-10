<?php

/**
 *	@file captcha.php
 *	Routines for using Google reCaptcha v2 for bot filtering
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("configuration.php");

function WGWVerifyCaptcha()
{
	if (!WGWConfig::$recaptcha_enabled) {
		// Captcha is disabled so we allow the request to proceed
		return true;
	}
	if(!array_key_exists("g-recaptcha-response", $_REQUEST)){
		// No captcha response at all and captcha is enabled, so don't proceed.
		// If we reach here, either it's our fault or the user is messing around
		// with the form.
		return false;
	}
	$captcha = $_REQUEST['g-recaptcha-response'];
	if (!$captcha) {
		// Same as above,
		return false;
	}
	$url = 'https://www.google.com/recaptcha/api/siteverify?secret=' . WGWConfig::$recaptcha_secret_key .  '&response=' . urlencode($captcha);
	$curlobj = curl_init($url);
	if (!$curlobj) {
		// Definitely our fault. Enable cURL extension.
		return false;
	}
	curl_setopt($curlobj, CURLOPT_RETURNTRANSFER, 1);
	$response = curl_exec($curlobj);
	if (curl_errno($curlobj)) {
		if (WGWConfig::$debug) {
			require_once("output.php");
			WGWOutput::$out->write("Curl failed: " . curl_error($curlobj));
		}
		return false;
	}	
	curl_close($curlobj);
	$response_decoded = json_decode($response,true);
	if (!array_key_exists("success", $response_decoded)) {
		// Failure during communication (maybe API was changed?)
		return false;
	}
	if ($response_decoded["success"]) {
		// Hooray!
		return true;
	}
	// It's a bot
	return false;
}

?>
