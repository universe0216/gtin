<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'controllers/Products.php';

class Products_archieve extends Products {

	protected $entity = 'products_archieve';
	protected $entity_label = 'Archived Products';
	protected $add_url_route = '';
	protected $list_empty_message = 'No archived products found.';
	protected $list_empty_search_message = 'No archived products match your search.';

	public function __construct()
	{
		parent::__construct();
		require_once APPPATH.'models/Product_archieve_model.php';
		$this->model = new Product_archieve_model();
		$this->load->library('catalog_archieve');
		$this->list_columns[] = array('key' => 'deleted_at', 'label' => 'Deleted At');
	}

	public function update($id = NULL)
	{
		show_404();
	}

	public function delete($id = NULL)
	{
		show_404();
	}

	public function delete_selected()
	{
		show_404();
	}

	public function batch_delete_preview()
	{
		show_404();
	}

	public function batch_delete()
	{
		show_404();
	}

	public function download($id = NULL)
	{
		show_404();
	}

	public function restore_preview($id = NULL)
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$this->auth->require_permission($this->permission_edit);
		$result = $this->catalog_archieve->describe_product_restore($id);

		if (empty($result['ok']))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('product_not_found'),
			), 404);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'data'    => array(
				'name'                => $result['name'],
				'needs_organization'  => ! empty($result['needs_organization']),
				'organization_name'   => $result['organization_name'],
			),
		));
	}

	public function restore($id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_invalid_method')), 405);
		}

		$this->auth->require_permission($this->permission_edit);
		$restore_organization = (bool) $this->input->post('restore_organization');
		$result = $this->catalog_archieve->restore_product($id, $restore_organization);

		if ( ! empty($result['ok']))
		{
			return $this->json_response(array(
				'success' => TRUE,
				'message' => app_line('product_restored'),
				'data'    => array(
					'restored_organization' => ! empty($result['restored_organization']),
				),
			));
		}

		return $this->archieve_restore_error($result);
	}

	protected function get_detail_config()
	{
		$fields = $this->detail_fields;
		$fields[] = array('name' => 'origin_id', 'label' => 'Original ID', 'type' => 'text', 'readonly' => TRUE);
		$fields[] = array('name' => 'deleted_at', 'label' => 'Deleted At', 'type' => 'text', 'readonly' => TRUE);

		return array(
			'enabled'    => TRUE,
			'detailUrl'  => site_url('products_archieve/detail'),
			'restoreUrl' => site_url('products_archieve/restore'),
			'restorePreviewUrl' => site_url('products_archieve/restore_preview'),
			'canRestore' => $this->auth->can($this->permission_edit),
			'fields'     => $this->localize_labeled($fields),
			'rowAction'  => array(
				'type'    => 'restore',
				'url'     => site_url('products_archieve/restore'),
				'previewUrl' => site_url('products_archieve/restore_preview'),
				'enabled' => $this->auth->can($this->permission_edit),
			),
		);
	}

	protected function archieve_restore_error($result)
	{
		$code = $result['code'] ?? 'failed';

		if ($code === 'not_found')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('product_not_found'),
			), 404);
		}

		if ($code === 'needs_organization')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'code'    => 'organization_archived',
				'message' => str_replace('{name}', (string) ($result['organization_name'] ?? ''), app_line('product_restore_with_org')),
			), 409);
		}

		if ($code === 'id_taken')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('product_restore_id_taken'),
			), 422);
		}

		return $this->json_response(array(
			'success' => FALSE,
			'message' => app_line('product_restore_failed'),
		), 500);
	}
}
