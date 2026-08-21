<?php
/**
 * Update promoter admin login email to velrke@gmail.com
 * Browser: /plots/database/update_admin_email.php
 */
header('Content-Type: text/plain; charset=utf-8');

$cfgPath = dirname(__DIR__) . '/application/config/database.php';
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db   = 'syncr_inventory';
$newEmail = 'velrke@gmail.com';

if (is_file($cfgPath)) {
	include $cfgPath;
	if (isset($db['default'])) {
		$host = $db['default']['hostname'];
		$user = $db['default']['username'];
		$pass = $db['default']['password'];
		$db   = $db['default']['database'];
	}
}

$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
	http_response_code(500);
	echo "DB connect failed: {$mysqli->connect_error}\n";
	exit(1);
}

$emailEsc = $mysqli->real_escape_string($newEmail);

$mysqli->query("UPDATE users SET email='{$emailEsc}', updated_at=NOW()
	WHERE deleted_at IS NULL AND (email='admin@syncr.test' OR (role='promoter_admin' AND id=1))");
echo "users.email -> {$newEmail} (affected {$mysqli->affected_rows})\n";

$exists = $mysqli->query("SELECT id FROM settings WHERE setting_key='test_admin_email'")->fetch_assoc();
if ($exists) {
	$mysqli->query("UPDATE settings SET setting_value='{$emailEsc}', updated_at=NOW() WHERE setting_key='test_admin_email'");
	echo "settings.test_admin_email updated\n";
} else {
	$mysqli->query("INSERT INTO settings (setting_key,setting_value,setting_group,is_secret,updated_at) VALUES ('test_admin_email','{$emailEsc}','credentials',0,NOW())");
	echo "settings.test_admin_email inserted\n";
}

echo "Done. Login with {$newEmail} (same password as before).\n";
$mysqli->close();
