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

	public function index()
	{
		$this->auth->require_permission($this->permission_view);

		$records = array();

		if ($this->model)
		{
			$records = $this->model->get_all();
		}

		$this->render('crud/index', array(
			'title'      => $this->entity_label,
			'records'    => $records,
			'fields'     => $this->fields,
			'entity'     => $this->entity,
			'nav_active' => $this->entity,
			'can_edit'   => $this->auth->can($this->permission_edit),
			'add_url'    => $this->auth->can($this->permission_edit) ? site_url('organization_registration') : NULL,
		));
	}
}
