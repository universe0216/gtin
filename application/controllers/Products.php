<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/BasicController.php';

class Products extends BasicController {

	protected $entity = 'products';
	protected $entity_label = 'Products';
	protected $add_url_route = 'product_registration';
	protected $list_search_placeholder = 'Search name, brand, barcode, standard number, organization...';
	protected $list_empty_message = 'No products found.';
	protected $list_empty_search_message = 'No products match your search.';
	protected $list_columns = array(
		array('key' => 'name', 'label' => 'Name'),
		array('key' => 'brand', 'label' => 'Brand'),
		array('key' => 'standard_number', 'label' => 'Standard Number'),
		array('key' => 'barcode_1d', 'label' => 'Barcode'),
		array('key' => 'package_type', 'label' => 'Package Type'),
		array('key' => 'organization_name', 'label' => 'Organization'),
		array('key' => 'price', 'label' => 'Price'),
		array('key' => 'reg_date', 'label' => 'Registration Date'),
		array('key' => 'test_status', 'label' => 'Test Status'),
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
			'name'     => 'brand',
			'label'    => 'Brand',
			'type'     => 'text',
			'required' => FALSE,
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
			'name'     => 'package_type',
			'label'    => 'Package Type',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[100]'),
		),
		array(
			'name'     => 'org_id',
			'label'    => 'Organization',
			'type'     => 'number',
			'required' => FALSE,
			'rules'    => array('trim', 'integer'),
		),
		array(
			'name'     => 'effect',
			'label'    => 'Effect',
			'type'     => 'textarea',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
		array(
			'name'     => 'usage',
			'label'    => 'Usage',
			'type'     => 'textarea',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
		array(
			'name'     => 'price',
			'label'    => 'Price',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'effective_period',
			'label'    => 'Effective Period',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'test_date',
			'label'    => 'Test Date',
			'type'     => 'date',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
		array(
			'name'     => 'test_status',
			'label'    => 'Test Status',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'description',
			'label'    => 'Description',
			'type'     => 'textarea',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
		array(
			'name'     => 'barcode_1d',
			'label'    => 'Barcode',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'barcode_1d_type',
			'label'    => 'Barcode Type',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[50]'),
		),
		array(
			'name'     => 'barcode_2d',
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
			'name'     => 'reg_date',
			'label'    => 'Registration Date',
			'type'     => 'date',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
		array(
			'name'     => 'processor_name',
			'label'    => 'Processor',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'global_id',
			'label'    => 'Global ID',
			'type'     => 'number',
			'required' => FALSE,
			'rules'    => array('trim', 'integer'),
		),
		array(
			'name'     => 'c_code',
			'label'    => 'Country Code',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[255]'),
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
		return app_line('add_products');
	}
}
