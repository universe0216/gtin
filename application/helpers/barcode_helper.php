<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Normalize a 1D barcode type label to a comparison key.
 */
function barcode_normalize_1d_type($type)
{
	return strtolower(preg_replace('/[\s\-]+/', '', trim((string) $type)));
}

/**
 * Return empty, type, or format when a 1D barcode cannot be accepted.
 */
function barcode_1d_problem($type, $value)
{
	$value = trim((string) $value);

	if ($value === '')
	{
		return 'empty';
	}

	$key = barcode_normalize_1d_type($type);

	if ($key === '')
	{
		return 'type';
	}

	if ( ! barcode_1d_matches($key, $value))
	{
		return 'format';
	}

	return NULL;
}

/**
 * Whether the value matches the normalized 1D barcode type.
 */
function barcode_1d_matches($type_key, $value)
{
	switch ($type_key)
	{
		case 'ean13':
			return (bool) preg_match('/^\d{13}$/', $value) && barcode_gs1_check_valid($value);
		case 'ean8':
			return (bool) preg_match('/^\d{8}$/', $value) && barcode_gs1_check_valid($value);
		case 'upca':
			return (bool) preg_match('/^\d{12}$/', $value) && barcode_gs1_check_valid($value);
		case 'upce':
			return barcode_upc_e_valid($value);
		case 'itf14':
			return (bool) preg_match('/^\d{14}$/', $value) && barcode_gs1_check_valid($value);
		case 'gs1128':
			return (bool) preg_match('/^(\(\d{2,4}\)[^\s()]{1,90})+$/', $value)
				|| (bool) preg_match('/^\d{4,48}$/', $value);
		case 'code128':
			return (bool) preg_match('/^[\x20-\x7E]{1,80}$/', $value) && trim($value) !== '';
		case 'code39':
			return (bool) preg_match('/^[0-9A-Z\-. $\/+%]{1,80}$/', $value) && trim($value) !== '';
		default:
			return FALSE;
	}
}

/**
 * GS1 modulo-10 check digit for the digits that precede it.
 */
function barcode_gs1_check_digit($data_digits)
{
	$data_digits = (string) $data_digits;
	$sum = 0;
	$length = strlen($data_digits);

	for ($i = 0; $i < $length; $i++)
	{
		$digit = (int) $data_digits[$length - 1 - $i];
		$sum += $digit * ($i % 2 === 0 ? 3 : 1);
	}

	return (string) ((10 - ($sum % 10)) % 10);
}

/**
 * Whether the last digit is the GS1 check digit for the preceding digits.
 */
function barcode_gs1_check_valid($value)
{
	if ( ! preg_match('/^\d{2,}$/', $value))
	{
		return FALSE;
	}

	$data = substr($value, 0, -1);

	return barcode_gs1_check_digit($data) === substr($value, -1);
}

/**
 * UPC-E is 8 digits: number system 0 or 1, six compressed digits, and a check digit.
 */
function barcode_upc_e_valid($value)
{
	if ( ! preg_match('/^[01]\d{7}$/', $value))
	{
		return FALSE;
	}

	$number_system = $value[0];
	$body = substr($value, 1, 6);
	$check = $value[7];
	$last = $body[5];

	if ($last === '0' || $last === '1' || $last === '2')
	{
		$manufacturer = substr($body, 0, 2).$last.'00';
		$product = '00'.substr($body, 2, 3);
	}
	elseif ($last === '3')
	{
		$manufacturer = substr($body, 0, 3).'00';
		$product = '000'.substr($body, 3, 2);
	}
	elseif ($last === '4')
	{
		$manufacturer = substr($body, 0, 4).'0';
		$product = '0000'.$body[4];
	}
	else
	{
		$manufacturer = substr($body, 0, 5);
		$product = '0000'.$last;
	}

	return barcode_gs1_check_digit($number_system.$manufacturer.$product) === $check;
}

/**
 * Item reference (0-999) after a 9-digit GS1 company prefix.
 * Accepts the 12-digit body, or the same body with a check digit or indicator digit.
 */
function barcode_item_reference($barcode, $prefix)
{
	$digits = preg_replace('/\D/', '', (string) $barcode);
	$prefix = preg_replace('/\D/', '', (string) $prefix);

	if ($prefix === '' || $digits === '')
	{
		return NULL;
	}

	if (strpos($digits, $prefix) === 0)
	{
		$offset = strlen($prefix);
	}
	elseif (strlen($digits) > strlen($prefix) && strpos($digits, $prefix) === 1)
	{
		$offset = strlen($prefix) + 1;
	}
	else
	{
		return NULL;
	}

	if (strlen($digits) < $offset + 3)
	{
		return NULL;
	}

	return (int) substr($digits, $offset, 3);
}

/**
 * Lowest unused item reference from 000 through 999.
 */
function barcode_next_item_reference(array $used_refs)
{
	for ($item_reference = 0; $item_reference <= 999; $item_reference++)
	{
		if (empty($used_refs[$item_reference]))
		{
			return $item_reference;
		}
	}

	return NULL;
}

/**
 * Build a 1D symbol from a 12-digit body for the normalized barcode type.
 */
function barcode_format_assigned($type_key, $body12)
{
	$body12 = preg_replace('/\D/', '', (string) $body12);

	if (strlen($body12) !== 12)
	{
		return NULL;
	}

	$ean13 = $body12.barcode_gs1_check_digit($body12);
	$indicator = '0'.$body12;
	$gtin14 = $indicator.barcode_gs1_check_digit($indicator);

	switch ($type_key)
	{
		case 'ean13':
		case 'code128':
		case 'code39':
			return $ean13;
		case 'upca':
			if ($body12[0] !== '0')
			{
				return NULL;
			}

			return substr($ean13, 1);
		case 'itf14':
			return $gtin14;
		case 'gs1128':
			return '(01)'.$gtin14;
		default:
			return NULL;
	}
}
