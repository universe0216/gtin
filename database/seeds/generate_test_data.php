<?php
/**
 * Generate test organizations, products, standard_category, and product_category rows.
 *
 * Usage:
 *   php database/seeds/generate_test_data.php
 *   php database/seeds/generate_test_data.php --orgs=10 --products=15
 *   php database/seeds/generate_test_data.php --replace
 */

$opts = getopt('', array('orgs::', 'products::', 'replace'));
$org_count = max(1, (int) ($opts['orgs'] ?? 10));
$products_per_org = max(1, (int) ($opts['products'] ?? 15));
$replace = array_key_exists('replace', $opts);

if ( ! defined('BASEPATH'))
{
	define('BASEPATH', TRUE);
}

$config_file = dirname(__DIR__).'/../application/config/barcode_types.php';
$config = array();
require $config_file;
$barcode_1d_types = $config['barcode_1d_types'] ?? array('EAN-13');
$barcode_2d_types = $config['barcode_2d_types'] ?? array('QR');

$pdo = new PDO('mysql:host=localhost;dbname=national_gs1_registry;charset=utf8', 'root', '123456', array(
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
));

function gs1_check_digit($body)
{
	$body = (string) $body;
	$sum = 0;
	$len = strlen($body);

	for ($i = 0; $i < $len; $i++)
	{
		$digit = (int) $body[$len - 1 - $i];
		$sum += $digit * ($i % 2 === 0 ? 3 : 1);
	}

	return (string) ((10 - ($sum % 10)) % 10);
}

function ean13_from_prefix_item($prefix9, $item)
{
	$body = substr(preg_replace('/\D/', '', $prefix9).str_pad((string) $item, 3, '0', STR_PAD_LEFT), 0, 12);
	$body = str_pad($body, 12, '0', STR_PAD_LEFT);

	return $body.gs1_check_digit($body);
}

function normalized_key($name, $standard, $brand, $package)
{
	$parts = array();

	foreach (array($name, $standard, $brand, $package) as $part)
	{
		$parts[] = strtolower(preg_replace('/\s+/u', '', (string) $part));
	}

	return implode('___', $parts);
}

$locations = $pdo->query('SELECT id FROM locations ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN);
if (empty($locations))
{
	fwrite(STDERR, "No locations found. Seed locations first.\n");
	exit(1);
}

$categories = $pdo->query(
	"SELECT id FROM categories WHERE level = 3 ORDER BY id ASC LIMIT 40"
)->fetchAll(PDO::FETCH_COLUMN);
if (empty($categories))
{
	$categories = $pdo->query(
		"SELECT id FROM categories ORDER BY id ASC LIMIT 40"
	)->fetchAll(PDO::FETCH_COLUMN);
}

if ($replace)
{
	$pdo->exec("DELETE pc FROM product_category pc
		INNER JOIN products p ON p.id = pc.product_id
		WHERE p.name LIKE 'Test Product %'");
	$pdo->exec("DELETE FROM standard_category WHERE standard_number LIKE 'STD-TEST-%'");
	$pdo->exec("DELETE FROM products WHERE name LIKE 'Test Product %'");
	$pdo->exec("DELETE FROM organizations WHERE name LIKE 'Test Company %'");
	echo "Removed previous Test Company / Test Product rows.\n";
}

$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');
$insert_org = $pdo->prepare(
	'INSERT INTO organizations
	(name, reg_number, parent_org, location_id, gs1_prefix, init_date, reg_date, expiry_date, address, phone, c_code, currency, description, created_at, updated_at)
	VALUES
	(:name, :reg_number, :parent_org, :location_id, :gs1_prefix, :init_date, :reg_date, :expiry_date, :address, :phone, :c_code, :currency, :description, :created_at, :updated_at)'
);
$insert_product = $pdo->prepare(
	'INSERT INTO products
	(name, standard_number, brand, package_type, normalized_key, org_id, effect, `usage`, price, effective_period, test_date, test_status, description, barcode_1d, barcode_1d_type, barcode_2d, barcode_2d_type, reg_date, processor_name, global_id, c_code, created_at, updated_at)
	VALUES
	(:name, :standard_number, :brand, :package_type, :normalized_key, :org_id, :effect, :usage, :price, :effective_period, :test_date, :test_status, :description, :barcode_1d, :barcode_1d_type, :barcode_2d, :barcode_2d_type, :reg_date, :processor_name, :global_id, :c_code, :created_at, :updated_at)'
);
$insert_standard = $pdo->prepare(
	'INSERT INTO standard_category (standard_number, category_id)
	VALUES (:standard_number, :category_id)
	ON DUPLICATE KEY UPDATE category_id = VALUES(category_id)'
);
$insert_product_category = $pdo->prepare(
	'INSERT IGNORE INTO product_category (product_id, category_id)
	VALUES (:product_id, :category_id)'
);

$existing_max = (int) $pdo->query(
	"SELECT COALESCE(MAX(CAST(SUBSTRING(name, 14) AS UNSIGNED)), 0)
	 FROM organizations WHERE name REGEXP '^Test Company [0-9]+$'"
)->fetchColumn();
$start_org = $existing_max + 1;

$pdo->beginTransaction();

$orgs_created = 0;
$products_created = 0;
$standards_created = 0;
$links_created = 0;

try
{
	for ($o = 0; $o < $org_count; $o++)
	{
		$org_n = $start_org + $o;
		$gs1_prefix = str_pad((string) (690000000 + $org_n), 9, '0', STR_PAD_LEFT);
		$location_id = (int) $locations[$o % count($locations)];
		$currency = ($o % 2 === 0) ? 2 : 1;

		$insert_org->execute(array(
			':name'         => 'Test Company '.$org_n,
			':reg_number'   => 900000 + $org_n,
			':parent_org'   => $o > 0 && $o % 4 === 0 ? 'Test Company '.($org_n - 1) : NULL,
			':location_id'  => $location_id,
			':gs1_prefix'   => $gs1_prefix,
			':init_date'    => date('Y-m-d', strtotime('-'.(365 + $o).' day')),
			':reg_date'     => $today,
			':expiry_date'  => date('Y-m-d', strtotime('+'.(180 + ($o * 30)).' day')),
			':address'      => $org_n.' Test Street',
			':phone'        => '9900'.str_pad((string) $org_n, 4, '0', STR_PAD_LEFT),
			':c_code'       => '496',
			':currency'     => $currency,
			':description'  => 'Generated test organization '.$org_n,
			':created_at'   => $now,
			':updated_at'   => $now,
		));
		$org_id = (int) $pdo->lastInsertId();
		$orgs_created++;

		for ($p = 1; $p <= $products_per_org; $p++)
		{
			$name = 'Test Product '.$org_n.'-'.str_pad((string) $p, 2, '0', STR_PAD_LEFT);
			$standard = 'STD-TEST-'.$org_n.'-'.str_pad((string) $p, 3, '0', STR_PAD_LEFT);
			$brand = 'Brand '.$org_n;
			$package = ($p % 3 === 0) ? 'Case' : 'Unit';
			$type_1d = $barcode_1d_types[($p - 1) % count($barcode_1d_types)];
			$type_2d = $barcode_2d_types[($p - 1) % count($barcode_2d_types)];
			$barcode = ean13_from_prefix_item($gs1_prefix, $p);
			$category_id = ! empty($categories)
				? (int) $categories[($org_n + $p) % count($categories)]
				: 0;

			$insert_product->execute(array(
				':name'              => $name,
				':standard_number'   => $standard,
				':brand'             => $brand,
				':package_type'      => $package,
				':normalized_key'    => normalized_key($name, $standard, $brand, $package),
				':org_id'            => $org_id,
				':effect'            => 'Test effect '.$p,
				':usage'             => 'Test usage '.$p,
				':price'             => (string) (1000 + ($p * 10)),
				':effective_period'  => '24 months',
				':test_date'         => date('Y-m-d', strtotime('-'.($p * 3).' day')),
				':test_status'       => ($p % 5 === 0) ? 'Failed' : 'Passed',
				':description'       => 'Generated test product '.$p.' for company '.$org_n,
				':barcode_1d'        => $barcode,
				':barcode_1d_type'   => $type_1d,
				':barcode_2d'        => $type_2d.':'.$barcode,
				':barcode_2d_type'   => $type_2d,
				':reg_date'          => date('Y-m-d', strtotime('-'.(30 + $p).' day')),
				':processor_name'    => 'Seed Processor',
				':global_id'         => NULL,
				':c_code'            => '496',
				':created_at'        => $now,
				':updated_at'        => $now,
			));
			$product_id = (int) $pdo->lastInsertId();
			$products_created++;

			if ($category_id > 0)
			{
				$insert_standard->execute(array(
					':standard_number' => $standard,
					':category_id'     => $category_id,
				));
				$standards_created++;

				$insert_product_category->execute(array(
					':product_id'  => $product_id,
					':category_id' => $category_id,
				));
				$links_created++;
			}
		}
	}

	$pdo->commit();
}
catch (Exception $e)
{
	$pdo->rollBack();
	fwrite(STDERR, $e->getMessage().PHP_EOL);
	exit(1);
}

echo "Created organizations: {$orgs_created}\n";
echo "Created products: {$products_created}\n";
echo "Standard-category links: {$standards_created}\n";
echo "Product-category links: {$links_created}\n";
echo "1D types used: ".implode(', ', $barcode_1d_types)."\n";
echo "2D types used: ".implode(', ', $barcode_2d_types)."\n";
