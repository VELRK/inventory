<?php
$m = new mysqli('localhost', 'root', '', 'syncr_inventory');
if ($m->connect_error) {
	fwrite(STDERR, $m->connect_error);
	exit(1);
}
$r = $m->query("SHOW COLUMNS FROM users LIKE 'avatar'");
if ($r && $r->num_rows) {
	echo "avatar exists\n";
} else {
	if (!$m->query('ALTER TABLE users ADD avatar VARCHAR(255) NULL AFTER phone')) {
		fwrite(STDERR, $m->error);
		exit(1);
	}
	echo "avatar added\n";
}
$m->close();
