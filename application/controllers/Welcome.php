<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Welcome extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('auth');
	}

	public function index()
	{
		if ($this->auth->is_logged_in())
		{
			redirect('product_registration');
		}

		redirect('login');
	}
}
