<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class BasicController extends CI_Controller {

	public $model;
	protected $entity = '';
	protected $entity_label = '';
	protected $fields = array();

	public function __construct()
	{
		parent::__construct();
		$this->load->helper(array('url', 'form'));
		$this->load->library('form_validation');
	}

	public function index()
	{
		$records = array();

		if ($this->model)
		{
			$records = $this->model->get_all();
		}

		$this->render('crud/index', array(
			'title'      => $this->entity_label,
			'records'    => $records,
			'fields'     => $this->fields,
			'entity'     => $this->entity,
			'nav_active' => $this->entity,
		));
	}

	public function get($id = NULL)
	{
		if ( ! $this->model)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Model not configured.'), 500);
		}

		$record = $this->model->get($id);

		if ( ! $record)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Record not found.'), 404);
		}

		return $this->json_response(array('success' => TRUE, 'data' => $record));
	}

	public function create()
	{
		if ( ! $this->model)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Model not configured.'), 500);
		}

		if ( ! $this->validate_fields())
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => validation_errors(' ', ' '),
			), 422);
		}

		$data = $this->collect_field_values();
		$id = $this->model->insert($data);

		if ( ! $id)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Failed to create record.'), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => 'Record created successfully.',
			'data'    => $this->model->get($id),
		));
	}

	public function update($id = NULL)
	{
		if ( ! $this->model)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Model not configured.'), 500);
		}

		if ( ! $this->model->get($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Record not found.'), 404);
		}

		if ( ! $this->validate_fields())
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => validation_errors(' ', ' '),
			), 422);
		}

		$data = $this->collect_field_values();

		if ( ! $this->model->update($id, $data))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Failed to update record.'), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => 'Record updated successfully.',
			'data'    => $this->model->get($id),
		));
	}

	public function delete($id = NULL)
	{
		if ( ! $this->model)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Model not configured.'), 500);
		}

		if ( ! $this->model->get($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Record not found.'), 404);
		}

		if ( ! $this->model->delete($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Failed to delete record.'), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => 'Record deleted successfully.',
		));
	}

	protected function render($view, $data = array())
	{
		$data['content'] = $this->load->view($view, $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	protected function json_response($payload, $status = 200)
	{
		return $this->output
			->set_content_type('application/json')
			->set_status_header($status)
			->set_output(json_encode($payload));
	}

	protected function validate_fields()
	{
		$this->form_validation->set_data($this->input->post());

		foreach ($this->fields as $field)
		{
			$rules = array();

			if ( ! empty($field['required']))
			{
				$rules[] = 'required';
			}

			if ( ! empty($field['rules']))
			{
				$rules = array_merge($rules, (array) $field['rules']);
			}

			if (empty($rules))
			{
				continue;
			}

			$this->form_validation->set_rules(
				$field['name'],
				$field['label'],
				implode('|', $rules)
			);
		}

		return $this->form_validation->run();
	}

	protected function collect_field_values()
	{
		$data = array();

		foreach ($this->fields as $field)
		{
			$data[$field['name']] = $this->input->post($field['name']);
		}

		return $data;
	}
}
