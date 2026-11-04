<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/BasicController.php';

class Categories extends BasicController {

	protected $entity = 'categories';
	protected $entity_label = 'Categories';
	protected $fields = array(
		array(
			'name'     => 'name',
			'label'    => 'Name',
			'type'     => 'text',
			'required' => TRUE,
			'rules'    => array('trim', 'max_length[255]'),
		),
	);

	protected $permission_view = 'categories';
	protected $permission_edit = 'categories';

	public function __construct()
	{
		parent::__construct();
		$this->auth->require_admin();
		$this->load->model('category_model', 'model');
	}

	public function index()
	{
		$this->auth->require_permission($this->permission_view);

		$this->render('categories/index', array(
			'title'        => $this->localized_title(),
			'nav_active'   => $this->entity,
			'can_edit'     => $this->auth->can($this->permission_edit),
			'records'      => $this->model->list_for_tree(),
			'page_scripts' => array('assets/js/categories.js'),
		));
	}

	public function create()
	{
		show_404();
	}

	public function update($id = NULL)
	{
		$this->auth->require_permission($this->permission_edit);

		if ( ! $this->model->get($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_record_not_found')), 404);
		}

		if ( ! $this->validate_fields())
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => validation_errors(' ', ' '),
			), 422);
		}

		if ( ! $this->model->update($id, $this->collect_field_values()))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_failed_update')), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('common_record_updated'),
			'data'    => $this->present($this->model->get($id)),
		));
	}

	public function delete($id = NULL)
	{
		show_404();
	}

	protected function present($row)
	{
		if ( ! $row)
		{
			return NULL;
		}

		return $this->model->present_row($row);
	}
}
