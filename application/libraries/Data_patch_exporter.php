<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Builds full / patch JSON exports for an external consumer app.
 * Each file is { companies: [], products: [] } with at most 5000 records.
 */
class Data_patch_exporter {

	const TYPE_FULL = 'full';
	const TYPE_PATCH = 'patch';

	const MAPPER_ORG = 'orgn_export_mapper';
	const MAPPER_PRODUCT = 'proudct_export_mapper';

	const MAX_RECORDS_PER_FILE = 5000;
	const DB_CHUNK = 2000;

	const ACTION_ADDED = 'added';
	const ACTION_UPDATED = 'updated';
	const ACTION_DELETED = 'deleted';

	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->model('settings_model');
		$this->CI->load->model('export_history_model');
		$this->CI->load->model('data_log_model');
	}

	public function prepare_runtime()
	{
		@ini_set('max_execution_time', '0');
		@ini_set('memory_limit', '1024M');
		@set_time_limit(0);
	}

	public function has_completed_export()
	{
		return (bool) $this->CI->export_history_model->latest_completed();
	}

	public function last_completed()
	{
		return $this->CI->export_history_model->latest_completed();
	}

	public function start($type, $account_id = NULL)
	{
		$type = $type === self::TYPE_PATCH ? self::TYPE_PATCH : self::TYPE_FULL;
		$last = $this->last_completed();

		if ($type === self::TYPE_PATCH && ! $last)
		{
			throw new RuntimeException(app_line('data_patch_no_baseline'));
		}

		$since_at = ($type === self::TYPE_PATCH)
			? ($last['finished_at'] ?: $last['created_at'])
			: NULL;

		$org_mapper = $this->CI->settings_model->get_json(self::MAPPER_ORG);
		$product_mapper = $this->CI->settings_model->get_json(self::MAPPER_PRODUCT);

		if (empty($org_mapper) || empty($product_mapper))
		{
			throw new RuntimeException(app_line('data_patch_mapper_missing'));
		}

		$patch = NULL;

		if ($type === self::TYPE_PATCH)
		{
			$patch = $this->build_patch_queue($since_at, $org_mapper, $product_mapper);

			if (empty($patch['queue']))
			{
				return array(
					'empty'         => TRUE,
					'type'          => $type,
					'company_total' => 0,
					'product_total' => 0,
					'since_at'      => $since_at,
				);
			}
		}

		$id = $this->CI->export_history_model->insert(array(
			'type'       => $type,
			'status'     => 'running',
			'account_id' => $account_id ? (int) $account_id : NULL,
			'since_at'   => $since_at,
		));

		if ( ! $id)
		{
			throw new RuntimeException(app_line('data_patch_start_failed'));
		}

		$workdir = $this->workdir($id);

		if ( ! is_dir($workdir) && ! @mkdir($workdir, 0755, TRUE))
		{
			throw new RuntimeException(app_line('data_patch_workdir_failed'));
		}

		$state = array(
			'phase'             => $type === self::TYPE_FULL ? 'companies' : 'patch',
			'org_offset'        => 0,
			'product_offset'    => 0,
			'patch_offset'      => 0,
			'file_index'        => 1,
			'current_count'     => 0,
			'current_companies' => array(),
			'current_products'  => array(),
			'company_total'     => 0,
			'product_total'     => 0,
			'company_done'      => 0,
			'product_done'      => 0,
			'files'             => array(),
			'org_mapper'        => $org_mapper,
			'product_mapper'    => $product_mapper,
			'since_at'          => $since_at,
			'patch_queue'       => array(),
		);

		if ($type === self::TYPE_FULL)
		{
			$state['company_total'] = (int) $this->CI->db->count_all('organizations');
			$state['product_total'] = (int) $this->CI->db->count_all('products');
		}
		else
		{
			$state['patch_queue'] = $patch['queue'];
			$state['company_total'] = $patch['company_total'];
			$state['product_total'] = $patch['product_total'];
		}

		$this->write_state($id, $state);
		$this->CI->export_history_model->update($id, array(
			'meta' => json_encode(array(
				'company_total' => $state['company_total'],
				'product_total' => $state['product_total'],
			), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		));

		return array(
			'empty'         => FALSE,
			'export_id'     => $id,
			'type'          => $type,
			'company_total' => $state['company_total'],
			'product_total' => $state['product_total'],
			'since_at'      => $since_at,
		);
	}

	public function process_chunk($export_id)
	{
		$export = $this->CI->export_history_model->get($export_id);

		if ( ! $export || $export['status'] !== 'running')
		{
			throw new RuntimeException(app_line('data_patch_not_running'));
		}

		$state = $this->read_state($export_id);

		if ($export['type'] === self::TYPE_FULL)
		{
			$state = $this->process_full_chunk($export_id, $state);
		}
		else
		{
			$state = $this->process_patch_chunk($export_id, $state);
		}

		if ($state['phase'] === 'finalize')
		{
			$state = $this->flush_file($export_id, $state, TRUE);
			$zip = $this->build_zip($export_id, $state);
			$finished_at = date('Y-m-d H:i:s');

			$this->CI->export_history_model->update($export_id, array(
				'status'        => 'completed',
				'file_count'    => count($state['files']),
				'company_count' => (int) $state['company_done'],
				'product_count' => (int) $state['product_done'],
				'storage_path'  => $zip['path'],
				'zip_name'      => $zip['name'],
				'finished_at'   => $finished_at,
				'meta'          => json_encode(array(
					'files' => $state['files'],
				), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			));

			$this->write_state($export_id, $state);

			return array(
				'done'          => TRUE,
				'export_id'     => (int) $export_id,
				'download_url'  => site_url('data_patch/download/'.$export_id),
				'file_count'    => count($state['files']),
				'company_count' => (int) $state['company_done'],
				'product_count' => (int) $state['product_done'],
				'zip_name'      => $zip['name'],
				'processed'     => (int) $state['company_done'] + (int) $state['product_done'],
				'total'         => (int) $state['company_total'] + (int) $state['product_total'],
			);
		}

		$this->write_state($export_id, $state);

		return array(
			'done'          => FALSE,
			'export_id'     => (int) $export_id,
			'phase'         => $state['phase'],
			'processed'     => (int) $state['company_done'] + (int) $state['product_done'],
			'total'         => (int) $state['company_total'] + (int) $state['product_total'],
			'company_done'  => (int) $state['company_done'],
			'company_total' => (int) $state['company_total'],
			'product_done'  => (int) $state['product_done'],
			'product_total' => (int) $state['product_total'],
			'file_count'    => count($state['files']),
		);
	}

	public function mark_failed($export_id, $message)
	{
		$this->CI->export_history_model->update($export_id, array(
			'status'        => 'failed',
			'error_message' => $this->limit_text($message, 2000),
			'finished_at'   => date('Y-m-d H:i:s'),
		));
	}

	public function download_path($export_id)
	{
		$export = $this->CI->export_history_model->get($export_id);

		if ( ! $export || $export['status'] !== 'completed' || empty($export['storage_path']))
		{
			return NULL;
		}

		if ( ! is_file($export['storage_path']))
		{
			return NULL;
		}

		return array(
			'path' => $export['storage_path'],
			'name' => $export['zip_name'] ?: ('data_patch_'.$export_id.'.zip'),
		);
	}

	protected function process_full_chunk($export_id, array $state)
	{
		if ($state['phase'] === 'companies')
		{
			$rows = $this->CI->db
				->order_by('id', 'ASC')
				->limit(self::DB_CHUNK, (int) $state['org_offset'])
				->get('organizations')
				->result_array();

			if (empty($rows))
			{
				$state['phase'] = 'products';

				return $state;
			}

			foreach ($rows as $row)
			{
				$mapped = $this->map_row($row, $state['org_mapper']);
				$mapped['action'] = self::ACTION_ADDED;
				$state = $this->add_record($export_id, $state, 'companies', $mapped);
				$state['company_done']++;
			}

			$state['org_offset'] += count($rows);

			if (count($rows) < self::DB_CHUNK)
			{
				$state['phase'] = 'products';
			}

			return $state;
		}

		if ($state['phase'] === 'products')
		{
			$rows = $this->CI->db
				->order_by('id', 'ASC')
				->limit(self::DB_CHUNK, (int) $state['product_offset'])
				->get('products')
				->result_array();

			if (empty($rows))
			{
				$state['phase'] = 'finalize';

				return $state;
			}

			foreach ($rows as $row)
			{
				$mapped = $this->map_row($row, $state['product_mapper']);
				$mapped['action'] = self::ACTION_ADDED;
				$state = $this->add_record($export_id, $state, 'products', $mapped);
				$state['product_done']++;
			}

			$state['product_offset'] += count($rows);

			if (count($rows) < self::DB_CHUNK)
			{
				$state['phase'] = 'finalize';
			}

			return $state;
		}

		$state['phase'] = 'finalize';

		return $state;
	}

	protected function process_patch_chunk($export_id, array $state)
	{
		$queue = $state['patch_queue'];
		$offset = (int) $state['patch_offset'];
		$slice = array_slice($queue, $offset, self::DB_CHUNK);

		if (empty($slice))
		{
			$state['phase'] = 'finalize';

			return $state;
		}

		foreach ($slice as $item)
		{
			$state = $this->add_record($export_id, $state, $item['bucket'], $item['row']);

			if ($item['bucket'] === 'companies')
			{
				$state['company_done']++;
			}
			else
			{
				$state['product_done']++;
			}
		}

		$state['patch_offset'] = $offset + count($slice);

		if ($state['patch_offset'] >= count($queue))
		{
			$state['phase'] = 'finalize';
		}

		return $state;
	}

	protected function add_record($export_id, array $state, $bucket, array $row)
	{
		if ((int) $state['current_count'] >= self::MAX_RECORDS_PER_FILE)
		{
			$state = $this->flush_file($export_id, $state, FALSE);
		}

		if ($bucket === 'companies')
		{
			$state['current_companies'][] = $row;
		}
		else
		{
			$state['current_products'][] = $row;
		}

		$state['current_count']++;

		return $state;
	}

	protected function flush_file($export_id, array $state, $allow_empty_final)
	{
		$has_rows = ((int) $state['current_count'] > 0);

		if ( ! $has_rows && ! ($allow_empty_final && empty($state['files'])))
		{
			return $state;
		}

		$payload = array(
			'companies' => $state['current_companies'],
			'products'  => $state['current_products'],
		);

		$index = (int) $state['file_index'];
		$name = sprintf('data_patch_%04d.json', $index);
		$path = $this->workdir($export_id).DIRECTORY_SEPARATOR.$name;
		$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($json === FALSE || @file_put_contents($path, $json) === FALSE)
		{
			throw new RuntimeException(app_line('data_patch_write_failed'));
		}

		$state['files'][] = $name;
		$state['file_index'] = $index + 1;
		$state['current_companies'] = array();
		$state['current_products'] = array();
		$state['current_count'] = 0;

		return $state;
	}

	protected function build_zip($export_id, array $state)
	{
		$workdir = $this->workdir($export_id);
		$name = 'data_patch_'.$export_id.'_'.date('Ymd_His').'.zip';
		$path = $workdir.DIRECTORY_SEPARATOR.$name;
		$zip = new ZipArchive();

		if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE)
		{
			throw new RuntimeException(app_line('data_patch_zip_failed'));
		}

		foreach ($state['files'] as $file)
		{
			$full = $workdir.DIRECTORY_SEPARATOR.$file;

			if (is_file($full))
			{
				$zip->addFile($full, $file);
			}
		}

		$zip->close();

		return array(
			'path' => $path,
			'name' => $name,
		);
	}

	/**
	 * Collapse data_log rows after $since_at into one patch item per entity.
	 */
	protected function build_patch_queue($since_at, array $org_mapper, array $product_mapper)
	{
		$logs = $this->CI->db
			->from('data_log')
			->where('created_at >', $since_at)
			->where_in('entity', array('organization', 'product'))
			->order_by('id', 'ASC')
			->get()
			->result_array();

		$groups = array();

		foreach ($logs as $log)
		{
			$key = $log['entity'].':'.$log['entity_id'];

			if ( ! isset($groups[$key]))
			{
				$groups[$key] = array();
			}

			$groups[$key][] = $log;
		}

		$companies = array();
		$products = array();

		foreach ($groups as $key => $entries)
		{
			$resolved = $this->resolve_patch_entity($entries);

			if ( ! $resolved)
			{
				continue;
			}

			list($entity, $entity_id) = explode(':', $key, 2);
			$mapper = $entity === 'organization' ? $org_mapper : $product_mapper;
			$mapped = $this->map_row($resolved['data'], $mapper);
			$mapped['action'] = $resolved['action'];

			if ($entity === 'organization')
			{
				$companies[] = $mapped;
			}
			else
			{
				$products[] = $mapped;
			}
		}

		$queue = array();

		foreach ($companies as $row)
		{
			$queue[] = array('bucket' => 'companies', 'row' => $row);
		}

		foreach ($products as $row)
		{
			$queue[] = array('bucket' => 'products', 'row' => $row);
		}

		return array(
			'queue'         => $queue,
			'company_total' => count($companies),
			'product_total' => count($products),
		);
	}

	protected function resolve_patch_entity(array $entries)
	{
		$action = NULL;
		$data = array();

		foreach ($entries as $entry)
		{
			$log_action = (string) ($entry['action'] ?? '');
			$original = $this->decode_json($entry['original_data'] ?? NULL);
			$new = $this->decode_json($entry['new_data'] ?? NULL);

			if ($log_action === 'create')
			{
				$action = self::ACTION_ADDED;
				$data = $new ?: $data;
			}
			elseif ($log_action === 'update')
			{
				if ($action !== self::ACTION_ADDED)
				{
					$action = self::ACTION_UPDATED;
				}

				$data = $new ?: $data;
			}
			elseif ($log_action === 'archive')
			{
				$action = self::ACTION_DELETED;
				$data = $original ?: $data;
			}
			elseif ($log_action === 'restore')
			{
				$action = self::ACTION_ADDED;
				$data = $new ?: $data;
			}
		}

		if ($action === NULL || empty($data))
		{
			return NULL;
		}

		return array(
			'action' => $action,
			'data'   => $data,
		);
	}

	protected function map_row(array $row, array $mapper)
	{
		$out = array();

		foreach ($mapper as $db_field => $export_key)
		{
			$db_field = (string) $db_field;
			$export_key = (string) $export_key;

			if ($export_key === '')
			{
				continue;
			}

			$out[$export_key] = array_key_exists($db_field, $row) ? $row[$db_field] : NULL;
		}

		return $out;
	}

	protected function decode_json($value)
	{
		if ($value === NULL || $value === '')
		{
			return array();
		}

		if (is_array($value))
		{
			return $value;
		}

		$decoded = json_decode((string) $value, TRUE);

		return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : array();
	}

	protected function workdir($export_id)
	{
		return rtrim(APPPATH, '/\\').DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'data_patch'.DIRECTORY_SEPARATOR.(int) $export_id;
	}

	protected function state_path($export_id)
	{
		return $this->workdir($export_id).DIRECTORY_SEPARATOR.'state.json';
	}

	protected function write_state($export_id, array $state)
	{
		$path = $this->state_path($export_id);
		$json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($json === FALSE || @file_put_contents($path, $json) === FALSE)
		{
			throw new RuntimeException(app_line('data_patch_write_failed'));
		}
	}

	protected function read_state($export_id)
	{
		$path = $this->state_path($export_id);

		if ( ! is_file($path))
		{
			throw new RuntimeException(app_line('data_patch_state_missing'));
		}

		$decoded = json_decode((string) file_get_contents($path), TRUE);

		if ( ! is_array($decoded))
		{
			throw new RuntimeException(app_line('data_patch_state_missing'));
		}

		return $decoded;
	}

	protected function limit_text($value, $max)
	{
		$value = (string) $value;

		if (function_exists('mb_substr'))
		{
			return mb_substr($value, 0, (int) $max);
		}

		return substr($value, 0, (int) $max);
	}
}
