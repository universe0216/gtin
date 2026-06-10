<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/BasicController.php';

class Locations extends BasicController {

	protected $entity = 'locations';
	protected $entity_label = 'Locations';
	protected $fields = array(
		array(
			'name'     => 'name',
			'label'    => 'Name',
			'type'     => 'text',
			'required' => TRUE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'description',
			'label'    => 'Description',
			'type'     => 'textarea',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
	);

	protected $permission_view = 'primary';
	protected $permission_edit = 'primary';

	public function __construct()
	{
		parent::__construct();
		$this->load->model('location_model', 'model');
	}
}
