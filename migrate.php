<?php

require_once("database.php");
require_once("user.php");
require_once("output.php");
require_once("login.php");

// World ID of the source world we're migrating the account from
global $g_wgwMigrationSource;
$g_wgwMigrationSource = 102;

global $g_wgwMigrateForm;
$g_wgwMigrateForm = <<<EOS
	<center>
		<form method="POST">
			<input type="hidden" name="page" value="migrate">
			<table border="0">
				<tbody>
					<tr>
						<td>Username: </td>
						<td><input type="text" name="worlduser" size="30"></td>
					</tr>
					<tr>
						<td>Password: </td>
						<td><input type="password" name="worldpass" size="30"></td>
					</tr>
					<tr>
						<td></td>
						<td><center><input type="submit" value="Migrate"></center></td>
					</tr>
				</tbody>
			</table>
		</form>
	</center>
EOS;

function WGWMigrateAccount($worlduser, $worldpass)
{
	global $g_wgwMigrationSource;
	
	WGWForceLogin();
	
	// Verify user and password, get their old account ID so we can query characters.
	$sql = "SELECT id, status, migrated_to FROM accounts WHERE login = '" . WGWDB::$con->real_escape_string($worlduser) . "' AND password = PASSWORD('" . WGWDB::$con->real_escape_string($worldpass) . "');";
	$result = WGWDB::$maps[$g_wgwMigrationSource]["db"]->query($sql);
	if (!$result) {
		WGWOutput::$out->write("Error connecting to the target world database.");
		return false;
	}
	if ($result->num_rows == 0) {
		WGWOutput::$out->write("Invalid username or bad password.");
		return false;
	}
	$row = $result->fetch_row();
	// Don't bother migrating banned accounts
	if ($row[1] != 1) {
		WGWOutput::$out->write("The target account is disabled.");
		return false;
	}
	if ($row[2] != null) {
		WGWOutput::$out->write("The target account has already been migrated.");
		return false;
	}
	$accid = $row[0];
	
	// Get a list of all characters associated with that account
	$sql = "SELECT charid, charname FROM chars WHERE accid = $accid;";
	$result = WGWDB::$maps[$g_wgwMigrationSource]["db"]->query($sql);
	if (!$result || $result->num_rows == 0) {
		WGWOutput::$out->write("The target account has no characters.");
		return false;
	}
	$chars = $result->fetch_all();
	
	// Get a list of content IDs associated with this account and find free content IDs
	$sql = "SELECT content_id FROM " . WGWConfig::$db_prefix . "contents WHERE account_id = " . WGWUser::$user->id . " AND enabled = 1";
	$result = WGWDB::$con->query($sql);
	if (!$result || $result->num_rows == 0) {
		WGWOutput::$out->write("No active content IDs are associated with the account.");
		return false;
	}
	$contents = array();
	$row = $result->fetch_row();
	while ($row) {
		$contents[] = $row[0];
		$row = $result->fetch_row();
	}
	$contents_str = "(";
	$first = true;
	foreach ($contents as $content) {
		if (!$first) {
			$contents_str = $contents_str . ", ";
		}
		$first = false;
		$contents_str = $contents_str . strval($content);
	}
	$contents_str = $contents_str . ")";
	$sql = "SELECT content_id FROM " . WGWConfig::$db_prefix . "chars WHERE content_id in $contents_str";
	$result = WGWDB::$con->query($sql);
	if (!$result) {
		WGWOutput::$out->write("Cannot query current characters.");
		return false;
	}
	$used_contents = array();
	$row = $result->fetch_row();
	while ($row) {
		$used_contents[] = $row[0];
		$row = $result->fetch_row();
	}
	$free_contents = array();
	foreach ($contents as $content) {
		if (!in_array($content, $used_contents)) {
			$free_contents[] = $content;
		}
	}
	if (count($free_contents) < count($chars)) {
		$needed = count($chars) - count($free_contents);
		WGWOutput::$out->write("You do not have enough free content IDs to perform the migrtion. Please delete $needed characters from your account and try again.");
		return false;
	}
	
	// All verifications passed, add characters to login database and associate with content IDs
	$char_count = count($chars);
	$success = array();
	$failure = array();
	for ($i = 0; $i < $char_count; $i++) {
		$sql = "SELECT chars.charid, accid, charname, goldworldpass, mjob, mlvl, pos_zone, race, face, head, body, hands, legs, feet, main, sub, size, nation FROM chars, char_stats, char_look WHERE char_stats.charid = chars.charid AND char_look.charid = chars.charid AND chars.charid = " . $chars[$i][0] . ";";
		$result = WGWDB::$maps[$g_wgwMigrationSource]["db"]->query($sql);
		if (!$result || $result->num_rows == 0) {
			$failure[] = $chars[$i];
			continue;
		}
		$row = $result->fetch_row();
		// Taken from the initial migration script, translated from Python to PHP
		//"INSERT INTO %schars (content_id, character_id, name, world_id, goldworldpass, main_job, main_job_lv, zone, race, face, hair, head, body, hands, legs, feet, main, sub, size, nation) VALUES (%d, %d, '%s', %d, '%s', %d, %d, %d, %d, %d, 0, %d, %d, %d, %d, %d, %d, %d, %d, %d)" % \
        //(LOGIN_PREFIX, new_content_id, row[0], charname_esc, world_id, gwp_esc, row[4], row[5], row[6], row[7], row[8], row[9], row[10], row[11], row[12], row[13], row[14], row[15], row[16], row[17])"
		$sql = "INSERT INTO " . WGWConfig::$db_prefix . "chars (content_id, character_id, name, world_id, goldworldpass, main_job, main_job_lv, zone, race, face, hair, head, body, hands, legs, feet, main, sub, size, nation) VALUES (";
		$sql = $sql . strval($free_contents[$i]) . ", " . strval($row[0]) . ", '" . WGWDB::$con->real_escape_string($row[2]) . "', " . strval($g_wgwMigrationSource) . ", '" . WGWDB::$con->real_escape_string($row[3]) . "', ";
		$sql = $sql . strval($row[4]) . ", " . strval($row[5]) . ", " . strval($row[6]) . ", " . strval($row[7]) . ", " . strval($row[8]) . ", 0, " . strval($row[9]) . ", " . strval($row[10]) . ", " . strval($row[11]) . ", ";
		$sql = $sql . strval($row[12]) . ", " . strval($row[13]) . ", " . strval($row[14]) . ", " . strval($row[15]) . ", " . strval($row[16]) . ", " . strval($row[17]) . ");";
		if (!WGWDB::$con->query($sql)) {
			echo $sql;die;
			$failure[] = $chars[$i];
			continue;
		}
		$sql = "UPDATE chars SET content_id = " . strval($free_contents[$i]) . " WHERE charid = " . strval($chars[$i][0]) . ";";
		if (!WGWDB::$maps[$g_wgwMigrationSource]["db"]->query($sql)) {
			$failure[] = $chars[$i];
			echo $sql;die;
			continue;
		}
		$success[] = $chars[$i];
	}
	
	// Only mark the account as migrated if at least one character has been successfully migrated
	if (count($success) > 0) {
		$sql = "UPDATE accounts SET migrated_to = '" . WGWDB::$con->real_escape_string($worlduser) . "' WHERE id = $accid;";
		WGWDB::$maps[$g_wgwMigrationSource]["db"]->query($sql);
	}
	
	$success_str = "";
	$first = true;
	foreach ($success as $char) {
		if (!$first) {
			$success_str = $success_str . ", ";
		}
		$first = false;
		$success_str = $success_str . htmlspecialchars($char[1]);
	}
	$failure_str = "";
	$first = true;
	foreach ($failure as $char) {
		if (!$first) {
			$failure_str = $failure_str . ", ";
		}
		$first = false;
		$failure_str = $failure_str . htmlspecialchars($char[1]);
	}
	if (count($failure) > 0) {
		WGWOutput::$out->write("Failed to migrate the following characters: $failure_str");
	}
	if (count($success) > 0) {
		WGWOutput::$out->write("Successfully migrated the following characters: $success_str");
		return true;
	}
	return false;
}

function WGWDoMigration()
{
	global $g_wgwMigrateForm;
	
	WGWForceLogin();
	
	$result = false;
	if (array_key_exists("worlduser", $_REQUEST) && array_key_exists("worldpass", $_REQUEST)) {
		$result = WGWMigrateAccount($_REQUEST["worlduser"], $_REQUEST["worldpass"]);
		if (!$result) {
			// Some spaces because we need to print the form again
			WGWOutput::$out->write("<br><br>");
		}
	}
	if (!$result) {
	WGWOutput::$out->write("<p style=\"text-align: center\">Enter your Tonberry login details to migrate your characters.</p>");
	WGWOutput::$out->write($g_wgwMigrateForm);
	}
}

?>