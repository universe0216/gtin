<?php
$m = new mysqli('localhost', 'root', '123456', 'goods_schema');

if ($m->connect_error)
{
	fwrite(STDERR, $m->connect_error.PHP_EOL);
	exit(1);
}

$m->set_charset('utf8');
$m->query('SET FOREIGN_KEY_CHECKS=0');
$m->query('DELETE FROM interbar');
$m->query('DELETE FROM interemp');
$m->query('DELETE FROM publictbl');

function fail($m, $sql)
{
	fwrite(STDERR, $m->error.' | '.$sql.PHP_EOL);
	exit(1);
}

$locations = array(
	array(1, 'NULL', 'Pyongyang', 'P01'),
	array(2, '1', 'Central District', 'P01-C01'),
	array(3, '1', 'Moranbong District', 'P01-C02'),
	array(4, 'NULL', 'South Pyongan', 'P02'),
	array(5, '4', 'Pyongsong', 'P02-C01'),
	array(6, '4', 'Anju', 'P02-C02'),
	array(7, 'NULL', 'Kangwon', 'P03'),
	array(8, '7', 'Wonsan', 'P03-C01'),
);

foreach ($locations as $row)
{
	$sql = sprintf(
		"INSERT INTO publictbl (OrgID, ParentID, Name, Tag, sign_data) VALUES (%d, %s, '%s', '%s', '%s')",
		$row[0],
		$row[1],
		$m->real_escape_string($row[2]),
		$m->real_escape_string($row[3]),
		$m->real_escape_string('loc-'.$row[0])
	);

	if ( ! $m->query($sql))
	{
		fail($m, $sql);
	}
}

$orgs = array(
	array(1001, '2027-12-31', '2024-01-15', 'Test Company', '2020-03-01', 'Central District, Pyongyang', '', 2, '8801234', '02-111-1001', 1, 'KP'),
	array(1002, '2028-06-30', '2024-05-20', 'Northwind Foods', '2019-07-12', 'Moranbong District, Pyongyang', 'Test Company', 3, '8802345', '02-111-1002', 2, 'KP'),
	array(1003, '2026-11-30', '2023-09-01', 'Harbor Goods', '2018-01-20', 'Pyongsong, South Pyongan', '', 5, '8803456', '0211-1003', 1, 'KP'),
	array(1004, '2029-01-31', '2025-02-10', 'Cedar Market', '2021-04-08', 'Anju, South Pyongan', 'Harbor Goods', 6, '8804567', '0211-1004', 3, 'KP'),
	array(1005, '2027-08-15', '2024-08-01', 'Lakeside Goods', '2017-11-11', 'Wonsan, Kangwon', '', 8, '8805678', '033-111-1005', 2, 'KP'),
);

foreach ($orgs as $row)
{
	$sql = sprintf(
		"INSERT INTO interemp
		(RegNum, ReRegDate, ValidDate, EmpName, StartDate, Place, upEmp, ParentID, EmpKey, Phone, BgID, sign_data, c_code, send)
		VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s', %d, '%s', '%s', %d, '%s', '%s', 'N')",
		$row[0],
		$m->real_escape_string($row[1]),
		$m->real_escape_string($row[2]),
		$m->real_escape_string($row[3]),
		$m->real_escape_string($row[4]),
		$m->real_escape_string($row[5]),
		$m->real_escape_string($row[6]),
		$row[7],
		$m->real_escape_string($row[8]),
		$m->real_escape_string($row[9]),
		$row[10],
		$m->real_escape_string('org-'.$row[0]),
		$m->real_escape_string($row[11])
	);

	if ( ! $m->query($sql))
	{
		fail($m, $sql);
	}
}

$products = array(
	array(1, 'Test Product 1-01', 'STD-1-001', 'Unit', '6000000010001', '2026-05-07', 'Brand 1', 1001, 'EAN-13', 'admin', '12 months', '2026-04-01', 'Passed', 'Nutrition', 'Daily use', '1000', 'Test product 1 for company 1', 10001, 'QR', 'QR:6000000010001', 'KP'),
	array(2, 'Test Product 1-02', 'STD-1-002', 'Unit', '6000000010002', '2026-05-07', 'Brand 1', 1001, 'EAN-13', 'admin', '12 months', '2026-04-01', 'Passed', 'Nutrition', 'Daily use', '1100', 'Test product 2 for company 1', 10002, 'QR', 'QR:6000000010002', 'KP'),
	array(3, 'Test Product 1-03', 'STD-1-003', 'Unit', '6000000010003', '2026-05-07', 'Brand 1', 1001, 'EAN-13', 'admin', '18 months', '2026-04-02', 'Passed', 'Nutrition', 'Daily use', '1200', 'Test product 3 for company 1', 10003, 'QR', 'QR:6000000010003', 'KP'),
	array(4, 'Lake Salt 250g', 'MNS 4301', 'Bag', '6000000020001', '2026-03-11', 'Lakeside', 1005, 'EAN-13', 'editor1', '24 months', '2026-02-20', 'Passed', 'Seasoning', 'Cooking', '2500', 'Salt product', 20001, 'QR', 'QR:6000000020001', 'KP'),
	array(5, 'Smoked Fish 200g', 'MNS 4302', 'Bag', '6000000020002', '2026-03-11', 'Lakeside', 1005, 'EAN-13', 'editor1', '6 months', '2026-02-21', 'Passed', 'Food', 'Ready to eat', '4800', 'Fish product', 20002, 'QR', 'QR:6000000020002', 'KP'),
	array(6, 'Berry Jam 300g', 'MNS 4303', 'Jar', '6000000020003', '2026-03-12', 'Lakeside', 1005, 'EAN-13', 'editor1', '18 months', '2026-02-22', 'Passed', 'Food', 'Spread', '3200', 'Jam product', 20003, 'Data Matrix', 'DM:6000000020003', 'KP'),
	array(7, 'Harbor Oil 500ml', 'MNS 5101', 'Bottle', '6000000030001', '2025-11-01', 'Harbor', 1003, 'EAN-13', 'editor2', '24 months', '2025-10-10', 'Passed', 'Cooking oil', 'Kitchen use', '5600', 'Oil product', 30001, 'QR', 'QR:6000000030001', 'KP'),
	array(8, 'Harbor Rice 1kg', 'MNS 5102', 'Bag', '6000000030002', '2025-11-02', 'Harbor', 1003, 'EAN-13', 'editor2', '12 months', '2025-10-11', 'Passed', 'Staple', 'Cooking', '4100', 'Rice product', 30002, 'QR', 'QR:6000000030002', 'KP'),
	array(9, 'Cedar Tea 80g', 'MNS 5201', 'Box', '6000000040001', '2026-01-08', 'Cedar', 1004, 'EAN-13', 'editor2', '24 months', '2025-12-15', 'Passed', 'Beverage', 'Brew', '2900', 'Tea product', 40001, 'QR', 'QR:6000000040001', 'KP'),
	array(10, 'Cedar Nuts 200g', 'MNS 5202', 'Bag', '6000000040002', '2026-01-09', 'Cedar', 1004, 'EAN-13', 'editor2', '9 months', '2025-12-16', 'Passed', 'Snack', 'Ready to eat', '3700', 'Nuts product', 40002, 'QR', 'QR:6000000040002', 'KP'),
	array(11, 'Northwind Milk 1L', 'MNS 4401', 'Bottle', '6000000050001', '2026-02-14', 'Northwind', 1002, 'EAN-13', 'admin', '3 months', '2026-01-20', 'Passed', 'Dairy', 'Drink cold', '2200', 'Milk product', 50001, 'QR', 'QR:6000000050001', 'KP'),
	array(12, 'Northwind Bread 400g', 'MNS 4402', 'Pack', '6000000050002', '2026-02-14', 'Northwind', 1002, 'EAN-13', 'admin', '7 days', '2026-01-21', 'Passed', 'Bakery', 'Ready to eat', '1800', 'Bread product', 50002, 'QR', 'QR:6000000050002', 'KP'),
);

foreach ($products as $row)
{
	$sql = sprintf(
		"INSERT INTO interbar
		(id, JPName, GuGyokID, PJType, LignSign, RegDate, SangHo, RegNum, NumKind, RegMan, Apoint, JTestDate, JTestStatus, Effect, `Using`, Value, Etc, BuGangID, NumKind2, LignSign2, sign_data, zipyo_Id, c_code)
		VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', %d, '%s')",
		$row[0],
		$m->real_escape_string($row[1]),
		$m->real_escape_string($row[2]),
		$m->real_escape_string($row[3]),
		$m->real_escape_string($row[4]),
		$m->real_escape_string($row[5]),
		$m->real_escape_string($row[6]),
		$row[7],
		$m->real_escape_string($row[8]),
		$m->real_escape_string($row[9]),
		$m->real_escape_string($row[10]),
		$m->real_escape_string($row[11]),
		$m->real_escape_string($row[12]),
		$m->real_escape_string($row[13]),
		$m->real_escape_string($row[14]),
		$m->real_escape_string($row[15]),
		$m->real_escape_string($row[16]),
		$row[17],
		$m->real_escape_string($row[18]),
		$m->real_escape_string($row[19]),
		$m->real_escape_string('prod-'.$row[0]),
		$row[0] + 900000,
		$m->real_escape_string($row[20])
	);

	if ( ! $m->query($sql))
	{
		fail($m, $sql);
	}
}

$m->query('SET FOREIGN_KEY_CHECKS=1');

foreach (array('publictbl', 'interemp', 'interbar') as $table)
{
	$c = $m->query("SELECT COUNT(*) AS c FROM `$table`")->fetch_assoc();
	echo $table.'='.$c['c'].PHP_EOL;
}

echo "OK\n";
