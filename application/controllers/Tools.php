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
			'barcode_types' => array(
				array('key' => 'ean13', 'label' => 'EAN-13', 'length' => 12),
				array('key' => 'ean8', 'label' => 'EAN-8', 'length' => 7),
				array('key' => 'upca', 'label' => 'UPC-A', 'length' => 11),
				array('key' => 'upce', 'label' => 'UPC-E', 'length' => 7),
				array('key' => 'itf14', 'label' => 'ITF-14', 'length' => 13),
				array('key' => 'gs1128', 'label' => 'GS1-128', 'length' => 0),
			),
		);

		$data['content'] = $this->load->view('tools/verify_checksum', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}
}
