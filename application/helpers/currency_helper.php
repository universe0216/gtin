<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Load currency config once and return currency_options.
 */
function currency_options_raw()
{
	static $loaded = FALSE;
	static $options = NULL;
	$CI =& get_instance();

	if ( ! $loaded)
	{
		$CI->config->load('currency', FALSE, TRUE);
		$loaded = TRUE;
		$value = $CI->config->item('currency_options');
		$options = is_array($value) ? $value : array();
	}

	return $options;
}

/**
 * Allowed currency DB values.
 */
function currency_values()
{
	$values = array();

	foreach (currency_options_raw() as $option)
	{
		$value = trim((string) ($option['value'] ?? ''));

		if ($value !== '')
		{
			$values[] = $value;
		}
	}

	return $values;
}

/**
 * Localized label for a stored currency value.
 */
function currency_label($value)
{
	$value = trim((string) $value);

	if ($value === '')
	{
		return '';
	}

	foreach (currency_options_raw() as $option)
	{
		if ((string) ($option['value'] ?? '') !== $value)
		{
			continue;
		}

		$label_key = (string) ($option['label_key'] ?? '');

		if ($label_key !== '' && function_exists('app_line'))
		{
			$line = app_line($label_key);

			if ($line !== '')
			{
				return $line;
			}
		}

		return (string) ($option['label'] ?? $value);
	}

	return $value;
}

/**
 * Select options for organization forms (optionally with empty first row).
 */
function currency_select_options($include_empty = TRUE)
{
	$options = array();

	if ($include_empty)
	{
		$options[] = array('value' => '', 'label' => '');
	}

	foreach (currency_options_raw() as $option)
	{
		$value = (string) ($option['value'] ?? '');

		if ($value === '')
		{
			continue;
		}

		$options[] = array(
			'value' => $value,
			'label' => currency_label($value),
		);
	}

	return $options;
}

/**
 * Options for JS filters/renderers: value, label, cssClass.
 */
function currency_options_for_js()
{
	$options = array();

	foreach (currency_options_raw() as $option)
	{
		$value = (string) ($option['value'] ?? '');

		if ($value === '')
		{
			continue;
		}

		$options[] = array(
			'value'    => $value,
			'label'    => currency_label($value),
			'cssClass' => (string) ($option['css_class'] ?? ''),
		);
	}

	return $options;
}

/**
 * Build CodeIgniter in_list rule including empty.
 */
function currency_in_list_rule()
{
	$values = currency_values();

	return 'in_list[,'.implode(',', $values).']';
}
