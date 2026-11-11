<?php
defined('BASEPATH') OR exit('No direct script access allowed');


function standard_number_prefix_pattern()
{
	return '[^\x00-\x7F]{2,8}';
}

/**
 * Whether a standard number matches:
 *   XX d{n}:year
 *   XX d{n}:year(xxx)
 *
 */
function standard_number_is_valid($value)
{
	$value = trim((string) $value);

	if ($value === '')
	{
		return FALSE;
	}

	$prefix = standard_number_prefix_pattern();

	return (bool) preg_match('/^'.$prefix.' \d+:\d+(?:\([^()\s]*\))?$/u', $value);
}

/**
 * Normalize a standard number to XX d{n}:year or XX d{n}:year(xxx).
 * Inserts the required space after the 2-8 character prefix when missing.
 * Returns the original trimmed value when the structure cannot be recognized.
 */
function standard_number_normalize($value)
{
	$value = trim((string) $value);

	if ($value === '')
	{
		return '';
	}

	if (standard_number_is_valid($value))
	{
		return $value;
	}

	$compact = preg_replace('/\s+/u', '', $value);

	if ( ! is_string($compact) || $compact === '')
	{
		return $value;
	}

	$prefix = standard_number_prefix_pattern();

	if (preg_match('/^('.$prefix.')(\d+):(\d+)(\([^()\s]*\))?$/u', $compact, $matches))
	{
		$normalized = $matches[1].' '.$matches[2].':'.$matches[3];

		if (isset($matches[4]) && $matches[4] !== '')
		{
			$normalized .= $matches[4];
		}

		return $normalized;
	}

	return $value;
}
