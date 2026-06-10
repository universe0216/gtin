<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/BasicController.php';

class Primary extends BasicController {

	protected $entity = 'primary';
	protected $entity_label = 'Primary';
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
		$this->load->model('primary_model', 'model');
	}
}
