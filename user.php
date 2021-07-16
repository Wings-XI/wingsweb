<?php

/**
 *	@file user.php
 *	Internal functions for user login and registration
 *	(C) 2020-2021 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("configuration.php");
require_once("serverutils.php");
require_once("userutils.php");

class WGWUser
{
	public static $user;
	
	public $name = "";
	public $id = 0;
	public $email = "";
	
	// Bitmask of installed expansions
	public $expansions = 0;
	// Bitmask of installed features (Secure, Wardrobe)
	public $features = 0;
	
	// Arbitrary members we may want to add in the futue
	// without breaking the class structure
	public $params = array();
	
	// Status:
	// 0 - Not activated
	// 1 - Active
	// 2 - Banned
	public $status = 0;
	
	// Privileges
	// 1 - Regular user
	// 2 - Admin
	// 4 - Root
	public $priv = 0;
	
	// How many unread GM responses we have
	public $unread_gm_messages = 0;
	// GM only - Number of pending tickets and/or tickets assigned to self
	public $gm_pending_tickets = 0;
	
	// Last time the data was refreshed from DB
	public $lastrefresh = 0;
	
	public function is_logged_in()
	{
		return (($this->name != "") and ($this->id != 0));
	}
	
	public function is_admin()
	{
		return $this->priv & 0x08 ? true : false;
	}
	
	public function is_super_admin()
	{
		// Deprecated
		return $this->is_admin();
	}
	
	public function log_access($id=null, $ip=null, $operation=1, $result=true)
	{
		// Change the DB and make charid optional for this to work
		if (!WGWConfig::$log_logins) {
			return;
		}
		if (!$id) {
			$id = $this->id;
		}
		if (!$ip) {
			$ip = WGWGetRemoteIPAddress();
		}
		if ((!is_numeric($id)) or (!$ip)) {
			return;
		}
		$resultnum = $result ? 1 : 0;
		WGWDB::$con->query("INSERT INTO " . WGWConfig::$db_prefix . "login_log (login_time, account_id, client_ip, operation, source, result) VALUES (NOW(), $id, '$ip', $operation, 2, $resultnum)");
		WGWDB::$con->query("COMMIT");
	}
	
	public function update_gm_msg_count()
	{
		require_once("database.php");
		$this->unread_gm_messages = 0;
		foreach (WGWDB::$maps as $worldid => $worlddata) {
			$sqlres = $worlddata["db"]->query("SELECT messageid FROM char_gmmessage WHERE accid=$this->id AND `read`=0;");
			$nummsg = 0;
			if ($sqlres) {
				$nummsg = $sqlres->num_rows;
			}
			$this->unread_gm_messages = $this->unread_gm_messages + $nummsg;
		}
	}
	
	public function update_pending_ticket_count()
	{
		if (!$this->is_admin()) {
			// Only GMs can have pending tickets
			$this->gm_pending_tickets = 0;
		}
		require_once("database.php");
		$this->gm_pending_tickets = 0;
		foreach (WGWDB::$maps as $worldid => $worlddata) {
			$sql = "SELECT callid, assignee, `status` FROM server_gmcalls WHERE `status` = 0 OR (assignee IN (SELECT charname FROM chars WHERE accid=$this->id) AND `status` < 3)";
			$result = $worlddata["db"]->query($sql);
			$nummsg = 0;
			if ($result) {
				$nummsg = $result->num_rows;
			}
			$this->gm_pending_tickets = $this->gm_pending_tickets + $nummsg;
		}
	}
	
	public function generate_salt($len)
	{
		$salt = "";
		for ($i = 0; $i < $len; $i++) {
			// Generate len printable characters (range 33-126)
			$salt = $salt . chr(mt_rand(33, 126));
		}
		return $salt;
	}

	/**
	 *	Attempt to migrate credentials from the old to the new login server,
	 *	which store hashes using a different method.
	 */
	public function try_migrate_user($user, $pass)
	{
		require_once("database.php");
		$user_escaped = WGWDB::$con->real_escape_string($user);
		$pass_escaped = WGWDB::$con->real_escape_string($pass);
		$secret_escaped = WGWDB::$con->real_escape_string(WGWConfig::$hash_secret);
		$sql = "SELECT password FROM " . WGWConfig::$db_prefix . "accounts WHERE username='$user_escaped' LIMIT 1;";
		$result = WGWDB::$con->query($sql);
		if ($result->num_rows == 0) {
			return false;
		}
		$row = $result->fetch_row();
		if ($row[0] and $row[0] != "") {
			return false;
		}
		foreach (WGWDB::$maps as $worldid => $worlddata) {
			$result = $worlddata["db"]->query("SELECT id, status, priv, current_email FROM accounts WHERE login='$user_escaped' AND password=PASSWORD('$pass_escaped') LIMIT 1");
			if ($result and $result->num_rows != 0) {
				$salt = $this->generate_salt(32);
				$salt_escaped = WGWDB::$con->real_escape_string($salt);
				$pass_hash = WGWDB::$con->real_escape_string(hash_pbkdf2("sha256", $pass, $salt . WGWConfig::$hash_secret, 2048));
				$updres = WGWDB::$con->query("UPDATE " . WGWConfig::$db_prefix . "accounts SET password='$pass_hash', salt='$salt_escaped' WHERE username='$user_escaped'");
				if (!$updres) {
					continue;
				}
				WGWDB::$con->query("COMMIT");
				return true;
			}
		}
		return false;
	}
	
	public function login($user, $pass)
	{
		require_once("database.php");
		$user_escaped = WGWDB::$con->real_escape_string($user);
		$pass_escaped = WGWDB::$con->real_escape_string($pass);
		$secret_escaped = WGWDB::$con->real_escape_string(WGWConfig::$hash_secret);
		$sql = "SELECT id, password, salt, email, expansions, features, status, privileges FROM " . WGWConfig::$db_prefix . "accounts WHERE username='$user_escaped' LIMIT 1";
		$result = WGWDB::$con->query($sql);
		$real_hash = "0000000000000000000000000000000000000000000000000000000000000000";
		$salt = "00000000000000000000000000000000";
		$user_exists = false;
		$row = null;
		if ($result->num_rows != 0) {
			$row = $result->fetch_row();
			$real_hash = $row[1];
			$salt = $row[2];
			$user_exists = true;
		}
		$entered_hash = WGWDB::$con->real_escape_string(hash_pbkdf2("sha256", $pass, $salt . WGWConfig::$hash_secret, 2048));
		if ((strcasecmp($entered_hash, $real_hash) != 0) or (!$user_exists)) {
			$migrated = false;
			if ($user_exists and !$real_hash and $this->try_migrate_user($user, $pass)) {
				$migrated = true;
			}
			if (!$migrated) {
				$this->log_access($user_exists ? $row[0] : 0, null, 1, false);
				return false;
			}
		}
		$this->name = $user;
		$this->id = $row[0];
		$this->status = intval($row[6]);
		$this->priv = $row[7];
		$this->email = $row[3];
		$this->expansions = intval($row[4]);
		$this->features = intval($row[5]);
		$this->log_access();
		$this->lastrefresh = time();
		$this->update_gm_msg_count();
		$this->update_pending_ticket_count();
		return true;
	}
	
	public function refresh()
	{
		if (!$this->id) {
			// Can only be called when logged-in
			return false;
		}
		require_once("database.php");
		$result = WGWDB::$con->query("SELECT expansions, features, status, privileges, email FROM " . WGWConfig::$db_prefix . "accounts WHERE id=$this->id LIMIT 1");
		if ($result->num_rows == 0) {
			return false;
		}
		$row = $result->fetch_row();
		$this->expansions = $row[0];
		$this->features = $row[1];
		$this->status = $row[2];
		$this->priv = $row[3];
		$this->email = $row[4];
		$this->lastrefresh = time();
		$this->update_gm_msg_count();
		$this->update_pending_ticket_count();
		return true;
	}
	
	public function logout()
	{
		$this->name = "";
		$this->id = 0;
		$this->email = "";
		$this->params = array();
		$this->status = 0;
		$this->priv = 0;
		$this->expansions = 0;
		$this->features = 0;
		$this->unread_gm_messages = 0;
		$this->gm_pending_tickets = 0;
	}
	
	public function savelastmodifytime()
	{
		WGWDB::$con->query("UPDATE " . WGWConfig::$db_prefix . "accounts SET timemodified = NOW() WHERE id=$this->id");
		WGWDB::$con->query("COMMIT");
	}
	
	public function changemail($email)
	{
		if (!$this->is_logged_in()) {
			return "Not logged in<br>";
		}
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return "Invalid e-mail address<br>";
		}
		$email_domain = strtolower(substr(strrchr($email, "@"), 1));
		if (WGWIsEmailDomainBanned($email_domain)) {
			return "The e-mail address is not allowed<br>";
		}
		require_once("database.php");
		$email_escaped = WGWDB::$con->real_escape_string($email);
		$result = WGWDB::$con->query("SELECT id FROM " . WGWConfig::$db_prefix . "accounts WHERE email='$email_escaped' LIMIT 1");
		if ($result->num_rows != 0) {
			return "The e-mail address given is already registered to another account<br>";
		}
		$this->savelastmodifytime();
		$result = WGWDB::$con->query("UPDATE " . WGWConfig::$db_prefix . "accounts SET email='$email_escaped' WHERE id=$this->id");
		if (!$result) {
			return "Internal error<br>";
		}
		$result = WGWDB::$con->query("COMMIT");
		$this->email = $email;
		return true;
	}
	
	public function changepassword($old, $new, $verify, $userid, $resetpwd=false)
	{
		if (!$resetpwd) {
			if (!$this->is_logged_in()) {
				return "Not logged in<br>";
			}
		}
		require_once("database.php");
		$error_msg = "";
		if ($userid) {
			$user_escaped = WGWDB::$con->real_escape_string(WGWUserNameByID($userid));
		}
		else {
			$user_escaped = WGWDB::$con->real_escape_string($this->name);
		}
		if (!$resetpwd) {
			$old_escaped = WGWDB::$con->real_escape_string($old);
			$result = WGWDB::$con->query("SELECT password, salt FROM " . WGWConfig::$db_prefix . "accounts WHERE username='$user_escaped' LIMIT 1");
			$real_hash = "0000000000000000000000000000000000000000000000000000000000000000";
			$salt = "00000000000000000000000000000000";
			$user_exists = false;
			$row = null;
			if ($result->num_rows != 0) {
				$row = $result->fetch_row();
				$real_hash = $row[0];
				$salt = $row[1];
				$user_exists = true;
			}
			$entered_hash = WGWDB::$con->real_escape_string(hash_pbkdf2("sha256", $old, $salt . WGWConfig::$hash_secret, 2048));
			if ((strcasecmp($entered_hash, $real_hash) != 0) or (!$user_exists)) {
				$error_msg .= "The old password is incorrect<br>";
			}
		}
		if (strlen($new) < 6) {
			$error_msg .= "The new password is too short (minimum of 6 characters required)<br>";
		}
		if (strlen($new) > 15) {
			$error_msg .= "The new password is too long (maximum of 15 characters allowed)<br>";
		}
		if ($verify != $new) {
			$error_msg .= "The password fields do not match<br>";
		}
		if ($error_msg != "") {
			return $error_msg;
		}
		$new_escaped = WGWDB::$con->real_escape_string($new);
		if ($resetpwd) {
			$id = $resetpwd;
		}
		else {
			$id = $this->id;
		}
		$this->savelastmodifytime();
		$secret_escaped = WGWDB::$con->real_escape_string(WGWConfig::$hash_secret);
		$salt = $this->generate_salt(32);
		$salt_escaped = WGWDB::$con->real_escape_string($salt);
		$pass_hash = WGWDB::$con->real_escape_string(hash_pbkdf2("sha256", $new, $salt . WGWConfig::$hash_secret, 2048));
		$result = WGWDB::$con->query("UPDATE " . WGWConfig::$db_prefix . "accounts SET password='$pass_hash', salt='$salt_escaped' WHERE username='$user_escaped'");
		if (!$result) {
			return "Internal error<br>";
		}
		$result = WGWDB::$con->query("COMMIT");
		return true;
	}
	
	public function signup($user, $pass, $verify, $email, $ip="0.0.0.0")
	{
		$error_msg = "";
		
		$username_valid = true;
		if (strlen($user) < 3) {
			$error_msg .= "Username is too short (minimum 3 characters required)<br>";
			$username_valid = false;
		}
		if (strlen($user) > 15) {
			$error_msg .= "Username is too long (maximum 15 characters allowed)<br>";
			$username_valid = false;
		}
		if (!ctype_alnum($user)) {
			$error_msg .= "Username can only contain latin letters and numbers<br>";
			$username_valid = false;
		}
		// If the username is invalid (too short or not alphanumeric) then it's pointless
		// to check whether it's already taken.
		require_once("database.php");
		$user_escaped = WGWDB::$con->real_escape_string($user);
		$pass_escaped = WGWDB::$con->real_escape_string($pass);
		$email_escaped = WGWDB::$con->real_escape_string($email);
		$result = WGWDB::$con->query("SELECT id FROM " . WGWConfig::$db_prefix . "accounts WHERE username='$user_escaped' LIMIT 1");
		if ($result->num_rows != 0) {
			$error_msg .= "Username is already taken<br>";
			$username_valid = false;
		}
		// Check if another account is registered on the same IP address
		$ip_valid = true;
		if (WGWConfig::$signup_accounts_per_ip > 0) {
			// Assuming this is a real IP address it should be SQL safe
			$result = WGWDB::$con->query("SELECT DISTINCT account_id FROM " . WGWConfig::$db_prefix . "login_log, " . WGWConfig::$db_prefix . "accounts WHERE " . WGWConfig::$db_prefix . "login_log.account_id = " . WGWConfig::$db_prefix . "accounts.id AND client_ip = '$ip' AND account_id != 0 AND result != 0 AND login_time >= NOW() - INTERVAL 1 MONTH AND ip_exempt = 0;");
			if ($result->num_rows >= WGWConfig::$signup_accounts_per_ip) {
				$error_msg .= "Too many accounts are associated with this IP address<br>";
				$ip_valid = false;
			}
		}
		if (WGWIsIPAddressBanned($ip)) {
			$error_msg .= "Registrations from this IP address are not allowed<br>";
			$ip_valid = false;
		}
		$password_valid = true;
		if (strlen($pass) < 6) {
			$error_msg .= "Password is too short (minimum 6 characters required)<br>";
			$password_valid = false;
		}
		if (strlen($pass) > 15) {
			$error_msg .= "Password is too long (maximum 15 characters allowed)<br>";
			$password_valid = false;
		}
		if ($verify != $pass) {
			$error_msg .= "Password fields do not match<br>";
			$password_valid = false;
		}
		$email_valid = true;
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$error_msg .= "Invalid e-mail address<br>";
			$email_valid = false;
		}
		$email_domain = strtolower(substr(strrchr($email, "@"), 1));
		if (WGWIsEmailDomainBanned($email_domain)) {
			$error_msg .= "The e-mail address is not allowed<br>";
			$email_valid = false;
		}
		if ($email_valid) {
			$result = WGWDB::$con->query("SELECT id FROM " . WGWConfig::$db_prefix . "accounts WHERE email='$email_escaped' LIMIT 1");
			if ($result->num_rows != 0) {
				$error_msg .= "The e-mail address given is already registered to another account<br>";
				$email_valid = false;
			}
		}
		$captcha_valid = true;
		if (WGWConfig::$recaptcha_enabled) {
			require_once("captcha.php");
			if (!WGWVerifyCaptcha()) {
				$error_msg .= "Please complete the captcha to verify that you're human<br>";
				$captcha_valid = false;
			}
		}
		// Verification of input failed, don't proceed
		if ((!$username_valid) or (!$password_valid) or (!$email_valid) or (!$captcha_valid) or (!$ip_valid) or ($error_msg != "")) {
			return $error_msg;
		}
		// All checks passed, we can now add the user
		if (WGWConfig::$verify_email) {
			// E-mail verification is enabled, so initial status set to disabled
			// until the user is verified.
			$initial_status = 0;
		}
		else {
			if (WGWConfig::$signup_admin_verify_required) {
				$initial_status = 3;
			}
			else {
				// User is immediately allowed to connect
				$initial_status = 1;
			}
		}
		$result = WGWDB::$con->query("SELECT MAX(id) FROM " . WGWConfig::$db_prefix . "accounts");
		if ($result->num_rows == 0) {
			return "Internal error (code=1)<br>";
		}
		$row = $result->fetch_row();
		$new_id = $row[0] + 1;
		$secret_escaped = WGWDB::$con->real_escape_string(WGWConfig::$hash_secret);
		$salt = $this->generate_salt(32);
		$salt_escaped = WGWDB::$con->real_escape_string($salt);
		$pass_hash = WGWDB::$con->real_escape_string(hash_pbkdf2("sha256", $pass, $salt . WGWConfig::$hash_secret, 2048));
		$result = WGWDB::$con->query("INSERT INTO " . WGWConfig::$db_prefix . "accounts (id, username, password, salt, email, timecreated, timemodified, status) VALUES ($new_id, '$user_escaped', '$pass_hash', '$salt_escaped', '$email_escaped', NOW(), NOW(), $initial_status)");
		if (!$result) {
			return "Internal error (code=2)<br>";
		}
		$result = WGWDB::$con->query("COMMIT");
		// Assign content IDs
		for ($i = 0; $i < WGWConfig::$content_ids_per_account; $i++) {
			WGWDB::$con->query("INSERT INTO " . WGWConfig::$db_prefix . "contents (account_id, enabled) VALUES ($new_id, 1)");
			WGWDB::$con->query("COMMIT");
		}
		if (WGWConfig::$verify_email) {
			require_once("activation.php");
			if (!WGWSendActivationMail($new_id, $user, $email)) {
				return "Internal error (code=3)<br>";
			}
		}
		$result = WGWDB::$con->query("SELECT expansions, features FROM " . WGWConfig::$db_prefix . "accounts WHERE id = $new_id LIMIT 1");
		if (!$result or $result->num_rows == 0) {
			return "Internal error (code=4)<br>";
		}
		$row = $result->fetch_row();
		$this->name = $user;
		$this->id = $new_id;
		$this->status = $initial_status;
		$this->priv = 1;
		$this->email = $email;
		$this->log_access(null, null, 2, true);
		$this->lastrefresh = time();
		$this->expansions = $row[0];
		$this->features = $row[1];
		return true;
	}
};

// Make sure the session always has a user object, even if not logged-in
session_start();
if (!array_key_exists("wgwuser", $_SESSION))
{
	$_SESSION["wgwuser"] = new WGWUser();
}
WGWUser::$user = $_SESSION["wgwuser"];
if (WGWUser::$user->lastrefresh + 300 < time()) {
	WGWUser::$user->refresh();
}

?>
