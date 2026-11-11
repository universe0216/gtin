<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Move bracket notes from the name tail to the head.
 * Keeps existing head brackets in place, then appends former tail brackets.
 */
function product_normalize_name_for_key($name)
{
	$name = trim((string) $name);

	if ($name === '')
	{
		return '';
	}

	$head_brackets = array();
	$tail_brackets = array();

	while (preg_match('/^\(([^()]*)\)\s*/u', $name, $matches))
	{
		$head_brackets[] = '('.$matches[1].')';
		$name = trim(preg_replace('/^\(([^()]*)\)\s*/u', '', $name, 1));
	}

	while (preg_match('/\s*\(([^()]*)\)$/u', $name, $matches))
	{
		array_unshift($tail_brackets, '('.$matches[1].')');
		$name = trim(preg_replace('/\s*\(([^()]*)\)$/u', '', $name, 1));
	}

	return implode('', $head_brackets).implode('', $tail_brackets).$name;
}

/**
 * Lowercase and strip all whitespace from one key part.
 */
function product_normalized_key_part($value)
{
	$value = preg_replace('/\s+/u', '', (string) $value);

	if ( ! is_string($value))
	{
		$value = '';
	}

	if (function_exists('mb_strtolower'))
	{
		return mb_strtolower($value, 'UTF-8');
	}

	return strtolower($value);
}

/**
 * Build products.normalized_key from name, standard_number, brand, package_type.
 */
function product_build_normalized_key($name, $standard_number, $brand, $package_type)
{
	$parts = array(
		product_normalized_key_part(product_normalize_name_for_key($name)),
		product_normalized_key_part($standard_number),
		product_normalized_key_part($brand),
		product_normalized_key_part($package_type),
	);

	return implode('_', $parts);
}
