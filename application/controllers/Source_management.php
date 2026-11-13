<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class Source_management extends AuthenticatedController {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper(array('url', 'form'));
		$this->load->library('source_manager');
		$this->load->model('source_file_history_model');
		$this->auth->require_superadmin();
	}

	public function index()
	{
		$data = array(
			'title'        => app_line('title_source_management'),
			'nav_active'   => 'source_management',
			'page_scripts' => array('assets/js/source_management.js'),
			'tree'         => $this->source_manager->tree(),
		);

		$data['content'] = $this->load->view('source_management/index', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	public function tree()
	{
		return $this->json_response(array(
			'success' => TRUE,
			'tree'    => $this->source_manager->tree(),
		));
	}

	public function file_info()
	{
		$path = $this->input->get('path');
		$file = $this->source_manager->resolve_file($path);

		if ($file === NULL)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('source_file_not_found'),
			), 404);
		}

		$history = $this->source_file_history_model->list_by_path($file['relative_path']);

		return $this->json_response(array(
			'success' => TRUE,
			'file'    => array(
				'relative_path' => $file['relative_path'],
				'basename'      => $file['basename'],
				'extension'     => $file['extension'],
				'size'          => (int) $file['size'],
				'modified_at'   => $file['modified_at'],
			),
			'history' => $history,
		));
	}

	public function replace()
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('common_invalid_method'),
			), 405);
		}

		$path = $this->input->post('path');
		$upload = isset($_FILES['source_file']) ? $_FILES['source_file'] : NULL;

		try
		{
			$result = $this->source_manager->replace_file($path, $upload);
		}
		catch (RuntimeException $exception)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => $exception->getMessage(),
			), 422);
		}
		catch (Exception $exception)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('source_replace_failed'),
			), 500);
		}

		$user = $this->auth->user();
		$this->source_file_history_model->insert(array(
			'relative_path' => $result['relative_path'],
			'backup_path'   => $result['backup_path'],
			'uploaded_name' => $result['uploaded_name'],
			'account_id'    => (int) $user['id'],
		));

		$history = $this->source_file_history_model->list_by_path($result['relative_path']);

		$file = $this->source_manager->resolve_file($result['relative_path']);

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('source_replace_success'),
			'file'    => array(
				'relative_path' => $result['relative_path'],
				'basename'      => $file ? $file['basename'] : basename($result['relative_path']),
				'extension'     => $file ? $file['extension'] : pathinfo($result['relative_path'], PATHINFO_EXTENSION),
				'size'          => $file ? (int) $file['size'] : (int) ($result['size'] ?? 0),
				'modified_at'   => $result['modified_at'],
				'backup_path'   => $result['backup_path'],
			),
			'history' => $history,
		));
	}

	public function add()
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('common_invalid_method'),
			), 405);
		}

		$folder = $this->input->post('folder');
		$upload = isset($_FILES['source_file']) ? $_FILES['source_file'] : NULL;

		try
		{
			$result = $this->source_manager->add_file($folder, $upload);
		}
		catch (RuntimeException $exception)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => $exception->getMessage(),
			), 422);
		}
		catch (Exception $exception)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('source_add_failed'),
			), 500);
		}

		$user = $this->auth->user();
		$this->source_file_history_model->insert(array(
			'relative_path' => $result['relative_path'],
			'backup_path'   => '',
			'uploaded_name' => 'create:'.$result['uploaded_name'],
			'account_id'    => (int) $user['id'],
		));

		$history = $this->source_file_history_model->list_by_path($result['relative_path']);

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('source_add_success'),
			'tree'    => $this->source_manager->tree(),
			'file'    => array(
				'relative_path' => $result['relative_path'],
				'basename'      => $result['basename'],
				'extension'     => $result['extension'],
				'size'          => (int) $result['size'],
				'modified_at'   => $result['modified_at'],
			),
			'history' => $history,
		));
	}

	public function restore()
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('common_invalid_method'),
			), 405);
		}

		$history_id = (int) $this->input->post('history_id');
		$history_row = $this->source_file_history_model->get($history_id);

		if ( ! $history_row)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('source_history_not_found'),
			), 404);
		}

		try
		{
			$result = $this->source_manager->restore_backup(
				$history_row['relative_path'],
				$history_row['backup_path']
			);
		}
		catch (RuntimeException $exception)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => $exception->getMessage(),
			), 422);
		}
		catch (Exception $exception)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('source_restore_failed'),
			), 500);
		}

		$user = $this->auth->user();
		$this->source_file_history_model->insert(array(
			'relative_path' => $result['relative_path'],
			'backup_path'   => $result['backup_path'],
			'uploaded_name' => 'restore:'.$result['uploaded_name'],
			'account_id'    => (int) $user['id'],
		));

		$history = $this->source_file_history_model->list_by_path($result['relative_path']);

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('source_restore_success'),
			'file'    => array(
				'relative_path' => $result['relative_path'],
				'basename'      => $result['basename'],
				'extension'     => $result['extension'],
				'size'          => (int) $result['size'],
				'modified_at'   => $result['modified_at'],
			),
			'history' => $history,
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
