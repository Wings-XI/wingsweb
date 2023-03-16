<?php

/**
 *	@file configuration.php
 *	Main configuration file, edit as needed.
 *	(C) 2020-2021 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 *	Exception: The actual values of the configuration, which may include sensitive
 *	information (such as passwords) do not need to be disclosed.
 *	To install, copy this file as configuration.php and change the values as needed.
 */

class WGWConfig
{
	// This will enable debug pages, do not use in producion
	public static $debug = false;
	
	// Credentials for the login DB
	public static $db_host = "127.0.0.1";
	public static $db_user = "topaz";
	public static $db_pass = "topaz";
	public static $db_database = "topaz_login";
	public static $db_prefix = "";
	
	// Secret added to password hashes
	public static $hash_secret = "";
	
	// Number of content IDs assigned on account creation
	// This is basically the number of characters on new accounts
	public static $content_ids_per_account = 3;
	
	// Log any login (and signup) to the accounts_ip_record table
	// In order to enable this, set the charid field of the table to optional
	public static $log_logins = true;
	
	// Whether new user registrations are allowed
	public static $signup_allowed = true;
	// Whether signups require manual verification by admin
	public static $signup_admin_verify_required = false;
	// Limit the number of accounts per IP address. Set to zero to allow unlimited accounts.
	public static $signup_accounts_per_ip = 1;
	// Signup threshold max accounts per time period
	public static $signup_threshold_accounts = 0;
	// Signup threshold time period in seconds
	public static $signup_threshold_period = 0;
	
	// Should we use reCaptcha (v2) for signups
	public static $recaptcha_enabled = false;
	// You should get those from reCaptcha. Do not disclose the secret key
	public static $recaptcha_site_key = "";
	public static $recaptcha_secret_key = "";
	
	// Require e-mail verification for new users (requires PHPMailer library)
	public static $verify_email = false;
	// Used as the "from" address when sending account activation messages
	public static $activation_fromemail = "donotreply@example.com";
	// Used as the name portion of the sender
	public static $activation_fromname = "FFXI";
	// Activation mail title
	public static $activation_title = "Activate your Topaz account";
	// Optional message to be included in the message
	public static $activation_message = "To activate your Topaz account please click or copy-paste the link below.<br>If you did not expect this message please ignore it.<br>";
	// Link base for activation (should point to index.php)
	public static $activation_link_base = "https://www.example.com/wings/index.php";

	// Allow users to reset their forgotten passwords
	public static $allow_password_reset = true;
	// Used as the "from" address when sending password reset messages
	public static $passreset_fromemail = "donotreply@example.com";
	// Used as the name portion of the sender
	public static $passreset_fromname = "FFXI";
	// Password reset mail title
	public static $passreset_title = "Topaz account password reset";
	// Optional message to be included in the message
	public static $passreset_message = "You have requested to reset your Topaz account password. To reset your pssword please click or copy-paste the link below.<br>If you did not expect this messgae please ignore it.<br>Your username is <b>%USER%</b><br>";
	// Link base for password reset (should point to index.php)
	public static $passreset_link_base = "https://www.example.com/wings/index.php";

	// SMTP configuration
	// Set to an SMTP server through which emails will be sent
	public static $smtp_server = "smtp.example.com";
	// Set the SMTP server port
	public static $smtp_port = 25;
	// Set whether the server uses TLS encryption
	// Valid values:
	// "starttls" - Excplicit TLS by using the SMTP STARTTLS command
	// "smtps" - Implicit TLS (perform handshake immediately upon successful TCP connection)
	// Not defined or set to anything else - No encryption
	public static $smtp_ssl = "starttls";
	// Whether the SMTP server requires authentication. If using a public e-mail
	// service this will most definitely be the case.
	public static $smtp_auth = true;
	// Username for SMTP authentication
	public static $smtp_user = "topaz";
	// Password for SMTP authentication
	// Note - If TLS is not enabled the password will be sent in the clear!
	public static $smtp_pass = "topaz";
	
	// If the server is behind a reverse proxy (e.g. Cloudflare), get the original user's
	// IP address from this member of the server global. If there is no reverse proxy, set
	// to null. For Cloudflare set to "HTTP_CF_CONNECTING_IP".
	public static $reverse_proxy_origin_ip_field = null;
	
	// Set to true to enable submitting helpdesk tickets through the website.
	// The server must support the helpdesk function.
	public static $helpdesk_enabled = false;
	// Allow users to change their race and appearance via the web interface.
	// 0 - Disable
	// 1 - Allow only once
	// 2 - Allow without restriction
	public static $allow_race_change = 0;
	// Do not display information on GMs this level or above
	public static $gm_threshold = 1;
	
	// Any queries containing any of the words below will be logged, useful for exploit
	// hunting (e.g. SQL injection)
	public static $word_log = array();
	
	// Length of temporary IP exception granted when LAN party mode is enabled
	// Default - 3 days
	public static $temp_ip_exception_length = 259200;
	// Cooldown between uses of LAN party mode
	// Default - 30 days
	public static $temp_ip_exception_cooldown = 2592000;
	
	// Multi factor authentication support
	public static $enable_mfa = true;
	// Cooldown after disabling MFA before it can be enabled again
	// Default - 7 days
	public static $mfa_cooldown = 604800;
	
};

if (!WGWConfig::$debug) {
	error_reporting( E_ALL ^ ( E_NOTICE | E_WARNING | E_DEPRECATED ) );
}

?>
