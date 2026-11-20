<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Load barcode_types config once and return the requested item.
 */
function barcode_types_config_item($key, $default = array())
{
	static $loaded = FALSE;
	$CI =& get_instance();

	if ( ! $loaded)
	{
		$CI->config->load('barcode_types', FALSE, TRUE);
		$loaded = TRUE;
	}

	$value = $CI->config->item($key);

	return is_array($value) ? $value : $default;
}

/**
 * 1D barcode type labels for selects and filters.
 */
function barcode_1d_type_options()
{
	return barcode_types_config_item('barcode_1d_types');
}

/**
 * 2D barcode type labels for selects and filters.
 */
function barcode_2d_type_options()
{
	return barcode_types_config_item('barcode_2d_types');
}

/**
 * Checksum tool type definitions (key, label, length).
 */
function barcode_checksum_type_options()
{
	return barcode_types_config_item('barcode_checksum_types');
}

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
 * Whether the value has the length required by this 1D barcode type.
 */
function barcode_1d_length_valid($type, $value)
{
	$value = trim((string) $value);
	$key = barcode_normalize_1d_type($type);

	switch ($key)
	{
		case 'ean13':
			return (bool) preg_match('/^\d{13}$/', $value);
		case 'ean8':
			return (bool) preg_match('/^\d{8}$/', $value);
		case 'upca':
			return (bool) preg_match('/^\d{12}$/', $value);
		case 'upce':
			return (bool) preg_match('/^[01]\d{7}$/', $value);
		case 'itf14':
			return (bool) preg_match('/^\d{14}$/', $value);
		case 'gs1128':
			return (bool) preg_match('/^(\(\d{2,4}\)[^\s()]{1,90})+$/', $value)
				|| (bool) preg_match('/^\d{4,48}$/', $value);
		case 'code128':
			return (bool) preg_match('/^[\x20-\x7E]{1,80}$/', $value);
		case 'code39':
			return (bool) preg_match('/^[0-9A-Z\-. $\/+%]{1,80}$/', $value);
		default:
			return FALSE;
	}
}

/**
 * Whether the check digit is valid for this 1D barcode type.
 * Code 128 and Code 39 have no check digit in this registry.
 */
function barcode_1d_checksum_valid($type, $value)
{
	$value = trim((string) $value);
	$key = barcode_normalize_1d_type($type);

	switch ($key)
	{
		case 'ean13':
		case 'ean8':
		case 'upca':
		case 'itf14':
			return barcode_gs1_check_valid($value);
		case 'upce':
			return barcode_upc_e_valid($value);
		case 'gs1128':
			if (preg_match('/^\(01\)(\d{14})$/', $value, $matches))
			{
				return barcode_gs1_check_valid($matches[1]);
			}

			if (preg_match('/^\d{8}$|^\d{12}$|^\d{13}$|^\d{14}$/', $value))
			{
				return barcode_gs1_check_valid($value);
			}

			return TRUE;
		case 'code128':
		case 'code39':
			return TRUE;
		default:
			return FALSE;
	}
}

/**
 * Company-prefix digits carried by this barcode, based on its type.
 * GS1-128 drops application identifiers 01, 8017, and 8018 before the prefix is read.
 */
function barcode_1d_organization_code($type, $value)
{
	$key = barcode_normalize_1d_type($type);
	$value = trim((string) $value);

	if ($key === 'gs1128')
	{
		$code = barcode_gs1_organization_code($value);

		if ($code !== NULL)
		{
			return $code;
		}
	}

	$digits = preg_replace('/\D/', '', $value);

	if ($key === 'itf14' || ($key === 'gs1128' && strlen($digits) === 14))
	{
		return substr($digits, 1, 9);
	}

	return substr($digits, 0, 9);
}

/**
 * Company prefix from a GS1 element string.
 * AIs 01, 8017, and 8018 are removed. AI 01 is a GTIN-14, so its indicator digit is skipped.
 */
function barcode_gs1_organization_code($value)
{
	$data = barcode_gs1_identity_data($value);

	if ($data === NULL)
	{
		return NULL;
	}

	if ($data['ai'] === '01')
	{
		return substr($data['digits'], 1, 9);
	}

	return substr($data['digits'], 0, 9);
}

/**
 * Data of the first AI 01, 8017, or 8018 in a parenthesized or raw GS1 element string.
 */
function barcode_gs1_identity_data($value)
{
	$lengths = array(
		'01'   => 14,
		'8017' => 18,
		'8018' => 18,
	);
	$value = trim((string) $value);

	if (strpos($value, '(') !== FALSE)
	{
		if ( ! preg_match('/\((01|8017|8018)\)(\d+)/', $value, $matches))
		{
			return NULL;
		}

		$data = substr($matches[2], 0, $lengths[$matches[1]]);

		if (strlen($data) !== $lengths[$matches[1]])
		{
			return NULL;
		}

		return array('ai' => $matches[1], 'digits' => $data);
	}

	if ( ! preg_match('/^\d+$/', $value))
	{
		return NULL;
	}

	$offset = 0;
	$length = strlen($value);

	while ($offset < $length)
	{
		$step = barcode_gs1_fixed_element($value, $offset);

		if ($step === NULL)
		{
			return NULL;
		}

		if (isset($lengths[$step['ai']]))
		{
			return array('ai' => $step['ai'], 'digits' => $step['digits']);
		}

		$offset += strlen($step['ai']) + strlen($step['digits']);
	}

	return NULL;
}

/**
 * Next fixed-length GS1 element at an offset, or NULL when the AI is unknown or incomplete.
 */
function barcode_gs1_fixed_element($value, $offset)
{
	foreach (array(4, 3, 2) as $width)
	{
		if ($offset + $width > strlen($value))
		{
			continue;
		}

		$ai = substr($value, $offset, $width);
		$data_length = barcode_gs1_fixed_ai_length($ai);

		if ($data_length === NULL || $offset + $width + $data_length > strlen($value))
		{
			continue;
		}

		return array(
			'ai'     => $ai,
			'digits' => substr($value, $offset + $width, $data_length),
		);
	}

	return NULL;
}

/**
 * Fixed data length for a GS1 application identifier, excluding the identifier itself.
 */
function barcode_gs1_fixed_ai_length($ai)
{
	static $lengths = NULL;

	if ($lengths === NULL)
	{
		$lengths = array(
			'00' => 18, '01' => 14, '02' => 14,
			'11' => 6, '12' => 6, '13' => 6, '14' => 6, '15' => 6, '16' => 6, '17' => 6, '18' => 6, '19' => 6,
			'20' => 2, '402' => 17,
			'410' => 13, '411' => 13, '412' => 13, '413' => 13, '414' => 13,
			'415' => 13, '416' => 13, '417' => 13, '418' => 13, '419' => 13,
			'422' => 3, '424' => 3, '426' => 3,
			'7001' => 13, '7003' => 10, '7006' => 6, '7007' => 6, '7040' => 4,
			'8001' => 14, '8005' => 6, '8006' => 18, '8017' => 18, '8018' => 18, '8026' => 18, '8111' => 4,
		);
		$measure_prefixes = array(
			'310', '311', '312', '313', '314', '315', '316',
			'320', '321', '322', '323', '324', '325', '326', '327', '328', '329',
			'330', '331', '332', '333', '334', '335', '336', '337',
			'340', '341', '342', '343', '344', '345', '346', '347', '348', '349',
			'350', '351', '352', '353', '354', '355', '356', '357',
			'360', '361', '362', '363', '364', '365', '366', '367', '368', '369',
		);

		foreach ($measure_prefixes as $prefix)
		{
			for ($decimal = 0; $decimal <= 9; $decimal++)
			{
				$lengths[$prefix.$decimal] = 6;
			}
		}
	}

	return isset($lengths[$ai]) ? $lengths[$ai] : NULL;
}

/**
 * Whether the barcode's company prefix matches the organization's prefix.
 */
function barcode_1d_organization_valid($type, $value, $prefix)
{
	$prefix = preg_replace('/\D/', '', (string) $prefix);
	$code = barcode_1d_organization_code($type, $value);

	return strlen($prefix) === 9 && $code === $prefix;
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
 * Expand a 7-digit UPC-E body (number system plus six digits) to 11 UPC-A digits.
 */
function barcode_upc_e_expand($body)
{
	if ( ! preg_match('/^[01]\d{6}$/', (string) $body))
	{
		return NULL;
	}

	$number_system = $body[0];
	$compressed = substr($body, 1, 6);
	$last = $compressed[5];

	if ($last === '0' || $last === '1' || $last === '2')
	{
		$manufacturer = substr($compressed, 0, 2).$last.'00';
		$product = '00'.substr($compressed, 2, 3);
	}
	elseif ($last === '3')
	{
		$manufacturer = substr($compressed, 0, 3).'00';
		$product = '000'.substr($compressed, 3, 2);
	}
	elseif ($last === '4')
	{
		$manufacturer = substr($compressed, 0, 4).'0';
		$product = '0000'.$compressed[4];
	}
	else
	{
		$manufacturer = substr($compressed, 0, 5);
		$product = '0000'.$last;
	}

	return $number_system.$manufacturer.$product;
}

/**
 * Check digit for a 7-digit UPC-E body.
 */
function barcode_upc_e_check_digit($body)
{
	$expanded = barcode_upc_e_expand($body);

	if ($expanded === NULL)
	{
		return NULL;
	}

	return barcode_gs1_check_digit($expanded);
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

	$check = barcode_upc_e_check_digit(substr($value, 0, 7));

	return $check !== NULL && $check === $value[7];
}

/**
 * Last digit for a barcode body that omits that digit.
 */
function barcode_checksum($type, $body)
{
	$key = barcode_normalize_1d_type($type);
	$body = preg_replace('/\D/', '', (string) $body);
	$lengths = array(
		'ean13'  => 12,
		'ean8'   => 7,
		'upca'   => 11,
		'upce'   => 7,
		'itf14'  => 13,
	);

	if ($key === 'gs1128')
	{
		return $body === '' ? NULL : barcode_gs1_check_digit($body);
	}

	if ( ! isset($lengths[$key]) || strlen($body) !== $lengths[$key])
	{
		return NULL;
	}

	if ($key === 'upce')
	{
		return barcode_upc_e_check_digit($body);
	}

	return barcode_gs1_check_digit($body);
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
 * Lowest unused item reference from 001 through 999.
 */
function barcode_next_item_reference(array $used_refs)
{
	for ($item_reference = 1; $item_reference <= 999; $item_reference++)
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
