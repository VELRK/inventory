<?php
/**
 * SYNCR test runner
 * php tests/run.php
 */

$root = dirname(__DIR__);
$failed = 0;
$passed = 0;

function assert_true($cond, $msg) {
	global $failed, $passed;
	if ($cond) {
		$passed++;
		echo "  PASS  $msg\n";
	} else {
		$failed++;
		echo "  FAIL  $msg\n";
	}
}

echo "=== UNIT: Schema_guard ===\n";
define('BASEPATH', 'test');
require $root . '/application/libraries/Schema_guard.php';
$g = new Schema_guard();

assert_true($g->is_blocked('DELETE FROM users') === false, 'allows DELETE data');
assert_true($g->is_blocked('DROP TABLE users') === 'DROP', 'blocks DROP');
assert_true($g->is_blocked('TRUNCATE TABLE users') === 'TRUNCATE', 'blocks TRUNCATE');
assert_true($g->is_blocked('ALTER TABLE projects DROP COLUMN name') !== false, 'blocks ALTER DROP');
assert_true($g->is_blocked('SELECT * FROM projects') === false, 'allows SELECT');
assert_true($g->is_delete_query('DELETE FROM projects WHERE id=1') === true, 'detects DELETE');
list($okDel, $delSql) = $g->build_delete_data('projects', array(1, 2));
assert_true($okDel === true && strpos($delSql, 'IN (1,2)') !== false, 'builds DELETE by ids');
list($ok, $sql) = $g->build_add_column('projects', 'rera_no', 'VARCHAR', '50', true, null, null);
assert_true($ok === true && strpos($sql, 'ADD COLUMN `rera_no` VARCHAR(50) NULL') !== false, 'builds ADD COLUMN SQL');
list($bad) = $g->build_add_column('projects', 'x', 'BLOB', '', true, null, null);
assert_true($bad === false, 'rejects disallowed type');

echo "\n=== UNIT: helpers ===\n";
require $root . '/application/helpers/syncr_helper.php';
assert_true(initials_of('Ravi Kumar') === 'RK', 'initials RK');
assert_true(status_label('on_hold') === 'On Hold', 'status label');
assert_true(strpos(format_inr(3600000), '36,00,000') !== false || strpos(format_inr(3600000), '3,600,000') !== false, 'INR format has thousands');

echo "\n=== INTEGRATION: API ===\n";
$base = 'http://localhost:8080/inventory/index.php/api';

function api($method, $path, $body = null, $token = null) {
	global $base;
	$ch = curl_init($base . $path);
	$headers = array('Content-Type: application/json', 'Accept: application/json');
	if ($token) {
		$headers[] = 'Authorization: Bearer ' . $token;
	}
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CUSTOMREQUEST => $method,
		CURLOPT_HTTPHEADER => $headers,
		CURLOPT_TIMEOUT => 20
	));
	if ($body !== null) {
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
	}
	$raw = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$err = curl_error($ch);
	curl_close($ch);
	$json = json_decode($raw, true);
	return array($code, $json, $err, $raw);
}

list($code, $json) = api('GET', '/docs');
assert_true($code === 200 && !empty($json['data']['endpoints']), 'GET /docs catalog');

list($code, $json) = api('POST', '/auth/login', array('email' => 'wrong@x.com', 'password' => 'bad'));
assert_true($code === 401 && $json['error']['code'] === 'INVALID_CREDENTIALS', 'login rejects bad credentials');

list($code, $json) = api('POST', '/auth/login', array('email' => 'admin@syncr.test', 'password' => 'Admin@123'));
assert_true($code === 200 && !empty($json['data']['token']), 'admin login');
$token = $json['data']['token'];

list($code, $json) = api('GET', '/auth/me', null, $token);
assert_true($code === 200 && $json['data']['role'] === 'promoter_admin', 'GET /auth/me');

list($code, $json) = api('GET', '/dashboard', null, $token);
assert_true($code === 200 && isset($json['data']['inventory']), 'GET /dashboard');

list($code, $json) = api('GET', '/dashboard/charts', null, $token);
assert_true($code === 200 && isset($json['data']['status_pie']), 'GET /dashboard/charts');

list($code, $json) = api('GET', '/projects?limit=5', null, $token);
assert_true($code === 200 && $json['data']['total'] >= 3, 'GET /projects');

list($code, $json) = api('GET', '/inventory?status=available', null, $token);
assert_true($code === 200 && isset($json['data']['stats']), 'GET /inventory');

list($code, $json) = api('GET', '/settings/credentials', null, $token);
assert_true($code === 200 && $json['data'][0]['email'] === 'admin@syncr.test', 'GET credentials');

list($code, $json) = api('POST', '/schema/query', array('sql' => 'DROP TABLE users'), $token);
assert_true($code === 403 && $json['error']['code'] === 'BLOCKED', 'schema blocks DROP');

list($code, $json) = api('POST', '/schema/query', array('sql' => 'SELECT id, name FROM projects LIMIT 1'), $token);
assert_true($code === 200 && isset($json['data']['rows']), 'schema allows SELECT');

list($code, $json) = api('POST', '/schema/delete-data', array('table' => 'schema_change_logs', 'ids' => array(-1)), $token);
assert_true($code === 200 && isset($json['data']['affected']), 'schema delete-data by ids');

list($code, $json) = api('POST', '/projects', array('name' => 'CRUD Test Park', 'city' => 'Chennai', 'location' => 'OMR'), $token);
assert_true($code === 201 && !empty($json['data']['id']), 'POST /projects create');
$pid = $json['data']['id'];
list($code, $json) = api('PUT', '/projects/' . $pid, array('name' => 'CRUD Test Park Edited', 'city' => 'Chennai', 'location' => 'OMR'), $token);
assert_true($code === 200 && $json['data']['name'] === 'CRUD Test Park Edited', 'PUT /projects/{id} update');
list($code, $json) = api('DELETE', '/projects/' . $pid, null, $token);
assert_true($code === 200, 'DELETE /projects/{id} archive');

list($code, $json) = api('GET', '/users?limit=1', null, $token);
assert_true($code === 200, 'GET /users');
list($code, $json) = api('GET', '/bookings?limit=1', null, $token);
assert_true($code === 200, 'GET /bookings');
list($code, $json) = api('GET', '/requests?limit=1', null, $token);
assert_true($code === 200, 'GET /requests');

list($code, $json) = api('GET', '/schema', null, $token);
assert_true($code === 200 && !empty($json['data']['tables']) && $json['data']['column_count'] > 20, 'GET /schema full column catalog');
$proj = null;
foreach ($json['data']['tables'] as $t) {
	if ($t['name'] === 'projects') { $proj = $t; break; }
}
assert_true($proj && count($proj['columns']) >= 8, 'projects table includes full column list');

list($code, $json) = api('POST', '/auth/login', array('email' => 'user@abc.test', 'password' => 'TeamUser@123'));
$teamToken = $json['data']['token'];
list($code, $json) = api('POST', '/projects', array('name' => 'Hack', 'city' => 'X'), $teamToken);
assert_true($code === 403, 'team user cannot create project');

list($code, $json) = api('GET', '/companies', null, $teamToken);
assert_true($code === 200, 'team user can view own company scope');

echo "\n============================\n";
echo "Passed: $passed   Failed: $failed\n";
exit($failed > 0 ? 1 : 0);
