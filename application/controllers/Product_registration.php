<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class Product_registration extends AuthenticatedController {

	protected $import_per_page = 15;

	public function __construct()
	{
		parent::__construct();
		$this->load->helper(array('url', 'file'));
		$this->load->model('product_registration_model');
		$this->load->model('product_registration_item_model');
		$this->load->library('product_registration_processor');
	}

	public function index()
	{
		$user = $this->auth->user();
		$registrations = $this->product_registration_model->get_incomplete_by_account($user['id']);
		$tabs = array();

		foreach ($registrations as $registration)
		{
			$items = $this->product_registration_item_model->get_by_product_registration($registration['id']);
			$tabs[] = $this->product_registration_processor->format_product_registration_tab($registration, $items);
		}

		$data = array(
			'title'      => 'Product Registration',
			'nav_active' => 'product_registration',
			'tabs'       => $tabs,
		);

		$data['content'] = $this->load->view('product_registration/index', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	public function upload()
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Invalid request method.'), 405);
		}

		if (empty($_FILES['zip_files']))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Please select at least one zip file.'), 422);
		}

		try
		{
			$user = $this->auth->user();
			$result = $this->product_registration_processor->process_uploads($_FILES['zip_files'], $user['id']);
		}
		catch (RuntimeException $exception)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => $exception->getMessage(),
			), 422);
		}
		catch (Exception $exception)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => 'Failed to process uploaded files.',
			), 500);
		}

		return $this->json_response(array(
			'success'       => TRUE,
			'message'       => count($result['registrations']).' registration file(s) uploaded successfully.',
			'registrations' => $result['registrations'],
			'tabs'          => $result['tabs'],
			'items'         => $result['items'],
		));
	}

	public function import_list()
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$per_page = $this->import_per_page;
		$page = max(1, (int) $this->input->get('page'));
		$search = trim((string) $this->input->get('q'));
		$total = $this->product_registration_model->count_by_status('completed', $search);
		$total_pages = max(1, (int) ceil($total / $per_page));

		if ($page > $total_pages)
		{
			$page = $total_pages;
		}

		$offset = ($page - 1) * $per_page;
		$registrations = $this->product_registration_model->get_by_status('completed', $per_page, $offset, $search);
		$range_start = $total > 0 ? $offset + 1 : 0;
		$range_end = min($offset + count($registrations), $total);

		return $this->json_response(array(
			'success'       => TRUE,
			'registrations' => $registrations,
			'total'         => $total,
			'page'          => $page,
			'per_page'      => $per_page,
			'total_pages'   => $total_pages,
			'range_start'   => $range_start,
			'range_end'     => $range_end,
			'search'        => $search,
		));
	}

	public function import_tab($id = NULL)
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$tab = $this->build_import_tab($id);

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

	public function delete($id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Invalid request method.'), 405);
		}

		$registration = $this->product_registration_model->get($id);

		if ( ! $registration)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Registration not found.'), 404);
		}

		if ( ! empty($registration['storage_path']))
		{
			$storage_dir = FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $registration['storage_path']);

			if (is_dir($storage_dir))
			{
				delete_files($storage_dir, TRUE);
				@rmdir($storage_dir);
			}
		}

		if ( ! $this->product_registration_model->delete($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Failed to delete registration.'), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => 'Registration stopped successfully.',
		));
	}

	protected function build_import_tab($id)
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

	protected function json_response($payload, $status = 200)
	{
		return $this->output
			->set_content_type('application/json')
			->set_status_header($status)
			->set_output(json_encode($payload));
	}
}
