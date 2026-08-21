<?php
$m = new mysqli('localhost', 'root', '', 'syncr_inventory');
$m->query("UPDATE registrations SET customer_name = CONCAT('Customer ', id) WHERE customer_name IS NULL OR customer_name=''");
$names = array('Ravi Kumar','Priya Sharma','Suresh Nair','Anitha Devi','Karthik R','Meena Iyer','Arun Prasad','Divya Menon','Vignesh K','Harini S');
$units = $m->query("SELECT id, project_id, price FROM inventory_units WHERE status IN ('available','booked') ORDER BY id DESC LIMIT 40")->fetch_all(MYSQLI_ASSOC);
$i = 0;
foreach ($units as $u) {
	$n = $names[$i % count($names)];
	$phone = '9843' . (300000 + $i);
	$email = 'demo' . $i . '@example.com';
	$month = str_pad((string) (($i % 9) + 1), 2, '0', STR_PAD_LEFT);
	$m->query("INSERT INTO bookings (unit_id,project_id,company_id,customer_name,customer_phone,customer_email,amount,booking_date,status,payment_status,notes,created_by,created_at) VALUES (".(int)$u['id'].",".(int)$u['project_id'].",1,'".$m->real_escape_string($n)."','$phone','$email',".(float)$u['price'].",'2026-$month-15','confirmed','partial','Client demo',1,NOW())");
	$i++;
}
echo 'bookings=' . $m->query('SELECT COUNT(*) c FROM bookings')->fetch_row()[0] . PHP_EOL;
echo 'registrations=' . $m->query('SELECT COUNT(*) c FROM registrations')->fetch_row()[0] . PHP_EOL;
