<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Procedure extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
	}

	public function index()
	{
		$data = array(
			'title'      => 'Procedure',
			'nav_active' => 'procedure',
			'steps'      => array(
				array(
					'number'      => 1,
					'title'       => 'Register Product',
					'description' => 'Create or import product records with GTIN identifiers and core attributes.',
					'icon'        => 'fa-barcode',
				),
				array(
					'number'      => 2,
					'title'       => 'Assign Organization',
					'description' => 'Link products to the responsible organization and ownership details.',
					'icon'        => 'fa-building',
				),
				array(
					'number'      => 3,
					'title'       => 'Configure Location',
					'description' => 'Define storage or handling locations used in the supply chain workflow.',
					'icon'        => 'fa-location-dot',
				),
				array(
					'number'      => 4,
					'title'       => 'Review & Publish',
					'description' => 'Validate data consistency, approve changes, and publish to downstream systems.',
					'icon'        => 'fa-circle-check',
				),
			),
		);

		$data['content'] = $this->load->view('procedure/index', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}
}
