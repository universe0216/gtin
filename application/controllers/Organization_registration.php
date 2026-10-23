<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class Organization_registration extends AuthenticatedController {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper(array('url', 'file'));
		$this->load->model('organization_registration_model');
		$this->load->model('organization_registration_item_model');
		$this->load->library('organization_registration_processor');
	}

	public function index()
	{
		$this->auth->require_permission('organization.edit');

		$user = $this->auth->user();
		$registrations = $this->organization_registration_model->get_incomplete_by_account($user['id']);
		$tabs = array();

		foreach ($registrations as $registration)
		{
			$items = $this->organization_registration_item_model->get_by_organization_registration($registration['id']);
			$tabs[] = $this->organization_registration_processor->format_registration_tab($registration, $items);
		}

		$data = array(
			'title'      => 'Organization Registration',
			'nav_active' => 'organizations',
			'tabs'       => $tabs,
		);

		$data['content'] = $this->load->view('organization_registration/index', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	public function upload()
	{
		$this->auth->require_permission('organization.edit');

		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_invalid_method')), 405);
		}

		if (empty($_FILES['zip_files']))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_select_zip_server')), 422);
		}

		try
		{
			$user = $this->auth->user();
			$result = $this->organization_registration_processor->process_uploads($_FILES['zip_files'], $user['id']);
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
				'message' => app_line('reg_upload_failed'),
			), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('reg_upload_success', count($result['registrations'])),
			'tabs'    => $result['tabs'],
		));
	}

	public function delete($id = NULL)
	{
		$this->auth->require_permission('organization.edit');

		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_invalid_method')), 405);
		}

		$registration = $this->organization_registration_model->get($id);

		if ( ! $registration)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_not_found')), 404);
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

		if ( ! $this->organization_registration_model->delete($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_delete_save_failed')), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('reg_stopped'),
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
