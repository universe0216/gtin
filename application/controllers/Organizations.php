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
	protected $detail_info_fields = array(
		array('key' => 'id', 'label' => 'ID'),
		array('key' => 'name', 'label' => 'Name'),
		array('key' => 'registration_number', 'label' => 'Registration Number'),
		array('key' => 'gs1_prefix', 'label' => 'GS1 Prefix'),
		array('key' => 'procedure_number', 'label' => 'Procedure Number'),
		array('key' => 'registration_date', 'label' => 'Registration Date'),
		array('key' => 'reregistration_date', 'label' => 'Re-registration Date'),
		array('key' => 'expiry_date', 'label' => 'Expiry Date'),
		array('key' => 'address', 'label' => 'Address'),
		array('key' => 'description', 'label' => 'Description'),
		array('key' => 'created_at', 'label' => 'Created At'),
		array('key' => 'updated_at', 'label' => 'Updated At'),
	);
	protected $detail_product_columns = array(
		array('key' => 'name', 'label' => 'Name'),
		array('key' => 'standard_number', 'label' => 'Standard Number'),
		array('key' => 'barcode', 'label' => 'Barcode'),
		array('key' => 'barcode_type', 'label' => 'Barcode Type'),
		array('key' => 'package_type', 'label' => 'Package Type'),
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
		$this->load->model('product_model');
	}

	public function detail($id = NULL)
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$this->auth->require_permission($this->permission_view);

		$record = $this->model->get($id);

		if ( ! $record)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => 'Organization not found.',
			), 404);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'data'    => $record,
			'fields'  => $this->detail_info_fields,
		));
	}

	public function products($id = NULL)
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$this->auth->require_permission($this->permission_view);

		$record = $this->model->get($id);

		if ( ! $record)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => 'Organization not found.',
			), 404);
		}

		$params = $this->list_query_params();
		$sort = $this->resolve_product_sort_for_columns($this->detail_product_columns);
		$total = $this->product_model->count_by_organization($id, $params['search']);
		$offset = ($params['page'] - 1) * $params['per_page'];
		$records = $this->product_model->get_paginated_by_organization(
			$id,
			$params['per_page'],
			$offset,
			$params['search'],
			$sort
		);
		$meta = $this->build_paginated_meta(
			$total,
			$params['page'],
			$params['per_page'],
			count($records),
			$params['search']
		);

		return $this->json_response(array_merge(array(
			'success'  => TRUE,
			'data'     => $records,
			'columns'  => $this->detail_product_columns,
			'sort'     => $sort['key'] ?? '',
			'sort_dir' => $sort['direction'] ?? '',
		), $meta));
	}

	protected function get_add_label()
	{
		return 'Register Organizations';
	}

	protected function get_detail_modal_partial()
	{
		return 'organizations/detail_modal';
	}

	protected function get_detail_config()
	{
		return array(
			'enabled'         => TRUE,
			'detailUrl'       => site_url('organizations/detail'),
			'productsUrl'     => site_url('organizations/products'),
			'updateUrl'       => site_url('organizations/update'),
			'canEdit'         => $this->auth->can($this->permission_edit),
			'infoFields'      => $this->detail_info_fields,
			'editableFields'  => $this->fields,
			'readOnlyFields'  => array(
				array('key' => 'id', 'label' => 'ID'),
				array('key' => 'created_at', 'label' => 'Created At'),
				array('key' => 'updated_at', 'label' => 'Updated At'),
			),
			'productColumns'    => $this->detail_product_columns,
			'productDeleteUrl'  => site_url('products/delete'),
			'canDeleteProduct'  => $this->auth->can('product.edit'),
			'perPage'           => $this->list_per_page,
			'perPageOptions'    => $this->list_per_page_options,
		);
	}
}
