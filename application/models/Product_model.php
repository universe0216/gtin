<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'models/Basic_model.php';

class Product_model extends Basic_model {

	protected $table = 'products';
	protected $searchable_columns = array(
		'products.name',
		'products.standard_number',
		'products.barcode',
		'products.barcode_type',
		'products.package_type',
		'organizations.name',
	);

	protected function prepare_list_query()
	{
		$this->db
			->select('products.*, organizations.name AS organization_name')
			->from($this->table)
			->join('organizations', 'organizations.id = products.organization_id', 'left');
	}

	protected function list_order_column()
	{
		return 'products.'.$this->primary_key;
	}
}
