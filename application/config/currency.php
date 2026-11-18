<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Organization currency options.
| value     = stored DB value (organizations.currency)
| label_key = language line key (english/chinese app_lang.php)
| css_class = optional row/cell class used in organization grids
|--------------------------------------------------------------------------
*/

$config['currency_options'] = array(
	array(
		'value'     => '1',
		'label_key' => 'currency_foreign',
		'css_class' => 'organization-currency-foreign',
	),
	array(
		'value'     => '2',
		'label_key' => 'currency_domestic',
		'css_class' => 'organization-currency-domestic',
	),
);
