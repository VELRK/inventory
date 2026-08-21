<?php
/**
 * Load a large client-demo dataset.
 * php database/demo_bulk.php
 */
$mysqli = new mysqli('localhost', 'root', '', 'syncr_inventory');
if ($mysqli->connect_error) {
	fwrite(STDERR, "MySQL error: {$mysqli->connect_error}\n");
	exit(1);
}
$mysqli->set_charset('utf8mb4');

$marker = $mysqli->query("SELECT setting_value FROM settings WHERE setting_key='demo_bulk_loaded'")->fetch_row();
if ($marker && $marker[0] === '1') {
	echo "Demo bulk data already loaded. Re-seeding extras skipped.\n";
	exit(0);
}

$hash = '$2y$10$S8Kk4PUYpcLm7z9jpCUHdOK1SSGXPUhijU0HSXybk5JGgzH65LiBK'; // TeamUser@123

$mysqli->query("INSERT INTO marketing_companies (name,email,phone,address,city,status,permissions,created_at) VALUES
('Zenith Realty','hello@zenith.test','9876500003','21 Race Course','Coimbatore','active','[\"view_inventory\",\"submit_block_requests\",\"manage_users\"]',NOW()),
('Prime Lands','hello@primelands.test','9876500004','5 ECR','Chennai','active','[\"view_inventory\",\"submit_block_requests\",\"manage_users\"]',NOW()),
('Skyline Homes','hello@skyline.test','9876500005','9 MG Road','Bengaluru','active','[\"view_inventory\",\"submit_block_requests\",\"manage_users\"]',NOW())");

$mysqli->query("INSERT INTO users (company_id,name,email,password_hash,phone,role,status,created_at) VALUES
(1,'Deepa Sales','deepa@abc.test','$hash','9000000011','marketing_team_user','active',NOW()),
(1,'Naveen Kumar','naveen@abc.test','$hash','9000000012','marketing_team_user','active',NOW()),
(1,'Lakshmi R','lakshmi@abc.test','$hash','9000000013','marketing_team_user','active',NOW()),
(2,'Meera Horizon','meera@horizon.test','$hash','9000000014','marketing_team_user','active',NOW()),
(2,'Vikram S','vikram@horizon.test','$hash','9000000015','marketing_team_user','active',NOW()),
(3,'Anand Zenith','anand@zenith.test','$hash','9000000016','marketing_team_admin','active',NOW()),
(3,'Sneha Z','sneha@zenith.test','$hash','9000000017','marketing_team_user','active',NOW()),
(4,'Rahul Prime','rahul@primelands.test','$hash','9000000018','marketing_team_admin','active',NOW()),
(5,'Divya Sky','divya@skyline.test','$hash','9000000019','marketing_team_admin','active',NOW())");

$mysqli->query("INSERT INTO projects (name,location,city,project_type,description,approval_details,contact_name,contact_phone,contact_email,status,created_at) VALUES
('Sunset Heights','Avinashi Road','Coimbatore','Residential Plot','Hill-view plotted layout with clubhouse.','DTCP Approved','Site Office','0422-222111','sunset@syncr.test','active',NOW()),
('Riverfront Enclave','Sungam','Coimbatore','Villa Plot','River-facing villa plots.','DTCP Approved','Site Office','0422-222112','river@syncr.test','active',NOW()),
('Marina Greens','ECR','Chennai','Residential Plot','Coastal gated community.','CMDA Approved','Site Office','044-333111','marina@syncr.test','active',NOW()),
('Orchid Park','Sarjapur','Bengaluru','Residential Plot','IT corridor plotted development.','BDA Approved','Site Office','080-444111','orchid@syncr.test','active',NOW()),
('Temple City','Srirangam','Tiruchirappalli','Residential Plot','Temple-town plotted layout.','DTCP Approved','Site Office','0431-555111','temple@syncr.test','active',NOW())");

$projects = array();
$res = $mysqli->query('SELECT id, name, city FROM projects WHERE deleted_at IS NULL');
while ($row = $res->fetch_assoc()) {
	$projects[] = $row;
}

$companies = array();
$res = $mysqli->query('SELECT id FROM marketing_companies WHERE deleted_at IS NULL');
while ($row = $res->fetch_assoc()) {
	$companies[] = (int) $row['id'];
}

foreach ($projects as $i => $p) {
	$cid = $companies[$i % count($companies)];
	$mysqli->query('INSERT IGNORE INTO company_project_assignments (company_id, project_id, created_at) VALUES ('.$cid.','.(int)$p['id'].',NOW())');
	$mysqli->query('INSERT IGNORE INTO company_project_assignments (company_id, project_id, created_at) VALUES (1,'.(int)$p['id'].',NOW())');
}

$users = array();
$res = $mysqli->query("SELECT id, company_id FROM users WHERE role IN ('marketing_team_admin','marketing_team_user') AND deleted_at IS NULL");
while ($row = $res->fetch_assoc()) {
	$users[] = $row;
}
foreach ($users as $u) {
	$res = $mysqli->query('SELECT project_id FROM company_project_assignments WHERE company_id='.(int)$u['company_id']);
	while ($row = $res->fetch_assoc()) {
		$mysqli->query('INSERT IGNORE INTO user_project_assignments (user_id, project_id, created_at) VALUES ('.(int)$u['id'].','.(int)$row['project_id'].',NOW())');
	}
}

$faces = array('East','West','North','South','North-East','South-East');
$types = array('Residential Plot','Villa Plot','Corner Plot');
$statuses = array('available','available','available','available','on_hold','blocked','booked','registered');
$blocks = array('Phase A','Phase B','Phase C','Block P','Block Q','Lakeside');
$unitStmt = $mysqli->prepare('INSERT INTO inventory_units (project_id,unit_no,block_phase,plot_type,area_sqft,facing,road_width_ft,dimensions,price,price_per_sqft,is_premium,is_corner,approval_details,remarks,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');

$createdUnits = array();
foreach ($projects as $p) {
	$pid = (int) $p['id'];
	$existing = (int) $mysqli->query('SELECT COUNT(*) c FROM inventory_units WHERE project_id='.$pid)->fetch_assoc()['c'];
	$need = max(0, 24 - $existing);
	for ($n = 1; $n <= $need; $n++) {
		$no = 'U-' . str_pad((string) ($existing + $n), 3, '0', STR_PAD_LEFT);
		$block = $blocks[$n % count($blocks)];
		$type = $types[$n % count($types)];
		$area = 1000 + (($n * 50) % 1400);
		$face = $faces[$n % count($faces)];
		$road = ($n % 2 === 0) ? 30 : 40;
		$dim = ($area >= 1600) ? '40x40' : '30x40';
		$pps = 2800 + ($n * 40);
		$price = $area * $pps;
		$prem = ($n % 7 === 0) ? 1 : 0;
		$corner = ($n % 9 === 0) ? 1 : 0;
		$st = $statuses[$n % count($statuses)];
		$appr = ($p['city'] === 'Chennai') ? 'CMDA Approved' : (($p['city'] === 'Bengaluru') ? 'BDA Approved' : 'DTCP Approved');
		$rem = $prem ? 'Premium inventory' : '';
		$unitStmt->bind_param('isssdsisddiisss', $pid, $no, $block, $type, $area, $face, $road, $dim, $price, $pps, $prem, $corner, $appr, $rem, $st);
		$unitStmt->execute();
		if ($unitStmt->error) {
			fwrite(STDERR, "Unit insert error: {$unitStmt->error}\n");
			exit(1);
		}
		$createdUnits[] = array('id' => $mysqli->insert_id, 'project_id' => $pid, 'status' => $st, 'price' => $price, 'unit_no' => $no);
	}
}
$unitStmt->close();

$names = array('Ravi Kumar','Priya Sharma','Suresh Nair','Anitha Devi','Karthik R','Meena Iyer','Arun Prasad','Divya Menon','Vignesh K','Harini S','Mohammed Ali','Fatima B','Joseph Antony','Lakshmi Narayan','Gopal Krishnan','Revathi M','Sanjay Patel','Nithya R','Bala Murugan','Kavya S','Imran Khan','Sowmya L','Prakash Raj','Aishwarya P','Dinesh Babu');
$reqUsers = $mysqli->query("SELECT id, company_id FROM users WHERE role='marketing_team_user' AND deleted_at IS NULL")->fetch_all(MYSQLI_ASSOC);
$avail = array();
$booked = array();
$regd = array();
$blocked = array();
$res = $mysqli->query("SELECT id, project_id, status, price, unit_no FROM inventory_units WHERE deleted_at IS NULL");
while ($row = $res->fetch_assoc()) {
	if ($row['status'] === 'available') $avail[] = $row;
	if ($row['status'] === 'booked') $booked[] = $row;
	if ($row['status'] === 'registered') $regd[] = $row;
	if ($row['status'] === 'blocked' || $row['status'] === 'on_hold') $blocked[] = $row;
}

$reqStmt = $mysqli->prepare('INSERT INTO block_requests (unit_id,company_id,requested_by,customer_name,customer_phone,customer_email,expected_booking_date,remarks,status,reviewed_by,reviewed_at,review_notes,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
$slice = array_slice($blocked, 0, 28);
foreach ($slice as $i => $u) {
	$ru = $reqUsers[$i % max(1, count($reqUsers))];
	$st = ($i % 3 === 0) ? 'pending' : (($i % 3 === 1) ? 'approved' : 'rejected');
	$name = $names[$i % count($names)];
	$phone = '9843' . str_pad((string) (200000 + $i), 6, '0', STR_PAD_LEFT);
	$email = strtolower(str_replace(' ', '.', $name)) . $i . '@example.com';
	$date = date('Y-m-d', strtotime('+'.($i+3).' days'));
	$notes = $st === 'pending' ? null : ($st === 'approved' ? 'Hold 7 days' : 'Budget mismatch');
	$reviewedBy = $st === 'pending' ? null : 1;
	$reviewedAt = $st === 'pending' ? null : date('Y-m-d H:i:s');
	$cid = (int) $ru['company_id'];
	$uid = (int) $u['id'];
	$rid = (int) $ru['id'];
	$reqStmt->bind_param('iiissssssiss', $uid, $cid, $rid, $name, $phone, $email, $date, $rmk, $st, $reviewedBy, $reviewedAt, $notes);
	$rmk = 'Site visit scheduled';
	$reqStmt->execute();
}
$reqStmt->close();

$bookStmt = $mysqli->prepare('INSERT INTO bookings (unit_id,project_id,company_id,customer_name,customer_phone,customer_email,amount,booking_date,status,payment_status,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,NOW())');
$bst = array('confirmed','confirmed','pending','cancelled');
$pay = array('paid','partial','unpaid','partial');
$bookPool = array_merge($booked, array_slice($avail, 0, 40));
foreach ($bookPool as $i => $u) {
	if ($i > 85) break;
	$name = $names[$i % count($names)];
	$phone = '9850' . str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT);
	$email = strtolower(str_replace(' ', '.', $name)) . '.b'.$i.'@example.com';
	$amount = (float) $u['price'];
	$bdate = date('Y-m-d', strtotime('2025-11-01 +'.$i.' days'));
	$status = $bst[$i % 4];
	$pstatus = $pay[$i % 4];
	$cid = $companies[$i % count($companies)];
	$uid = (int) $u['id'];
	$pid = (int) $u['project_id'];
	$bookStmt->bind_param('iiisssdssss', $uid, $pid, $cid, $name, $phone, $email, $amount, $bdate, $status, $pstatus, $note);
	$note = 'Demo booking';
	$bookStmt->execute();
}
$bookStmt->close();

$regStmt = $mysqli->prepare('INSERT INTO registrations (unit_id,project_id,company_id,customer_name,customer_phone,customer_email,amount,registration_date,status,payment_status,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,NOW())');
$regPool = array_merge($regd, array_slice($booked, 0, 30));
foreach ($regPool as $i => $u) {
	if ($i > 48) break;
	$name = $names[(count($names) - 1 - $i) % count($names)];
	$phone = '9860' . str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT);
	$email = strtolower(str_replace(' ', '.', $name)) . '.r'.$i.'@example.com';
	$amount = (float) $u['price'];
	$rdate = date('Y-m-d', strtotime('2025-12-01 +'.$i.' days'));
	$status = ($i % 5 === 0) ? 'pending' : 'confirmed';
	$pstatus = ($status === 'confirmed') ? 'paid' : 'partial';
	$cid = $companies[$i % count($companies)];
	$uid = (int) $u['id'];
	$pid = (int) $u['project_id'];
	$regStmt->bind_param('iiisssdssss', $uid, $pid, $cid, $name, $phone, $email, $amount, $rdate, $status, $pstatus, $note);
	$note = 'Registered at SRO';
	$regStmt->execute();
}
$regStmt->close();

$acts = array(
	'inventory.status' => 'status changed',
	'request.create' => 'Block request submitted',
	'booking.create' => 'Booking created',
	'user.create' => 'Created user',
	'project.update' => 'Updated project details'
);
for ($i = 0; $i < 60; $i++) {
	$keys = array_keys($acts);
	$action = $keys[$i % count($keys)];
	$desc = $acts[$action] . ' #' . ($i + 1);
	$uid = ($i % 4) + 1;
	$mysqli->query("INSERT INTO activity_logs (user_id,company_id,action,entity_type,entity_id,description,ip_address,created_at) VALUES ($uid,NULL,'$action','demo',$i,'".$mysqli->real_escape_string($desc)."','127.0.0.1',DATE_SUB(NOW(), INTERVAL $i HOUR))");
}

$mysqli->query("INSERT INTO settings (setting_key,setting_value,setting_group,is_secret,updated_at) VALUES ('demo_bulk_loaded','1','general',0,NOW())
	ON DUPLICATE KEY UPDATE setting_value='1', updated_at=NOW()");

$counts = array();
foreach (array('projects','inventory_units','bookings','registrations','block_requests','users','marketing_companies','activity_logs') as $t) {
	$counts[$t] = (int) $mysqli->query("SELECT COUNT(*) c FROM $t")->fetch_assoc()['c'];
}
echo "Demo bulk loaded:\n";
foreach ($counts as $t => $c) {
	echo "  $t: $c\n";
}
