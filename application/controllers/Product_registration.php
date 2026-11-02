<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class Product_registration extends AuthenticatedController {

	protected $import_per_page = 10;

	public function __construct()
	{
		parent::__construct();
		$this->load->helper(array('url', 'file', 'barcode'));
		$this->load->model('product_registration_model');
		$this->load->model('product_registration_item_model');
		$this->load->library('product_registration_processor');
	}

	public function index()
	{
		$user = $this->auth->user();
		$registrations = $this->product_registration_model->get_incomplete_by_account($user['id']);
		$tabs = array();

		foreach ($registrations as $registration)
		{
			$items = $this->product_registration_item_model->get_by_product_registration($registration['id']);
			$tabs[] = $this->product_registration_processor->format_product_registration_tab($registration, $items);
		}

		$data = array(
			'title'      => 'Product Registration',
			'nav_active' => 'product_registration',
			'tabs'       => $tabs,
		);

		$data['content'] = $this->load->view('product_registration/index', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	public function upload()
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_invalid_method')), 405);
		}

		if (empty($_FILES['zip_files']))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_select_files_server')), 422);
		}

		try
		{
			$user = $this->auth->user();
			$result = $this->product_registration_processor->process_uploads($_FILES['zip_files'], $user['id']);
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
				'message' => app_line('reg_upload_failed'),
			), 500);
		}

		return $this->json_response(array(
			'success'       => TRUE,
			'message'       => app_line('reg_upload_success', count($result['registrations'])),
			'registrations' => $result['registrations'],
			'tabs'          => $result['tabs'],
			'items'         => $result['items'],
		));
	}

	public function import_list()
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$per_page = $this->import_per_page;
		$page = max(1, (int) $this->input->get('page'));
		$search = trim((string) $this->input->get('q'));
		$total = $this->product_registration_model->count_by_status('completed', $search);
		$total_pages = max(1, (int) ceil($total / $per_page));

		if ($page > $total_pages)
		{
			$page = $total_pages;
		}

		$offset = ($page - 1) * $per_page;
		$registrations = $this->product_registration_model->get_by_status('completed', $per_page, $offset, $search);
		$range_start = $total > 0 ? $offset + 1 : 0;
		$range_end = min($offset + count($registrations), $total);

		return $this->json_response(array(
			'success'       => TRUE,
			'registrations' => $registrations,
			'total'         => $total,
			'page'          => $page,
			'per_page'      => $per_page,
			'total_pages'   => $total_pages,
			'range_start'   => $range_start,
			'range_end'     => $range_end,
			'search'        => $search,
		));
	}

	public function import_tab($id = NULL)
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$tab = $this->build_import_tab($id);

		if ($tab === NULL)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_not_found'),
			), 404);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'tab'     => $tab,
		));
	}

	public function reject_item($id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_invalid_method')), 405);
		}

		$reason = trim((string) $this->input->post('reason'));

		if ($reason === '')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_reject_reason_required'),
			), 422);
		}

		$item = $this->product_registration_item_model->get($id);

		if ( ! $item)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_item_not_found')), 404);
		}

		$registration = $this->product_registration_model->get($item['product_registration_id']);

		if ( ! $registration)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_not_found')), 404);
		}

		if ($registration['status'] === 'completed')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_completed_locked'),
			), 422);
		}

		if ($item['status'] === 'rejected')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_already_rejected'),
			), 422);
		}

		$was_pending = $item['status'] === 'pending';
		$was_approved = $item['status'] === 'approved';

		if ( ! $this->product_registration_item_model->update($id, array(
			'status'  => 'rejected',
			'message' => $reason,
		)))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_reject_failed')), 500);
		}

		if ($was_pending || $was_approved)
		{
			$counts = array(
				'rejected' => (int) $registration['rejected'] + 1,
			);

			if ($was_approved)
			{
				$counts['approved'] = max(0, (int) $registration['approved'] - 1);
			}

			$this->product_registration_model->update($registration['id'], $counts);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('reg_reject_success'),
			'data'    => array(
				'id'                      => (int) $id,
				'product_registration_id' => (int) $item['product_registration_id'],
				'status'                  => 'rejected',
				'message'                 => $reason,
			),
		));
	}

	public function accept_item($id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_invalid_method')), 405);
		}

		$item = $this->product_registration_item_model->get($id);

		if ( ! $item)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_item_not_found')), 404);
		}

		$registration = $this->product_registration_model->get($item['product_registration_id']);

		if ( ! $registration)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_not_found')), 404);
		}

		if ($registration['status'] === 'completed')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_completed_locked'),
			), 422);
		}

		if ($item['status'] === 'rejected')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_accept_blocked'),
			), 422);
		}

		$was_pending = $item['status'] === 'pending';
		$barcode_1d_type = trim((string) $this->input->post('barcode_1d_type', FALSE));
		$barcode_1d = trim((string) $this->input->post('barcode_1d', FALSE));
		$problem = barcode_1d_problem($barcode_1d_type, $barcode_1d);

		if ($problem !== NULL)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => $this->barcode_1d_problem_message($problem, $barcode_1d_type),
			), 422);
		}

		$info_json = $this->apply_posted_barcode_fields($item['info'], array(
			'barcode_1d_type' => $barcode_1d_type,
			'barcode_1d'      => $barcode_1d,
		));

		if ($info_json === NULL)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_barcode_field_missing'),
			), 422);
		}

		$barcode = $this->normalize_item_barcode($this->input->post('barcode'));
		$update_data = array(
			'status'  => 'approved',
			'message' => NULL,
			'info'    => $info_json,
		);

		if ($barcode !== NULL)
		{
			$update_data['barcode'] = $barcode;
		}

		if ( ! $this->product_registration_item_model->update($id, $update_data))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_accept_save_failed')), 500);
		}

		if ($was_pending)
		{
			$this->product_registration_model->update($registration['id'], array(
				'approved' => (int) $registration['approved'] + 1,
			));
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('reg_accept_success'),
			'data'    => array(
				'id'                      => (int) $id,
				'product_registration_id' => (int) $item['product_registration_id'],
				'status'                  => 'approved',
				'barcode'                 => $barcode,
			),
		));
	}

	public function update_item_barcodes($id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_invalid_method')), 405);
		}

		$item = $this->product_registration_item_model->get($id);

		if ( ! $item)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_item_not_found')), 404);
		}

		$registration = $this->product_registration_model->get($item['product_registration_id']);

		if ( ! $registration)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_not_found')), 404);
		}

		if ($registration['status'] === 'completed')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_completed_locked'),
			), 422);
		}

		$field_columns = array(
			'barcode_1d_type' => array('type of 1d barcode'),
			'barcode_1d'      => array('1d barcode', 'barcode'),
			'barcode_2d_type' => array('type of 2d barcode'),
			'barcode_2d'      => array('2d barcode'),
		);
		$field_limits = array(
			'barcode_1d_type' => 50,
			'barcode_2d_type' => 50,
			'barcode_1d'      => 255,
			'barcode_2d'      => 255,
		);
		$posted = array();

		foreach ($field_columns as $key => $names)
		{
			if ( ! array_key_exists($key, $_POST))
			{
				continue;
			}

			$value = trim((string) $this->input->post($key, FALSE));
			$length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);

			if ($length > $field_limits[$key])
			{
				return $this->json_response(array(
					'success' => FALSE,
					'message' => app_line('reg_barcode_too_long'),
				), 422);
			}

			$posted[$key] = $value;
		}

		if (empty($posted))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_barcode_save_failed'),
			), 422);
		}

		$info = json_decode((string) $item['info'], TRUE);

		if ( ! is_array($info))
		{
			$info = array();
		}

		$columns = $info['columns'] ?? array();
		$cells = $info['cells'] ?? array();

		foreach ($posted as $key => $value)
		{
			$cell_index = $this->find_column_index($columns, $field_columns[$key]);

			if ($cell_index === NULL)
			{
				return $this->json_response(array(
					'success' => FALSE,
					'message' => app_line('reg_barcode_field_missing'),
				), 422);
			}

			while (count($cells) <= $cell_index)
			{
				$cells[] = '';
			}

			$cells[$cell_index] = $value;
		}

		$info['cells'] = array_values($cells);

		if ( ! $this->product_registration_item_model->update($id, array(
			'info' => json_encode($info),
		)))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_barcode_save_failed'),
			), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'data'    => array(
				'id'     => (int) $id,
				'fields' => $posted,
			),
		));
	}

	public function delete($id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_invalid_method')), 405);
		}

		$registration = $this->product_registration_model->get($id);

		if ( ! $registration)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_not_found')), 404);
		}

		if ( ! empty($registration['storage_path']))
		{
			$storage_dir = FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $registration['storage_path']);

			if (is_dir($storage_dir))
			{
				delete_files($storage_dir, TRUE);
				@rmdir($storage_dir);
			}
		}

		if ( ! $this->product_registration_model->delete($id))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_delete_save_failed')), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('reg_stopped'),
		));
	}

	protected function build_import_tab($id)
	{
		$registration = $this->product_registration_model->get($id);

		if ( ! $registration || $registration['status'] !== 'completed')
		{
			return NULL;
		}

		$items = $this->product_registration_item_model->get_by_product_registration($id);
		$tab = $this->product_registration_processor->format_product_registration_tab($registration, $items);

		foreach ($tab['rows'] as $index => $row)
		{
			$item = $items[$index] ?? array();
			$tab['rows'][$index]['status'] = $item['status'] ?? '';
			$tab['rows'][$index]['message'] = $item['message'] ?? '';
			$tab['rows'][$index]['barcode'] = $item['barcode'] ?? '';
		}

		return $tab;
	}

	protected function normalize_item_barcode($barcode)
	{
		$barcode = preg_replace('/\D/', '', trim((string) $barcode));

		if ($barcode === '')
		{
			return NULL;
		}

		if (strlen($barcode) < 12 || strlen($barcode) > 13)
		{
			return NULL;
		}

		return substr($barcode, 0, 13);
	}

	protected function barcode_1d_problem_message($problem, $type)
	{
		if ($problem === 'empty')
		{
			return app_line('reg_barcode_1d_required');
		}

		if ($problem === 'type')
		{
			return app_line('reg_barcode_1d_type_required');
		}

		$hints = array(
			'ean13'   => 'reg_barcode_hint_ean13',
			'ean8'    => 'reg_barcode_hint_ean8',
			'upca'    => 'reg_barcode_hint_upca',
			'upce'    => 'reg_barcode_hint_upce',
			'itf14'   => 'reg_barcode_hint_itf14',
			'gs1128'  => 'reg_barcode_hint_gs1128',
			'code128' => 'reg_barcode_hint_code128',
			'code39'  => 'reg_barcode_hint_code39',
		);
		$key = barcode_normalize_1d_type($type);
		$hint_key = isset($hints[$key]) ? $hints[$key] : 'reg_barcode_hint_unknown';

		return str_replace(
			array('{type}', '{hint}'),
			array($type, app_line($hint_key)),
			app_line('reg_barcode_1d_format')
		);
	}

	protected function apply_posted_barcode_fields($info_json, $fields)
	{
		$info = json_decode((string) $info_json, TRUE);

		if ( ! is_array($info))
		{
			$info = array();
		}

		$columns = $info['columns'] ?? array();
		$cells = $info['cells'] ?? array();
		$field_columns = array(
			'barcode_1d_type' => array('type of 1d barcode'),
			'barcode_1d'      => array('1d barcode', 'barcode'),
		);

		foreach ($fields as $key => $value)
		{
			if ( ! isset($field_columns[$key]))
			{
				continue;
			}

			$cell_index = $this->find_column_index($columns, $field_columns[$key]);

			if ($cell_index === NULL)
			{
				return NULL;
			}

			while (count($cells) <= $cell_index)
			{
				$cells[] = '';
			}

			$cells[$cell_index] = $value;
		}

		$info['cells'] = array_values($cells);

		return json_encode($info);
	}

	protected function barcode_cell_index($columns)
	{
		return $this->find_column_index($columns, array('1d barcode', 'barcode'));
	}

	protected function find_column_index($columns, $names)
	{
		$normalized = array();

		foreach (array_values((array) $columns) as $index => $column)
		{
			$normalized[$index] = $this->normalize_column_name($column);
		}

		foreach ((array) $names as $name)
		{
			$target = $this->normalize_column_name($name);

			foreach ($normalized as $index => $column)
			{
				if ($column === $target)
				{
					return (int) $index;
				}
			}
		}

		return NULL;
	}

	protected function normalize_column_name($column)
	{
		$name = strtolower(trim((string) $column));
		$name = preg_replace('/\s+/', ' ', $name);

		return $name;
	}

	protected function apply_barcode_to_item_info($info_json, $barcode)
	{
		$info = json_decode((string) $info_json, TRUE);

		if ( ! is_array($info))
		{
			$info = array();
		}

		$cell_index = $this->barcode_cell_index($info['columns'] ?? array());

		if ($cell_index === NULL)
		{
			return json_encode($info);
		}

		$cells = $info['cells'] ?? array();

		while (count($cells) <= $cell_index)
		{
			$cells[] = '';
		}

		$cells[$cell_index] = $barcode;
		$info['cells'] = array_values($cells);

		return json_encode($info);
	}

	protected function json_response($payload, $status = 200)
	{
		return $this->output
			->set_content_type('application/json')
			->set_status_header($status)
			->set_output(json_encode($payload));
	}
}
