<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/BasicController.php';

class Organizations extends BasicController {

	protected $entity = 'organizations';
	protected $entity_label = 'Organizations';
	protected $fields = array(
		array(
			'name'     => 'name',
			'label'    => 'Name',
			'type'     => 'text',
			'required' => TRUE,
			'rules'    => array('trim', 'max_length[255]'),
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
}
