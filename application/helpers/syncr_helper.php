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
	if (array_key_exists($key, $body) && $body[$key] !== '') {
		return $body[$key];
	}
	$post = $ci->input->post($key);
	if ($post !== null && $post !== false && $post !== '') {
		return $post;
	}
	$get = $ci->input->get($key);
	if ($get !== null && $get !== false && $get !== '') {
		return $get;
	}
	return $default;
}

function now_dt()
{
	return date('Y-m-d H:i:s');
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
	return rtrim(base_url(), '/') . '/' . ltrim($path, '/');
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
