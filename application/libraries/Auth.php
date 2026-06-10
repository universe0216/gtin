<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth {

	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('session');
		$this->CI->load->model('account_model');
		$this->CI->config->load('permissions', TRUE);
	}

	public function login($username, $password)
	{
		$account = $this->CI->account_model->get_by_username($username);

		if ( ! $account || ! $account['is_active'])
		{
			return FALSE;
		}

		if ( ! password_verify($password, $account['password']))
		{
			return FALSE;
		}

		$permissions = $account['is_admin']
			? $this->CI->config->item('all_permissions', 'permissions')
			: $this->CI->account_model->get_permissions($account['id']);

		$this->CI->session->set_userdata(array(
			'auth_user_id'       => (int) $account['id'],
			'auth_username'      => $account['username'],
			'auth_full_name'     => $account['full_name'],
			'auth_is_admin'      => (bool) $account['is_admin'],
			'auth_permissions'   => $permissions,
			'auth_logged_in'     => TRUE,
		));

		return TRUE;
	}

	public function logout()
	{
		$this->CI->session->unset_userdata(array(
			'auth_user_id',
			'auth_username',
			'auth_full_name',
			'auth_is_admin',
			'auth_permissions',
			'auth_logged_in',
		));
	}

	public function is_logged_in()
	{
		return (bool) $this->CI->session->userdata('auth_logged_in');
	}

	public function is_admin()
	{
		return (bool) $this->CI->session->userdata('auth_is_admin');
	}

	public function user()
	{
		if ( ! $this->is_logged_in())
		{
			return NULL;
		}

		return array(
			'id'        => (int) $this->CI->session->userdata('auth_user_id'),
			'username'  => $this->CI->session->userdata('auth_username'),
			'full_name' => $this->CI->session->userdata('auth_full_name'),
			'is_admin'  => $this->is_admin(),
		);
	}

	public function permissions()
	{
		$permissions = $this->CI->session->userdata('auth_permissions');

		return is_array($permissions) ? $permissions : array();
	}

	public function can($permission)
	{
		if ($this->is_admin())
		{
			return TRUE;
		}

		$permissions = $this->permissions();

		if (in_array($permission, $permissions, TRUE))
		{
			return TRUE;
		}

		if (substr($permission, -5) === '.view')
		{
			$edit_permission = substr($permission, 0, -5).'.edit';

			return in_array($edit_permission, $permissions, TRUE);
		}

		return FALSE;
	}

	public function require_login()
	{
		if ($this->is_logged_in())
		{
			return;
		}

		$redirect = '';

		if (isset($this->CI->uri) && is_object($this->CI->uri))
		{
			$redirect = $this->CI->uri->uri_string();
		}

		redirect('login?redirect='.urlencode($redirect));
	}

	public function require_permission($permission)
	{
		$this->require_login();

		if ($this->can($permission))
		{
			return;
		}

		show_error('You do not have permission to access this page.', 403);
	}

	public function require_admin()
	{
		$this->require_login();

		if ($this->is_admin())
		{
			return;
		}

		show_error('Administrator access required.', 403);
	}
}
