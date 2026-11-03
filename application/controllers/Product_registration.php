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

		$counts = array();

		if ($was_pending || $was_approved)
		{
			$counts['rejected'] = (int) $registration['rejected'] + 1;

			if ($was_approved)
			{
				$counts['approved'] = max(0, (int) $registration['approved'] - 1);
			}
		}

		$registration_status = $this->mark_registration_processing($registration, $counts);

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('reg_reject_success'),
			'data'    => array(
				'id'                      => (int) $id,
				'product_registration_id' => (int) $item['product_registration_id'],
				'status'                  => 'rejected',
				'message'                 => $reason,
				'registration_status'     => $registration_status,
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

		$counts = array();

		if ($was_pending)
		{
			$counts['approved'] = (int) $registration['approved'] + 1;
		}

		$registration_status = $this->mark_registration_processing($registration, $counts);

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('reg_accept_success'),
			'data'    => array(
				'id'                      => (int) $id,
				'product_registration_id' => (int) $item['product_registration_id'],
				'status'                  => 'approved',
				'barcode'                 => $barcode,
				'registration_status'     => $registration_status,
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

	public function auto_assign_barcode($id = NULL)
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

		$barcode_type = trim((string) $this->input->post('barcode_1d_type', FALSE));

		if ($barcode_type === '')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_barcode_1d_type_required'),
			), 422);
		}

		$info = json_decode((string) $item['info'], TRUE);

		if ( ! is_array($info))
		{
			$info = array();
		}

		$cells = $info['cells'] ?? array();
		$factory_name = isset($cells[22]) ? trim((string) $cells[22]) : '';
		$reserved = $this->input->post('reserved');

		if ( ! is_array($reserved))
		{
			$reserved = array();
		}

		$barcode_index = $this->barcode_cell_index($info['columns'] ?? array());
		$siblings = $this->product_registration_item_model->get_by_product_registration($item['product_registration_id']);

		foreach ($siblings as $sibling)
		{
			if ((int) $sibling['id'] === (int) $item['id'])
			{
				continue;
			}

			$sibling_info = json_decode((string) $sibling['info'], TRUE);

			if ( ! is_array($sibling_info) || $barcode_index === NULL)
			{
				continue;
			}

			$sibling_cells = $sibling_info['cells'] ?? array();

			if (isset($sibling_cells[$barcode_index]) && trim((string) $sibling_cells[$barcode_index]) !== '')
			{
				$reserved[] = $sibling_cells[$barcode_index];
			}
		}

		$result = $this->product_registration_processor->assign_factory_barcode($factory_name, $barcode_type, $reserved);
		$messages = array(
			'factory' => app_line('reg_factory_not_registered'),
			'prefix'  => app_line('reg_auto_assign_no_prefix'),
			'full'    => app_line('reg_auto_assign_full'),
			'type'    => app_line('reg_auto_assign_unsupported'),
		);

		if ( ! empty($result['error']))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => $messages[$result['error']] ?? app_line('reg_auto_assign_unsupported'),
			), 422);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'data'    => array(
				'barcode' => $result['barcode'],
			),
		));
	}

	public function auto_process($id = NULL)
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

		if ($registration['status'] === 'completed')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_completed_locked'),
			), 422);
		}

		$items = $this->product_registration_item_model->get_by_product_registration($registration['id']);
		$step = $this->auto_process_step($registration, $items);

		return $this->json_response(array(
			'success' => empty($step['error']),
			'message' => $step['message'],
			'data'    => $step['data'],
		), empty($step['error']) ? 200 : (int) $step['status']);
	}

	public function save_products($id = NULL)
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

		if ($registration['status'] === 'completed')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_completed_locked'),
			), 422);
		}

		$this->load->model('product_model');
		$this->load->model('organization_model');

		$items = $this->product_registration_item_model->get_by_product_registration($registration['id']);
		$approved = array();
		$ready = array();

		foreach ($items as $item)
		{
			if (($item['status'] ?? '') !== 'approved')
			{
				continue;
			}

			$approved[] = $item;

			if ($this->item_barcode_1d($item) !== '')
			{
				$ready[] = $item;
			}
		}

		if (empty($ready))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => empty($approved) ? app_line('reg_save_none') : app_line('reg_save_no_barcode'),
			), 422);
		}

		$rows = array();

		foreach ($ready as $item)
		{
			$built = $this->product_row_from_registration_item($item, $registration);

			if (isset($built['error']))
			{
				return $this->json_response(array(
					'success' => FALSE,
					'message' => $built['error'],
				), 422);
			}

			$rows[] = $built;
		}

		$this->db->trans_begin();
		$saved = 0;

		foreach ($rows as $row)
		{
			$existing = $this->product_model->find_catalog_match($row['org_id'], $row['barcode_1d'], $row['name']);

			if ($existing)
			{
				$ok = $this->product_model->update($existing['id'], $row);
			}
			else
			{
				$ok = $this->product_model->insert($row);
			}

			if ( ! $ok)
			{
				$this->db->trans_rollback();

				return $this->json_response(array(
					'success' => FALSE,
					'message' => app_line('reg_save_failed'),
				), 500);
			}

			$saved++;
		}

		if ( ! $this->product_registration_model->update($registration['id'], array('status' => 'completed')))
		{
			$this->db->trans_rollback();

			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_save_failed'),
			), 500);
		}

		$this->db->trans_commit();

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('reg_save_success', $saved),
			'data'    => array(
				'saved'  => $saved,
				'status' => 'completed',
			),
		));
	}

	public function download($id = NULL)
	{
		$registration = $this->product_registration_model->get($id);

		if ( ! $registration)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_not_found')), 404);
		}

		if (strcasecmp((string) $registration['status'], 'completed') !== 0)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_download_not_completed'),
			), 422);
		}

		$this->load->library('product_registration_export');
		$items = $this->product_registration_item_model->get_by_product_registration($registration['id']);

		try
		{
			$archive = $this->product_registration_export->build($registration, $items);
		}
		catch (Exception $exception)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_download_failed'),
			), 500);
		}

		if ((int) $archive['count'] < 1)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('reg_download_empty'),
			), 422);
		}

		$this->load->helper('download');
		force_download($archive['filename'], $archive['binary']);
	}

	public function assignees()
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$this->load->model('account_model');
		$user = $this->auth->user();
		$accounts = $this->account_model->get_product_editors($user['id']);
		$users = array();

		foreach ($accounts as $account)
		{
			$users[] = array(
				'id'        => (int) $account['id'],
				'username'  => $account['username'],
				'full_name' => $account['full_name'],
				'is_admin'  => (bool) $account['is_admin'],
			);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'users'   => $users,
		));
	}

	public function assign($id = NULL)
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

		$user = $this->auth->user();

		if ((int) $registration['account_id'] !== (int) $user['id'])
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_send_forbidden')), 403);
		}

		$account_id = (int) $this->input->post('account_id');
		$this->load->model('account_model');
		$target = $this->account_model->get($account_id);
		$permissions = ($target && isset($target['permissions']) && is_array($target['permissions']))
			? $target['permissions']
			: array();
		$can_receive = $target
			&& ! empty($target['is_active'])
			&& ( ! empty($target['is_admin']) || in_array('product.edit', $permissions, TRUE))
			&& (int) $target['id'] !== (int) $user['id'];

		if ( ! $can_receive)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_send_user_invalid')), 422);
		}

		if ( ! $this->product_registration_model->update($registration['id'], array(
			'account_id' => (int) $target['id'],
		)))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_send_failed')), 500);
		}

		$name = trim((string) $target['full_name']) !== '' ? $target['full_name'] : $target['username'];

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('reg_send_success', $name),
			'data'    => array(
				'account_id' => (int) $target['id'],
				'full_name'  => $name,
			),
		));
	}

	public function incoming()
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$raw_ids = $this->input->get('ids');
		$known = array();

		if (is_array($raw_ids))
		{
			$known = $raw_ids;
		}
		elseif (is_string($raw_ids) && $raw_ids !== '')
		{
			$known = explode(',', $raw_ids);
		}

		$known = array_fill_keys(array_map('intval', $known), TRUE);
		$user = $this->auth->user();
		$registrations = $this->product_registration_model->get_incomplete_by_account($user['id']);
		$tabs = array();

		foreach ($registrations as $registration)
		{
			if (isset($known[(int) $registration['id']]))
			{
				continue;
			}

			$items = $this->product_registration_item_model->get_by_product_registration($registration['id']);
			$tabs[] = $this->product_registration_processor->format_product_registration_tab($registration, $items);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'tabs'    => $tabs,
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

		if ($registration['status'] === 'completed')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('reg_completed_locked')), 422);
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

	protected function product_row_from_registration_item($item, $registration)
	{
		$info = json_decode((string) $item['info'], TRUE);

		if ( ! is_array($info))
		{
			$info = array();
		}

		$columns = $info['columns'] ?? array();
		$cells = $info['cells'] ?? array();
		$factory_name = $this->registration_cell($columns, $cells, array('factory name'));

		if ($factory_name === '' && isset($cells[22]))
		{
			$factory_name = trim((string) $cells[22]);
		}

		$organization = $factory_name === '' ? NULL : $this->organization_model->get_by_name($factory_name);

		if ( ! $organization)
		{
			return array('error' => app_line('reg_factory_not_registered'));
		}

		$name = $this->registration_cell($columns, $cells, array('product name'));

		if ($name === '')
		{
			$name = trim((string) ($item['name'] ?? ''));
		}

		if ($name === '')
		{
			return array('error' => app_line('reg_save_failed'));
		}

		return array(
			'name'              => $this->limit_text($name, 255, ''),
			'standard_number'   => $this->limit_text($this->registration_cell($columns, $cells, array('standard')), 100),
			'brand'             => $this->limit_text($this->registration_cell($columns, $cells, array('brand', 'size of brand')), 255),
			'package_type'      => $this->limit_text($this->registration_cell($columns, $cells, array('package type')), 100),
			'org_id'            => (int) $organization['id'],
			'effect'            => $this->limit_text($this->registration_cell($columns, $cells, array('effect')), 65535),
			'usage'             => $this->limit_text($this->registration_cell($columns, $cells, array('usage')), 65535),
			'price'             => $this->limit_text($this->registration_cell($columns, $cells, array('price')), 255),
			'effective_period'  => $this->limit_text($this->registration_cell($columns, $cells, array('perios before expired', 'period before expired')), 255),
			'description'       => $this->limit_text($this->registration_cell($columns, $cells, array('notes', 'description')), 65535),
			'barcode_1d'        => $this->limit_text($this->registration_cell($columns, $cells, array('1d barcode', 'barcode')), 255),
			'barcode_1d_type'   => $this->limit_text($this->registration_cell($columns, $cells, array('type of 1d barcode')), 50),
			'barcode_2d'        => $this->limit_text($this->registration_cell($columns, $cells, array('2d barcode')), 255),
			'barcode_2d_type'   => $this->limit_text($this->registration_cell($columns, $cells, array('type of 2d barcode')), 50),
			'reg_date'          => $this->registration_date($this->registration_cell($columns, $cells, array('receivedate', 'receive date'))),
			'processor_name'    => $this->limit_text($registration['processor_name'] ?? '', 255),
			'c_code'            => $this->limit_text($this->registration_cell($columns, $cells, array('c_code', 'c code')), 255),
		);
	}

	protected function registration_cell($columns, $cells, $names)
	{
		$index = $this->find_column_index($columns, $names);

		if ($index === NULL || ! isset($cells[$index]))
		{
			return '';
		}

		return trim((string) $cells[$index]);
	}

	protected function registration_date($value)
	{
		$value = trim((string) $value);

		if ($value === '')
		{
			return NULL;
		}

		$time = strtotime($value);

		if ($time === FALSE)
		{
			return NULL;
		}

		return date('Y-m-d', $time);
	}

	protected function limit_text($value, $max, $empty = NULL)
	{
		$value = trim((string) $value);

		if ($value === '')
		{
			return $empty;
		}

		if (function_exists('mb_substr'))
		{
			return mb_substr($value, 0, $max);
		}

		return substr($value, 0, $max);
	}

	protected function auto_process_step($registration, $items)
	{
		$reserved = array();

		foreach ($items as $item)
		{
			$barcode = $this->item_barcode_1d($item);

			if ($barcode !== '')
			{
				$reserved[] = $barcode;
			}
		}

		foreach ($items as $item)
		{
			$info = $this->item_info($item);
			$is_rejected = ($item['status'] ?? '') === 'rejected';

			if ($is_rejected)
			{
				$type = $this->registration_cell(
					$info['columns'] ?? array(),
					$info['cells'] ?? array(),
					array('type of 1d barcode')
				);

				if ($this->item_barcode_1d($item) === '' && $type === '')
				{
					continue;
				}

				$info = $this->write_barcode_cells($info, '', '');

				if ($info === NULL)
				{
					return array(
						'error'   => TRUE,
						'status'  => 422,
						'message' => app_line('reg_barcode_field_missing'),
						'data'    => array('done' => FALSE, 'assigned' => array(), 'cleared' => array()),
					);
				}

				if ( ! $this->product_registration_item_model->update($item['id'], array(
					'info'    => json_encode($info),
					'barcode' => NULL,
				)))
				{
					return array(
						'error'   => TRUE,
						'status'  => 500,
						'message' => app_line('reg_barcode_save_failed'),
						'data'    => array('done' => FALSE, 'assigned' => array(), 'cleared' => array()),
					);
				}

				return array(
					'error'   => FALSE,
					'message' => app_line('reg_auto_process_cleared'),
					'data'    => array(
						'done'     => FALSE,
						'assigned' => array(),
						'cleared'  => array(array('id' => (int) $item['id'])),
					),
				);
			}

			if ($this->item_barcode_1d($item) !== '')
			{
				continue;
			}

			$cells = $info['cells'] ?? array();
			$factory_name = isset($cells[22]) ? trim((string) $cells[22]) : '';
			$result = $this->product_registration_processor->assign_factory_barcode($factory_name, 'EAN-13', $reserved);
			$messages = array(
				'factory' => app_line('reg_factory_not_registered'),
				'prefix'  => app_line('reg_auto_assign_no_prefix'),
				'full'    => app_line('reg_auto_assign_full'),
				'type'    => app_line('reg_auto_assign_unsupported'),
			);

			if ( ! empty($result['error']))
			{
				return array(
					'error'   => TRUE,
					'status'  => 422,
					'message' => $messages[$result['error']] ?? app_line('reg_auto_assign_unsupported'),
					'data'    => array('done' => FALSE, 'assigned' => array(), 'cleared' => array()),
				);
			}

			$info = $this->write_barcode_cells($info, $result['barcode'], 'EAN-13');

			if ($info === NULL)
			{
				return array(
					'error'   => TRUE,
					'status'  => 422,
					'message' => app_line('reg_barcode_field_missing'),
					'data'    => array('done' => FALSE, 'assigned' => array(), 'cleared' => array()),
				);
			}

			if ( ! $this->product_registration_item_model->update($item['id'], array(
				'info'    => json_encode($info),
				'status'  => 'approved',
				'message' => NULL,
			)))
			{
				return array(
					'error'   => TRUE,
					'status'  => 500,
					'message' => app_line('reg_barcode_save_failed'),
					'data'    => array('done' => FALSE, 'assigned' => array(), 'cleared' => array()),
				);
			}

			$counts = array();

			if (($item['status'] ?? '') !== 'approved')
			{
				$counts['approved'] = (int) $registration['approved'] + 1;
			}

			$registration_status = $this->mark_registration_processing($registration, $counts);

			return array(
				'error'   => FALSE,
				'message' => app_line('reg_auto_process_done', 1),
				'data'    => array(
					'done'                 => FALSE,
					'registration_status'  => $registration_status,
					'assigned' => array(array(
						'id'      => (int) $item['id'],
						'barcode' => $result['barcode'],
						'status'  => 'approved',
					)),
					'cleared'  => array(),
				),
			);
		}

		return array(
			'error'   => FALSE,
			'message' => app_line('reg_auto_process_none'),
			'data'    => array(
				'done'     => TRUE,
				'assigned' => array(),
				'cleared'  => array(),
			),
		);
	}

	protected function mark_registration_processing($registration, $extra = array())
	{
		if ( ! $registration || strcasecmp((string) $registration['status'], 'completed') === 0)
		{
			return $registration ? (string) $registration['status'] : '';
		}

		$data = is_array($extra) ? $extra : array();

		if (strcasecmp((string) $registration['status'], 'processing') !== 0)
		{
			$data['status'] = 'processing';
		}

		if ($data)
		{
			$this->product_registration_model->update($registration['id'], $data);
		}

		return 'processing';
	}

	protected function write_barcode_cells($info, $barcode, $type)
	{
		$columns = $info['columns'] ?? array();
		$cells = $info['cells'] ?? array();
		$barcode_index = $this->barcode_cell_index($columns);
		$type_index = $this->find_column_index($columns, array('type of 1d barcode'));

		if ($barcode_index === NULL)
		{
			return NULL;
		}

		while (count($cells) <= $barcode_index)
		{
			$cells[] = '';
		}

		$cells[$barcode_index] = $barcode;

		if ($type_index !== NULL)
		{
			while (count($cells) <= $type_index)
			{
				$cells[] = '';
			}

			$cells[$type_index] = $type;
		}

		$info['cells'] = array_values($cells);

		return $info;
	}

	protected function item_info($item)
	{
		$info = json_decode((string) ($item['info'] ?? ''), TRUE);

		return is_array($info) ? $info : array();
	}

	protected function item_barcode_1d($item)
	{
		$info = $this->item_info($item);

		return $this->registration_cell(
			$info['columns'] ?? array(),
			$info['cells'] ?? array(),
			array('1d barcode', 'barcode')
		);
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
