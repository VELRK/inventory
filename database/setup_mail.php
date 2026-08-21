<?php
/**
 * Configure Hostinger SMTP + seed email templates.
 * Browser: /plots/database/setup_mail.php
 * CLI: php database/setup_mail.php
 */
header('Content-Type: text/plain; charset=utf-8');

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

$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
	http_response_code(500);
	echo "DB connect failed: {$mysqli->connect_error}\n";
	exit(1);
}

$mysqli->query("CREATE TABLE IF NOT EXISTS `email_templates` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`event_key` VARCHAR(80) NOT NULL,
	`name` VARCHAR(120) NOT NULL,
	`subject` VARCHAR(200) NOT NULL,
	`body` TEXT NOT NULL,
	`placeholders` VARCHAR(500) NULL,
	`is_active` TINYINT(1) NOT NULL DEFAULT 1,
	`updated_at` DATETIME NULL,
	`created_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uk_email_event` (`event_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "email_templates table ready\n";

$settings = array(
	array('mail_protocol', 'smtp', 'mail', 0),
	array('mail_smtp_host', 'smtp.hostinger.com', 'mail', 0),
	array('mail_smtp_port', '465', 'mail', 0),
	array('mail_smtp_user', 'info@superfinelabels.in', 'mail', 0),
	array('mail_smtp_pass', 'Velmurugn0071@!!', 'mail', 1),
	array('mail_smtp_crypto', 'ssl', 'mail', 0),
	array('mail_from_email', 'info@superfinelabels.in', 'mail', 0),
	array('mail_from_name', 'Inventory', 'mail', 0),
	array('mail_enabled', '1', 'mail', 0),
	array('app_frontend_url', 'https://superfinelabels.in/plots/app', 'general', 0),
	array('app_name', 'Inventory', 'general', 0),
);

foreach ($settings as $s) {
	list($key, $value, $group, $secret) = $s;
	$keyEsc = $mysqli->real_escape_string($key);
	$valEsc = $mysqli->real_escape_string($value);
	$groupEsc = $mysqli->real_escape_string($group);
	$exists = $mysqli->query("SELECT id FROM settings WHERE setting_key='{$keyEsc}'")->fetch_assoc();
	if ($exists) {
		$mysqli->query("UPDATE settings SET setting_value='{$valEsc}', updated_at=NOW() WHERE setting_key='{$keyEsc}'");
		echo "updated setting {$key}\n";
	} else {
		$mysqli->query("INSERT INTO settings (setting_key,setting_value,setting_group,is_secret,updated_at) VALUES ('{$keyEsc}','{$valEsc}','{$groupEsc}',{$secret},NOW())");
		echo "inserted setting {$key}\n";
	}
}

$templates = array(
	array('auth.forgot', 'Password reset request', 'Reset your Inventory password', "Hello {name},\n\nWe received a password reset request for your Inventory account.\n\nClick this link to set a new password (valid {expires}):\n{link}\n\nIf you did not request this, you can ignore this email.", 'name, link, expires, token'),
	array('auth.reset_done', 'Password reset confirmation', 'Your Inventory password was reset', "Hello {name},\n\nYour password was changed successfully using the email reset link.\n\nSign in here:\n{login_link}\n\nIf you did not do this, contact your administrator immediately.", 'name, login_link'),
	array('auth.password_changed', 'Password change confirmation', 'Your Inventory password was changed', "Hello {name},\n\nYour account password was changed from the Inventory portal.\n\nIf this was not you, reset your password from the login page immediately.", 'name'),
	array('user.created', 'New user welcome', 'Welcome to Inventory', "Hello {name},\n\nYour Inventory account is ready.\nEmail: {email}\n\nSign in and change your password after first login.", 'name, email'),
	array('request.submitted', 'Hold request submitted (admin)', 'New hold request · {unit_no}', "Hello,\n\nA hold request was submitted.\n\nUnit: {unit_no}\nProject: {project}\nCompany: {company}\n\nPlease review it in the Inventory portal.", 'unit_no, project, company'),
	array('request.approved', 'Hold request approved', 'Hold request approved · {unit_no}', "Hello,\n\nYour hold request for unit {unit_no} was approved.\nYou can proceed to book the unit with the customer.", 'unit_no, notes'),
	array('request.rejected', 'Hold request rejected', 'Hold request rejected · {unit_no}', "Hello,\n\nYour hold request for unit {unit_no} was rejected.\nNotes: {notes}", 'unit_no, notes'),
	array('inventory.status', 'Inventory status update', 'Unit {unit_no} is now {status}', "Hello,\n\nInventory status was updated.\n\nUnit: {unit_no}\nNew status: {status}", 'unit_no, status'),
	array('booking.created', 'Booking created', 'New booking · {unit_no}', "Hello,\n\nA booking was recorded.\n\nCustomer: {customer}\nUnit: {unit_no}\nAmount: {amount}\n\nThank you.", 'customer, unit_no, amount'),
	array('registration.created', 'Registration created', 'New registration · {unit_no}', "Hello,\n\nA registration was recorded.\n\nCustomer: {customer}\nUnit: {unit_no}\n\nThank you.", 'customer, unit_no'),
	array('company.created', 'Marketing company added', 'Company added · {name}', "Hello,\n\nMarketing company {name} has been added to Inventory.", 'name'),
	array('mail.test', 'SMTP test email', 'Inventory mail test', "Hello,\n\nThis is a test email from Inventory.\nIf you received this, SMTP is working correctly.", ''),
);

foreach ($templates as $t) {
	list($event, $name, $subject, $body, $ph) = $t;
	$e = $mysqli->real_escape_string($event);
	$n = $mysqli->real_escape_string($name);
	$s = $mysqli->real_escape_string($subject);
	$b = $mysqli->real_escape_string($body);
	$p = $mysqli->real_escape_string($ph);
	$exists = $mysqli->query("SELECT id FROM email_templates WHERE event_key='{$e}'")->fetch_assoc();
	if ($exists) {
		echo "template exists {$event}\n";
		continue;
	}
	$mysqli->query("INSERT INTO email_templates (event_key,name,subject,body,placeholders,is_active,created_at,updated_at) VALUES ('{$e}','{$n}','{$s}','{$b}','{$p}',1,NOW(),NOW())");
	echo "seeded template {$event}\n";
}

echo "Done. SMTP enabled for Hostinger (smtp.hostinger.com:465 SSL).\n";
$mysqli->close();
