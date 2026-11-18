<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class Tools extends AuthenticatedController {

	public function verify_checksum()
	{
		$data = array(
			'title'        => app_line('title_verify_checksum'),
			'nav_active'   => 'tools_verify_checksum',
			'page_scripts' => array('assets/js/verify_checksum.js'),
			'barcode_types' => barcode_checksum_type_options(),
		);

		$data['content'] = $this->load->view('tools/verify_checksum', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}
}
