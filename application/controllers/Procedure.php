<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class Procedure extends AuthenticatedController {

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
		$procedures = $this->procedure_model->get_all();
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

	protected function json_response($payload, $status = 200)
	{
		return $this->output
			->set_content_type('application/json')
			->set_status_header($status)
			->set_output(json_encode($payload));
	}
}
