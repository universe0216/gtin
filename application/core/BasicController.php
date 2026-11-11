<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

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
	protected $list_scripts = array();
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
			'title'      => $this->localized_title(),
			'records'    => $records,
			'fields'     => $this->localize_labeled($this->fields),
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
				'message' => app_line('common_list_unconfigured'),
			), 404);
		}

		return $this->json_list_response();
	}

	public function get($id = NULL)
	{
		$this->auth->require_permission($this->permission_view);

		if ( ! $this->model)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_model_missing')), 500);
		}

		$record = $this->model->get($id);

		if ( ! $record)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_record_not_found')), 404);
		}

		return $this->json_response(array('success' => TRUE, 'data' => $record));
	}

	public function create()
	{
		$this->auth->require_permission($this->permission_edit);

		if ( ! $this->model)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_model_missing')), 500);
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
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_failed_create')), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('common_record_created'),
			'data'    => $this->model->get($id),
		));
	}

	public function update($id = NULL)
	{
		$this->auth->require_permission($this->permission_edit);

		if ( ! $this->model)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_model_missing')), 500);
		}

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

		$data = $this->collect_field_values();

		if ( ! $this->model->update($id, $data))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_failed_update')), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('common_record_updated'),
			'data'    => $this->model->get($id),
		));
	}

	public function delete($id = NULL)
	{
		$this->auth->require_permission($this->permission_edit);

		if ( ! $this->model)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_model_missing')), 500);
		}

		if ( ! $this->model->get($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_record_not_found')), 404);
		}

		if ( ! $this->model->delete($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_failed_delete')), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('common_record_deleted'),
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
			'title'                     => $this->localized_title(),
			'nav_active'                => $this->entity,
			'entity'                    => $this->entity,
			'can_edit'                  => $this->auth->can($this->permission_edit),
			'add_url'                   => $this->get_add_url(),
			'add_label'                 => $this->get_add_label(),
			'list_columns'              => $this->localize_labeled($this->list_columns),
			'list_api_url'              => site_url($this->entity.'/list'),
			'list_search_placeholder'   => $this->localized_line('search_'.$this->entity, $this->list_search_placeholder),
			'list_empty_message'        => $this->localized_line('empty_'.$this->entity, $this->list_empty_message),
			'list_empty_search_message' => $this->localized_line('empty_search_'.$this->entity, $this->list_empty_search_message),
			'list_per_page'             => $this->list_per_page,
			'list_per_page_options'     => $this->list_per_page_options,
			'detail_modal_partial'      => $this->get_detail_modal_partial(),
			'detail_config'             => $this->get_detail_config(),
			'page_scripts'              => $this->list_scripts,
		);

		$this->render($this->list_view, array_merge($data, $this->list_view_data()));
	}

	protected function list_view_data()
	{
		return array();
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
				'message' => app_line('common_model_missing'),
			), 500);
		}

		$params = $this->list_query_params();
		$filters = $this->list_filters_from_params($params);

		$total = $this->model->count_filtered($params['search'], $filters);
		$offset = ($params['page'] - 1) * $params['per_page'];
		$records = $this->model->get_paginated($params['per_page'], $offset, $params['search'], $params['sort'], $filters);
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
			'columns'  => $this->localize_labeled($this->list_columns),
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
			'page'           => $page,
			'per_page'       => $per_page,
			'search'         => trim((string) $this->input->get('q')),
			'sort'           => $this->resolve_list_sort(),
			'column_filters' => $this->column_filter_params(),
		);
	}

	protected function list_filters_from_params(array $params)
	{
		$filters = array();

		if ( ! empty($params['location_ids']) && is_array($params['location_ids']))
		{
			$filters['location_ids'] = $params['location_ids'];
		}

		if ( ! empty($params['column_filters']) && is_array($params['column_filters']))
		{
			$filters['columns'] = $params['column_filters'];
		}

		return $filters;
	}

	protected function column_filter_params()
	{
		if ( ! $this->model || ! method_exists($this->model, 'get_filterable_columns'))
		{
			return array();
		}

		$map = $this->model->get_filterable_columns();
		$raw = $this->input->get('filter');
		$filters = array();

		if ( ! is_array($raw) || empty($map))
		{
			return $filters;
		}

		foreach ($raw as $key => $value)
		{
			$key = trim((string) $key);
			$value = trim((string) $value);

			if ($key === '' || $value === '' || ! isset($map[$key]))
			{
				continue;
			}

			if (mb_strlen($value) > 255)
			{
				$value = mb_substr($value, 0, 255);
			}

			$filters[$key] = $value;
		}

		return $filters;
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
		return $this->localized_line('add_'.$this->entity, app_line('common_add_new'));
	}

	protected function localized_title()
	{
		return $this->localized_line('title_'.$this->entity, $this->entity_label);
	}

	protected function localized_line($key, $fallback)
	{
		$line = $this->lang->line($key);

		return $line ? $line : $fallback;
	}

	protected function localize_labeled($items)
	{
		$copy = array();

		foreach ($items as $item)
		{
			$id = '';

			if (isset($item['key']))
			{
				$id = $item['key'];
			}
			elseif (isset($item['name']))
			{
				$id = $item['name'];
			}

			if ($id !== '')
			{
				$line = $this->lang->line('col_'.$id);

				if ($line)
				{
					$item['label'] = $line;
				}
			}

			$copy[] = $item;
		}

		return $copy;
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

			$label = $this->lang->line('col_'.$field['name']);

			$this->form_validation->set_rules(
				$field['name'],
				$label ? $label : $field['label'],
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
			$value = $this->input->post($field['name']);
			$type = isset($field['type']) ? $field['type'] : 'text';

			if (($type === 'number' || $type === 'date' || $type === 'select' || $type === 'location') && ($value === '' || $value === FALSE))
			{
				$value = NULL;
			}

			$data[$field['name']] = $value;
		}

		return $data;
	}

	public function export()
	{
		$this->auth->require_permission($this->permission_view);

		if ( ! $this->model)
		{
			show_error(app_line('common_model_missing'), 500);
		}

		if ( ! class_exists(Spreadsheet::class))
		{
			show_error(app_line('books_spreadsheet_missing'), 500);
		}

		$params = $this->list_query_params();
		$filters = $this->list_filters_from_params($params);

		$records = $this->model->get_filtered($params['search'], $params['sort'], $filters);
		$columns = $this->export_columns();

		$this->load->library('spreadsheet_value_binder');
		Cell::setValueBinder(new Spreadsheet_value_binder());

		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle($this->export_sheet_title());

		foreach ($columns as $index => $column)
		{
			$sheet->setCellValueExplicitByColumnAndRow($index + 1, 1, $column['label'], DataType::TYPE_STRING);
		}

		foreach ($records as $row_index => $record)
		{
			foreach ($columns as $column_index => $column)
			{
				$value = $this->export_value($column['key'], $record);
				$sheet->setCellValueExplicitByColumnAndRow(
					$column_index + 1,
					$row_index + 2,
					$value,
					DataType::TYPE_STRING
				);
			}
		}

		if ($columns)
		{
			$sheet->getStyleByColumnAndRow(1, 1, count($columns), 1)->getFont()->setBold(TRUE);
		}

		$filename = $this->export_filename();

		header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header('Cache-Control: max-age=0');

		$writer = new Xls($spreadsheet);
		$writer->setPreCalculateFormulas(FALSE);
		$writer->save('php://output');
		$spreadsheet->disconnectWorksheets();
		exit;
	}

	protected function export_value($field, array $record)
	{
		return isset($record[$field]) ? (string) $record[$field] : '';
	}

	protected function export_columns()
	{
		$columns = array();

		foreach ($this->model->list_fields() as $field)
		{
			$columns[] = array(
				'key'   => $field,
				'label' => $this->localized_line('col_'.$field, $field),
			);
		}

		return $columns;
	}

	protected function export_sheet_title()
	{
		$title = $this->localized_title();
		$title = str_replace(array('\\', '/', '?', '*', '[', ']', ':'), ' ', $title);

		return substr($title, 0, 31);
	}

	protected function export_filename()
	{
		return $this->entity.'-'.date('Y-m-d').'.xls';
	}
}
