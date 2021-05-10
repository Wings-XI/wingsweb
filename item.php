<?php

/**
 *	@file item.php
 *	Display item information
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("output.php");
require_once("login.php");
require_once("lists.php");
require_once("database.php");
require_once("zones.php");

global $g_wgwItemFlags;
$g_wgwItemFlags = array(
	"ITEM_FLAG_WALLHANGING" => 0x0001,
	"ITEM_FLAG_01" => 0x0002,
	"ITEM_FLAG_MYSTERY_BOX" => 0x0004,
	"ITEM_FLAG_MOG_GARDEN" => 0x0008,
	"ITEM_FLAG_MAIL2ACCOUNT" => 0x0010,
	"ITEM_FLAG_INSCRIBABLE" => 0x0020,
	"ITEM_FLAG_NOAUCTION" => 0x0040,
	"ITEM_FLAG_SCROLL" => 0x0080,
	"ITEM_FLAG_LINKSHELL" => 0x0100,
	"ITEM_FLAG_CANUSE" => 0x0200,
	"ITEM_FLAG_CANTRADENPC" => 0x0400,
	"ITEM_FLAG_CANEQUIP" => 0x0800,
	"ITEM_FLAG_NOSALE" => 0x1000,
	"ITEM_FLAG_NODELIVERY" => 0x2000,
	"ITEM_FLAG_EX" => 0x4000,
	"ITEM_FLAG_RARE" => 0x8000
	);

function WGWShowItemInfoById($itemid, $worldid=100)
{
	global $g_base;
	global $g_wgwItemFlags;
	
	if (!ctype_digit($itemid)) {
		WGWOutput::$out->write("Item ID must be numeric.<br>");
		die(0);
	}
	$result = WGWDB::$maps[$worldid]["db"]->query("SELECT * FROM item_basic WHERE itemid=$itemid");
	if ($result->num_rows == 0) {
		WGWOutput::$out->write("No such item.<br>");
		die(0);
	}
	$item_details = $result->fetch_assoc();
	$item_name = htmlspecialchars(ucfirst(str_replace("_", " ", $item_details["name"])));
	WGWOutput::$out->title = "Item: $item_name";
	WGWOutput::$out->write("<h2>$item_name</h2>");
	if (($item_details["flags"] & $g_wgwItemFlags["ITEM_FLAG_EX"]) &&
		($item_details["flags"] & $g_wgwItemFlags["ITEM_FLAG_RARE"])) {
		WGWOutput::$out->write("<p><b>Rare / Exclusive</b></p>");
	}
	else if ($item_details["flags"] & $g_wgwItemFlags["ITEM_FLAG_EX"]) {
		WGWOutput::$out->write("<p><b>Exclusive</b></p>");
	}
	else if ($item_details["flags"] & $g_wgwItemFlags["ITEM_FLAG_RARE"]) {
		WGWOutput::$out->write("<p><b>Rare</b></p>");
	}
	// Top table (because we're going to split the screen to two columns for better usage)
	WGWOutput::$out->write("<table border=\"0\" style=\"width: 100%\"><tbody><tr><td style=\"width: 50%; vertical-align: top\">");
	// Auction History
	WGWOutput::$out->write("<h3>Auction History</h3><table border=\"0\">
	<colgroup>
	<col span=\"1\" style=\"width: 30%;\">
	<col span=\"1\" style=\"width: 30%;\">
	<col span=\"1\" style=\"width: 10%;\">
	<col span=\"1\" style=\"width: 10%;\">
	<col span=\"1\" style=\"width: 20%;\">
	</colgroup>
	<tbody>");
	$no_auction = false;
	if (($item_details["flags"] & $g_wgwItemFlags["ITEM_FLAG_EX"]) ||
		($item_details["flags"] & $g_wgwItemFlags["ITEM_FLAG_NOAUCTION"])) {
		$no_auction = true;
	}
	if (!$no_auction) {
		$result = WGWDB::$maps[$worldid]["db"]->query("SELECT stack, COUNT(*) FROM auction_house WHERE itemid=$itemid AND sell_date = 0 GROUP BY stack");
		$row = $result->fetch_row();
		$item_count = 0;
		$stack_count = 0;
		while ($row) {
			if ($row[0]) {
				$stack_count = $row[1];
			}
			else {
				$item_count = $row[1];
			}
			$row = $result->fetch_row();
		}
		if (($item_count) or ($stack_count)) {
			WGWOutput::$out->write("<p>Quantity currently on auction: ");
			if ($item_count) {
				WGWOutput::$out->write("$item_count items");
				if ($stack_count) {
					WGWOutput::$out->write(" and ");
				}
			}
			if ($stack_count) {
				WGWOutput::$out->write("$stack_count stacks");
			}
			WGWOutput::$out->write(".</p>");
		}
		else {
			WGWOutput::$out->write("<p>Out of stock.</p>");
		}
		
		$result = WGWDB::$maps[$worldid]["db"]->query("SELECT seller_name, buyer_name, stack, sale, sell_date FROM auction_house WHERE itemid=$itemid AND sell_date != 0 ORDER BY sell_date DESC LIMIT 30");
		$result_count = $result->num_rows;
	}
	else {
		// Don't bother running a query that we know won't return anything
		$result = null;
		$result_count = 0;
	}
	$row = null;
	if ($result_count != 0) {
		$row = $result->fetch_row();
		WGWOutput::$out->write("<tr><td style=\"padding-right: 10%;\"><b>Seller</b></td><td style=\"width: 10px;\"><b>Buyer</b></td>
		<td style=\"width: 10px;\"><b>Quantity</b></td><td style=\"width: 10px; text-align: right;\"><b>Price</b></td><td style=\"width: 10px; text-align: right\"><b>Date</b></td></tr>");
	}
	while ($row) {
		WGWOutput::$out->write("<tr><td style=\"width: 10px;\">$row[0]</td><td style=\"width: 10px;\">$row[1]</td><td style=\"width: 10px;\">");
		if ($row[2]) {
			WGWOutput::$out->write("Stack");
		}
		else {
			WGWOutput::$out->write("Single");
		}
		WGWOutput::$out->write("</td><td style=\"width: 10px; text-align: right\">$row[3]</td><td style=\"width: 10px; text-align: right\">");
		WGWOutput::$out->write(date("Y/m/d", $row[4]));
		WGWOutput::$out->write("</td></tr>");
		//WGWOutput::$out->write("<tr><td style=\"width: 10px;\">$row[0]</td><td style=\"text-align: right\">$joblevel</td></tr>");
		$row = $result->fetch_row();
	}
	WGWOutput::$out->write("</tbody></table>");
	if ($result_count == 0) {
		if ($no_auction) {
			WGWOutput::$out->write("This item cannot be put on auction.");
		}
		else {
			WGWOutput::$out->write("No auction history.");
		}
	}
	WGWOutput::$out->write("</td><td style=\"width: 50%; vertical-align: top;\">");
	WGWOutput::$out->write("<h3>Bazaar</h3><table border=\"0\" style=\"width: 100%;\">
	<colgroup>
	<col span=\"1\" style=\"width: 30%;\">
	<col span=\"1\" style=\"width: 30%;\">
	<col span=\"1\" style=\"width: 10%;\">
	<col span=\"1\" style=\"width: 10%;\">
	<col span=\"1\" style=\"width: 20%;\">
	</colgroup>
	<tbody>");
	$no_bazaar = false;
	if ($item_details["flags"] & $g_wgwItemFlags["ITEM_FLAG_EX"]) {
		$no_bazaar = true;
	}
	if (!$no_bazaar) {
		$result = WGWDB::$maps[$worldid]["db"]->query("
			SELECT chars.charid, charname, pos_zone, quantity, bazaar, accounts_sessions.charid AS online
			FROM chars
			INNER JOIN char_inventory
			ON chars.charid = char_inventory.charid
			LEFT JOIN accounts_sessions
			ON chars.charid = accounts_sessions.charid
			WHERE itemId=$itemid AND bazaar != 0 ORDER BY charname
		");
		$result_count = $result->num_rows;
	}
	else {
		$result = null;
		$result_count = 0;
	}
	if ($result_count != 0) {
		WGWOutput::$out->write("<p>Number of players bazaaring this item: $result_count.</p>");
		$row = $result->fetch_row();
		WGWOutput::$out->write("<tr><td style=\"width: 10px;\"><b>Seller</b></td><td style=\"width: 10px;\"><b>Zone</b></td>
		<td style=\"width: 10px; text-align: right;\"><b>Quantity</b></td><td style=\"width: 10px; text-align: right;\"><b>Price</b></td><td style=\"width: 10px; text-align: center;\"><b>Status</b></td></tr>");
	}
	while ($row) {
		WGWOutput::$out->write("<tr><td style=\"width: 10px;\">$row[1]</td><td style=\"width: 10px;\">");
		WGWOutput::$out->write(WGWGetZoneName($row[2]));
		WGWOutput::$out->write("</td><td style=\"width: 10px; text-align: right;\">
		$row[3]</td><td style=\"width: 10px; text-align: right\">$row[4]</td>");
		if ($row[5]) {
			WGWOutput::$out->write("<td style=\"width: 10px; text-align: center; color: green;\">Online</td>");
		}
		else {
			WGWOutput::$out->write("<td style=\"width: 10px; text-align: center; color: red;\">Offline</td>");
		}
		WGWOutput::$out->write("</tr>");
		$row = $result->fetch_row();
	}
	WGWOutput::$out->write("</tbody></table>");
	if ($result_count == 0) {
		if ($no_bazaar) {
			WGWOutput::$out->write("This item cannot be bazaared.");
		}
		else {
			WGWOutput::$out->write("Not currently available on any player's bazaar.");
		}
	}
	WGWOutput::$out->write("</td></tr></tbody></table>");
}

function WGWShowItemInfo()
{
	if (!array_key_exists("id", $_REQUEST)) {
		WGWOutput::$out->write("No item ID was specified");
		die(0);
	}
	$itemid = $_REQUEST["id"];
	$worldid = 100;
	if (array_key_exists("worldid", $_REQUEST)) {
		$worldid = intval($_REQUEST["worldid"]);
	}
	WGWShowItemInfoById($itemid, $worldid);
}
 
?>
