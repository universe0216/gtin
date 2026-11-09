<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/BasicController.php';

class Organizations extends BasicController {

	protected $entity = 'organizations';
	protected $entity_label = 'Organizations';
	protected $list_view = 'organizations/index';
	protected $list_scripts = array(
		'assets/js/organizations.js',
		'assets/js/organization_detail.js',
	);
	protected $add_url_route = 'organization_registration';
	protected $list_search_placeholder = 'Search name, registration number, GS1 prefix, parent, phone...';
	protected $list_empty_message = 'No organizations found.';
	protected $list_empty_search_message = 'No organizations match your search.';
	protected $list_columns = array(
		array('key' => 'name', 'label' => 'Name', 'pinned' => TRUE),
		array('key' => 'reg_number', 'label' => 'Registration Number'),
		array('key' => 'gs1_prefix', 'label' => 'GS1 Prefix'),
		array('key' => 'parent_org', 'label' => 'Parent Organization'),
		array('key' => 'phone', 'label' => 'Phone'),
		array('key' => 'currency', 'label' => 'Currency'),
		array('key' => 'init_date', 'label' => 'Initial Date'),
		array('key' => 'reg_date', 'label' => 'Registration Date'),
		array('key' => 'expiry_date', 'label' => 'Expiry Date'),
	);
	protected $detail_info_fields = array(
		array('key' => 'id', 'label' => 'ID'),
		array('key' => 'name', 'label' => 'Name'),
		array('key' => 'reg_number', 'label' => 'Registration Number'),
		array('key' => 'parent_org', 'label' => 'Parent Organization'),
		array('key' => 'gs1_prefix', 'label' => 'GS1 Prefix'),
		array('key' => 'init_date', 'label' => 'Initial Date'),
		array('key' => 'reg_date', 'label' => 'Registration Date'),
		array('key' => 'expiry_date', 'label' => 'Expiry Date'),
		array('key' => 'address', 'label' => 'Address'),
		array('key' => 'phone', 'label' => 'Phone'),
		array('key' => 'c_code', 'label' => 'Country Code'),
		array('key' => 'currency', 'label' => 'Currency'),
		array('key' => 'description', 'label' => 'Description'),
		array('key' => 'created_at', 'label' => 'Created At'),
		array('key' => 'updated_at', 'label' => 'Updated At'),
		array('key' => 'location_id', 'label' => 'Location'),
	);
	protected $detail_product_columns = array(
		array('key' => 'name', 'label' => 'Name'),
		array('key' => 'brand', 'label' => 'Brand'),
		array('key' => 'standard_number', 'label' => 'Standard Number'),
		array('key' => 'barcode_1d', 'label' => 'Barcode'),
		array('key' => 'package_type', 'label' => 'Package Type'),
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
			'name'     => 'reg_number',
			'label'    => 'Registration Number',
			'type'     => 'number',
			'required' => FALSE,
			'rules'    => array('trim', 'integer'),
		),
		array(
			'name'     => 'parent_org',
			'label'    => 'Parent Organization',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'gs1_prefix',
			'label'    => 'GS1 Prefix',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[50]'),
		),
		array(
			'name'     => 'init_date',
			'label'    => 'Initial Date',
			'type'     => 'date',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
		array(
			'name'     => 'reg_date',
			'label'    => 'Registration Date',
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
			'name'     => 'phone',
			'label'    => 'Phone',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'c_code',
			'label'    => 'Country Code',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[50]'),
		),
		array(
			'name'     => 'currency',
			'label'    => 'Currency',
			'type'     => 'select',
			'required' => FALSE,
			'rules'    => array('trim', 'in_list[,1,2]'),
			'options'  => array(
				array('value' => '', 'label' => ''),
				array('value' => '1', 'label' => 'Foreign'),
				array('value' => '2', 'label' => 'Domestic'),
			),
		),
		array(
			'name'     => 'description',
			'label'    => 'Description',
			'type'     => 'textarea',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
		array(
			'name'     => 'location_id',
			'label'    => 'Location',
			'type'     => 'location',
			'required' => FALSE,
			'rules'    => array('trim', 'callback_valid_location'),
		),
	);

	protected $permission_view = 'organization.view';
	protected $permission_edit = 'organization.edit';

	public function __construct()
	{
		parent::__construct();
		$this->load->model('organization_model', 'model');
		$this->load->model('product_model');
		$this->load->model('location_model');

		foreach ($this->fields as &$field)
		{
			if (($field['name'] ?? '') === 'currency')
			{
				$field['options'] = array(
					array('value' => '', 'label' => ''),
					array('value' => '1', 'label' => app_line('currency_foreign')),
					array('value' => '2', 'label' => app_line('currency_domestic')),
				);
			}
		}
		unset($field);
	}

	public function valid_location($value)
	{
		if ($value === '' || $value === NULL || $value === FALSE)
		{
			return TRUE;
		}

		if ($this->location_model->get($value))
		{
			return TRUE;
		}

		$this->form_validation->set_message('valid_location', app_line('location_invalid'));

		return FALSE;
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
				'message' => app_line('org_not_found'),
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
				'message' => app_line('org_not_found'),
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
			'columns'  => $this->localize_labeled($this->detail_product_columns),
			'sort'     => $sort['key'] ?? '',
			'sort_dir' => $sort['direction'] ?? '',
		), $meta));
	}

	public function delete_preview($id = NULL)
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$this->auth->require_permission($this->permission_edit);
		$record = $this->model->get($id);

		if ( ! $record)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('org_not_found'),
			), 404);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'data'    => array(
				'id'            => (int) $record['id'],
				'name'          => $record['name'],
				'product_count' => $this->product_model->count_by_organization($record['id']),
			),
		));
	}

	public function delete($id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_invalid_method')), 405);
		}

		$this->auth->require_permission($this->permission_edit);
		$this->load->library('catalog_archieve');
		$result = $this->catalog_archieve->archive_organization($id);

		if (empty($result['ok']))
		{
			$status = ($result['code'] ?? '') === 'not_found' ? 404 : 500;

			return $this->json_response(array(
				'success' => FALSE,
				'message' => $status === 404 ? app_line('org_not_found') : app_line('org_delete_failed'),
			), $status);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('org_deleted'),
			'data'    => array(
				'product_count' => (int) ($result['product_count'] ?? 0),
			),
		));
	}

	protected function list_query_params()
	{
		$params = parent::list_query_params();
		$params['location_ids'] = $this->location_filter_ids();

		return $params;
	}

	protected function location_filter_ids()
	{
		return $this->location_model->filter_ids($this->input->get('location_id'));
	}

	protected function export_value($field, array $record)
	{
		if ($field === 'location_id')
		{
			return $this->location_model->path_name($record['location_id'] ?? 0);
		}

		if ($field === 'currency')
		{
			$currency = (string) ($record['currency'] ?? '');

			if ($currency === '1')
			{
				return app_line('currency_foreign');
			}

			if ($currency === '2')
			{
				return app_line('currency_domestic');
			}

			return '';
		}

		return parent::export_value($field, $record);
	}

	protected function get_add_label()
	{
		return app_line('add_organizations');
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
			'infoFields'      => $this->localize_labeled($this->detail_info_fields),
			'editableFields'  => $this->localize_labeled($this->fields),
			'locationNodes'   => $this->location_nodes(),
			'readOnlyFields'  => $this->localize_labeled(array(
				array('key' => 'id', 'label' => 'ID'),
				array('key' => 'created_at', 'label' => 'Created At'),
				array('key' => 'updated_at', 'label' => 'Updated At'),
			)),
			'productColumns'    => $this->localize_labeled($this->detail_product_columns),
			'productDeleteUrl'  => site_url('products/delete'),
			'canDeleteProduct'  => $this->auth->can('product.edit'),
			'deleteUrl'         => site_url('organizations/delete'),
			'deletePreviewUrl'  => site_url('organizations/delete_preview'),
			'canDelete'         => $this->auth->can($this->permission_edit),
			'rowAction'         => array(
				'type'    => 'delete',
				'url'     => site_url('organizations/delete'),
				'enabled' => $this->auth->can($this->permission_edit),
			),
			'perPage'           => $this->list_per_page,
			'perPageOptions'    => $this->list_per_page_options,
		);
	}

	protected function location_nodes()
	{
		return $this->location_model->tree_nodes();
	}
}
