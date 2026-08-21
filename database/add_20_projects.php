<?php
$m = new mysqli('localhost', 'root', '', 'syncr_inventory');
if ($m->connect_error) {
	fwrite(STDERR, $m->connect_error . PHP_EOL);
	exit(1);
}
$m->set_charset('utf8mb4');

$projects = array(
	array('Palm Grove','Kalapatti','Coimbatore','Residential Plot','Wide-road plotted layout near IT parks.','DTCP Approved'),
	array('Golden Avenue','Peelamedu','Coimbatore','Residential Plot','Premium corner plots with park.','DTCP Approved'),
	array('Silver Oak','Vadavalli','Coimbatore','Villa Plot','Hill-side villa plots.','DTCP Approved'),
	array('Nexus City','Ganapathy','Coimbatore','Residential Plot','Gated community near ring road.','DTCP Approved'),
	array('Blue Horizon','Madurai Bypass','Madurai','Residential Plot','Temple-city plotted development.','DTCP Approved'),
	array('Meenakshi Nagar','Anna Nagar','Madurai','Residential Plot','DTCP layout with 40 ft roads.','DTCP Approved'),
	array('Cauvery Greens','Woraiyur','Tiruchirappalli','Residential Plot','Cauvery-side plotted township.','DTCP Approved'),
	array('Rockfort Villas','Cantonment','Tiruchirappalli','Villa Plot','Villa plots near Rockfort.','DTCP Approved'),
	array('Delta Pearl','Thanjavur Road','Thanjavur','Residential Plot','Agri-belt plotted layout.','DTCP Approved'),
	array('Salem Heights','Junction','Salem','Residential Plot','NH-adjacent plotted community.','DTCP Approved'),
	array('Yercaud View','Hasthampatti','Salem','Villa Plot','Hill-view villa plots.','DTCP Approved'),
	array('Erode Central','Perundurai','Erode','Residential Plot','Textile-city plotted layout.','DTCP Approved'),
	array('Kovai Lakeside','Sulur','Coimbatore','Villa Plot','Lake-facing villa plots.','DTCP Approved'),
	array('Chennai Gateway','Tambaram','Chennai','Residential Plot','South Chennai plotted township.','CMDA Approved'),
	array('OMR Pearl','Sholinganallur','Chennai','Residential Plot','IT corridor plotted layout.','CMDA Approved'),
	array('Bay Breeze','Mahabalipuram','Chennai','Villa Plot','Coastal villa plots.','CMDA Approved'),
	array('Garden City','Hosur Road','Bengaluru','Residential Plot','Electronics city plotted layout.','BDA Approved'),
	array('Nandi Hills Estate','Devanahalli','Bengaluru','Villa Plot','Airport-side villa plots.','BDA Approved'),
	array('Mysore Royal','VV Mohalla','Mysuru','Residential Plot','Heritage-city plotted layout.','MUDA Approved'),
	array('Nilgiri Meadows','Coonoor Road','Ooty','Villa Plot','Hill-station villa plots.','DTCP Approved'),
);

$pstmt = $m->prepare('INSERT INTO projects (name,location,city,project_type,description,approval_details,contact_name,contact_phone,contact_email,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())');
$newIds = array();
foreach ($projects as $i => $p) {
	$exists = $m->query("SELECT id FROM projects WHERE name='".$m->real_escape_string($p[0])."' AND deleted_at IS NULL")->fetch_row();
	if ($exists) {
		$newIds[] = (int) $exists[0];
		continue;
	}
	$contact = 'Site Office';
	$phone = '0422-' . (200000 + $i);
	$email = 'p' . ($i + 1) . '@syncr.test';
	$status = 'active';
	$pstmt->bind_param('ssssssssss', $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $contact, $phone, $email, $status);
	$pstmt->execute();
	$newIds[] = (int) $m->insert_id;
}
$pstmt->close();

$companies = array();
$res = $m->query('SELECT id FROM marketing_companies WHERE deleted_at IS NULL');
while ($row = $res->fetch_assoc()) {
	$companies[] = (int) $row['id'];
}
$teamUsers = array();
$res = $m->query("SELECT id, company_id FROM users WHERE role IN ('marketing_team_admin','marketing_team_user') AND deleted_at IS NULL");
while ($row = $res->fetch_assoc()) {
	$teamUsers[] = $row;
}

foreach ($newIds as $i => $pid) {
	$cid = $companies[$i % count($companies)];
	$m->query('INSERT IGNORE INTO company_project_assignments (company_id, project_id, created_at) VALUES (1,'.$pid.',NOW())');
	$m->query('INSERT IGNORE INTO company_project_assignments (company_id, project_id, created_at) VALUES ('.$cid.','.$pid.',NOW())');
	foreach ($teamUsers as $u) {
		if ((int) $u['company_id'] === 1 || (int) $u['company_id'] === $cid) {
			$m->query('INSERT IGNORE INTO user_project_assignments (user_id, project_id, created_at) VALUES ('.(int)$u['id'].','.$pid.',NOW())');
		}
	}
}

$faces = array('East','West','North','South','North-East');
$types = array('Residential Plot','Villa Plot','Corner Plot');
$statuses = array('available','available','available','on_hold','blocked','booked','registered');
$blocks = array('Phase A','Phase B','Phase C','Block 1','Block 2');
$names = array('Ravi Kumar','Priya Sharma','Suresh Nair','Anitha Devi','Karthik R','Meena Iyer','Arun Prasad','Divya Menon','Vignesh K','Harini S','Mohammed Ali','Lakshmi Narayan','Gopal Krishnan','Revathi M','Sanjay Patel');

$ustmt = $m->prepare('INSERT INTO inventory_units (project_id,unit_no,block_phase,plot_type,area_sqft,facing,road_width_ft,dimensions,price,price_per_sqft,is_premium,is_corner,approval_details,remarks,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
$bstmt = $m->prepare('INSERT INTO bookings (unit_id,project_id,company_id,customer_name,customer_phone,customer_email,amount,booking_date,status,payment_status,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,NOW())');
$rstmt = $m->prepare('INSERT INTO registrations (unit_id,project_id,company_id,customer_name,customer_phone,customer_email,amount,registration_date,status,payment_status,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,NOW())');
$qstmt = $m->prepare('INSERT INTO block_requests (unit_id,company_id,requested_by,customer_name,customer_phone,customer_email,expected_booking_date,remarks,status,reviewed_by,reviewed_at,review_notes,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())');

$unitCount = 0;
$bookCount = 0;
$regCount = 0;
$reqCount = 0;

foreach ($newIds as $pi => $pid) {
	$proj = $m->query('SELECT name, city, approval_details FROM projects WHERE id='.$pid)->fetch_assoc();
	$have = (int) $m->query("SELECT COUNT(*) c FROM inventory_units WHERE project_id=$pid AND unit_no LIKE 'P20-%'")->fetch_assoc()['c'];
	if ($have >= 12) {
		continue;
	}
	for ($n = 1; $n <= 12; $n++) {
		$no = 'P20-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
		$block = $blocks[$n % count($blocks)];
		$type = $types[$n % count($types)];
		$area = 1000.0 + ($n * 80);
		$face = $faces[$n % count($faces)];
		$road = ($n % 2 === 0) ? 30.0 : 40.0;
		$dim = ($area >= 1600) ? '40x40' : '30x40';
		$pps = 3000.0 + ($n * 50);
		$price = $area * $pps;
		$prem = ($n % 5 === 0) ? 1 : 0;
		$corner = ($n % 6 === 0) ? 1 : 0;
		$st = $statuses[$n % count($statuses)];
		$appr = $proj['approval_details'];
		$rem = $prem ? 'Premium plot' : 'Standard plot';
		$ustmt->bind_param('isssdsisddiisss', $pid, $no, $block, $type, $area, $face, $road, $dim, $price, $pps, $prem, $corner, $appr, $rem, $st);
		$ustmt->execute();
		$uid = (int) $m->insert_id;
		$unitCount++;
		$name = $names[$n % count($names)];
		$phone = '9876' . str_pad((string) (100000 + $pi * 20 + $n), 6, '0', STR_PAD_LEFT);
		$email = strtolower(str_replace(' ', '.', $name)) . ".$pid.$n@example.com";
		$cid = $companies[$n % count($companies)];
		$bstat = array('confirmed','pending','cancelled')[$n % 3];
		$pstat = array('paid','partial','unpaid')[$n % 3];
		$bdate = date('Y-m-d', strtotime('2026-01-01 +'.($pi * 12 + $n).' days'));
		if ($st === 'booked' || $n % 4 === 0) {
			$bstmt->bind_param('iiisssdssss', $uid, $pid, $cid, $name, $phone, $email, $price, $bdate, $bstat, $pstat, $note);
			$note = 'Linked to '.$proj['name'];
			$bstmt->execute();
			$bookCount++;
		}
		if ($st === 'registered' || $n % 5 === 0) {
			$rdate = date('Y-m-d', strtotime($bdate.' +20 days'));
			$rst = ($n % 6 === 0) ? 'pending' : 'confirmed';
			$rp = ($rst === 'confirmed') ? 'paid' : 'partial';
			$rstmt->bind_param('iiisssdssss', $uid, $pid, $cid, $name, $phone, $email, $price, $rdate, $rst, $rp, $rnote);
			$rnote = 'Registration '.$proj['name'];
			$rstmt->execute();
			$regCount++;
		}
		if ($st === 'blocked' || $st === 'on_hold' || $n % 6 === 0) {
			$ru = $teamUsers[$n % count($teamUsers)];
			$qst = array('pending','approved','rejected')[$n % 3];
			$revBy = ($qst === 'pending') ? 0 : 1;
			$revAt = ($qst === 'pending') ? '' : date('Y-m-d H:i:s');
			$revNotes = ($qst === 'approved') ? 'Hold 7 days' : (($qst === 'rejected') ? 'Budget mismatch' : '');
			$edate = date('Y-m-d', strtotime('+'.(7 + $n).' days'));
			$rid = (int) $ru['id'];
			$rcid = (int) $ru['company_id'];
			$qstmt->bind_param('iiissssssiss', $uid, $rcid, $rid, $name, $phone, $email, $edate, $qrmk, $qst, $revBy, $revAt, $revNotes);
			$qrmk = 'Site visit for '.$proj['name'];
			$qstmt->execute();
			$reqCount++;
		}
		$m->query("INSERT INTO activity_logs (user_id,company_id,action,entity_type,entity_id,description,ip_address,created_at) VALUES (1,NULL,'project.seed','projects',$pid,'".$m->real_escape_string($proj['name'].' unit '.$no.' added as '.ucfirst($st))."','127.0.0.1',NOW())");
	}
}

echo "Added/ensured 20 projects with related data.\n";
echo "new_or_existing_ids=" . count($newIds) . " units+$unitCount bookings+$bookCount registrations+$regCount requests+$reqCount\n";
foreach (array('projects','inventory_units','bookings','registrations','block_requests','activity_logs') as $t) {
	echo $t . '=' . $m->query("SELECT COUNT(*) c FROM $t")->fetch_row()[0] . PHP_EOL;
}
