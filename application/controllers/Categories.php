<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/BasicController.php';

class Categories extends BasicController {

	protected $entity = 'categories';
	protected $entity_label = 'Categories';
	protected $fields = array(
		array(
			'name'     => 'name',
			'label'    => 'Name',
			'type'     => 'text',
			'required' => TRUE,
			'rules'    => array('trim', 'max_length[255]'),
		),
	);

	protected $permission_view = 'categories';
	protected $permission_edit = 'categories';

	public function __construct()
	{
		parent::__construct();
		$this->auth->require_admin();
		$this->load->model('category_model', 'model');
		$this->load->model('standard_category_model');
		$this->load->model('product_category_model');
		$this->load->model('product_model');
	}

	public function index()
	{
		$this->auth->require_permission($this->permission_view);

		$this->render('categories/index', array(
			'title'             => $this->localized_title(),
			'nav_active'        => $this->entity,
			'can_edit'          => $this->auth->can($this->permission_edit),
			'records'           => $this->model->list_for_tree(),
			'standard_numbers'  => $this->distinct_standard_numbers(),
			'page_scripts'      => array('assets/js/categories.js'),
		));
	}

	public function detail($id = NULL)
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$this->auth->require_permission($this->permission_view);
		$row = $this->model->get($id);

		if ( ! $row)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_record_not_found')), 404);
		}

		$data = $this->present($row);
		$data['standard_numbers'] = $this->standard_category_model->list_by_category($id);

		return $this->json_response(array(
			'success' => TRUE,
			'data'    => $data,
		));
	}

	public function create()
	{
		show_404();
	}

	public function update($id = NULL)
	{
		$this->auth->require_permission($this->permission_edit);

		if ( ! $this->model->get($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_record_not_found')), 404);
		}

		if ( ! $this->validate_fields())
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => validation_errors(' ', ' '),
			), 422);
		}

		$standard_numbers = $this->collect_standard_numbers();

		$this->db->trans_begin();

		if ( ! $this->model->update($id, $this->collect_field_values()))
		{
			$this->db->trans_rollback();

			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_failed_update')), 500);
		}

		$sync = $this->standard_category_model->sync_for_category($id, $standard_numbers);
		$this->sync_products_for_standards($id, $sync['added'], $sync['removed'], $sync['moved'] ?? array());

		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();

			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_failed_update')), 500);
		}

		$this->db->trans_commit();

		$data = $this->present($this->model->get($id));
		$data['standard_numbers'] = $this->standard_category_model->list_by_category($id);

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('common_record_updated'),
			'data'    => $data,
		));
	}

	public function delete($id = NULL)
	{
		show_404();
	}

	protected function present($row)
	{
		if ( ! $row)
		{
			return NULL;
		}

		return $this->model->present_row($row);
	}

	protected function collect_standard_numbers()
	{
		$raw = $this->input->post('standard_numbers');

		if (is_string($raw))
		{
			$decoded = json_decode($raw, TRUE);

			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
			{
				$raw = $decoded;
			}
			else
			{
				$raw = preg_split('/[\r\n,]+/', $raw);
			}
		}

		if ( ! is_array($raw))
		{
			return array();
		}

		$out = array();

		foreach ($raw as $value)
		{
			$value = trim((string) $value);

			if ($value === '')
			{
				continue;
			}

			$out[$value] = TRUE;
		}

		return array_keys($out);
	}

	protected function sync_products_for_standards($category_id, array $added, array $removed, array $moved = array())
	{
		if ( ! empty($moved))
		{
			foreach ($moved as $standard_number => $previous_category_id)
			{
				$product_ids = $this->product_ids_by_standard_numbers(array($standard_number));
				$this->product_category_model->remove_category_for_products(
					$product_ids,
					(int) $previous_category_id
				);
			}
		}

		if ( ! empty($added))
		{
			$product_ids = $this->product_ids_by_standard_numbers($added);
			$this->product_category_model->assign_category_to_products($product_ids, $category_id);
		}

		if ( ! empty($removed))
		{
			$product_ids = $this->product_ids_by_standard_numbers($removed);
			$this->product_category_model->remove_category_for_products($product_ids, $category_id);
		}
	}

	protected function product_ids_by_standard_numbers(array $standard_numbers)
	{
		if (empty($standard_numbers))
		{
			return array();
		}

		$rows = $this->db
			->select('id')
			->where_in('standard_number', $standard_numbers)
			->get('products')
			->result_array();

		return array_map(function ($row) {
			return (int) $row['id'];
		}, $rows);
	}

	protected function distinct_standard_numbers()
	{
		$rows = $this->db
			->select('standard_number')
			->where('standard_number IS NOT NULL', NULL, FALSE)
			->where('standard_number !=', '')
			->group_by('standard_number')
			->order_by('standard_number', 'ASC')
			->limit(5000)
			->get('products')
			->result_array();

		return array_map(function ($row) {
			return (string) $row['standard_number'];
		}, $rows);
	}
}
