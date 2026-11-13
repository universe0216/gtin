<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class Data_transfer extends AuthenticatedController {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
		$this->auth->require_admin();
		$this->load->library('legacy_data_transfer');
		$this->legacy_data_transfer->prepare_runtime();
	}

	public function index()
	{
		$data = array(
			'title'        => app_line('title_data_transfer'),
			'nav_active'   => 'data_transfer',
			'page_scripts' => array('assets/js/data_transfer.js'),
		);

		$data['content'] = $this->load->view('data_transfer/index', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	public function start()
	{
		return $this->run_step(function () {
			$counts = $this->legacy_data_transfer->source_counts();
			$this->legacy_data_transfer->clear_target();

			return array(
				'message' => app_line('data_transfer_cleared'),
				'data'    => array(
					'step'   => 'cleared',
					'source' => $counts,
				),
			);
		});
	}

	public function locations()
	{
		return $this->run_step(function () {
			$offset = (int) $this->input->post('offset');
			$limit = (int) $this->input->post('limit');

			if ($offset < 1 && $this->input->post('clear'))
			{
				$this->legacy_data_transfer->clear_tables(array('locations'));
			}

			$result = $this->legacy_data_transfer->transfer_locations_step(
				$offset,
				$limit > 0 ? $limit : NULL
			);

			return array(
				'message' => app_line(
					'data_transfer_locations_progress',
					(int) $result['next_offset'],
					! empty($result['done']) ? app_line('data_transfer_step_complete') : app_line('data_transfer_step_continue')
				),
				'data'    => array_merge(array('step' => 'locations'), $result),
			);
		});
	}

	public function organizations()
	{
		return $this->run_step(function () {
			$offset = (int) $this->input->post('offset');
			$limit = (int) $this->input->post('limit');

			if ($offset < 1 && $this->input->post('clear'))
			{
				$this->legacy_data_transfer->clear_tables(array('organizations'));
			}

			$result = $this->legacy_data_transfer->transfer_organizations_step(
				$offset,
				$limit > 0 ? $limit : NULL
			);

			return array(
				'message' => app_line(
					'data_transfer_organizations_progress',
					(int) $result['next_offset'],
					! empty($result['done']) ? app_line('data_transfer_step_complete') : app_line('data_transfer_step_continue')
				),
				'data'    => array_merge(array('step' => 'organizations'), $result),
			);
		});
	}

	public function products()
	{
		return $this->run_step(function () {
			$offset = (int) $this->input->post('offset');
			$limit = (int) $this->input->post('limit');

			if ($offset < 1 && $this->input->post('clear'))
			{
				$this->legacy_data_transfer->clear_tables(array('products'));
			}

			$result = $this->legacy_data_transfer->transfer_products_step(
				$offset,
				$limit > 0 ? $limit : NULL
			);

			return array(
				'message' => app_line(
					'data_transfer_products_progress',
					(int) $result['next_offset'],
					! empty($result['done']) ? app_line('data_transfer_step_complete') : app_line('data_transfer_step_continue')
				),
				'data'    => array_merge(array('step' => 'products'), $result),
			);
		});
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
					: app_line('data_transfer_failed'),
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
