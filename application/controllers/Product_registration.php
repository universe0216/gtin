<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class Product_registration extends AuthenticatedController {

	protected $import_per_page = 10;

	public function __construct()
	{
		parent::__construct();
		$this->load->helper(array('url', 'file'));
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
			return $this->json_response(array('success' => FALSE, 'message' => 'Invalid request method.'), 405);
		}

		if (empty($_FILES['zip_files']))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Please select at least one zip file.'), 422);
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
				'message' => 'Failed to process uploaded files.',
			), 500);
		}

		return $this->json_response(array(
			'success'       => TRUE,
			'message'       => count($result['registrations']).' registration file(s) uploaded successfully.',
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
				'message' => 'Registration not found.',
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
			return $this->json_response(array('success' => FALSE, 'message' => 'Invalid request method.'), 405);
		}

		$reason = trim((string) $this->input->post('reason'));

		if ($reason === '')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => 'Please enter a rejection reason.',
			), 422);
		}

		$item = $this->product_registration_item_model->get($id);

		if ( ! $item)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Product item not found.'), 404);
		}

		$registration = $this->product_registration_model->get($item['product_registration_id']);

		if ( ! $registration)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Registration not found.'), 404);
		}

		if ($registration['status'] === 'completed')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => 'Completed procedures cannot be changed.',
			), 422);
		}

		if ($item['status'] === 'rejected')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => 'This product has already been rejected.',
			), 422);
		}

		if ($item['status'] === 'approved')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => 'This product has already been accepted.',
			), 422);
		}

		$was_pending = $item['status'] === 'pending';

		if ( ! $this->product_registration_item_model->update($id, array(
			'status'  => 'rejected',
			'message' => $reason,
		)))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Failed to reject product.'), 500);
		}

		if ($was_pending)
		{
			$this->product_registration_model->update($registration['id'], array(
				'rejected' => (int) $registration['rejected'] + 1,
			));
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => 'Product rejected successfully.',
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
			return $this->json_response(array('success' => FALSE, 'message' => 'Invalid request method.'), 405);
		}

		$item = $this->product_registration_item_model->get($id);

		if ( ! $item)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Product item not found.'), 404);
		}

		$registration = $this->product_registration_model->get($item['product_registration_id']);

		if ( ! $registration)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Registration not found.'), 404);
		}

		if ($registration['status'] === 'completed')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => 'Completed procedures cannot be changed.',
			), 422);
		}

		if ($item['status'] === 'approved')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => 'This product has already been accepted.',
			), 422);
		}

		if ($item['status'] === 'rejected')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => 'Rejected products cannot be accepted.',
			), 422);
		}

		$was_pending = $item['status'] === 'pending';
		$barcode = $this->normalize_item_barcode($this->input->post('barcode'));
		$update_data = array(
			'status'  => 'approved',
			'message' => NULL,
		);

		if ($barcode !== NULL)
		{
			$update_data['barcode'] = $barcode;
			$update_data['info'] = $this->apply_barcode_to_item_info($item['info'], $barcode);
		}

		if ( ! $this->product_registration_item_model->update($id, $update_data))
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Failed to accept product.'), 500);
		}

		if ($was_pending)
		{
			$this->product_registration_model->update($registration['id'], array(
				'approved' => (int) $registration['approved'] + 1,
			));
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => 'Product accepted successfully.',
			'data'    => array(
				'id'                      => (int) $id,
				'product_registration_id' => (int) $item['product_registration_id'],
				'status'                  => 'approved',
				'barcode'                 => $barcode,
			),
		));
	}

	public function delete($id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Invalid request method.'), 405);
		}

		$registration = $this->product_registration_model->get($id);

		if ( ! $registration)
		{
			return $this->json_response(array('success' => FALSE, 'message' => 'Registration not found.'), 404);
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
			return $this->json_response(array('success' => FALSE, 'message' => 'Failed to delete registration.'), 500);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => 'Registration stopped successfully.',
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

	protected function barcode_cell_index($columns)
	{
		foreach ((array) $columns as $index => $column)
		{
			if (strtolower(trim((string) $column)) === 'barcode')
			{
				return (int) $index;
			}
		}

		return 7;
	}

	protected function apply_barcode_to_item_info($info_json, $barcode)
	{
		$info = json_decode((string) $info_json, TRUE);

		if ( ! is_array($info))
		{
			$info = array();
		}

		$cell_index = $this->barcode_cell_index($info['columns'] ?? array());
		$cells = $info['cells'] ?? array();

		while (count($cells) <= $cell_index)
		{
			$cells[] = '';
		}

		$cells[$cell_index] = $barcode;
		$info['cells'] = $cells;

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
