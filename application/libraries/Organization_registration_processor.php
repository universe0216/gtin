<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Organization_registration_processor {

	protected $CI;
	protected $storage_root;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->storage_root = FCPATH.'storage'.DIRECTORY_SEPARATOR.'organization-registrations'.DIRECTORY_SEPARATOR;
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

		$this->validate_no_duplicate_registrations($normalized);

		$month_dir = $this->storage_root.date('Y-m').DIRECTORY_SEPARATOR;
		$this->ensure_directory($month_dir);

		$results = array(
			'registrations' => array(),
			'tabs'          => array(),
		);

		foreach ($normalized as $file)
		{
			$result = $this->process_single_zip($file, $month_dir, $account_id);
			$results['registrations'][] = $result['registration'];
			$results['tabs'][] = $result['tab'];
		}

		return $results;
	}

	public function format_registration_tab($registration, $saved_items)
	{
		return $this->build_tab_data($registration, $saved_items);
	}

	protected function validate_no_duplicate_registrations($files)
	{
		$this->CI->load->model('organization_registration_model');
		$seen = array();

		foreach ($files as $file)
		{
			$parsed = $this->parse_zip_filename($file['name']);
			$key = $parsed['procedure_number'].'|'.$file['name'];

			if (isset($seen[$key]))
			{
				throw new RuntimeException('Duplicate file in upload: '.$file['name'].'.');
			}

			$seen[$key] = TRUE;

			if ($this->CI->organization_registration_model->exists_by_file_and_procedure($file['name'], $parsed['procedure_number']))
			{
				throw new RuntimeException(
					'Registration '.$parsed['procedure_number'].' with file '.$file['name'].' already exists.'
				);
			}
		}
	}

	protected function process_single_zip($file, $month_dir, $account_id)
	{
		$original_name = $file['name'];
		$parsed = $this->parse_zip_filename($original_name);
		$folder_name = $parsed['procedure_number'].'_'.$parsed['organization_name'];
		$registration_dir = $month_dir.$folder_name.DIRECTORY_SEPARATOR;
		$this->ensure_directory($registration_dir);

		$zip_path = $registration_dir.$original_name;

		if ( ! move_uploaded_file($file['tmp_name'], $zip_path))
		{
			throw new RuntimeException('Failed to store '.$original_name.'.');
		}

		$this->extract_zip($zip_path, $registration_dir);

		$xls_path = $this->find_spreadsheet($registration_dir);

		if ( ! $xls_path)
		{
			throw new RuntimeException('No spreadsheet found in '.$original_name.'.');
		}

		$sheet = $this->parse_spreadsheet($xls_path);
		$organizations = $sheet['rows'];
		$now = date('Y-m-d H:i:s');
		$relative_storage = 'storage/organization-registrations/'.date('Y-m').'/'.$folder_name.'/';

		$this->CI->load->model('organization_registration_model');
		$this->CI->load->model('organization_registration_item_model');

		$organization_registration_id = $this->CI->organization_registration_model->insert(array(
			'file_name'        => $original_name,
			'procedure_number' => $parsed['procedure_number'],
			'status'           => 'uploaded',
			'account_id'       => (int) $account_id,
			'total_items'      => count($organizations),
			'storage_path'     => $relative_storage,
		));

		if ( ! $organization_registration_id)
		{
			throw new RuntimeException('Failed to save registration record for '.$original_name.'.');
		}

		$db_items = array();

		foreach ($organizations as $organization)
		{
			$db_items[] = array(
				'organization_registration_id' => $organization_registration_id,
				'org_registration_id'        => $organization['org_registration_id'],
				'gs1_prefix'                   => $organization['gs1_prefix'],
				'name'                         => $organization['name'],
				'parent_organization_name'     => $organization['parent_organization_name'],
				'created_at'                   => $now,
			);
		}

		$this->CI->organization_registration_item_model->insert_batch($db_items);
		$registration = $this->CI->organization_registration_model->get($organization_registration_id);
		$saved_items = $this->CI->organization_registration_item_model->get_by_organization_registration($organization_registration_id);

		return array(
			'registration' => $registration,
			'tab'          => $this->build_tab_data($registration, $saved_items),
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
			throw new RuntimeException(
				'Invalid zip filename format: '.$filename.'. Expected [procedure_number]_[organization_name].zip'
			);
		}

		return array(
			'procedure_number'  => $matches[1],
			'organization_name' => trim($matches[2]),
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

		$header_map = array();
		$start_index = 0;
		$first_cell = isset($rows[0][0]) ? trim((string) $rows[0][0]) : '';

		if ($first_cell !== '' && ! ctype_digit($first_cell))
		{
			$header_map = $this->build_header_map($this->normalize_row_cells($rows[0], $column_count));
			$start_index = 1;
		}

		$organizations = array();
		$seen = array();

		for ($index = $start_index; $index < count($rows); $index++)
		{
			$cells = $this->normalize_row_cells($rows[$index], $column_count);

			if ($this->is_empty_row($cells))
			{
				continue;
			}

			$item = $this->map_row_to_item($cells, $header_map);

			if ($item['org_registration_id'] === '')
			{
				continue;
			}

			if ($item['name'] === '')
			{
				$item['name'] = $item['org_registration_id'];
			}

			$key = $item['org_registration_id'].'|'.$item['name'];

			if (isset($seen[$key]))
			{
				continue;
			}

			$seen[$key] = TRUE;
			$organizations[] = $item;
		}

		if (empty($organizations))
		{
			throw new RuntimeException('No organization rows found in spreadsheet.');
		}

		return array(
			'rows' => $organizations,
		);
	}

	protected function build_header_map($headers)
	{
		$map = array();

		foreach ($headers as $index => $header)
		{
			$key = mb_strtolower(trim((string) $header), 'UTF-8');

			if ($key === '')
			{
				continue;
			}

			if ($this->header_matches($key, array('org registration id', 'registration id', 'org id', 'id', 'number')))
			{
				$map['org_registration_id'] = $index;
			}
			elseif ($this->header_matches($key, array('name', 'organization', 'organization name')))
			{
				$map['name'] = $index;
			}
			elseif ($this->header_matches($key, array('gs1 prefix', 'gs1', 'prefix')))
			{
				$map['gs1_prefix'] = $index;
			}
			elseif ($this->header_matches($key, array('parent organization', 'parent organization name', 'parent', 'parent name')))
			{
				$map['parent_organization_name'] = $index;
			}
		}

		return $map;
	}

	protected function header_matches($header, $needles)
	{
		foreach ($needles as $needle)
		{
			if ($header === $needle || strpos($header, $needle) !== FALSE)
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	protected function map_row_to_item($cells, $header_map)
	{
		return array(
			'org_registration_id'      => $this->cell_value($cells, $header_map, 'org_registration_id', 0),
			'name'                     => $this->cell_value($cells, $header_map, 'name', 1),
			'gs1_prefix'               => $this->cell_value($cells, $header_map, 'gs1_prefix', 2),
			'parent_organization_name' => $this->cell_value($cells, $header_map, 'parent_organization_name', 3),
		);
	}

	protected function cell_value($cells, $header_map, $field, $fallback_index)
	{
		$index = isset($header_map[$field]) ? $header_map[$field] : $fallback_index;

		return isset($cells[$index]) ? trim((string) $cells[$index]) : '';
	}

	protected function build_tab_data($registration, $saved_items)
	{
		$columns = array(
			'Org Registration ID',
			'Name',
			'GS1 Prefix',
			'Parent Organization',
		);
		$rows = array();

		foreach ($saved_items as $item)
		{
			$rows[] = array(
				'id'                  => (int) $item['id'],
				'org_registration_id' => $item['org_registration_id'],
				'cells'               => array(
					$item['org_registration_id'],
					$item['name'],
					$item['gs1_prefix'] ?? '',
					$item['parent_organization_name'] ?? '',
				),
			);
		}

		$organization_name = $this->organization_name_from_file($registration['file_name']);

		return array(
			'organization_registration_id' => (int) $registration['id'],
			'file_name'                    => $registration['file_name'],
			'procedure_number'             => $registration['procedure_number'],
			'organization_name'            => $organization_name,
			'processor_name'               => $registration['processor_name'] ?? '',
			'status'                       => $registration['status'],
			'created_at'                   => $registration['created_at'],
			'total_items'                  => (int) $registration['total_items'],
			'columns'                      => $columns,
			'rows'                         => $rows,
		);
	}

	protected function organization_name_from_file($file_name)
	{
		$basename = pathinfo($file_name, PATHINFO_FILENAME);

		if (preg_match('/^\d+_(.+)$/u', $basename, $matches))
		{
			return trim($matches[1]);
		}

		return '';
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
}
