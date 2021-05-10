<?php

/**
 *	@file serverutils.php
 *	Global util functions used by the system
 *	(C) 2021 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("configuration.php");


/**
 *	Return the real IP address of the connecting user, taking the possibility
 *	of a reverse proxy into account.
 *	@return The origin IP address as a string
 */
function WGWGetRemoteIPAddress()
{
	if (property_exists("WGWConfig", "reverse_proxy_origin_ip_field") and WGWConfig::$reverse_proxy_origin_ip_field) {
		if (array_key_exists(WGWConfig::$reverse_proxy_origin_ip_field, $_SERVER)) {
			return $_SERVER[WGWConfig::$reverse_proxy_origin_ip_field];
		}
	}
	return $_SERVER["REMOTE_ADDR"];
}

?>
