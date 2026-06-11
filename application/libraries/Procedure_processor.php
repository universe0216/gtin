<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Procedure_processor {

	protected $CI;
	protected $storage_root;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->storage_root = FCPATH.'storage'.DIRECTORY_SEPARATOR;
	}

	public function process_uploads($files, $account_id)
	{
		if (empty($files))
		{
			throw new RuntimeException('No files were uploaded.');
		}

		$normalized = $this->normalize_uploaded_files($files);

		if (empty($normalized))
		{
			throw new RuntimeException('No valid zip files were uploaded.');
		}

		$this->validate_no_duplicate_procedures($normalized);

		$month_dir = $this->storage_root.date('Y-m').DIRECTORY_SEPARATOR;
		$this->ensure_directory($month_dir);

		$results = array(
			'procedures' => array(),
			'tabs'       => array(),
			'items'      => array(),
		);

		foreach ($normalized as $file)
		{
			$result = $this->process_single_zip($file, $month_dir, $account_id);
			$results['procedures'][] = $result['procedure'];
			$results['tabs'][] = $result['tab'];
			$results['items'] = array_merge($results['items'], $result['items']);
		}

		return $results;
	}

	public function format_procedure_tab($procedure, $saved_items)
	{
		return $this->build_tab_data($procedure, $saved_items);
	}

	protected function validate_no_duplicate_procedures($files)
	{
		$this->CI->load->model('procedure_model');
		$seen = array();

		foreach ($files as $file)
		{
			$parsed = $this->parse_zip_filename($file['name']);
			$key = $parsed['procedure_number'].'|'.$file['name'];

			if (isset($seen[$key]))
			{
				throw new RuntimeException(
					'Duplicate file in upload: '.$file['name'].'.'
				);
			}

			$seen[$key] = TRUE;

			if ($this->CI->procedure_model->exists_by_file_and_procedure($file['name'], $parsed['procedure_number']))
			{
				throw new RuntimeException(
					'Procedure '.$parsed['procedure_number'].' with file '.$file['name'].' already exists.'
				);
			}
		}
	}

	protected function process_single_zip($file, $month_dir, $account_id)
	{
		$original_name = $file['name'];
		$parsed = $this->parse_zip_filename($original_name);
		$folder_name = $parsed['procedure_number'].'_'.$parsed['organization_name'];
		$procedure_dir = $month_dir.$folder_name.DIRECTORY_SEPARATOR;
		$this->ensure_directory($procedure_dir);

		$zip_path = $procedure_dir.$original_name;

		if ( ! move_uploaded_file($file['tmp_name'], $zip_path))
		{
			throw new RuntimeException('Failed to store '.$original_name.'.');
		}

		$this->extract_zip($zip_path, $procedure_dir);

		$xls_path = $this->find_spreadsheet($procedure_dir);

		if ( ! $xls_path)
		{
			throw new RuntimeException('No spreadsheet found in '.$original_name.'.');
		}

		$sheet = $this->parse_spreadsheet($xls_path);
		$headers = $sheet['headers'];
		$products = $sheet['rows'];
		$images = $this->collect_images($procedure_dir);
		$now = date('Y-m-d H:i:s');
		$relative_storage = 'storage/'.date('Y-m').'/'.$folder_name.'/';

		$this->CI->load->model('procedure_model');
		$this->CI->load->model('procedure_item_model');

		$procedure_id = $this->CI->procedure_model->insert(array(
			'file_name'          => $original_name,
			'procedure_number'   => $parsed['procedure_number'],
			'organization_name'  => $parsed['organization_name'],
			'account_id'         => (int) $account_id,
			'status'             => 'uploaded',
			'total_products'     => count($products),
			'approved'           => 0,
			'rejected'           => 0,
			'storage_path'       => $relative_storage,
		));

		if ( ! $procedure_id)
		{
			throw new RuntimeException('Failed to save procedure record for '.$original_name.'.');
		}

		$db_items = array();

		foreach ($products as $product)
		{
			$matched_images = $this->match_product_images($product['product_procedure_number'], $images);
			$image_paths = array();
			$image_urls = array();

			foreach ($matched_images as $image_path)
			{
				$relative_image = $this->relative_public_path($image_path);
				$image_paths[] = $relative_image;
				$image_urls[] = base_url($relative_image);
			}

			$info = array(
				'procedure_number'  => $parsed['procedure_number'],
				'organization_name' => $parsed['organization_name'],
				'zip_file'          => $original_name,
				'columns'           => $headers,
				'cells'             => $product['cells'],
				'images'            => $image_paths,
				'image_urls'        => $image_urls,
				'has_image'         => ! empty($matched_images),
			);

			$db_items[] = array(
				'procedure_id'             => $procedure_id,
				'product_procedure_number' => $product['product_procedure_number'],
				'name'                     => $product['name'],
				'info'                     => json_encode($info),
				'status'                   => 'pending',
				'message'                  => NULL,
				'barcode'                  => NULL,
				'created_at'               => $now,
			);
		}

		$this->CI->procedure_item_model->insert_batch($db_items);
		$procedure = $this->CI->procedure_model->get($procedure_id);
		$saved_items = $this->CI->procedure_item_model->get_by_procedure($procedure_id);

		return array(
			'procedure' => $procedure,
			'tab'       => $this->build_tab_data($procedure, $saved_items, $headers),
			'items'     => $this->build_response_items($saved_items, $parsed, $original_name),
		);
	}

	protected function normalize_uploaded_files($files)
	{
		$normalized = array();

		if (is_array($files['name']))
		{
			$count = count($files['name']);

			for ($i = 0; $i < $count; $i++)
			{
				if ((int) $files['error'][$i] !== UPLOAD_ERR_OK || empty($files['name'][$i]))
				{
					continue;
				}

				$normalized[] = array(
					'name'     => $files['name'][$i],
					'tmp_name' => $files['tmp_name'][$i],
					'error'    => $files['error'][$i],
				);
			}
		}
		elseif ((int) $files['error'] === UPLOAD_ERR_OK && ! empty($files['name']))
		{
			$normalized[] = $files;
		}

		return $normalized;
	}

	protected function parse_zip_filename($filename)
	{
		$basename = pathinfo($filename, PATHINFO_FILENAME);

		if ( ! preg_match('/^(\d+)_(.+)$/u', $basename, $matches))
		{
			throw new RuntimeException('Invalid zip filename format: '.$filename.'. Expected [procedure_number]_[organization_name].zip');
		}

		return array(
			'procedure_number'  => $matches[1],
			'organization_name'   => trim($matches[2]),
		);
	}

	protected function extract_zip($zip_path, $destination)
	{
		if ( ! class_exists('ZipArchive'))
		{
			throw new RuntimeException('ZipArchive extension is not available.');
		}

		$zip = new ZipArchive();

		if ($zip->open($zip_path) !== TRUE)
		{
			throw new RuntimeException('Failed to open zip archive.');
		}

		if ( ! $zip->extractTo($destination))
		{
			$zip->close();
			throw new RuntimeException('Failed to extract zip archive.');
		}

		$zip->close();
	}

	protected function find_spreadsheet($directory)
	{
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file)
		{
			if ( ! $file->isFile())
			{
				continue;
			}

			$extension = strtolower($file->getExtension());

			if (in_array($extension, array('xls', 'xlsx', 'csv'), TRUE))
			{
				return $file->getPathname();
			}
		}

		return NULL;
	}

	protected function parse_spreadsheet($file_path)
	{
		if ( ! class_exists(IOFactory::class))
		{
			throw new RuntimeException('Spreadsheet reader is not available. Run composer install.');
		}

		$this->CI->load->library('spreadsheet_value_binder');
		Cell::setValueBinder(new Spreadsheet_value_binder());

		$reader = IOFactory::createReaderForFile($file_path);
		$reader->setReadDataOnly(TRUE);
		$spreadsheet = $reader->load($file_path);
		$rows = $spreadsheet->getActiveSheet()->toArray(NULL, TRUE, TRUE, FALSE);

		if (empty($rows))
		{
			throw new RuntimeException('No rows found in spreadsheet.');
		}

		$column_count = 0;

		foreach ($rows as $row)
		{
			$column_count = max($column_count, count($row));
		}

		$headers = array();
		$start_index = 0;
		$first_cell = isset($rows[0][0]) ? trim((string) $rows[0][0]) : '';

		if ($first_cell !== '' && ! ctype_digit($first_cell))
		{
			$headers = $this->normalize_row_cells($rows[0], $column_count);
			$start_index = 1;
		}
		else
		{
			for ($i = 0; $i < $column_count; $i++)
			{
				$headers[] = $this->column_label($i);
			}
		}

		$products = array();
		$seen = array();

		for ($index = $start_index; $index < count($rows); $index++)
		{
			$cells = $this->normalize_row_cells($rows[$index], $column_count);

			if ($this->is_empty_row($cells))
			{
				continue;
			}

			$number = isset($cells[0]) ? trim((string) $cells[0]) : '';
			$name = isset($cells[1]) ? trim((string) $cells[1]) : '';

			if ($number === '')
			{
				continue;
			}

			if ($name === '')
			{
				$name = $number;
			}

			$key = $number.'|'.implode('|', $cells);

			if (isset($seen[$key]))
			{
				continue;
			}

			$seen[$key] = TRUE;
			$products[] = array(
				'product_procedure_number' => $number,
				'name'                     => $name,
				'cells'                    => $cells,
			);
		}

		if (empty($products))
		{
			throw new RuntimeException('No product rows found in spreadsheet.');
		}

		return array(
			'headers' => $headers,
			'rows'    => $products,
		);
	}

	protected function collect_images($directory)
	{
		$images = array();
		$allowed = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp');
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file)
		{
			if ( ! $file->isFile())
			{
				continue;
			}

			$extension = strtolower($file->getExtension());

			if (in_array($extension, $allowed, TRUE))
			{
				$images[] = $file->getPathname();
			}
		}

		return $images;
	}

	protected function match_product_images($product_procedure_number, $images)
	{
		$number_key = $this->normalize_match_key($product_procedure_number);
		$matched = array();

		foreach ($images as $image_path)
		{
			$basename = pathinfo($image_path, PATHINFO_FILENAME);
			$key = $this->normalize_match_key($basename);

			if ($key === $number_key || strpos($key, $number_key.'_') === 0)
			{
				$matched[] = $image_path;
			}
		}

		return $matched;
	}

	protected function normalize_match_key($value)
	{
		$value = trim((string) $value);
		$value = preg_replace('/\s+/u', ' ', $value);
		$value = mb_strtolower($value, 'UTF-8');

		return str_replace(' ', '_', $value);
	}

	protected function ensure_directory($path)
	{
		if (is_dir($path))
		{
			return;
		}

		if ( ! mkdir($path, 0755, TRUE) && ! is_dir($path))
		{
			throw new RuntimeException('Failed to create storage directory.');
		}
	}

	protected function relative_public_path($absolute_path)
	{
		$relative = str_replace(FCPATH, '', $absolute_path);
		$relative = str_replace('\\', '/', $relative);

		return ltrim($relative, '/');
	}

	protected function build_response_items($saved_items, $parsed, $original_name)
	{
		$items = array();

		foreach ($saved_items as $item)
		{
			$info = json_decode($item['info'], TRUE);

			if ( ! is_array($info))
			{
				$info = array();
			}

			$items[] = array(
				'id'                       => (int) $item['id'],
				'procedure_id'             => (int) $item['procedure_id'],
				'product_procedure_number' => $item['product_procedure_number'],
				'name'                     => $item['name'],
				'procedure_number'         => $parsed['procedure_number'],
				'organization_name'        => $parsed['organization_name'],
				'file_name'                => $original_name,
				'columns'                  => $info['columns'] ?? array(),
				'cells'                    => $info['cells'] ?? array(),
				'has_image'                => ! empty($info['has_image']),
				'image_urls'               => $info['image_urls'] ?? array(),
				'info'                     => $info,
			);
		}

		return $items;
	}

	protected function build_tab_data($procedure, $saved_items, $fallback_headers = array())
	{
		$columns = $fallback_headers;
		$rows = array();

		foreach ($saved_items as $item)
		{
			$info = json_decode($item['info'], TRUE);

			if ( ! is_array($info))
			{
				$info = array();
			}

			if (empty($columns) && ! empty($info['columns']))
			{
				$columns = $info['columns'];
			}

			$cells = $info['cells'] ?? array($item['product_procedure_number'], $item['name']);

			if (empty($columns))
			{
				$columns = array();

				for ($i = 0; $i < count($cells); $i++)
				{
					$columns[] = $this->column_label($i);
				}
			}

			$rows[] = array(
				'id'                       => (int) $item['id'],
				'product_procedure_number' => $item['product_procedure_number'],
				'cells'                    => $this->normalize_row_cells($cells, count($columns)),
				'image_urls'               => $info['image_urls'] ?? array(),
				'has_image'                => ! empty($info['has_image']),
			);
		}

		return array(
			'procedure_id'      => (int) $procedure['id'],
			'file_name'         => $procedure['file_name'],
			'procedure_number'  => $procedure['procedure_number'],
			'organization_name' => $procedure['organization_name'],
			'processor_name'    => $procedure['processor_name'] ?? '',
			'status'            => $procedure['status'],
			'created_at'        => $procedure['created_at'],
			'total_products'    => (int) $procedure['total_products'],
			'columns'           => $columns,
			'rows'              => $rows,
		);
	}

	protected function normalize_row_cells($row, $column_count)
	{
		$cells = array();

		for ($i = 0; $i < $column_count; $i++)
		{
			$value = isset($row[$i]) ? $row[$i] : '';
			$cells[] = trim((string) $value);
		}

		return $cells;
	}

	protected function is_empty_row($cells)
	{
		foreach ($cells as $cell)
		{
			if ($cell !== '')
			{
				return FALSE;
			}
		}

		return TRUE;
	}

	protected function column_label($index)
	{
		$label = '';
		$index = (int) $index;

		do
		{
			$label = chr(65 + ($index % 26)).$label;
			$index = intdiv($index, 26) - 1;
		}
		while ($index >= 0);

		return $label;
	}
}
