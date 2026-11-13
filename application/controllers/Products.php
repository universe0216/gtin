<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/BasicController.php';

class Products extends BasicController {

	protected $entity = 'products';
	protected $entity_label = 'Products';
	protected $list_view = 'products/index';
	protected $list_scripts = array(
		'assets/js/entity_history.js',
		'assets/js/product_detail_modal.js',
		'assets/js/products.js',
	);
	protected $add_url_route = 'product_registration';
	protected $list_search_placeholder = 'Search name, brand, barcode, standard number, organization...';
	protected $list_empty_message = 'No products found.';
	protected $list_empty_search_message = 'No products match your search.';
	protected $list_columns = array(
		array('key' => 'name', 'label' => 'Name', 'pinned' => TRUE),
		array('key' => 'organization_name', 'label' => 'Organization'),
		array('key' => 'brand', 'label' => 'Brand'),
		array('key' => 'standard_number', 'label' => 'Standard Number'),
		array('key' => 'barcode_1d', 'label' => 'Barcode'),
		array('key' => 'barcode_1d_type', 'label' => 'Barcode Type'),
		array('key' => 'barcode_2d', 'label' => '2D Barcode'),
		array('key' => 'barcode_2d_type', 'label' => '2D Barcode Type'),
		array('key' => 'package_type', 'label' => 'Package Type'),
		array('key' => 'price', 'label' => 'Price'),
		array('key' => 'reg_date', 'label' => 'Registration Date'),
		array('key' => 'processor_name', 'label' => 'Processor'),
	);
	protected $detail_fields = array(
		array('name' => 'name', 'label' => 'Name', 'type' => 'text'),
		array('name' => 'brand', 'label' => 'Brand', 'type' => 'text'),
		array('name' => 'standard_number', 'label' => 'Standard Number', 'type' => 'text'),
		array('name' => 'category_id', 'label' => 'Category', 'type' => 'category'),
		array('name' => 'organization_name', 'label' => 'Organization', 'type' => 'text', 'readonly' => TRUE),
		array(
			'name'    => 'barcode_1d_type',
			'label'   => 'Barcode Type',
			'type'    => 'select',
			'options' => array('EAN-13', 'EAN-8', 'UPC-A', 'UPC-E', 'ITF-14', 'GS1-128', 'Code 128', 'Code 39'),
		),
		array('name' => 'barcode_1d', 'label' => 'Barcode', 'type' => 'text'),
		array(
			'name'    => 'barcode_2d_type',
			'label'   => '2D Barcode Type',
			'type'    => 'select',
			'options' => array('QR', 'Data Matrix', 'GS1 DataMatrix', 'PDF417', 'Aztec'),
		),
		array('name' => 'barcode_2d', 'label' => '2D Barcode', 'type' => 'text'),
		array('name' => 'package_type', 'label' => 'Package Type', 'type' => 'text'),
		array('name' => 'price', 'label' => 'Price', 'type' => 'text'),
		array('name' => 'effective_period', 'label' => 'Effective Period', 'type' => 'text'),
		array('name' => 'reg_date', 'label' => 'Registration Date', 'type' => 'date'),
		array('name' => 'test_date', 'label' => 'Test Date', 'type' => 'date'),
		array('name' => 'test_status', 'label' => 'Test Status', 'type' => 'text'),
		array('name' => 'processor_name', 'label' => 'Processor', 'type' => 'text'),
		array('name' => 'c_code', 'label' => 'Country Code', 'type' => 'text'),
		array('name' => 'global_id', 'label' => 'Global ID', 'type' => 'text'),
		array('name' => 'effect', 'label' => 'Effect', 'type' => 'textarea'),
		array('name' => 'usage', 'label' => 'Usage', 'type' => 'textarea'),
		array('name' => 'description', 'label' => 'Description', 'type' => 'textarea'),
	);
	protected $fields = array(
		array(
			'name'     => 'name',
			'label'    => 'Name',
			'type'     => 'text',
			'required' => TRUE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'brand',
			'label'    => 'Brand',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'standard_number',
			'label'    => 'Standard Number',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[100]'),
		),
		array(
			'name'     => 'package_type',
			'label'    => 'Package Type',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[100]'),
		),
		array(
			'name'     => 'org_id',
			'label'    => 'Organization',
			'type'     => 'number',
			'required' => FALSE,
			'rules'    => array('trim', 'integer'),
		),
		array(
			'name'     => 'effect',
			'label'    => 'Effect',
			'type'     => 'textarea',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
		array(
			'name'     => 'usage',
			'label'    => 'Usage',
			'type'     => 'textarea',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
		array(
			'name'     => 'price',
			'label'    => 'Price',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'effective_period',
			'label'    => 'Effective Period',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'test_date',
			'label'    => 'Test Date',
			'type'     => 'date',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
		array(
			'name'     => 'test_status',
			'label'    => 'Test Status',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'description',
			'label'    => 'Description',
			'type'     => 'textarea',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
		array(
			'name'     => 'barcode_1d',
			'label'    => 'Barcode',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'barcode_1d_type',
			'label'    => 'Barcode Type',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[50]'),
		),
		array(
			'name'     => 'barcode_2d',
			'label'    => '2D Barcode',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'barcode_2d_type',
			'label'    => '2D Barcode Type',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[50]'),
		),
		array(
			'name'     => 'reg_date',
			'label'    => 'Registration Date',
			'type'     => 'date',
			'required' => FALSE,
			'rules'    => array('trim'),
		),
		array(
			'name'     => 'processor_name',
			'label'    => 'Processor',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[255]'),
		),
		array(
			'name'     => 'global_id',
			'label'    => 'Global ID',
			'type'     => 'number',
			'required' => FALSE,
			'rules'    => array('trim', 'integer'),
		),
		array(
			'name'     => 'c_code',
			'label'    => 'Country Code',
			'type'     => 'text',
			'required' => FALSE,
			'rules'    => array('trim', 'max_length[255]'),
		),
	);

	protected $permission_view = 'product.view';
	protected $permission_edit = 'product.edit';

	public function __construct()
	{
		parent::__construct();
		$this->load->model('product_model', 'model');
		$this->load->model('location_model');
	}

	protected function list_view_data()
	{
		return array(
			'location_nodes' => $this->location_model->tree_nodes(),
			'is_admin'       => $this->auth->is_admin(),
		);
	}

	public function generate_normalized_keys()
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_invalid_method')), 405);
		}

		$this->auth->require_admin();

		@ini_set('max_execution_time', '0');
		@ini_set('memory_limit', '1024M');
		@set_time_limit(0);
		ignore_user_abort(TRUE);

		$updated = $this->model->regenerate_all_normalized_keys(1000);

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('product_generate_key_done', $updated),
			'data'    => array(
				'updated' => $updated,
			),
		));
	}

	protected function list_query_params()
	{
		$params = parent::list_query_params();
		$params['location_ids'] = $this->location_model->filter_ids($this->input->get('location_id'));

		return $params;
	}

	public function update($id = NULL)
	{
		$this->auth->require_permission($this->permission_edit);

		if ( ! $this->model->get($id))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('product_not_found'),
			), 404);
		}

		if ( ! $this->validate_fields())
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => validation_errors(' ', ' '),
			), 422);
		}

		$data = $this->collect_field_values();

		if ($this->input->post('org_id') === NULL)
		{
			unset($data['org_id']);
		}

		unset($data['category_id']);

		if ( ! $this->model->update($id, $data))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('common_failed_update'),
			), 500);
		}

		if ($this->input->post('category_id') !== NULL)
		{
			$this->load->model('product_category_model');
			$this->product_category_model->set_product_category(
				(int) $id,
				(int) $this->input->post('category_id')
			);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('common_record_updated'),
			'data'    => $this->model->get_with_organization($id),
		));
	}

	public function detail($id = NULL)
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$this->auth->require_permission($this->permission_view);
		$record = $this->model->get_with_organization($id);

		if ( ! $record)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('product_not_found'),
			), 404);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'data'    => $record,
		));
	}

	public function history($id = NULL)
	{
		if ( ! $this->input->is_ajax_request())
		{
			show_404();
		}

		$this->auth->require_permission($this->permission_view);
		$record = $this->model->get($id);

		if ( ! $record)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('product_not_found'),
			), 404);
		}

		$entity_id = ! empty($record['origin_id'])
			? (int) $record['origin_id']
			: (int) $record['id'];

		$this->load->library('data_logger');

		return $this->json_response(array(
			'success' => TRUE,
			'data'    => $this->data_logger->history_for(Data_logger::ENTITY_PRODUCT, $entity_id),
		));
	}

	public function download($id = NULL)
	{
		$this->auth->require_permission($this->permission_view);
		$product = $this->model->get_with_organization($id);

		if ( ! $product)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('product_not_found'),
			), 404);
		}

		if (trim((string) ($product['barcode_1d'] ?? '')) === '')
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('product_download_no_barcode'),
			), 422);
		}

		$this->load->model('product_registration_item_model');
		$this->load->library('product_registration_export');
		$source = $this->product_registration_item_model->find_latest_approved_match(
			$product['barcode_1d'],
			$product['name']
		);

		try
		{
			$archive = $this->product_registration_export->build_catalog($product, $source ?: NULL);
		}
		catch (Exception $exception)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('product_download_failed'),
			), 500);
		}

		if ((int) $archive['count'] < 1)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('product_download_no_barcode'),
			), 422);
		}

		$this->load->helper('download');
		force_download($archive['filename'], $archive['binary']);
	}

	public function delete_selected()
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_invalid_method')), 405);
		}

		$this->auth->require_permission($this->permission_edit);
		$raw_ids = $this->input->post('ids');

		if ( ! is_array($raw_ids))
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('product_delete_selected_none')), 422);
		}

		$ids = array();

		foreach ($raw_ids as $raw_id)
		{
			$id = (int) $raw_id;

			if ($id > 0)
			{
				$ids[$id] = $id;
			}
		}

		$ids = array_values($ids);

		if (count($ids) < 2)
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('product_delete_selected_none')), 422);
		}

		if (count($ids) > 100)
		{
			$ids = array_slice($ids, 0, 100);
		}

		$this->load->library('catalog_archieve');
		$deleted = 0;
		$failed = 0;

		foreach ($ids as $id)
		{
			$result = $this->catalog_archieve->archive_product($id);

			if ( ! empty($result['ok']))
			{
				$deleted++;
			}
			else
			{
				$failed++;
			}
		}

		if ($deleted < 1)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('product_delete_failed'),
			), 500);
		}

		$message = $failed > 0
			? app_line('product_delete_selected_partial', $deleted, $failed)
			: app_line('product_delete_selected_done', $deleted);

		return $this->json_response(array(
			'success' => TRUE,
			'message' => $message,
			'deleted' => $deleted,
			'failed'  => $failed,
		));
	}

	public function delete($id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_invalid_method')), 405);
		}

		$this->auth->require_permission($this->permission_edit);
		$this->load->library('catalog_archieve');
		$result = $this->catalog_archieve->archive_product($id);

		if (empty($result['ok']))
		{
			$status = ($result['code'] ?? '') === 'not_found' ? 404 : 500;

			return $this->json_response(array(
				'success' => FALSE,
				'message' => $status === 404 ? app_line('product_not_found') : app_line('product_delete_failed'),
			), $status);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'message' => app_line('product_deleted'),
		));
	}

	public function batch_delete_preview()
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_invalid_method')), 405);
		}

		$this->auth->require_permission($this->permission_edit);

		try
		{
			$rows = $this->parse_batch_delete_file($this->batch_delete_uploaded_path());
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
				'message' => app_line('product_delete_batch_failed'),
			), 500);
		}

		if (empty($rows))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('product_delete_batch_empty'),
			), 422);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'rows'    => $rows,
		));
	}

	public function batch_delete()
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			return $this->json_response(array('success' => FALSE, 'message' => app_line('common_invalid_method')), 405);
		}

		$this->auth->require_permission($this->permission_edit);

		$raw_barcodes = $this->input->post('barcodes');

		if ( ! is_array($raw_barcodes))
		{
			$raw_barcodes = array();
		}

		$barcodes = array();

		foreach ($raw_barcodes as $raw)
		{
			$value = trim((string) $raw);

			if ($value !== '')
			{
				$barcodes[$value] = $value;
			}
		}

		$barcodes = array_values($barcodes);

		if (empty($barcodes))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('product_delete_batch_empty'),
			), 422);
		}

		$ids = $this->model->get_ids_by_barcodes($barcodes);

		if (empty($ids))
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('product_delete_batch_none'),
			), 422);
		}

		$this->load->library('catalog_archieve');
		$deleted = 0;
		$failed = 0;

		foreach ($ids as $id)
		{
			$result = $this->catalog_archieve->archive_product($id);

			if ( ! empty($result['ok']))
			{
				$deleted++;
			}
			else
			{
				$failed++;
			}
		}

		if ($deleted < 1)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => app_line('product_delete_failed'),
			), 500);
		}

		$message = $failed > 0
			? app_line('product_delete_batch_partial', $deleted, $failed)
			: app_line('product_delete_batch_done', $deleted);

		return $this->json_response(array(
			'success' => TRUE,
			'message' => $message,
			'deleted' => $deleted,
			'failed'  => $failed,
		));
	}

	protected function batch_delete_uploaded_path()
	{
		if (empty($_FILES['file']) || ! is_array($_FILES['file']))
		{
			throw new RuntimeException(app_line('product_delete_batch_invalid'));
		}

		$file = $_FILES['file'];
		$error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || ! is_uploaded_file($file['tmp_name']))
		{
			throw new RuntimeException(app_line('product_delete_batch_invalid'));
		}

		$name = strtolower((string) ($file['name'] ?? ''));

		if ( ! preg_match('/\.(xls|xlsx)$/', $name))
		{
			throw new RuntimeException(app_line('product_delete_batch_invalid'));
		}

		return $file['tmp_name'];
	}

	protected function parse_batch_delete_file($file_path)
	{
		if ( ! class_exists('\PhpOffice\PhpSpreadsheet\IOFactory'))
		{
			throw new RuntimeException(app_line('upload_reader_missing'));
		}

		$this->load->library('spreadsheet_value_binder');
		\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new Spreadsheet_value_binder());

		$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file_path);
		$reader->setReadDataOnly(TRUE);
		$spreadsheet = $reader->load($file_path);
		$sheet_rows = $spreadsheet->getActiveSheet()->toArray(NULL, TRUE, TRUE, FALSE);

		if (empty($sheet_rows))
		{
			throw new RuntimeException(app_line('upload_no_rows'));
		}

		$start = 0;
		$first = $this->batch_delete_cell_string(isset($sheet_rows[0][0]) ? $sheet_rows[0][0] : '');
		$second = strtolower($this->batch_delete_cell_string(isset($sheet_rows[0][1]) ? $sheet_rows[0][1] : ''));
		$third = strtolower($this->batch_delete_cell_string(isset($sheet_rows[0][2]) ? $sheet_rows[0][2] : ''));

		if ($first !== '' && ! ctype_digit($first))
		{
			$start = 1;
		}
		elseif ($second === 'name' || strpos($third, 'barcode') !== FALSE)
		{
			$start = 1;
		}

		$rows = array();
		$seen = array();

		for ($i = $start; $i < count($sheet_rows); $i++)
		{
			$cells = isset($sheet_rows[$i]) && is_array($sheet_rows[$i]) ? $sheet_rows[$i] : array();
			$row_index = $this->batch_delete_cell_string(isset($cells[0]) ? $cells[0] : '');
			$name = $this->batch_delete_cell_string(isset($cells[1]) ? $cells[1] : '');
			$barcode = $this->batch_delete_cell_string(isset($cells[2]) ? $cells[2] : '');

			if ($barcode === '')
			{
				continue;
			}

			if (isset($seen[$barcode]))
			{
				continue;
			}

			$seen[$barcode] = TRUE;
			$rows[] = array(
				'row_index'  => $row_index !== '' ? $row_index : (string) (count($rows) + 1),
				'name'       => $name,
				'barcode_1d' => $barcode,
			);
		}

		return $rows;
	}

	protected function batch_delete_cell_string($value)
	{
		if ($value === NULL || $value === '')
		{
			return '';
		}

		if (is_int($value))
		{
			return (string) $value;
		}

		if (is_float($value))
		{
			if (floor($value) == $value)
			{
				return sprintf('%.0f', $value);
			}

			return rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');
		}

		return trim((string) $value);
	}

	protected function get_add_label()
	{
		return app_line('add_products');
	}

	protected function get_detail_config()
	{
		$this->load->model('category_model');

		return array(
			'enabled'       => TRUE,
			'detailUrl'     => site_url('products/detail'),
			'historyUrl'    => site_url('products/history'),
			'downloadUrl'   => site_url('products/download'),
			'updateUrl'     => site_url('products/update'),
			'canEdit'       => $this->auth->can($this->permission_edit),
			'deleteUrl'     => site_url('products/delete'),
			'canDelete'     => $this->auth->can($this->permission_edit),
			'fields'        => $this->localize_labeled($this->detail_fields),
			'categoryNodes' => $this->category_model->tree_nodes(3),
			'rowAction'     => array(
				'type'    => 'delete',
				'url'     => site_url('products/delete'),
				'enabled' => $this->auth->can($this->permission_edit),
			),
		);
	}
}
