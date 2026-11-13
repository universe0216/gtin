<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class History extends AuthenticatedController {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
		$this->load->model('product_registration_model');
		$this->load->model('product_registration_item_model');
		$this->load->model('organization_registration_model');
		$this->load->model('organization_registration_item_model');
		$this->load->library('product_registration_processor');
		$this->load->library('organization_registration_processor');
	}

	public function products($id = NULL)
	{
		if ($id !== NULL)
		{
			if ($this->input->is_ajax_request())
			{
				return $this->json_product_registration_items($id);
			}

			show_404();
		}

		return $this->render_product_registration_history(
			app_line('title_products_history'),
			'history_products',
			'history/products'
		);
	}

	public function procedure($id = NULL)
	{
		if ($id !== NULL)
		{
			if ($this->input->is_ajax_request())
			{
				return $this->json_product_registration_items($id);
			}

			show_404();
		}

		return $this->render_product_registration_history(
			'Procedure History',
			'history_procedure',
			'history/procedure'
		);
	}

	public function organizations($id = NULL)
	{
		if ($id !== NULL)
		{
			if ($this->input->is_ajax_request())
			{
				return $this->json_organization_items($id);
			}

			show_404();
		}

		$registrations = $this->organization_registration_model->get_all();

		foreach ($registrations as $index => $registration)
		{
			$registrations[$index]['organization_name'] = $this->organization_name_from_file($registration['file_name']);
		}

		$data = array(
			'title'         => app_line('title_organizations_history'),
			'nav_active'    => 'history_organizations',
			'registrations' => $registrations,
			'total'         => count($registrations),
		);

		$data['content'] = $this->load->view('history/organizations', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	public function activity_log()
	{
		$this->auth->require_admin();

		$data = array(
			'title'        => app_line('title_activity_log'),
			'nav_active'   => 'history_activity_log',
			'page_scripts' => array('assets/js/history_logs.js'),
		);

		$data['content'] = $this->load->view('history/activity_log', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	public function activity_log_list()
	{
		$this->auth->require_admin();

		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$this->load->model('activity_log_model');
		$page = max(1, (int) $this->input->get('page'));
		$per_page = (int) $this->input->get('per_page');
		$per_page = in_array($per_page, array(10, 20, 50, 100), TRUE) ? $per_page : 20;
		$search = trim((string) $this->input->get('q'));
		$action = trim((string) $this->input->get('action'));
		$total = $this->activity_log_model->count_filtered($search, $action);
		$offset = ($page - 1) * $per_page;
		$rows = $this->activity_log_model->get_paginated($per_page, $offset, $search, $action);

		foreach ($rows as &$row)
		{
			$row['account_label'] = $this->account_label($row);
			$row['has_request_data'] = ! empty($row['request_data']) ? 1 : 0;
		}
		unset($row);

		return $this->json_response(array(
			'success' => TRUE,
			'data'    => $rows,
			'total'   => $total,
			'page'    => $page,
			'per_page'=> $per_page,
		));
	}

	public function activity_log_detail($id = NULL)
	{
		$this->auth->require_admin();

		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$this->load->model('activity_log_model');
		$row = $this->activity_log_model->get($id);

		if ( ! $row)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('common_record_not_found'),
			), 404);
		}

		$row['account_label'] = $this->account_label($row);
		$row['request_data_pretty'] = $this->pretty_json($row['request_data'] ?? NULL);

		return $this->json_response(array(
			'success' => TRUE,
			'data'    => $row,
		));
	}

	public function data_log()
	{
		$this->auth->require_admin();

		$data = array(
			'title'        => app_line('title_data_log'),
			'nav_active'   => 'history_data_log',
			'page_scripts' => array('assets/js/history_logs.js'),
		);

		$data['content'] = $this->load->view('history/data_log', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	public function data_log_list()
	{
		$this->auth->require_admin();

		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$this->load->model('data_log_model');
		$page = max(1, (int) $this->input->get('page'));
		$per_page = (int) $this->input->get('per_page');
		$per_page = in_array($per_page, array(10, 20, 50, 100), TRUE) ? $per_page : 20;
		$search = trim((string) $this->input->get('q'));
		$entity = trim((string) $this->input->get('entity'));
		$action = trim((string) $this->input->get('action'));
		$total = $this->data_log_model->count_filtered($search, $entity, $action);
		$offset = ($page - 1) * $per_page;
		$rows = $this->data_log_model->get_paginated($per_page, $offset, $search, $entity, $action);

		foreach ($rows as &$row)
		{
			$row['account_label'] = $this->account_label($row);
			$row['has_payload'] = ( ! empty($row['original_data']) || ! empty($row['new_data'])) ? 1 : 0;
		}
		unset($row);

		return $this->json_response(array(
			'success' => TRUE,
			'data'    => $rows,
			'total'   => $total,
			'page'    => $page,
			'per_page'=> $per_page,
		));
	}

	public function data_log_detail($id = NULL)
	{
		$this->auth->require_admin();

		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$this->load->model('data_log_model');
		$row = $this->data_log_model->get($id);

		if ( ! $row)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('common_record_not_found'),
			), 404);
		}

		$row['account_label'] = $this->account_label($row);
		$row['original_data_pretty'] = $this->pretty_json($row['original_data'] ?? NULL);
		$row['new_data_pretty'] = $this->pretty_json($row['new_data'] ?? NULL);

		return $this->json_response(array(
			'success' => TRUE,
			'data'    => $row,
		));
	}

	protected function account_label(array $row)
	{
		$name = trim((string) ($row['account_name'] ?? ''));
		$username = trim((string) ($row['username'] ?? ''));

		if ($name !== '' && $username !== '')
		{
			return $name.' ('.$username.')';
		}

		if ($name !== '')
		{
			return $name;
		}

		if ($username !== '')
		{
			return $username;
		}

		return ! empty($row['account_id']) ? '#'.$row['account_id'] : '—';
	}

	protected function pretty_json($value)
	{
		if ($value === NULL || $value === '')
		{
			return '';
		}

		if (is_array($value))
		{
			return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		$decoded = json_decode((string) $value, TRUE);

		if (json_last_error() === JSON_ERROR_NONE)
		{
			return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		return (string) $value;
	}

	protected function render_product_registration_history($title, $nav_active, $route)
	{
		$registrations = $this->product_registration_model->get_by_status('completed');

		$data = array(
			'title'         => $title,
			'nav_active'    => $nav_active,
			'registrations' => $registrations,
			'total'         => count($registrations),
			'history_route' => $route,
		);

		$data['content'] = $this->load->view('history/products', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	protected function build_history_tab($id)
	{
		$registration = $this->product_registration_model->get($id);

		if ( ! $registration || $registration['status'] !== 'completed')
		{
			return NULL;
		}

		$items = $this->product_registration_item_model->get_by_product_registration($id);
		$tab = $this->product_registration_processor->format_product_registration_tab($registration, $items);

		foreach ($tab['rows'] as $index => $row)
		{
			$item = $items[$index] ?? array();
			$tab['rows'][$index]['status'] = $item['status'] ?? '';
			$tab['rows'][$index]['message'] = $item['message'] ?? '';
			$tab['rows'][$index]['barcode'] = $item['barcode'] ?? '';
		}

		return $tab;
	}

	protected function build_organization_history_tab($id)
	{
		$registration = $this->organization_registration_model->get($id);

		if ( ! $registration)
		{
			return NULL;
		}

		$items = $this->organization_registration_item_model->get_by_organization_registration($id);

		return $this->organization_registration_processor->format_registration_tab($registration, $items);
	}

	protected function organization_name_from_file($file_name)
	{
		$basename = pathinfo($file_name, PATHINFO_FILENAME);

		if (preg_match('/^\d+_(.+)$/u', $basename, $matches))
		{
			return trim($matches[1]);
		}

		return '';
	}

	protected function json_product_registration_items($id)
	{
		$tab = $this->build_history_tab($id);

		if ($tab === NULL)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_not_found'),
			), 404);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'tab'     => $tab,
		));
	}

	protected function json_organization_items($id)
	{
		$tab = $this->build_organization_history_tab($id);

		if ($tab === NULL)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_not_found'),
			), 404);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'tab'     => $tab,
		));
	}

	protected function json_response($payload, $status = 200)
	{
		return $this->output
			->set_content_type('application/json')
			->set_status_header($status)
			->set_output(json_encode($payload));
	}
}
