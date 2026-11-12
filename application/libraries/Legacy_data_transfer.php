<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Transfer locations, organizations, and products from goods_schema.
 * Designed for large volumes (10k+ orgs, 250k+ products) via chunked steps.
 */
class Legacy_data_transfer {

	protected $CI;
	protected $old_db;
	protected $chunk_size = 2000;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->helper(array('product_key', 'standard_number'));
	}

	public function prepare_runtime()
	{
		@ini_set('max_execution_time', '0');
		@ini_set('max_input_time', '-1');
		@ini_set('memory_limit', '2048M');
		@set_time_limit(0);
		ignore_user_abort(TRUE);

		if (function_exists('apache_setenv'))
		{
			@apache_setenv('no-gzip', '1');
		}

		@ini_set('zlib.output_compression', '0');
		@ini_set('implicit_flush', '1');

		while (ob_get_level() > 0)
		{
			@ob_end_flush();
		}

		@ob_implicit_flush(TRUE);
	}

	public function clear_target()
	{
		return $this->clear_tables(array('products', 'organizations', 'locations'));
	}

	public function clear_tables(array $tables)
	{
		$allowed = array(
			'products'      => TRUE,
			'organizations' => TRUE,
			'locations'     => TRUE,
		);
		$new_db = $this->CI->db;
		$this->tune_db_session($new_db);
		$new_db->save_queries = FALSE;

		$new_db->query('SET FOREIGN_KEY_CHECKS=0');

		foreach ($tables as $table)
		{
			$table = strtolower(trim((string) $table));

			if ( ! isset($allowed[$table]))
			{
				continue;
			}

			$new_db->query('TRUNCATE TABLE '.$table);
		}

		$new_db->query('SET FOREIGN_KEY_CHECKS=1');

		return array(
			'cleared' => TRUE,
			'tables'  => array_values(array_filter(array_map(function ($table) use ($allowed) {
				$table = strtolower(trim((string) $table));

				return isset($allowed[$table]) ? $table : NULL;
			}, $tables))),
		);
	}

	public function transfer_locations_step()
	{
		$this->open_old_db();
		$new_db = $this->CI->db;
		$this->tune_db_session($new_db);
		$new_db->save_queries = FALSE;

		$rows = $this->old_db
			->order_by('OrgID', 'ASC')
			->get('publictbl')
			->result_array();

		$now = date('Y-m-d H:i:s');
		$ids = array();
		$parents = array();
		$batch = array();

		foreach ($rows as $row)
		{
			$id = (int) ($row['OrgID'] ?? 0);

			if ($id < 1)
			{
				continue;
			}

			$parent_id = $this->nullable_int($row['ParentID'] ?? NULL);
			$parents[$id] = $parent_id;
			$ids[$id] = TRUE;
			$batch[] = array(
				'id'          => $id,
				'parent_id'   => NULL,
				'level'       => 1,
				'sort_order'  => $id,
				'name'        => $this->text($row['Name'] ?? '', 'Location '.$id),
				'tag'         => $this->nullable_text($row['Tag'] ?? NULL, 50),
				'description' => NULL,
				'created_at'  => $now,
				'updated_at'  => $now,
			);
		}

		foreach ($batch as &$item)
		{
			$parent_id = isset($parents[$item['id']]) ? $parents[$item['id']] : NULL;

			if ($parent_id !== NULL && $parent_id > 0 && isset($ids[$parent_id]) && $parent_id !== $item['id'])
			{
				$item['parent_id'] = $parent_id;
			}
			else
			{
				$item['parent_id'] = NULL;
			}

			$item['level'] = $this->location_level($item['id'], $parents, $ids);
		}
		unset($item);

		$new_db->query('SET FOREIGN_KEY_CHECKS=0');

		try
		{
			$this->insert_batches($new_db, 'locations', $batch);
		}
		finally
		{
			$new_db->query('SET FOREIGN_KEY_CHECKS=1');
		}

		if ( ! empty($ids))
		{
			$max_id = max(array_keys($ids));
			$new_db->query('ALTER TABLE locations AUTO_INCREMENT = '.((int) $max_id + 1));
		}

		return array(
			'count' => count($ids),
		);
	}

	public function transfer_organizations_step($offset = 0, $limit = NULL)
	{
		$this->open_old_db();
		$new_db = $this->CI->db;
		$this->tune_db_session($new_db);
		$new_db->save_queries = FALSE;

		$limit = $limit === NULL ? $this->chunk_size : max(1, (int) $limit);
		$offset = max(0, (int) $offset);
		$has_currency = $this->old_db->field_exists('currency', 'interemp');
		$location_ids = $this->location_id_set($new_db);
		$now = date('Y-m-d H:i:s');

		$rows = $this->old_db
			->order_by('RegNum', 'ASC')
			->limit($limit, $offset)
			->get('interemp')
			->result_array();

		$existing = $this->organization_reg_map($new_db);
		$batch = array();
		$seen = array();

		foreach ($rows as $row)
		{
			$reg_number = $this->nullable_int($row['RegNum'] ?? NULL);

			if ($reg_number === NULL || isset($seen[$reg_number]) || isset($existing[$reg_number]))
			{
				continue;
			}

			$seen[$reg_number] = TRUE;
			$location_id = $this->nullable_int($row['ParentID'] ?? NULL);

			if ($location_id !== NULL && ! isset($location_ids[$location_id]))
			{
				$location_id = NULL;
			}

			$item = array(
				'name'         => $this->text($row['EmpName'] ?? '', 'Organization '.$reg_number),
				'reg_number'   => $reg_number,
				'parent_org'   => $this->nullable_text($row['upEmp'] ?? NULL, 255),
				'location_id'  => $location_id,
				'gs1_prefix'   => $this->nullable_text($row['EmpKey'] ?? NULL, 50),
				'init_date'    => $this->nullable_date($row['StartDate'] ?? NULL),
				'reg_date'     => $this->nullable_date($row['ValidDate'] ?? NULL),
				'expiry_date'  => $this->nullable_date($row['ReRegDate'] ?? NULL),
				'address'      => $this->nullable_text($row['Place'] ?? NULL),
				'phone'        => $this->nullable_text($row['Phone'] ?? NULL, 255),
				'c_code'       => $this->nullable_text($row['c_code'] ?? NULL, 50),
				'currency'     => NULL,
				'description'  => NULL,
				'created_at'   => $now,
				'updated_at'   => $now,
			);

			if ($has_currency)
			{
				$item['currency'] = $this->nullable_int($row['currency'] ?? NULL);
			}

			$batch[] = $item;
		}

		$this->insert_batches($new_db, 'organizations', $batch);
		$this->touch_runtime();

		$fetched = count($rows);
		$done = $fetched < $limit;

		return array(
			'count'       => count($batch),
			'offset'      => $offset,
			'next_offset' => $offset + $fetched,
			'done'        => $done,
		);
	}

	public function transfer_products_step($offset = 0, $limit = NULL)
	{
		$this->open_old_db();
		$new_db = $this->CI->db;
		$this->tune_db_session($new_db);
		$new_db->save_queries = FALSE;

		$limit = $limit === NULL ? $this->chunk_size : max(1, (int) $limit);
		$offset = max(0, (int) $offset);
		$org_by_reg = $this->organization_reg_map($new_db);
		$now = date('Y-m-d H:i:s');

		$rows = $this->old_db
			->order_by('id', 'ASC')
			->limit($limit, $offset)
			->get('interbar')
			->result_array();

		$batch = array();
		$max_id = 0;
		$count = 0;

		foreach ($rows as $row)
		{
			$id = (int) ($row['id'] ?? 0);

			if ($id < 1)
			{
				continue;
			}

			if ($id > $max_id)
			{
				$max_id = $id;
			}

			$name = $this->text($row['JPName'] ?? '', 'Product '.$id);
			$standard_number = $this->nullable_text(
				standard_number_normalize($row['GuGyokID'] ?? ''),
				100
			);
			$brand = $this->nullable_text($row['SangHo'] ?? NULL, 255);
			$package_type = $this->nullable_text($row['PJType'] ?? NULL, 100);
			$reg_number = $this->nullable_int($row['RegNum'] ?? NULL);
			$org_id = ($reg_number !== NULL && isset($org_by_reg[$reg_number]))
				? $org_by_reg[$reg_number]
				: NULL;

			$batch[] = array(
				'id'               => $id,
				'name'             => $name,
				'standard_number'  => $standard_number,
				'brand'            => $brand,
				'package_type'     => $package_type,
				'normalized_key'   => product_build_normalized_key(
					$name,
					$standard_number !== NULL ? $standard_number : '',
					$brand !== NULL ? $brand : '',
					$package_type !== NULL ? $package_type : ''
				),
				'org_id'           => $org_id,
				'effect'           => $this->nullable_text($row['Effect'] ?? NULL),
				'usage'            => $this->nullable_text($row['Using'] ?? NULL),
				'price'            => $this->nullable_text($row['Value'] ?? NULL, 255),
				'effective_period' => $this->nullable_text(
					isset($row['Apoint']) ? $row['Apoint'] : ($row['Appoint'] ?? NULL),
					255
				),
				'test_date'        => $this->nullable_date($row['JTestDate'] ?? NULL),
				'test_status'      => $this->nullable_text($row['JTestStatus'] ?? NULL, 255),
				'description'      => $this->nullable_text($row['Etc'] ?? NULL),
				'barcode_1d'       => $this->nullable_text($row['LignSign'] ?? NULL, 255),
				'barcode_1d_type'  => $this->nullable_text($row['NumKind'] ?? NULL, 50),
				'barcode_2d'       => $this->nullable_text(
					isset($row['LignSign2']) ? $row['LignSign2'] : ($row['LisngSign2'] ?? NULL),
					255
				),
				'barcode_2d_type'  => $this->nullable_text($row['NumKind2'] ?? NULL, 50),
				'reg_date'         => $this->nullable_date($row['RegDate'] ?? NULL),
				'processor_name'   => $this->nullable_text($row['RegMan'] ?? NULL, 255),
				'global_id'        => $this->nullable_int($row['BuGangID'] ?? NULL),
				'c_code'           => $this->nullable_text($row['c_code'] ?? NULL, 255),
				'created_at'       => $now,
				'updated_at'       => $now,
			);
			$count++;
		}

		$new_db->query('SET FOREIGN_KEY_CHECKS=0');

		try
		{
			$this->insert_batches($new_db, 'products', $batch);
		}
		finally
		{
			$new_db->query('SET FOREIGN_KEY_CHECKS=1');
		}

		$fetched = count($rows);
		$done = $fetched < $limit;

		if ($done && $max_id > 0)
		{
			$current_max = (int) $new_db->select_max('id')->get('products')->row()->id;
			$auto = max($max_id, $current_max) + 1;
			$new_db->query('ALTER TABLE products AUTO_INCREMENT = '.$auto);
		}

		$this->touch_runtime();

		return array(
			'count'       => $count,
			'offset'      => $offset,
			'next_offset' => $offset + $fetched,
			'done'        => $done,
			'max_id'      => $max_id,
		);
	}

	public function source_counts()
	{
		$this->open_old_db();

		return array(
			'locations'     => (int) $this->old_db->count_all('publictbl'),
			'organizations' => (int) $this->old_db->count_all('interemp'),
			'products'      => (int) $this->old_db->count_all('interbar'),
		);
	}

	protected function open_old_db()
	{
		if ($this->old_db)
		{
			$this->tune_db_session($this->old_db);
			$this->old_db->save_queries = FALSE;

			return;
		}

		$this->old_db = $this->CI->load->database('goods', TRUE);

		if ( ! $this->old_db)
		{
			throw new RuntimeException(app_line('data_transfer_old_db_failed'));
		}

		$this->old_db->save_queries = FALSE;
		$this->tune_db_session($this->old_db);
		$this->assert_old_tables();
	}

	protected function tune_db_session($db)
	{
		$db->query('SET SESSION wait_timeout=28800');
		$db->query('SET SESSION interactive_timeout=28800');
		$db->query('SET SESSION net_read_timeout=600');
		$db->query('SET SESSION net_write_timeout=600');
	}

	protected function touch_runtime()
	{
		@set_time_limit(0);
	}

	protected function assert_old_tables()
	{
		foreach (array('publictbl', 'interemp', 'interbar') as $table)
		{
			if ( ! $this->old_db->table_exists($table))
			{
				throw new RuntimeException(app_line('data_transfer_missing_table', $table));
			}
		}
	}

	protected function location_id_set($new_db)
	{
		$ids = array();
		$rows = $new_db->select('id')->get('locations')->result_array();

		foreach ($rows as $row)
		{
			$ids[(int) $row['id']] = TRUE;
		}

		return $ids;
	}

	protected function organization_reg_map($new_db)
	{
		$map = array();
		$rows = $new_db->select('id, reg_number')->get('organizations')->result_array();

		foreach ($rows as $row)
		{
			$reg = $this->nullable_int($row['reg_number'] ?? NULL);

			if ($reg !== NULL)
			{
				$map[$reg] = (int) $row['id'];
			}
		}

		return $map;
	}

	protected function insert_batches($db, $table, array $rows)
	{
		if (empty($rows))
		{
			return;
		}

		foreach (array_chunk($rows, 200) as $chunk)
		{
			$this->touch_runtime();

			if ( ! $db->insert_batch($table, $chunk))
			{
				throw new RuntimeException(app_line('data_transfer_insert_failed', $table));
			}
		}
	}

	protected function location_level($id, array $parents, array $ids)
	{
		$level = 1;
		$current = $id;
		$guard = array();

		while (isset($parents[$current]) && $parents[$current] !== NULL && $parents[$current] > 0)
		{
			$parent = (int) $parents[$current];

			if ($parent === $current || isset($guard[$parent]) || ! isset($ids[$parent]))
			{
				break;
			}

			$guard[$parent] = TRUE;
			$level++;
			$current = $parent;

			if ($level > 50)
			{
				break;
			}
		}

		return $level;
	}

	protected function text($value, $fallback = '')
	{
		$value = trim((string) $value);

		return $value !== '' ? $value : $fallback;
	}

	protected function nullable_text($value, $max = NULL)
	{
		if ($value === NULL)
		{
			return NULL;
		}

		$value = trim((string) $value);

		if ($value === '')
		{
			return NULL;
		}

		if ($max !== NULL && function_exists('mb_substr'))
		{
			$value = mb_substr($value, 0, (int) $max);
		}
		elseif ($max !== NULL)
		{
			$value = substr($value, 0, (int) $max);
		}

		return $value;
	}

	protected function nullable_int($value)
	{
		if ($value === NULL || $value === '')
		{
			return NULL;
		}

		if ( ! is_numeric($value))
		{
			return NULL;
		}

		return (int) $value;
	}

	protected function nullable_date($value)
	{
		$value = trim((string) $value);

		if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00')
		{
			return NULL;
		}

		$timestamp = strtotime($value);

		if ($timestamp === FALSE)
		{
			return NULL;
		}

		return date('Y-m-d', $timestamp);
	}
}
