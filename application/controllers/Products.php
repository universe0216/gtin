<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/BasicController.php';

class Products extends BasicController {

	protected $entity = 'products';
	protected $entity_label = 'Products';
	protected $fields = array(
		array(
			'name'     => 'name',
			'label'    => 'Name',
			'type'     => 'text',
			'required' => TRUE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'standard_number',
			'label'    => 'Standard Number',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[100]'),
		),
		array(
			'name'     => 'barcode',
			'label'    => 'Barcode',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[100]'),
		),
		array(
			'name'     => 'barcode_type',
			'label'    => 'Barcode Type',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[50]'),
		),
		array(
			'name'     => 'barcode_2d_value',
			'label'    => '2D Barcode',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'barcode_2d_type',
			'label'    => '2D Barcode Type',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[50]'),
		),
		array(
			'name'     => 'package_type',
			'label'    => 'Package Type',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[100]'),
		),
		array(
			'name'     => 'organization_id',
			'label'    => 'Organization ID',
			'type'     => 'number',
			'required' => FALSE,
			'rules'    => array('trim', 'integer'),
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
			'name'     => 'description',
			'label'    => 'Description',
			'type'     => 'textarea',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
	);

	protected $permission_view = 'product.view';
	protected $permission_edit = 'product.edit';

	public function __construct()
	{
		parent::__construct();
		$this->load->model('product_model', 'model');
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
			'add_url'    => $this->auth->can($this->permission_edit) ? site_url('product_registration') : NULL,
		));
	}
}
