<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class Registration_data extends AuthenticatedController {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper(array('url', 'file'));
		$this->load->model('product_registration_model');
		$this->load->model('product_registration_item_model');
		$this->load->model('account_model');
	}

	public function index()
	{
		$registrations = $this->product_registration_model->get_incomplete();
		$editors = $this->account_model->get_product_editors();
		$user = $this->auth->user();

		$data = array(
			'title'         => app_line('title_registration_data'),
			'nav_active'    => 'registration_data',
			'registrations' => $registrations,
			'editors'       => $editors,
			'account'       => $user,
		);

		$data['content'] = $this->load->view('registration_data/index', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	public function items($id = NULL)
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$registration = $this->open_registration($id);

		if ( ! $registration)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_not_found'),
			), 404);
		}

		$items = $this->product_registration_item_model->get_by_product_registration($registration['id']);
		$rows = array();

		foreach ($items as $item)
		{
			$rows[] = array(
				'id'                       => (int) $item['id'],
				'product_procedure_number' => $item['product_procedure_number'],
				'name'                     => $item['name'],
				'status'                   => $item['status'],
				'barcode'                  => $item['barcode'] ?? '',
				'message'                  => $item['message'] ?? '',
			);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'items'   => $rows,
		));
	}

	public function assign($id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_invalid_method')), 405);
		}

		$registration = $this->open_registration($id);

		if ( ! $registration)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_not_found')), 404);
		}

		$account_id = (int) $this->input->post('account_id');
		$target = $this->account_model->get($account_id);
		$permissions = ($target && isset($target['permissions']) && is_array($target['permissions']))
			? $target['permissions']
			: array();
		$can_receive = $target
			&& ! empty($target['is_active'])
			&& ( ! empty($target['is_admin']) || in_array('product.edit', $permissions, TRUE));

		if ( ! $can_receive)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_send_user_invalid')), 422);
		}

		if ((int) $registration['account_id'] === (int) $target['id'])
		{
			$name = trim((string) $target['full_name']) !== '' ? $target['full_name'] : $target['username'];

			return $this->json_response(array(
				'success' => TRUE,
				'message' => app_line('reg_send_success', $name),
				'data'    => array(
					'account_id' => (int) $target['id'],
					'full_name'  => $name,
				),
			));
		}

		if ( ! $this->product_registration_model->update($registration['id'], array(
			'account_id' => (int) $target['id'],
		)))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_send_failed')), 500);
		}

		$name = trim((string) $target['full_name']) !== '' ? $target['full_name'] : $target['username'];

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('reg_send_success', $name),
			'data'    => array(
				'account_id' => (int) $target['id'],
				'full_name'  => $name,
			),
		));
	}

	public function delete($id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_invalid_method')), 405);
		}

		$registration = $this->open_registration($id);

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

		if ( ! $this->product_registration_model->delete($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_delete_save_failed')), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('reg_data_deleted'),
		));
	}

	protected function open_registration($id)
	{
		$registration = $this->product_registration_model->get($id);

		if ( ! $registration || strcasecmp((string) $registration['status'], 'completed') === 0)
		{
			return NULL;
		}

		return $registration;
	}

	protected function json_response($payload, $status = 200)
	{
		return $this->output
			->set_content_type('application/json')
			->set_status_header($status)
			->set_output(json_encode($payload));
	}
}
