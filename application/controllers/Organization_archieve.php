<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'controllers/Organizations.php';

class Organization_archieve extends Organizations {

	protected $entity = 'organization_archieve';
	protected $entity_label = 'Archived Organizations';
	protected $add_url_route = '';
	protected $list_empty_message = 'No archived organizations found.';
	protected $list_empty_search_message = 'No archived organizations match your search.';

	public function __construct()
	{
		parent::__construct();
		require_once APPPATH.'models/Organization_archieve_model.php';
		require_once APPPATH.'models/Product_archieve_model.php';
		$this->model = new Organization_archieve_model();
		$this->product_model = new Product_archieve_model();
		$this->load->library('catalog_archieve');
		$this->list_columns[] = array('key' => 'deleted_at', 'label' => 'Deleted At');
	}

	public function delete($id = NULL)
	{
		show_404();
	}

	public function delete_preview($id = NULL)
	{
		show_404();
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
		$organization_id = (int) $record['origin_id'];
		$total = $this->product_model->count_by_organization($organization_id, $params['search']);
		$offset = ($params['page'] - 1) * $params['per_page'];
		$records = $this->product_model->get_paginated_by_organization(
			$organization_id,
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

	public function restore($id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_invalid_method')), 405);
		}

		$this->auth->require_permission($this->permission_edit);
		$result = $this->catalog_archieve->restore_organization($id);

		if ( ! empty($result['ok']))
		{
			return $this->json_response(array(
				'success' => TRUE,
				'message' => app_line('org_restored'),
			));
		}

		$code = $result['code'] ?? 'failed';

		if ($code === 'not_found')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('org_not_found'),
			), 404);
		}

		if ($code === 'id_taken')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('org_restore_id_taken'),
			), 422);
		}

		return $this->json_response(array(
			'success' => FALSE,
			'message' => app_line('org_restore_failed'),
		), 500);
	}

	protected function get_detail_config()
	{
		$info_fields = $this->detail_info_fields;
		$info_fields[] = array('key' => 'origin_id', 'label' => 'Original ID');
		$info_fields[] = array('key' => 'deleted_at', 'label' => 'Deleted At');

		return array(
			'enabled'            => TRUE,
			'detailUrl'          => site_url('organization_archieve/detail'),
			'productsUrl'        => site_url('organization_archieve/products'),
			'canEdit'            => FALSE,
			'infoFields'         => $this->localize_labeled($info_fields),
			'editableFields'     => array(),
			'readOnlyFields'     => $this->localize_labeled($info_fields),
			'productColumns'     => $this->localize_labeled($this->detail_product_columns),
			'canDeleteProduct'   => FALSE,
			'canRestoreProduct'  => $this->auth->can('product.edit'),
			'productRestoreUrl'  => site_url('products_archieve/restore'),
			'productRestorePreviewUrl' => site_url('products_archieve/restore_preview'),
			'canRestore'         => $this->auth->can($this->permission_edit),
			'restoreUrl'         => site_url('organization_archieve/restore'),
			'rowAction'          => array(
				'type'    => 'restore',
				'url'     => site_url('organization_archieve/restore'),
				'enabled' => $this->auth->can($this->permission_edit),
			),
			'perPage'            => $this->list_per_page,
			'perPageOptions'     => $this->list_per_page_options,
		);
	}
}
