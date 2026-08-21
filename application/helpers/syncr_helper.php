<?php
defined('BASEPATH') OR exit('No direct script access allowed');

function is_multipart_request()
{
	$ct = '';
	if (!empty($_SERVER['CONTENT_TYPE'])) {
		$ct = (string) $_SERVER['CONTENT_TYPE'];
	} elseif (!empty($_SERVER['HTTP_CONTENT_TYPE'])) {
		$ct = (string) $_SERVER['HTTP_CONTENT_TYPE'];
	}
	return stripos($ct, 'multipart/form-data') !== false;
}

function json_body()
{
	static $cached = null;
	if ($cached !== null) {
		return $cached;
	}
	// Do not read php://input for multipart uploads — use $_POST / $_FILES instead.
	if (is_multipart_request()) {
		$cached = array();
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
	$key = strtolower(trim((string) $status));
	if ($key === '') {
		return '';
	}
	return isset($map[$key]) ? $map[$key] : ucfirst(str_replace('_', ' ', $key));
}

/** Map raw/label status to inventory_units ENUM slug, or null if invalid. */
function normalize_unit_status($status)
{
	$raw = strtolower(trim((string) $status));
	$raw = str_replace(array('-', ' '), '_', $raw);
	$aliases = array(
		'available' => 'available',
		'avail' => 'available',
		'on_hold' => 'on_hold',
		'hold' => 'on_hold',
		'onhold' => 'on_hold',
		'blocked' => 'on_hold', // legacy → on_hold
		'booked' => 'booked',
		'booking' => 'booked',
		'registered' => 'registered',
		'registration' => 'registered',
		'sold' => 'registered'
	);
	return isset($aliases[$raw]) ? $aliases[$raw] : null;
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
	$path = trim((string) $path);
	// Never treat base64 / data-URIs as file paths (Flutter sometimes posts these).
	if (stripos($path, 'data:') === 0 || preg_match('#^[A-Za-z0-9+/]{80,}={0,2}$#', $path)) {
		return null;
	}
	if (preg_match('#^https?://#i', $path)) {
		return $path;
	}
	return rtrim(public_base_url(), '/') . '/' . ltrim($path, '/');
}

/**
 * Save a PHP $_FILES entry under uploads/{folder}/.
 * Returns relative path or false on error ($error set).
 */
function store_uploaded_file($file, $folder = 'projects', &$error = null)
{
	$error = null;
	$folder = preg_replace('/[^a-z0-9_-]/i', '', strtolower((string) $folder));
	if ($folder === '') {
		$folder = 'projects';
	}
	if (!is_array($file) || !isset($file['error'])) {
		$error = 'Please choose an image file to upload.';
		return false;
	}
	$php_error = (int) $file['error'];
	if ($php_error === UPLOAD_ERR_NO_FILE) {
		$error = 'Please choose an image file to upload.';
		return false;
	}
	if (in_array($php_error, array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
		$error = 'The file is too large. Maximum size is 4 MB.';
		return false;
	}
	if ($php_error !== UPLOAD_ERR_OK) {
		$error = 'Upload failed (PHP error ' . $php_error . ').';
		return false;
	}
	$size = isset($file['size']) ? (int) $file['size'] : 0;
	if ($size <= 0) {
		$error = 'The selected file is empty.';
		return false;
	}
	if ($size > 4194304) {
		$error = 'The file is too large. Maximum size is 4 MB.';
		return false;
	}
	$ext = strtolower(pathinfo(isset($file['name']) ? $file['name'] : '', PATHINFO_EXTENSION));
	$allowed_ext = array('jpg' => true, 'jpeg' => true, 'png' => true, 'webp' => true);
	if ($ext === '' || !isset($allowed_ext[$ext])) {
		$error = 'Invalid file type. Upload a JPG, PNG, or WEBP image.';
		return false;
	}
	if ($ext === 'jpeg') {
		$ext = 'jpg';
	}
	$tmp = isset($file['tmp_name']) ? $file['tmp_name'] : '';
	if ($tmp === '' || !is_uploaded_file($tmp)) {
		$error = 'Invalid upload temp file.';
		return false;
	}
	$info = @getimagesize($tmp);
	if ($info === false) {
		$error = 'This file is not a valid image.';
		return false;
	}
	$dir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR;
	if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
		$error = 'Upload folder could not be created: uploads/' . $folder;
		return false;
	}
	$slug = function_exists('slugify_filename') ? slugify_filename(isset($file['name']) ? $file['name'] : 'cover') : 'cover';
	$stored = $folder . '_' . $slug . '_' . date('YmdHis') . '_v1.' . $ext;
	$dest = $dir . $stored;
	if (!@move_uploaded_file($tmp, $dest)) {
		$error = 'The image could not be saved on the server.';
		return false;
	}
	@chmod($dest, 0644);
	return 'uploads/' . $folder . '/' . $stored;
}

/**
 * Pick first uploaded image from common field names.
 */
function request_uploaded_image($keys = null)
{
	$keys = $keys ? (array) $keys : array('cover_image', 'coverImage', 'file', 'image', 'photo', 'cover', 'avatar');
	foreach ($keys as $key) {
		if (!empty($_FILES[$key]) && isset($_FILES[$key]['error']) && (int) $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE) {
			return $_FILES[$key];
		}
	}
	return null;
}

/**
 * Normalize an image field from API clients:
 * - empty → null
 * - uploads/... path → keep
 * - https URL under /uploads/ → extract relative path when possible
 * - data:image/...;base64,... → decode, save under uploads/{folder}/, return path
 * Returns relative path like uploads/projects/foo.jpg, or null.
 * On invalid base64 payload, sets $error and returns false.
 */
function store_image_input($value, $folder = 'projects', &$error = null)
{
	$error = null;
	$value = is_string($value) ? trim($value) : '';
	if ($value === '') {
		return null;
	}

	$folder = preg_replace('/[^a-z0-9_-]/i', '', strtolower((string) $folder));
	if ($folder === '') {
		$folder = 'projects';
	}

	// Already a stored relative path.
	if (preg_match('#^uploads/[a-z0-9_-]+/#i', $value) && stripos($value, 'data:') === false) {
		return $value;
	}

	// Absolute URL pointing at our uploads folder → keep relative path.
	if (preg_match('#^https?://#i', $value)) {
		if (preg_match('#(/uploads/[a-z0-9_-]+/[^?\s]+)#i', $value, $m)) {
			return ltrim($m[1], '/');
		}
		if (strlen($value) <= 255) {
			return $value;
		}
		$error = 'Image URL is too long. Upload the image file with multipart form-data.';
		return false;
	}

	$ext = 'jpg';
	$raw = null;
	if (preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', $value, $m)) {
		$extMap = array('jpeg' => 'jpg', 'jpg' => 'jpg', 'png' => 'png', 'webp' => 'webp');
		$key = strtolower($m[1]);
		$ext = isset($extMap[$key]) ? $extMap[$key] : 'jpg';
		$raw = base64_decode(substr($value, strpos($value, ',') + 1), true);
	} elseif (preg_match('#^[A-Za-z0-9+/=\r\n]+$#', $value) && strlen($value) > 200) {
		$raw = base64_decode($value, true);
	} else {
		$error = 'Send the image as multipart file field "cover_image" (or "file"), not a path string.';
		return false;
	}

	if ($raw === false || $raw === '') {
		$error = 'Could not decode image base64. Prefer multipart file upload.';
		return false;
	}
	$max = 4 * 1024 * 1024;
	if (strlen($raw) > $max) {
		$error = 'Image is too large after decode. Maximum size is 4 MB.';
		return false;
	}

	$info = @getimagesizefromstring($raw);
	if ($info === false) {
		$error = 'Decoded data is not a valid image. Use JPG, PNG, or WEBP.';
		return false;
	}
	if (!empty($info['mime'])) {
		$mimeExt = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
		if (isset($mimeExt[$info['mime']])) {
			$ext = $mimeExt[$info['mime']];
		} else {
			$error = 'Unsupported image type (' . $info['mime'] . '). Use JPG, PNG, or WEBP.';
			return false;
		}
	}

	$dir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR;
	if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
		$error = 'Upload folder could not be created: uploads/' . $folder;
		return false;
	}
	$name = $folder . '_cover_' . date('YmdHis') . '_' . substr(md5($raw), 0, 8) . '.' . $ext;
	$dest = $dir . $name;
	if (@file_put_contents($dest, $raw) === false) {
		$error = 'Could not save image on the server.';
		return false;
	}
	@chmod($dest, 0644);
	return 'uploads/' . $folder . '/' . $name;
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
