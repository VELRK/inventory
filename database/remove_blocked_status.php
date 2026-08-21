<?php
/**
 * Convert blocked units → on_hold using application/config/database.php
 * Browser: https://yoursite/plots/database/remove_blocked_status.php
 * CLI: php database/remove_blocked_status.php
 */
$cfgPath = dirname(__DIR__) . '/application/config/database.php';
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db   = 'syncr_inventory';

if (is_file($cfgPath)) {
	include $cfgPath;
	if (isset($db['default'])) {
		$host = $db['default']['hostname'];
		$user = $db['default']['username'];
		$pass = $db['default']['password'];
		$db   = $db['default']['database'];
	}
}

header('Content-Type: text/plain; charset=utf-8');
$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
	http_response_code(500);
	echo "DB connect failed: {$mysqli->connect_error}\n";
	exit(1);
}

$mysqli->query("UPDATE inventory_units SET status = 'on_hold' WHERE status = 'blocked'");
echo "Updated blocked → on_hold: " . $mysqli->affected_rows . " rows\n";

$sql = "ALTER TABLE inventory_units
	MODIFY COLUMN `status` ENUM('available','on_hold','booked','registered') NOT NULL DEFAULT 'available'";
if (!$mysqli->query($sql)) {
	// Already migrated or ENUM mismatch — still ok if no blocked left
	echo "ALTER note: {$mysqli->error}\n";
} else {
	echo "ENUM updated (blocked removed).\n";
}
$mysqli->close();
echo "Done.\n";
