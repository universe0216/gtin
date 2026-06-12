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
}
