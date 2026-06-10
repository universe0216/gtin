<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Login extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper(array('url', 'form'));
		$this->load->library(array('session', 'form_validation', 'auth'));
	}

	public function index()
	{
		if ($this->auth->is_logged_in())
		{
			redirect('primary');
		}

		$this->load->view('auth/login', array(
			'title'    => 'Login',
			'redirect' => $this->input->get('redirect'),
			'error'    => $this->session->flashdata('login_error'),
		));
	}

	public function authenticate()
	{
		if ($this->auth->is_logged_in())
		{
			redirect('primary');
		}

		$this->form_validation->set_rules('username', 'Username', 'required|trim');
		$this->form_validation->set_rules('password', 'Password', 'required');

		if ( ! $this->form_validation->run())
		{
			$this->session->set_flashdata('login_error', validation_errors(' ', ' '));
			redirect('login?redirect='.urlencode($this->input->post('redirect')));
		}

		$username = $this->input->post('username');
		$password = $this->input->post('password');
		$redirect = $this->input->post('redirect');

		if ( ! $this->auth->login($username, $password))
		{
			$this->session->set_flashdata('login_error', 'Invalid username or password.');
			redirect('login?redirect='.urlencode($redirect));
		}

		if ($redirect && preg_match('/^[a-zA-Z0-9_\/-]+$/', $redirect))
		{
			redirect($redirect);
		}

		redirect('primary');
	}

	public function logout()
	{
		$this->auth->logout();
		redirect('login');
	}
}
