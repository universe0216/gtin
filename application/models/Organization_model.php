<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'models/Basic_model.php';

class Organization_model extends Basic_model {

	protected $table = 'organizations';
	protected $searchable_columns = array(
		'name',
		'registration_number',
		'gs1_prefix',
		'procedure_number',
		'address',
	);
	protected $sortable_columns = array(
		'name'                => 'organizations.name',
		'registration_number' => 'organizations.registration_number',
		'gs1_prefix'          => 'organizations.gs1_prefix',
		'procedure_number'    => 'organizations.procedure_number',
		'registration_date'   => 'organizations.registration_date',
		'reregistration_date' => 'organizations.reregistration_date',
		'expiry_date'         => 'organizations.expiry_date',
		'address'             => 'organizations.address',
	);
}
