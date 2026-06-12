<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class Org_registration extends AuthenticatedController {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper(array('url', 'file'));
		$this->load->model('org_registration_model');
		$this->load->model('org_registration_item_model');
		$this->load->library('org_registration_processor');
	}

	public function index()
	{
		$this->auth->require_permission('organization.edit');

		$user = $this->auth->user();
		$registrations = $this->org_registration_model->get_incomplete_by_account($user['id']);
		$tabs = array();

		foreach ($registrations as $registration)
		{
			$items = $this->org_registration_item_model->get_by_registration($registration['id']);
			$tabs[] = $this->org_registration_processor->format_registration_tab($registration, $items);
		}

		$data = array(
			'title'      => 'Organization Registration',
			'nav_active' => 'organizations',
			'tabs'       => $tabs,
		);

		$data['content'] = $this->load->view('org_registration/index', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	public function upload()
	{
		$this->auth->require_permission('organization.edit');

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
			$result = $this->org_registration_processor->process_uploads($_FILES['zip_files'], $user['id']);
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
			'success' => TRUE,
			'message' => count($result['registrations']).' registration file(s) uploaded successfully.',
			'tabs'    => $result['tabs'],
		));
	}

	public function delete($id = NULL)
	{
		$this->auth->require_permission('organization.edit');

		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Invalid request method.'), 405);
		}

		$registration = $this->org_registration_model->get($id);

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

		if ( ! $this->org_registration_model->delete($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Failed to delete registration.'), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => 'Registration stopped successfully.',
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
