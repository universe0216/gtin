<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class Account extends AuthenticatedController {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper(array('url', 'form'));
		$this->load->library('form_validation');
		$this->load->model('account_model');
	}

	public function password()
	{
		$data = array(
			'title'      => app_line('title_update_password'),
			'nav_active' => 'account_password',
			'success'    => $this->session->flashdata('password_success'),
			'error'      => $this->session->flashdata('password_error'),
		);

		$data['content'] = $this->load->view('account/password', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	public function update_password()
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('account/password');
		}

		$this->form_validation->set_rules('current_password', app_line('field_current_password'), 'required');
		$this->form_validation->set_rules('new_password', app_line('field_new_password'), 'required|min_length[6]');
		$this->form_validation->set_rules(
			'confirm_password',
			app_line('field_confirm_password'),
			'required|matches[new_password]'
		);

		if ( ! $this->form_validation->run())
		{
			$this->session->set_flashdata('password_error', validation_errors(' ', ' '));
			redirect('account/password');
		}

		$user = $this->auth->user();
		$hash = $this->account_model->get_password_hash($user['id']);

		if ( ! $hash || ! password_verify($this->input->post('current_password'), $hash))
		{
			$this->session->set_flashdata('password_error', app_line('account_password_incorrect'));
			redirect('account/password');
		}

		$updated = $this->account_model->update($user['id'], array(
			'password' => password_hash($this->input->post('new_password'), PASSWORD_DEFAULT),
		));

		if ( ! $updated)
		{
			$this->session->set_flashdata('password_error', app_line('account_password_failed'));
			redirect('account/password');
		}

		$this->session->set_flashdata('password_success', app_line('account_password_updated'));
		redirect('account/password');
	}
}
