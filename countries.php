<?php

require_once("geoip2.phar");
require_once("database.php");
require_once("login.php");
require_once("output.php");

function GetUserCountries()
{

	$georeader = new GeoIp2\Database\Reader("GeoLite2-Country.mmdb");

	$result = WGWDB::$con->query("SELECT account_id, client_ip, COUNT(*) AS num_logins FROM ww_login_log WHERE operation = 0 AND source = 1 AND result = 1 AND TIMESTAMPDIFF(DAY, login_time, NOW()) <= 30 GROUP BY account_id, client_ip ORDER BY account_id, COUNT(*) DESC");
	if (!$result || $result->num_rows == 0) {
		return false;
	}
	$row = $result->fetch_row();
	$user_countries = array();
	$country_names = array();
	$country_count = array();
	while ($row) {
		if (!array_key_exists($row[0], $user_countries)) {
			$georecord = $georeader->country($row[1]);
			$country_iso = $georecord->country->isoCode;
			$user_countries[$row[0]] = $country_iso;
			if (!array_key_exists($country_iso, $country_names)) {
				$country_names[$country_iso] = $georecord->country->name;
			}
			if (array_key_exists($country_iso, $country_count)) {
				$country_count[$country_iso]++;
			}
			else {
				$country_count[$country_iso] = 1;
			}
		}
		$row = $result->fetch_row();
	}
	
	return array("user_countries" => $user_countries, "country_names" => $country_names, "country_count" => $country_count);
}

function DisplayCountryStats($country_data)
{
	$reverse_count = array();
	foreach ($country_data["country_count"] as $code => $count) {
		if (array_key_exists($count, $reverse_count)) {
			$reverse_count[$count][] = $code;
		}
		else {
			$reverse_count[$count] = array($code);
		}
	}
	krsort($reverse_count);
	WGWOutput::$out->write("<table border=\"0\"><tbody><tr><td colspan=\"2\"><b>Country</b></td><td><b>Players</b></td></tr>");
	foreach ($reverse_count as $count => $codes) {
		foreach ($codes as $code) {
			WGWOutput::$out->write("<tr>");
			WGWOutput::$out->write("<td><img src=\"flags/" . strtolower($code) . ".png\" height=\"15\" alt=\"" . $code . "\"></td>");
			WGWOutput::$out->write("<td>" . $country_data["country_names"][$code] . "</td>");
			WGWOutput::$out->write("<td>" . strval($count) . "</td>");
			WGWOutput::$out->write("</tr>");
		}
	}
	WGWOutput::$out->write("</tbody></table>");
}

function DoCountryStats()
{
	WGWForceAdmin();

	$stats = GetUserCountries();
	if (!$stats) {
		WGWOutput::$out->write("Error gettings statistics.");
		return;
	}
	WGWOutput::$out->write("<h2>Wings players by country statistics</h2>");
	DisplayCountryStats($stats);
}

?>
