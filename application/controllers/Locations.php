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
	);

	protected $permission_view = 'locations';
	protected $permission_edit = 'locations';

	public function __construct()
	{
		parent::__construct();
		$this->auth->require_admin();
		$this->load->model('location_model', 'model');
	}

	public function index()
	{
		$this->auth->require_permission($this->permission_view);

		$this->render('locations/index', array(
			'title'        => $this->localized_title(),
			'nav_active'   => $this->entity,
			'can_edit'     => $this->auth->can($this->permission_edit),
			'records'      => $this->model->list_for_tree(),
			'page_scripts' => array('assets/js/locations.js'),
		));
	}

	public function create()
	{
		$this->auth->require_permission($this->permission_edit);

		if ( ! $this->validate_fields())
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => validation_errors(' ', ' '),
			), 422);
		}

		$parent = $this->resolve_parent($this->input->post('parent_id'));

		if (isset($parent['error']))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line($parent['error']),
			), 422);
		}

		$data = $this->collect_field_values();
		$data['parent_id'] = $parent['id'];
		$data['level'] = $parent['level'];
		$data['sort_order'] = $this->model->next_sort_order($parent['id']);
		$id = $this->model->insert($data);

		if ( ! $id)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_failed_create')), 500);
		}

		$this->model->update($id, array(
			'tag' => $this->model->build_tag($id, $parent['id']),
		));

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('common_record_created'),
			'data'    => $this->present($this->model->get($id)),
		));
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

	public function move($id = NULL)
	{
		$this->auth->require_permission($this->permission_edit);
		$direction = $this->input->post('direction');

		if ($direction !== 'up' && $direction !== 'down')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('common_invalid_method'),
			), 422);
		}

		$moved = $this->model->move_sibling($id, $direction);

		if ($moved === FALSE)
		{
			$status = $this->model->get($id) ? 422 : 404;

			return $this->json_response(array(
				'success' => FALSE,
				'message' => $status === 404 ? app_line('common_record_not_found') : app_line('common_failed_update'),
			), $status);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('common_record_updated'),
			'data'    => array(
				$this->present($moved[0]),
				$this->present($moved[1]),
			),
		));
	}

	public function delete($id = NULL)
	{
		$this->auth->require_permission($this->permission_edit);

		if ( ! $this->model->get($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_record_not_found')), 404);
		}

		if ($this->model->count_children($id) > 0)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('location_has_children'),
			), 422);
		}

		if ( ! $this->model->delete($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_failed_delete')), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('common_record_deleted'),
		));
	}

	protected function resolve_parent($parent_id)
	{
		$parent_id = trim((string) $parent_id);

		if ($parent_id === '')
		{
			return array('id' => NULL, 'level' => 1);
		}

		$parent = $this->model->get($parent_id);

		if ( ! $parent)
		{
			return array('error' => 'location_parent_missing');
		}

		$level = (int) $parent['level'] + 1;

		if ($level > 3)
		{
			return array('error' => 'location_city_no_children');
		}

		return array(
			'id'    => (int) $parent['id'],
			'level' => $level,
		);
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
