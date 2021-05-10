<?php

/**
 *	@file databse.php
 *	Wrapper around Mysqli for access to the server DB
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */

require_once("configuration.php");
// require_once("output.php");

class WGWDB
{
	public static $con;
	public static $maps;
};

function WGWConnectToLoginDB()
{
	if (WGWDB::$con) {
		return true;
	}
	WGWDB::$con = new mysqli(WGWConfig::$db_host, WGWConfig::$db_user, WGWConfig::$db_pass, WGWConfig::$db_database);
	if (WGWDB::$con->connect_errno) {
		echo "Error connecting to DB.";
		die(1);
	}
	return true;
}

function WGWConnectToMapDB()
{
	if (WGWDB::$maps) {
		return true;
	}
	$result = array();
	$dbres = WGWDB::$con->query("SELECT id, name, db_server_ip, db_server_port, db_use_ssl, db_ssl_verify_cert, db_ssl_ca_cert, db_ssl_client_cert, db_ssl_client_key, db_username, db_password, db_database, db_prefix, is_active, is_test FROM " . WGWConfig::$db_prefix . "worlds;");
	if (!$dbres) {
		echo "Error fetching world list!";
		die(1);
	}
	$row = $dbres->fetch_row();
	while ($row) {
		if (!$row[13]) {
			$row = $dbres->fetch_row();
			continue;
		}
		$mapcon = new mysqli($row[2], $row[9], $row[10], $row[11]);
		if (!$mapcon) {
			echo "Error connecting to world DB of world " . strval($row[0]);
			die(1);
		}
		$newentry = array("name" => $row[1], "test" => $row[14], "db" => $mapcon);
		$result[$row[0]] = $newentry;
		$row = $dbres->fetch_row();
	}
	WGWDB::$maps = $result;
	return true;
}

WGWConnectToLoginDB();
WGWConnectToMapDB();

?>
