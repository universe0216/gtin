<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'models/Basic_model.php';

class Organization_model extends Basic_model {

	protected $table = 'organizations';
	protected $searchable_columns = array(
		'name',
		'reg_number',
		'gs1_prefix',
		'parent_org',
		'phone',
		'c_code',
		'address',
	);
	protected $sortable_columns = array(
		'name'        => 'organizations.name',
		'reg_number'  => 'organizations.reg_number',
		'parent_org'  => 'organizations.parent_org',
		'gs1_prefix'  => 'organizations.gs1_prefix',
		'init_date'   => 'organizations.init_date',
		'reg_date'    => 'organizations.reg_date',
		'expiry_date' => 'organizations.expiry_date',
		'phone'       => 'organizations.phone',
		'c_code'      => 'organizations.c_code',
		'address'     => 'organizations.address',
	);

	public function get_by_name($name)
	{
		$name = trim((string) $name);

		if ($name === '')
		{
			return NULL;
		}

		return $this->db
			->where('name', $name)
			->get($this->table)
			->row_array();
	}
}
