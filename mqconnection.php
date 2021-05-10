<?php

/**
 *	@file mqconnection.php
 *	Easy access to the map MQ server
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
 
require_once("configuration.php");
require_once("database.php");
require_once("output.php");

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Message\AMQPMessage;

require_once("PhpAmqpLib/autoload.php");

global $g_wgwMQCnnections;
$g_wgwMQCnnections = array();

global $g_wgwMQSSL;
$g_wgwMQSSL = true;
global $g_wgwMQSSLVerify;
$g_wgwMQSSLVerify = false;
global $g_wgwMQSSLCACert;
$g_wgwMQSSLCACert = "mqca.crt";
global $g_wgwForcePort;
$g_wgwForcePort = 5671;

function WGWConnectToMQ($worldid=100, $queue_name="wgwsite")
{
	global $g_wgwMQCnnections;
	global $g_wgwMQSSL;
	global $g_wgwMQSSLVerify;
	global $g_wgwMQSSLCACert;
	global $g_wgwForcePort;
	
	if (array_key_exists($worldid, $g_wgwMQCnnections)) {
		return true;
	}
	$dbres = WGWDB::$con->query("SELECT id, name, mq_server_ip, mq_server_port, mq_use_ssl, mq_ssl_verify_cert, mq_ssl_ca_cert, mq_ssl_client_cert, mq_ssl_client_key, mq_username, mq_password, mq_vhost, is_active, is_test FROM " . WGWConfig::$db_prefix . "worlds WHERE id=$worldid;");
	if (!$dbres) {
		WGWOutput::$out->write("Error fetching world list!");
		die(1);
	}
	$row = $dbres->fetch_row();
	if (!$row) {
		WGWOutput::$out->write("No such world id!");
		die(1);
	}
	if (!$row[12]) {
		WGWOutput::$out->write("World is not active!");
		die(1);
	}
	$port = $row[3];
	if ($g_wgwForcePort) {
		$port = $g_wgwForcePort;
	}
	if ($g_wgwMQSSL) {
		// Using SSL
		$ssl_options = array();
		if ($g_wgwMQSSLVerify) {
			$ssl_options["verify_peer"] = true;
			$ssl_options["verify_peer_name"] = true;
			$ssl_options["cafile"] = $g_wgwMQSSLCACert;
		}
		else {
			$ssl_options["verify_peer"] = false;
			$ssl_options["verify_peer_name"] = false;
		}
		$connection = new AMQPSSLConnection($row[2], $port, $row[9], $row[10], $row[11], $ssl_options);
	}
	else {
		$connection = new AMQPStreamConnection($row[2], $port, $row[9], $row[11], $row[10]);
	}
	if (!$connection) {
		WGWOutput::$out->write("Connection to MQ server failed!");
		die(1);
	}
	$channel = $connection->channel();
	if (!$channel) {
		WGWOutput::$out->write("MQ channel creation failed!");
		die(1);
	}
	$channel->queue_declare($queue_name, false, false, false, false);
	$channel->queue_bind($queue_name, "amq.fanout", $queue_name);
	$g_wgwMQCnnections[$worldid] = array($connection, $channel);
	return true;
}

function WGWBuildChatPacket($message, $sender, $zoneid, $is_gm = false, $msgtype = 6)
{
	$packet = "\x17\x82\x00\x00";
	$packet .= chr($msgtype);
	if ($is_gm) {
		$packet .= "\x01";
	}
	else {
		$packet .= "\x00";
	}
	$packet .= pack("S", $zoneid);
	$sender_len = strlen($sender);
	if ($sender_len > 15) {
		return false;
	}
	$packet .= $sender;
	$packet .= str_repeat("\x00", 16 - $sender_len);
	$msg_len = strlen($message);
	if ($msg_len > 106) {
		return false;
	}
	$packet .= $message;
	$packet .= str_repeat("\x00", 0x82 - strlen($packet));
	return $packet;
}

function WGWBuildMapMQMessage($msgservtype, $payload)
{
	$packet = "NWAD";
	// Set origin to zero because no map server will share it
	$packet .= "\x00\x00\x00\x00\x00\x00\x00\x00";
	$packet .= chr($msgservtype);
	$packet .= pack("L", strlen($payload));
	$packet .= "\x00\x00\x00\x00";
	$packet .= $payload;
	return $packet;
}

function WGWSendMessageToMap($message, $worldid=100)
{
	global $g_wgwMQCnnections;
	if (!WGWConnectToMQ($worldid)) {
		WGWOutput::$out->write("MQ initialization failed!");
		die(1);
	}
	$g_wgwMQCnnections[$worldid][1]->basic_publish($message, "amq.fanout", "wgwsite");
}

function WGWSendNotificationToGMs($content, $sender, $zoneid)
{
	$chat = WGWBuildChatPacket("GM ticket opened on website by " . $sender . ": " . $content, $sender, $zoneid);
	if (!$chat) {
		return;
	}
	$message = WGWBuildMapMQMessage(16, $chat);
	if (!$message) {
		return;
	}
	$msgobj = new AMQPMessage($message);
	WGWSendMessageToMap($msgobj);
	WGWOutput::$out->write("Message successfully sent!");
}

?>
