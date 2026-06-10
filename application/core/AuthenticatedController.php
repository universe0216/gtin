<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class AuthenticatedController extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('auth');
		$this->auth->require_login();
	}
}
