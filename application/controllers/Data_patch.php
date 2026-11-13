<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class Data_patch extends AuthenticatedController {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
		$this->auth->require_admin();
		$this->load->library('data_patch_exporter');
		$this->data_patch_exporter->prepare_runtime();
		$this->load->model('export_history_model');
	}

	public function index()
	{
		$last = $this->data_patch_exporter->last_completed();
		$history = $this->export_history_model->list_recent(15);

		$data = array(
			'title'        => app_line('title_data_patch'),
			'nav_active'   => 'tools_data_patch',
			'page_scripts' => array('assets/js/data_patch.js'),
			'has_export'   => $last ? TRUE : FALSE,
			'last_export'  => $last,
			'history'      => $history,
		);

		$data['content'] = $this->load->view('data_patch/index', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	public function status()
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$last = $this->data_patch_exporter->last_completed();

		return $this->json_response(array(
			'success' => TRUE,
			'data'    => array(
				'has_export'  => $last ? TRUE : FALSE,
				'last_export' => $last,
			),
		));
	}

	public function export_start()
	{
		return $this->run_step(function () {
			$type = trim((string) $this->input->post('type'));

			if ($type !== Data_patch_exporter::TYPE_PATCH)
			{
				$type = Data_patch_exporter::TYPE_FULL;
			}

			$user = $this->auth->user();
			$result = $this->data_patch_exporter->start(
				$type,
				$user && ! empty($user['id']) ? (int) $user['id'] : NULL
			);

			if ( ! empty($result['empty']))
			{
				return array(
					'message' => app_line('data_patch_no_changes'),
					'data'    => $result,
				);
			}

			return array(
				'message' => app_line('data_patch_started'),
				'data'    => $result,
			);
		});
	}

	public function export_chunk()
	{
		return $this->run_step(function () {
			$export_id = (int) $this->input->post('export_id');

			if ($export_id < 1)
			{
				throw new RuntimeException(app_line('data_patch_invalid_export'));
			}

			try
			{
				$result = $this->data_patch_exporter->process_chunk($export_id);
			}
			catch (Throwable $exception)
			{
				$this->data_patch_exporter->mark_failed($export_id, $exception->getMessage());
				throw $exception;
			}

			$message = ! empty($result['done'])
				? app_line('data_patch_complete')
				: app_line('data_patch_running');

			return array(
				'message' => $message,
				'data'    => $result,
			);
		});
	}

	public function download($id = NULL)
	{
		$this->auth->require_admin();
		$file = $this->data_patch_exporter->download_path($id);

		if ( ! $file)
		{
			show_404();
		}

		$this->load->helper('download');
		force_download($file['name'], file_get_contents($file['path']));
	}

	protected function run_step($callback)
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('common_invalid_method'),
			), 405);
		}

		try
		{
			$payload = $callback();
		}
		catch (Throwable $exception)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => $exception->getMessage() !== ''
					? $exception->getMessage()
					: app_line('data_patch_failed'),
			), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => $payload['message'],
			'data'    => $payload['data'],
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
