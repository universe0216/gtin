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

		$month_dir = $this->storage_root.date('Y-m').DIRECTORY_SEPARATOR;
		$this->ensure_directory($month_dir);

		$results = array(
			'procedures' => array(),
			'items'      => array(),
		);

		foreach ($normalized as $file)
		{
			$result = $this->process_single_zip($file, $month_dir, $account_id);
			$results['procedures'][] = $result['procedure'];
			$results['items'] = array_merge($results['items'], $result['items']);
		}

		return $results;
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

		$products = $this->parse_spreadsheet($xls_path);
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
			$matched_images = $this->match_product_images($product, $images);
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
				'images'            => $image_paths,
				'image_urls'        => $image_urls,
				'has_image'         => ! empty($matched_images),
			);

			$db_items[] = array(
				'procedure_id'             => $procedure_id,
				'product_procedure_number' => $product['product_procedure_number'],
				'name'                     => $product['name'],
				'info'                     => json_encode($info),
				'created_at'               => $now,
			);
		}

		$this->CI->procedure_item_model->insert_batch($db_items);
		$saved_items = $this->build_response_items(
			$this->CI->procedure_item_model->get_by_procedure($procedure_id),
			$parsed,
			$original_name
		);

		return array(
			'procedure' => $this->CI->procedure_model->get($procedure_id),
			'items'     => $saved_items,
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
		$products = array();
		$seen = array();

		foreach ($rows as $index => $row)
		{
			$number = isset($row[0]) ? trim((string) $row[0]) : '';
			$name = isset($row[1]) ? trim((string) $row[1]) : '';

			if ($number === '' || $name === '')
			{
				continue;
			}

			if ($index === 0 && ! ctype_digit($number))
			{
				continue;
			}

			$key = $number.'|'.$name;

			if (isset($seen[$key]))
			{
				continue;
			}

			$seen[$key] = TRUE;
			$products[] = array(
				'product_procedure_number' => $number,
				'name'                     => $name,
			);
		}

		if (empty($products))
		{
			throw new RuntimeException('No product rows found in spreadsheet.');
		}

		return $products;
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

	protected function match_product_images($product, $images)
	{
		$expected = $this->normalize_match_key(
			$product['product_procedure_number'].'_'.$product['name']
		);
		$matched = array();

		foreach ($images as $image_path)
		{
			$basename = pathinfo($image_path, PATHINFO_FILENAME);
			$key = $this->normalize_match_key($basename);

			if ($key === $expected)
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
				'has_image'                => ! empty($info['has_image']),
				'image_urls'               => $info['image_urls'] ?? array(),
				'info'                     => $info,
			);
		}

		return $items;
	}
}
