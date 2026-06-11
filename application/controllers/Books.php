<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class Books extends AuthenticatedController {

	public function gtin_country_code()
	{
		$this->config->load('gtin_country_codes');
		$country_codes = $this->config->item('gtin_country_codes');

		$data = array(
			'title'         => 'GTIN Country Codes',
			'nav_active'    => 'books_gtin_country_code',
			'country_codes' => $country_codes,
		);

		$data['content'] = $this->load->view('books/gtin_country_code', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}
}
