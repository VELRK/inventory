<?php
/**
 * Create + seed role_permissions for Access control page.
 * Browser: /plots/database/setup_access.php
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

$mysqli->query("CREATE TABLE IF NOT EXISTS `role_permissions` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`role` VARCHAR(40) NOT NULL,
	`permission_key` VARCHAR(80) NOT NULL,
	`is_allowed` TINYINT(1) NOT NULL DEFAULT 1,
	`updated_at` DATETIME NULL,
	`created_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uk_role_perm` (`role`, `permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "role_permissions table ready\n";

$count = (int) $mysqli->query('SELECT COUNT(*) c FROM role_permissions')->fetch_assoc()['c'];
if ($count > 0) {
	echo "Already seeded ({$count} rows). Skip.\n";
	$mysqli->close();
	exit(0);
}

$all = array(
	'nav.dashboard','nav.projects','nav.inventory','nav.companies','nav.users','nav.requests','nav.bookings',
	'nav.activity','nav.settings','nav.email_templates','nav.access','nav.schema','nav.api_tester',
	'projects.manage','inventory.create','inventory.edit','inventory.delete','companies.manage','users.manage',
	'requests.review','bookings.manage','registrations.manage','settings.manage','activity.view','access.manage'
);
$teamAdmin = array(
	'nav.dashboard','nav.projects','nav.inventory','nav.requests','nav.bookings','nav.users',
	'inventory.edit','users.manage','bookings.manage'
);
$teamUser = array('nav.dashboard','nav.projects','nav.inventory','nav.requests','nav.users');

$seed = array(
	'promoter_admin' => $all,
	'marketing_team_admin' => $teamAdmin,
	'marketing_team_user' => $teamUser,
);

foreach ($seed as $role => $keys) {
	foreach ($keys as $key) {
		$r = $mysqli->real_escape_string($role);
		$k = $mysqli->real_escape_string($key);
		$mysqli->query("INSERT IGNORE INTO role_permissions (role, permission_key, is_allowed, created_at, updated_at) VALUES ('{$r}','{$k}',1,NOW(),NOW())");
	}
	echo "seeded {$role}\n";
}

echo "Done.\n";
$mysqli->close();
