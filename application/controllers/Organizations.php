<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/BasicController.php';

class Organizations extends BasicController {

	protected $entity = 'organizations';
	protected $entity_label = 'Organizations';
	protected $add_url_route = 'organization_registration';
	protected $list_search_placeholder = 'Search name, registration number, GS1 prefix, procedure number...';
	protected $list_empty_message = 'No organizations found.';
	protected $list_empty_search_message = 'No organizations match your search.';
	protected $list_columns = array(
		array('key' => 'name', 'label' => 'Name'),
		array('key' => 'registration_number', 'label' => 'Registration Number'),
		array('key' => 'gs1_prefix', 'label' => 'GS1 Prefix'),
		array('key' => 'procedure_number', 'label' => 'Procedure Number'),
		array('key' => 'registration_date', 'label' => 'Registration Date'),
		array('key' => 'reregistration_date', 'label' => 'Re-registration Date'),
		array('key' => 'expiry_date', 'label' => 'Expiry Date'),
		array('key' => 'address', 'label' => 'Address'),
	);
	protected $fields = array(
		array(
			'name'     => 'name',
			'label'    => 'Name',
			'type'     => 'text',
			'required' => TRUE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'registration_number',
			'label'    => 'Registration Number',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[100]'),
		),
		array(
			'name'     => 'gs1_prefix',
			'label'    => 'GS1 Prefix',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[50]'),
		),
		array(
			'name'     => 'registration_date',
			'label'    => 'Registration Date',
			'type'     => 'date',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
		array(
			'name'     => 'reregistration_date',
			'label'    => 'Re-registration Date',
			'type'     => 'date',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
		array(
			'name'     => 'expiry_date',
			'label'    => 'Expiry Date',
			'type'     => 'date',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
		array(
			'name'     => 'address',
			'label'    => 'Address',
			'type'     => 'textarea',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
		array(
			'name'     => 'procedure_number',
			'label'    => 'Procedure Number',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[50]'),
		),
		array(
			'name'     => 'description',
			'label'    => 'Description',
			'type'     => 'textarea',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
	);

	protected $permission_view = 'organization.view';
	protected $permission_edit = 'organization.edit';

	public function __construct()
	{
		parent::__construct();
		$this->load->model('organization_model', 'model');
	}

	protected function get_add_label()
	{
		return 'Register Organizations';
	}
}
