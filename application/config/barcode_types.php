<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Barcode type labels used in product UI (edit selects, grid filters, registration).
| Manage these lists here so all consumers stay in sync.
|--------------------------------------------------------------------------
*/

$config['barcode_1d_types'] = array(
	'EAN-13',
	'EAN-8',
	// 'UPC-A',
	// 'UPC-E',
	'ITF-14',
	'GS1-128',
	'Code 128',
	'Code 39',
);

$config['barcode_2d_types'] = array(
	'QR',
	'Data Matrix',
	'GS1 DataMatrix',
	'PDF417',
	'Aztec',
);

/*
|--------------------------------------------------------------------------
| Types for Tools → Verify checksum (key + body length excluding check digit).
| length 0 means variable / AI-based input.
|--------------------------------------------------------------------------
*/

$config['barcode_checksum_types'] = array(
	array('key' => 'ean13', 'label' => 'EAN-13', 'length' => 12),
	array('key' => 'ean8', 'label' => 'EAN-8', 'length' => 7),
	array('key' => 'upca', 'label' => 'UPC-A', 'length' => 11),
	array('key' => 'upce', 'label' => 'UPC-E', 'length' => 7),
	array('key' => 'itf14', 'label' => 'ITF-14', 'length' => 13),
	array('key' => 'gs1128', 'label' => 'GS1-128', 'length' => 0),
);
