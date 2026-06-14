<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class History extends AuthenticatedController {

	protected $history_per_page = 10;

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
			'Products History',
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
			'title'         => 'Organizations History',
			'nav_active'    => 'history_organizations',
			'registrations' => $registrations,
			'total'         => count($registrations),
		);

		$data['content'] = $this->load->view('history/organizations', $data, TRUE);
		$this->load->view('layouts/main', $data);
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
				'message' => 'Registration not found.',
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
				'message' => 'Registration not found.',
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
