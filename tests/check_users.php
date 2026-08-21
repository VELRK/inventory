<?php
$ch = curl_init('http://localhost:8080/inventory/index.php/api/auth/login');
curl_setopt_array($ch, array(
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_POST => true,
	CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
	CURLOPT_POSTFIELDS => json_encode(array('email' => 'admin@syncr.test', 'password' => 'Admin@123')),
));
$raw = curl_exec($ch);
curl_close($ch);
$j = json_decode($raw, true);
$token = $j['data']['token'] ?? '';
if (!$token) {
	fwrite(STDERR, "login failed: $raw\n");
	exit(1);
}
$ch = curl_init('http://localhost:8080/inventory/index.php/api/users?page=1&limit=10');
curl_setopt_array($ch, array(
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HTTPHEADER => array('Authorization: Bearer '.$token, 'Accept: application/json'),
));
$out = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP $code\n";
$j2 = json_decode($out, true);
if (!empty($j2['success'])) {
	echo 'users=' . count($j2['data']['items']) . ' total=' . $j2['data']['total'] . "\n";
	echo $j2['data']['items'][0]['email'] . "\n";
} else {
	echo $out;
}
