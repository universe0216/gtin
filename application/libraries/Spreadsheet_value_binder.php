<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * PhpSpreadsheet 1.8.x checks string offsets before numeric types,
 * which triggers notices on PHP 7.4+/8 when cell values are integers.
 */
class Spreadsheet_value_binder extends DefaultValueBinder {

	public static function dataTypeForValue($pValue)
	{
		if (is_int($pValue) || is_float($pValue))
		{
			return DataType::TYPE_NUMERIC;
		}

		if (is_bool($pValue))
		{
			return DataType::TYPE_BOOL;
		}

		return parent::dataTypeForValue($pValue);
	}
}
