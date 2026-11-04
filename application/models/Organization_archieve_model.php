<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'models/Organization_model.php';

class Organization_archieve_model extends Organization_model {

	protected $table = 'organization_archieve';
	protected $sortable_columns = array(
		'name'        => 'organization_archieve.name',
		'reg_number'  => 'organization_archieve.reg_number',
		'parent_org'  => 'organization_archieve.parent_org',
		'gs1_prefix'  => 'organization_archieve.gs1_prefix',
		'init_date'   => 'organization_archieve.init_date',
		'reg_date'    => 'organization_archieve.reg_date',
		'expiry_date' => 'organization_archieve.expiry_date',
		'phone'       => 'organization_archieve.phone',
		'c_code'      => 'organization_archieve.c_code',
		'currency'    => 'organization_archieve.currency',
		'address'     => 'organization_archieve.address',
		'deleted_at'  => 'organization_archieve.deleted_at',
	);

	protected function list_order_column()
	{
		return 'organization_archieve.deleted_at';
	}
}
