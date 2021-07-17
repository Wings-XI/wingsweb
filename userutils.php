<?php

/**
 *	@file userutils.php
 *	Misc utilities for user management.
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("CIDR.php");
require_once("database.php");

function WGWAccountIDByName($user)
{
	$user_escaped = WGWDB::$con->real_escape_string($user);
	$result = WGWDB::$con->query("SELECT id FROM " . WGWConfig::$db_prefix . "accounts WHERE username='$user_escaped' LIMIT 1");
	if ($result->num_rows != 0) {
		$row = $result->fetch_row();
		return $row[0];
	}
	return false;
}

function WGWUserNameByID($account)
{
	$result = WGWDB::$con->query("SELECT username FROM " . WGWConfig::$db_prefix . "accounts WHERE id=$account LIMIT 1");
	if ($result->num_rows != 0) {
		$row = $result->fetch_row();
		return $row[0];
	}
	return false;
}

function WGWAccountIDOfChar($charid, $worldid)
{
	$sql = "SELECT account_id FROM " . WGWConfig::$db_prefix. "contents WHERE content_id = (SELECT content_id FROM " . WGWConfig::$db_prefix. "chars WHERE character_id = $charid AND world_id = $worldid) LIMIT 1";
	$result = WGWDB::$con->query($sql);
	if ($result and $result->num_rows != 0) {
		$row = $result->fetch_row();
		return $row[0];
	}
	return false;
}

function WGWIsEmailDomainBanned($domain)
{
	$domain_escaped = WGWDB::$con->real_escape_string($domain);
	$result = WGWDB::$con->query("SELECT domain FROM " . WGWConfig::$db_prefix . "blocked_domains WHERE domain='$domain' LIMIT 1");
	if ($result->num_rows != 0) {
		return true;
	}
	return false;
}

function WGWIsIP4AddressBanned($ip4address)
{
	// Note: $ip4address is assumed to already be escaped.
	// This function is called by WGWIsIPAddressBanned if needed, do not call directly.
	$result = WGWDB::$con->query("SELECT network_address FROM " . WGWConfig::$db_prefix . "blocked_ranges WHERE (INET_ATON(network_address) & INET_ATON(subnet_mask)) = (INET_ATON(\"$ip4address\") & INET_ATON(subnet_mask)) LIMIT 1;");
	if ($result->num_rows != 0) {
		return true;
	}
	return false;
}

function WGWIsIP6AddressBanned($ip6address)
{
	// Note: $ip6address is assumed to already be escaped.
	// This function is called by WGWIsIPAddressBanned if needed, do not call directly.
	$sql = "SELECT network_address, prefix FROM " . WGWConfig::$db_prefix . "blocked_ranges_v6";
	$result = WGWDB::$con->query($sql);
	if (!$result) {
		// Fail closed
		return true;
	}
	// MySQL / MariaDB don't have proper tools to parse IPv6 on the DB side.
	// fallback to doing the calculation ourselves.
	$row = $result->fetch_row();
	while ($row) {
		$cidr = $row[0] . "/" . strval($row[1]);
		if (CIDR::match($ip6address, $cidr)) {
			return true;
		}
		$row = $result->fetch_row();
	}
	return false;
}

function WGWIsIPAddressBanned($ipaddress)
{
	if (!$ipaddress) {
		// If we can't get an IP address block by default
		return true;
	}
	$ip_escaped = WGWDB::$con->real_escape_string($ipaddress);
	$ipver = 0;
	if (filter_var($ipaddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		$ipver = 4;
		return WGWIsIP4AddressBanned($ip_escaped);
	}
	else if (filter_var($ipaddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
		$ipver = 6;
		return WGWIsIP6AddressBanned($ip_escaped);
	}
	// Fail closed
	return true;
}

?>
