<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class Procedure extends AuthenticatedController {

	protected $import_per_page = 15;

	public function __construct()
	{
		parent::__construct();
		$this->load->helper(array('url', 'file'));
		$this->load->model('procedure_model');
		$this->load->model('procedure_item_model');
		$this->load->library('procedure_processor');
	}

	public function index()
	{
		$user = $this->auth->user();
		$procedures = $this->procedure_model->get_incomplete_by_account($user['id']);
		$tabs = array();

		foreach ($procedures as $procedure)
		{
			$items = $this->procedure_item_model->get_by_procedure($procedure['id']);
			$tabs[] = $this->procedure_processor->format_procedure_tab($procedure, $items);
		}

		$data = array(
			'title'      => 'Procedure',
			'nav_active' => 'procedure',
			'tabs'       => $tabs,
		);

		$data['content'] = $this->load->view('procedure/index', $data, TRUE);
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
			$result = $this->procedure_processor->process_uploads($_FILES['zip_files'], $user['id']);
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
			'success'    => TRUE,
			'message'    => count($result['procedures']).' procedure file(s) uploaded successfully.',
			'procedures' => $result['procedures'],
			'tabs'       => $result['tabs'],
			'items'      => $result['items'],
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
		$total = $this->procedure_model->count_by_status('completed', $search);
		$total_pages = max(1, (int) ceil($total / $per_page));

		if ($page > $total_pages)
		{
			$page = $total_pages;
		}

		$offset = ($page - 1) * $per_page;
		$procedures = $this->procedure_model->get_by_status('completed', $per_page, $offset, $search);
		$range_start = $total > 0 ? $offset + 1 : 0;
		$range_end = min($offset + count($procedures), $total);

		return $this->json_response(array(
			'success'     => TRUE,
			'procedures'  => $procedures,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
			'range_start' => $range_start,
			'range_end'   => $range_end,
			'search'      => $search,
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
				'message' => 'Procedure not found.',
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

		$procedure = $this->procedure_model->get($id);

		if ( ! $procedure)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Procedure not found.'), 404);
		}

		if ( ! empty($procedure['storage_path']))
		{
			$storage_dir = FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $procedure['storage_path']);

			if (is_dir($storage_dir))
			{
				delete_files($storage_dir, TRUE);
				@rmdir($storage_dir);
			}
		}

		if ( ! $this->procedure_model->delete($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Failed to delete procedure.'), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => 'Procedure stopped successfully.',
		));
	}

	protected function build_import_tab($id)
	{
		$procedure = $this->procedure_model->get($id);

		if ( ! $procedure || $procedure['status'] !== 'completed')
		{
			return NULL;
		}

		$items = $this->procedure_item_model->get_by_procedure($id);
		$tab = $this->procedure_processor->format_procedure_tab($procedure, $items);

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
