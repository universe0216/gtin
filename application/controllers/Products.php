<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/BasicController.php';

class Products extends BasicController {

	protected $entity = 'products';
	protected $entity_label = 'Products';
	protected $add_url_route = 'product_registration';
	protected $list_search_placeholder = 'Search name, barcode, standard number, organization...';
	protected $list_empty_message = 'No products found.';
	protected $list_empty_search_message = 'No products match your search.';
	protected $list_columns = array(
		array('key' => 'name', 'label' => 'Name'),
		array('key' => 'standard_number', 'label' => 'Standard Number'),
		array('key' => 'barcode', 'label' => 'Barcode'),
		array('key' => 'barcode_type', 'label' => 'Barcode Type'),
		array('key' => 'package_type', 'label' => 'Package Type'),
		array('key' => 'organization_name', 'label' => 'Organization'),
		array('key' => 'registration_date', 'label' => 'Registration Date'),
		array('key' => 'expiry_date', 'label' => 'Expiry Date'),
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

	protected function get_add_label()
	{
		return 'Register Products';
	}
}
