<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class Settings extends AuthenticatedController {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
		$this->auth->require_admin();
		$this->load->model('settings_model');
	}

	public function index()
	{
		$data = array(
			'title'        => app_line('title_settings'),
			'nav_active'   => 'settings',
			'page_scripts' => array('assets/js/settings.js'),
			'settings'     => $this->settings_model->list_keys(),
		);

		$data['content'] = $this->load->view('settings/index', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	public function list_items()
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		return $this->json_response(array(
			'success' => TRUE,
			'data'    => $this->settings_model->list_keys(),
		));
	}

	public function get_item()
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$key = trim((string) $this->input->get_post('key'));
		$row = $this->settings_model->get($key);

		if ( ! $row)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('settings_not_found'),
			), 404);
		}

		$decoded = json_decode((string) ($row['settings'] ?? ''), TRUE);

		if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded))
		{
			$decoded = array();
		}

		$pairs = array();

		foreach ($decoded as $field => $value)
		{
			$pairs[] = array(
				'field' => (string) $field,
				'value' => is_scalar($value) || $value === NULL
					? (string) $value
					: json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'data'    => array(
				'key'         => $row['key'],
				'description' => $row['description'],
				'pairs'       => $pairs,
			),
		));
	}

	public function save_item()
	{
		if ($this->input->method(TRUE) !== 'POST' || ! $this->input->is_ajax_request())
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('common_invalid_method'),
			), 405);
		}

		$key = trim((string) $this->input->post('key'));
		$description = trim((string) $this->input->post('description'));
		$pairs_raw = $this->input->post('pairs');

		if ($key === '' || ! $this->settings_model->get($key))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('settings_not_found'),
			), 404);
		}

		if (is_string($pairs_raw))
		{
			$pairs = json_decode($pairs_raw, TRUE);
		}
		else
		{
			$pairs = $pairs_raw;
		}

		if ( ! is_array($pairs))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('settings_invalid_pairs'),
			), 422);
		}

		$mapped = array();

		foreach ($pairs as $pair)
		{
			if ( ! is_array($pair))
			{
				continue;
			}

			$field = trim((string) ($pair['field'] ?? ''));

			if ($field === '')
			{
				continue;
			}

			if (isset($mapped[$field]))
			{
				return $this->json_response(array(
					'success' => FALSE,
					'message' => app_line('settings_duplicate_field', $field),
				), 422);
			}

			$mapped[$field] = isset($pair['value']) ? (string) $pair['value'] : '';
		}

		if ( ! $this->settings_model->set_json($key, $mapped, $description))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('settings_save_failed'),
			), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('settings_saved'),
			'data'    => array(
				'key'         => $key,
				'description' => $description,
			),
		));
	}

	public function create_item()
	{
		if ($this->input->method(TRUE) !== 'POST' || ! $this->input->is_ajax_request())
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('common_invalid_method'),
			), 405);
		}

		$key = trim((string) $this->input->post('key'));
		$description = trim((string) $this->input->post('description'));

		if ($key === '' || ! preg_match('/^[a-zA-Z0-9_\-\.]+$/', $key))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('settings_invalid_key'),
			), 422);
		}

		if ($this->settings_model->get($key))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('settings_key_exists'),
			), 422);
		}

		if ( ! $this->settings_model->create_setting($key, $description, array()))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('settings_create_failed'),
			), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('settings_created'),
			'data'    => array(
				'key'         => $key,
				'description' => $description,
			),
		));
	}

	public function delete_item()
	{
		if ($this->input->method(TRUE) !== 'POST' || ! $this->input->is_ajax_request())
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('common_invalid_method'),
			), 405);
		}

		$key = trim((string) $this->input->post('key'));

		if ($key === '' || ! $this->settings_model->get($key))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('settings_not_found'),
			), 404);
		}

		if ( ! $this->settings_model->delete_setting($key))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('settings_delete_failed'),
			), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('settings_deleted'),
		));
	}

	protected function json_response($payload, $status = 200)
	{
		return $this->output
			->set_content_type('application/json')
			->set_status_header($status)
			->set_output(json_encode($payload));
	}
}
