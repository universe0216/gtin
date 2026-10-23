<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Supported interface languages. Keys are CodeIgniter language folder names.
 */
function app_languages()
{
	return array(
		'english' => 'English',
		'chinese' => '中文',
	);
}

function app_language()
{
	$language = config_item('language');
	$languages = app_languages();

	return isset($languages[$language]) ? $language : 'english';
}

function app_html_lang()
{
	return app_language() === 'chinese' ? 'zh-Hans' : 'en';
}

/**
 * Translate a line from application/language/{lang}/app_lang.php.
 * Extra arguments are passed to vsprintf.
 */
function app_line($key)
{
	$CI =& get_instance();
	$line = $CI->lang->line($key);

	if ($line === FALSE || $line === '')
	{
		$line = $key;
	}

	$args = array_slice(func_get_args(), 1);

	if ($args)
	{
		$line = vsprintf($line, $args);
	}

	return $line;
}
