<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class BasicController extends AuthenticatedController {

	public $model;
	protected $entity = '';
	protected $entity_label = '';
	protected $fields = array();
	protected $permission_view = '';
	protected $permission_edit = '';
	protected $list_per_page = 10;
	protected $list_per_page_options = array(5, 10, 20, 50);
	protected $list_view = 'entity_list/index';
	protected $list_columns = array();
	protected $list_search_placeholder = 'Search...';
	protected $list_empty_message = 'No records found.';
	protected $list_empty_search_message = 'No records match your search.';
	protected $add_url_route = '';

	public function __construct()
	{
		parent::__construct();
		$this->load->helper(array('url', 'form'));
		$this->load->library('form_validation');
	}

	public function index()
	{
		$this->auth->require_permission($this->permission_view);

		if ( ! empty($this->list_columns))
		{
			return $this->render_entity_list();
		}

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
		));
	}

	public function list()
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$this->auth->require_permission($this->permission_view);

		if (empty($this->list_columns))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => 'List API not configured for this entity.',
			), 404);
		}

		return $this->json_list_response();
	}

	public function get($id = NULL)
	{
		$this->auth->require_permission($this->permission_view);

		if ( ! $this->model)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Model not configured.'), 500);
		}

		$record = $this->model->get($id);

		if ( ! $record)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Record not found.'), 404);
		}

		return $this->json_response(array('success' => TRUE, 'data' => $record));
	}

	public function create()
	{
		$this->auth->require_permission($this->permission_edit);

		if ( ! $this->model)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Model not configured.'), 500);
		}

		if ( ! $this->validate_fields())
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => validation_errors(' ', ' '),
			), 422);
		}

		$data = $this->collect_field_values();
		$id = $this->model->insert($data);

		if ( ! $id)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Failed to create record.'), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => 'Record created successfully.',
			'data'    => $this->model->get($id),
		));
	}

	public function update($id = NULL)
	{
		$this->auth->require_permission($this->permission_edit);

		if ( ! $this->model)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Model not configured.'), 500);
		}

		if ( ! $this->model->get($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Record not found.'), 404);
		}

		if ( ! $this->validate_fields())
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => validation_errors(' ', ' '),
			), 422);
		}

		$data = $this->collect_field_values();

		if ( ! $this->model->update($id, $data))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Failed to update record.'), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => 'Record updated successfully.',
			'data'    => $this->model->get($id),
		));
	}

	public function delete($id = NULL)
	{
		$this->auth->require_permission($this->permission_edit);

		if ( ! $this->model)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Model not configured.'), 500);
		}

		if ( ! $this->model->get($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Record not found.'), 404);
		}

		if ( ! $this->model->delete($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Failed to delete record.'), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => 'Record deleted successfully.',
		));
	}

	protected function render($view, $data = array())
	{
		$data['content'] = $this->load->view($view, $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	protected function render_entity_list()
	{
		$data = array(
			'title'                     => $this->entity_label,
			'nav_active'                => $this->entity,
			'entity'                    => $this->entity,
			'can_edit'                  => $this->auth->can($this->permission_edit),
			'add_url'                   => $this->get_add_url(),
			'add_label'                 => $this->get_add_label(),
			'list_columns'              => $this->list_columns,
			'list_api_url'              => site_url($this->entity.'/list'),
			'list_search_placeholder'   => $this->list_search_placeholder,
			'list_empty_message'        => $this->list_empty_message,
			'list_empty_search_message' => $this->list_empty_search_message,
			'list_per_page'             => $this->list_per_page,
			'list_per_page_options'     => $this->list_per_page_options,
			'detail_modal_partial'      => $this->get_detail_modal_partial(),
			'detail_config'             => $this->get_detail_config(),
		);

		$this->render($this->list_view, $data);
	}

	protected function get_detail_modal_partial()
	{
		return NULL;
	}

	protected function get_detail_config()
	{
		return NULL;
	}

	protected function build_paginated_meta($total, $page, $per_page, $record_count, $search = '')
	{
		$total_pages = max(1, (int) ceil($total / $per_page));
		$page = min($page, $total_pages);
		$offset = ($page - 1) * $per_page;
		$range_start = $total > 0 ? $offset + 1 : 0;
		$range_end = min($offset + $record_count, $total);

		return array(
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
			'row_offset'  => $offset,
			'range_start' => $range_start,
			'range_end'   => $range_end,
			'search'      => $search,
		);
	}

	protected function json_list_response()
	{
		if ( ! $this->model)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => 'Model not configured.',
			), 500);
		}

		$params = $this->list_query_params();
		$total = $this->model->count_filtered($params['search']);
		$offset = ($params['page'] - 1) * $params['per_page'];
		$records = $this->model->get_paginated($params['per_page'], $offset, $params['search'], $params['sort']);
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
			'columns'  => $this->list_columns,
			'sort'     => $params['sort']['key'] ?? '',
			'sort_dir' => $params['sort']['direction'] ?? '',
		), $meta));
	}

	protected function list_query_params()
	{
		$per_page = (int) $this->input->get('per_page');
		$page = max(1, (int) $this->input->get('page'));

		if ( ! in_array($per_page, $this->list_per_page_options, TRUE))
		{
			$per_page = $this->list_per_page;
		}

		return array(
			'page'     => $page,
			'per_page' => $per_page,
			'search'   => trim((string) $this->input->get('q')),
			'sort'     => $this->resolve_list_sort(),
		);
	}

	protected function resolve_list_sort()
	{
		if ( ! $this->model)
		{
			return array();
		}

		return $this->model->resolve_sort(
			$this->input->get('sort'),
			$this->input->get('dir')
		);
	}

	protected function resolve_product_sort_for_columns($columns)
	{
		$keys = array();

		foreach ((array) $columns as $column)
		{
			$keys[] = $column['key'];
		}

		return $this->product_model->resolve_sort(
			$this->input->get('sort'),
			$this->input->get('dir'),
			$this->product_model->get_sortable_columns_for_keys($keys)
		);
	}

	protected function get_add_url()
	{
		if ( ! $this->auth->can($this->permission_edit) || $this->add_url_route === '')
		{
			return NULL;
		}

		return site_url($this->add_url_route);
	}

	protected function get_add_label()
	{
		return 'Add New';
	}

	protected function json_response($payload, $status = 200)
	{
		return $this->output
			->set_content_type('application/json')
			->set_status_header($status)
			->set_output(json_encode($payload));
	}

	protected function validate_fields()
	{
		$this->form_validation->set_data($this->input->post());

		foreach ($this->fields as $field)
		{
			$rules = array();

			if ( ! empty($field['required']))
			{
				$rules[] = 'required';
			}

			if ( ! empty($field['rules']))
			{
				$rules = array_merge($rules, (array) $field['rules']);
			}

			if (empty($rules))
			{
				continue;
			}

			$this->form_validation->set_rules(
				$field['name'],
				$field['label'],
				implode('|', $rules)
			);
		}

		return $this->form_validation->run();
	}

	protected function collect_field_values()
	{
		$data = array();

		foreach ($this->fields as $field)
		{
			$data[$field['name']] = $this->input->post($field['name']);
		}

		return $data;
	}
}
