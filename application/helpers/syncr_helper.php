<?php
defined('BASEPATH') OR exit('No direct script access allowed');

function json_body()
{
	static $cached = null;
	if ($cached !== null) {
		return $cached;
	}
	$raw = file_get_contents('php://input');
	if ($raw === false || $raw === '') {
		$cached = array();
		return $cached;
	}
	$decoded = json_decode($raw, true);
	$cached = is_array($decoded) ? $decoded : array();
	return $cached;
}

function request_value($key, $default = null)
{
	$ci =& get_instance();
	$body = json_body();
	if (array_key_exists($key, $body) && $body[$key] !== '' && $body[$key] !== null) {
		return $body[$key];
	}
	// Accept camelCase from mobile clients (companyId → company_id).
	$camel = preg_replace_callback('/_([a-z])/', function ($m) {
		return strtoupper($m[1]);
	}, $key);
	if ($camel !== $key && array_key_exists($camel, $body) && $body[$camel] !== '' && $body[$camel] !== null) {
		return $body[$camel];
	}
	$post = $ci->input->post($key);
	if ($post !== null && $post !== false && $post !== '') {
		return $post;
	}
	if ($camel !== $key) {
		$postCamel = $ci->input->post($camel);
		if ($postCamel !== null && $postCamel !== false && $postCamel !== '') {
			return $postCamel;
		}
	}
	$get = $ci->input->get($key);
	if ($get !== null && $get !== false && $get !== '') {
		return $get;
	}
	return $default;
}

/** First non-empty value among keys (snake or camel). */
function request_any($keys, $default = null)
{
	foreach ((array) $keys as $key) {
		$val = request_value($key, null);
		if ($val !== null && $val !== '') {
			return $val;
		}
	}
	return $default;
}

function now_dt()
{
	return date('Y-m-d H:i:s');
}

function db_error_message($fallback = 'Database error. Please try again.')
{
	$ci =& get_instance();
	$err = $ci->db->error();
	$code = isset($err['code']) ? (int) $err['code'] : 0;
	$msg = isset($err['message']) ? trim((string) $err['message']) : '';
	if ($code === 1062 || stripos($msg, 'Duplicate') !== false) {
		return 'This record already exists (duplicate value).';
	}
	if ($code === 1452 || stripos($msg, 'foreign key') !== false) {
		return 'Related record not found (invalid company or project id).';
	}
	if ($code === 1265 || stripos($msg, 'truncated') !== false || stripos($msg, 'Data truncated') !== false) {
		return 'Invalid value for a field (check role/status).';
	}
	if ($msg !== '') {
		// Keep short for mobile UI
		return strlen($msg) > 180 ? substr($msg, 0, 177) . '…' : $msg;
	}
	return $fallback;
}

function format_inr($amount)
{
	$amount = (float) $amount;
	return '₹' . number_format($amount, 0, '.', ',');
}

function initials_of($name)
{
	$parts = preg_split('/\s+/', trim((string) $name));
	$out = '';
	foreach ($parts as $part) {
		if ($part !== '') {
			$out .= strtoupper(substr($part, 0, 1));
		}
		if (strlen($out) >= 2) {
			break;
		}
	}
	return $out !== '' ? $out : 'NA';
}

function status_label($status)
{
	$map = array(
		'available' => 'Available',
		'on_hold' => 'On Hold',
		'booked' => 'Booked',
		'registered' => 'Registered',
		'pending' => 'Pending',
		'approved' => 'Approved',
		'rejected' => 'Rejected',
		'confirmed' => 'Confirmed',
		'cancelled' => 'Cancelled',
		'unpaid' => 'Unpaid',
		'partial' => 'Partial',
		'paid' => 'Paid',
		'active' => 'Active',
		'inactive' => 'Inactive'
	);
	$key = strtolower((string) $status);
	return isset($map[$key]) ? $map[$key] : ucfirst(str_replace('_', ' ', $key));
}

function pagination_params($default_limit = 10)
{
	$page = max(1, (int) request_value('page', 1));
	$limit = (int) request_value('limit', $default_limit);
	if ($limit < 1) {
		$limit = $default_limit;
	}
	if ($limit > 100) {
		$limit = 100;
	}
	$offset = ($page - 1) * $limit;
	return array($page, $limit, $offset);
}

function csv_download($filename, $headers, $rows)
{
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	$out = fopen('php://output', 'w');
	fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
	fputcsv($out, $headers);
	foreach ($rows as $row) {
		fputcsv($out, $row);
	}
	fclose($out);
	exit;
}

function media_url($path)
{
	if ($path === null || $path === '') {
		return null;
	}
	if (preg_match('#^https?://#i', $path)) {
		return $path;
	}
	return rtrim(public_base_url(), '/') . '/' . ltrim($path, '/');
}

/**
 * Public site root for media links (never localhost on Hostinger).
 */
function public_base_url()
{
	$host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
	if ($host !== '' && stripos($host, 'superfinelabels') !== false) {
		return 'https://superfinelabels.in/plots';
	}
	$configured = rtrim((string) base_url(), '/');
	if ($configured !== '' && stripos($configured, 'localhost') === false && stripos($configured, '127.0.0.1') === false) {
		return $configured;
	}
	if ($host !== '') {
		$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
			|| (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
			|| (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
		$scheme = $https ? 'https' : 'http';
		// Local XAMPP default app folder
		$script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) : '';
		$script = rtrim(str_replace('/index.php', '', $script), '/');
		if ($script === '' || $script === '/') {
			$script = '/inventory';
		}
		return $scheme . '://' . $host . $script;
	}
	return $configured !== '' ? $configured : 'http://localhost:8080/inventory';
}

function slugify_filename($name)
{
	$base = pathinfo((string) $name, PATHINFO_FILENAME);
	$base = strtolower(trim($base));
	$base = preg_replace('/[^a-z0-9]+/', '-', $base);
	$base = trim($base, '-');
	if ($base === '') {
		$base = 'image';
	}
	return substr($base, 0, 40);
}

function frontend_app_url($path = '/')
{
	$ci =& get_instance();
	$base = '';
	if (isset($ci->setting_model)) {
		$base = trim((string) $ci->setting_model->get('app_frontend_url', ''));
	}
	if ($base === '' || stripos($base, 'localhost') !== false) {
		$host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
		if ($host !== '' && stripos($host, 'superfinelabels') !== false) {
			$base = 'https://superfinelabels.in/plots/app';
		} elseif (stripos((string) base_url(), 'superfinelabels') !== false || stripos((string) base_url(), '/plots') !== false) {
			$base = 'https://superfinelabels.in/plots/app';
		} else {
			$base = 'http://localhost:5173/plots/app';
		}
	}
	// Normalize …/plots/app/reset (no double slash; keep query string)
	$path = (string) $path;
	if ($path !== '' && $path[0] !== '/') {
		$path = '/' . $path;
	}
	return rtrim($base, '/') . $path;
}

/**
 * Issue a one-time password set/reset token. Returns the raw token string.
 */
function create_password_reset_token($user_id, $ttl_seconds = 172800)
{
	$ci =& get_instance();
	$ci->db->where('user_id', (int) $user_id)
		->where('used_at IS NULL', null, false)
		->update('password_resets', array('used_at' => now_dt()));
	$token = bin2hex(random_bytes(24));
	$ci->db->insert('password_resets', array(
		'user_id' => (int) $user_id,
		'token' => $token,
		'expires_at' => date('Y-m-d H:i:s', time() + (int) $ttl_seconds),
		'created_at' => now_dt()
	));
	return $token;
}

/**
 * Same flow for new-user invite and forgot-password: email a /reset?token= link.
 * $kind = 'invite' | 'forgot'
 */
function send_password_link_mail($user, $kind = 'forgot', $ttl_seconds = 172800)
{
	$ci =& get_instance();
	if (!$user || empty($user->email)) {
		return false;
	}
	$token = create_password_reset_token($user->id, $ttl_seconds);
	$link = frontend_app_url('/reset?token=' . rawurlencode($token));
	$expires = ((int) $ttl_seconds >= 86400)
		? (round($ttl_seconds / 86400) . ' days')
		: (round($ttl_seconds / 3600) . ' hours');
	$context = array(
		'name' => $user->name ?: 'there',
		'email' => $user->email,
		'token' => $token,
		'link' => $link,
		'expires' => $expires,
		'login_link' => frontend_app_url('/login')
	);
	$event = ($kind === 'invite') ? 'user.created' : 'auth.forgot';
	$ok = $ci->mailer->notify_event($event, $user->email, $context);
	// If invite template missing/inactive, fall back to the same reset template.
	if (!$ok && $kind === 'invite') {
		$ok = $ci->mailer->notify_event('auth.forgot', $user->email, $context);
	}
	return $ok;
}
