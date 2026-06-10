<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class Admin extends AuthenticatedController {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper(array('url', 'form'));
		$this->load->library('form_validation');
		$this->load->model('account_model');
		$this->config->load('permissions', TRUE);
		$this->auth->require_admin();
	}

	public function index()
	{
		$this->render('admin/index', array(
			'title'              => 'User Management',
			'nav_active'         => 'admin',
			'accounts'           => $this->account_model->get_all(),
			'permission_groups'  => $this->config->item('permission_groups', 'permissions'),
			'current_user_id'    => $this->auth->user()['id'],
		));
	}

	public function get($id = NULL)
	{
		$account = $this->account_model->get($id);

		if ( ! $account)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'User not found.'), 404);
		}

		return $this->json_response(array('success' => TRUE, 'data' => $account));
	}

	public function create()
	{
		if ( ! $this->validate_account(TRUE))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => validation_errors(' ', ' '),
			), 422);
		}

		$data = $this->collect_account_data(TRUE);
		$permissions = $this->collect_permissions();

		if ($this->account_model->username_exists($data['username']))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Username already exists.'), 422);
		}

		$id = $this->account_model->insert($data, $permissions);

		if ( ! $id)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Failed to create user.'), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => 'User created successfully.',
			'data'    => $this->account_model->get($id),
		));
	}

	public function update($id = NULL)
	{
		if ( ! $this->account_model->get($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'User not found.'), 404);
		}

		if ( ! $this->validate_account(FALSE))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => validation_errors(' ', ' '),
			), 422);
		}

		$data = $this->collect_account_data(FALSE);
		$permissions = $this->collect_permissions();

		if ($this->account_model->username_exists($data['username'], $id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Username already exists.'), 422);
		}

		if ( ! $this->account_model->update($id, $data, $permissions))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Failed to update user.'), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => 'User updated successfully.',
			'data'    => $this->account_model->get($id),
		));
	}

	public function delete($id = NULL)
	{
		$current = $this->auth->user();

		if ((int) $id === (int) $current['id'])
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'You cannot delete your own account.'), 422);
		}

		if ( ! $this->account_model->get($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'User not found.'), 404);
		}

		if ( ! $this->account_model->delete($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Failed to delete user.'), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => 'User deleted successfully.',
		));
	}

	protected function render($view, $data = array())
	{
		$data['content'] = $this->load->view($view, $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	protected function json_response($payload, $status = 200)
	{
		return $this->output
			->set_content_type('application/json')
			->set_status_header($status)
			->set_output(json_encode($payload));
	}

	protected function validate_account($is_create)
	{
		$this->form_validation->set_data($this->input->post());
		$this->form_validation->set_rules('username', 'Username', 'required|trim|min_length[3]|max_length[100]|alpha_dash');
		$this->form_validation->set_rules('full_name', 'Full Name', 'required|trim|max_length[255]');

		if ($is_create)
		{
			$this->form_validation->set_rules('password', 'Password', 'required|min_length[6]');
		}
		else
		{
			$password = $this->input->post('password');

			if ($password !== NULL && $password !== '')
			{
				$this->form_validation->set_rules('password', 'Password', 'min_length[6]');
			}
		}

		return $this->form_validation->run();
	}

	protected function collect_account_data($is_create)
	{
		$data = array(
			'username'   => $this->input->post('username'),
			'full_name'  => $this->input->post('full_name'),
			'is_admin'   => $this->input->post('is_admin') ? 1 : 0,
			'is_active'  => $this->input->post('is_active') ? 1 : 0,
		);

		$password = $this->input->post('password');

		if ($is_create || ($password !== NULL && $password !== ''))
		{
			$data['password'] = password_hash($password, PASSWORD_DEFAULT);
		}

		return $data;
	}

	protected function collect_permissions()
	{
		if ($this->input->post('is_admin'))
		{
			return array();
		}

		$permissions = $this->input->post('permissions');

		return is_array($permissions) ? array_values($permissions) : array();
	}
}
