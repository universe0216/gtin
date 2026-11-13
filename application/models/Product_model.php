<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'models/Basic_model.php';

class Product_model extends Basic_model {

	protected $table = 'products';

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('product_key');
	}

	public function insert($data)
	{
		$data = $this->with_normalized_key($data);
		$id = parent::insert($data);

		if ($id)
		{
			$row = $this->get($id);

			if ($row)
			{
				$this->load->library('data_logger');
				$this->data_logger->log_create(Data_logger::ENTITY_PRODUCT, (int) $id, $row);
			}
		}

		return $id;
	}

	public function update($id, $data)
	{
		$original = $this->get($id) ?: array();
		$data = $this->with_normalized_key($data, $original);
		$ok = parent::update($id, $data);

		if ($ok)
		{
			$updated = $this->get($id);

			if ($updated)
			{
				$this->load->library('data_logger');
				$this->data_logger->log_update(
					Data_logger::ENTITY_PRODUCT,
					(int) $id,
					$original ?: NULL,
					$updated
				);
			}
		}

		return $ok;
	}

	protected function with_normalized_key(array $data, array $existing = array())
	{
		$name = array_key_exists('name', $data) ? $data['name'] : ($existing['name'] ?? '');
		$standard_number = array_key_exists('standard_number', $data)
			? $data['standard_number']
			: ($existing['standard_number'] ?? '');
		$brand = array_key_exists('brand', $data) ? $data['brand'] : ($existing['brand'] ?? '');
		$package_type = array_key_exists('package_type', $data)
			? $data['package_type']
			: ($existing['package_type'] ?? '');

		$data['normalized_key'] = product_build_normalized_key($name, $standard_number, $brand, $package_type);

		return $data;
	}

	protected $searchable_columns = array(
		'products.name',
		'products.brand',
		'products.standard_number',
		'products.barcode_1d',
		'products.barcode_2d',
		'products.package_type',
		'organizations.name',
	);
	protected $sortable_columns = array(
		'name'              => 'products.name',
		'brand'             => 'products.brand',
		'standard_number'   => 'products.standard_number',
		'barcode_1d'        => 'products.barcode_1d',
		'barcode_1d_type'   => 'products.barcode_1d_type',
		'barcode_2d'        => 'products.barcode_2d',
		'barcode_2d_type'   => 'products.barcode_2d_type',
		'package_type'      => 'products.package_type',
		'organization_name' => 'organizations.name',
		'processor_name'    => 'products.processor_name',
		'price'             => 'products.price',
		'reg_date'          => 'products.reg_date',
		'test_date'         => 'products.test_date',
		'test_status'       => 'products.test_status',
	);
	protected $filterable_columns = array(
		'name'              => 'products.name',
		'brand'             => 'products.brand',
		'standard_number'   => 'products.standard_number',
		'barcode_1d'        => 'products.barcode_1d',
		'barcode_1d_type'   => 'products.barcode_1d_type',
		'barcode_2d'        => 'products.barcode_2d',
		'barcode_2d_type'   => 'products.barcode_2d_type',
		'package_type'      => 'products.package_type',
		'organization_name' => 'organizations.name',
		'processor_name'    => 'products.processor_name',
		'price'             => 'products.price',
		'reg_date'          => 'products.reg_date',
		'test_status'       => 'products.test_status',
	);

	protected function prepare_list_query()
	{
		$this->db
			->select('products.*, organizations.name AS organization_name')
			->from($this->table)
			->join('organizations', 'organizations.id = products.org_id', 'left');
	}

	protected function list_order_column()
	{
		return 'products.'.$this->primary_key;
	}

	protected function apply_list_filters(array $filters)
	{
		$ids = $this->location_filter_id_list($filters);

		if (empty($ids))
		{
			return;
		}

		$this->db->where_in('organizations.location_id', $ids);
	}

	protected function location_filter_id_list(array $filters)
	{
		$ids = array();

		foreach ((array) ($filters['location_ids'] ?? array()) as $id)
		{
			if (is_numeric($id))
			{
				$ids[] = (int) $id;
			}
		}

		return $ids;
	}

	public function count_by_organization($organization_id, $search = '', $normalized_key = '')
	{
		$this->prepare_organization_products_query($organization_id);
		$this->apply_organization_product_search_filters($search, $normalized_key);

		return (int) $this->db->count_all_results();
	}

	public function get_paginated_by_organization($organization_id, $limit, $offset = 0, $search = '', $sort = array(), $normalized_key = '')
	{
		$this->prepare_organization_products_query($organization_id);
		$this->apply_organization_product_search_filters($search, $normalized_key);
		$this->apply_list_order($sort);

		return $this->db
			->limit((int) $limit, (int) $offset)
			->get()
			->result_array();
	}

	public function get_sortable_columns_for_keys($keys)
	{
		return array_intersect_key($this->sortable_columns, array_flip((array) $keys));
	}

	/**
	 * Rebuild normalized_key for every product in batches.
	 */
	public function regenerate_all_normalized_keys($batch_size = 1000)
	{
		$this->load->helper('product_key');

		$batch_size = max(100, (int) $batch_size);
		$last_id = 0;
		$updated = 0;

		while (TRUE)
		{
			$rows = $this->db
				->select('id, name, standard_number, brand, package_type')
				->from($this->table)
				->where('id >', $last_id)
				->order_by('id', 'ASC')
				->limit($batch_size)
				->get()
				->result_array();

			if (empty($rows))
			{
				break;
			}

			foreach ($rows as $row)
			{
				$key = product_build_normalized_key(
					$row['name'] ?? '',
					$row['standard_number'] ?? '',
					$row['brand'] ?? '',
					$row['package_type'] ?? ''
				);

				$this->db
					->where($this->primary_key, (int) $row['id'])
					->update($this->table, array('normalized_key' => $key));

				$last_id = (int) $row['id'];
				$updated++;
			}
		}

		return $updated;
	}

	protected function prepare_organization_products_query($organization_id)
	{
		$this->db
			->select('products.*, organizations.name AS organization_name')
			->from($this->table)
			->join('organizations', 'organizations.id = products.org_id', 'left')
			->where('products.org_id', (int) $organization_id);
	}

	protected function apply_organization_product_search_filters($search, $normalized_key = '')
	{
		$search = trim((string) $search);

		if ($search !== '')
		{
			$this->db->group_start();
			$this->db->like('products.name', $search);
			$this->db->or_like('products.brand', $search);
			$this->db->or_like('products.standard_number', $search);
			$this->db->or_like('products.barcode_1d', $search);
			$this->db->or_like('products.barcode_1d_type', $search);
			$this->db->or_like('products.barcode_2d', $search);
			$this->db->or_like('products.package_type', $search);
			$this->db->group_end();
			return;
		}

		$normalized_key = trim((string) $normalized_key);

		if ($normalized_key !== '' && $normalized_key !== '___')
		{
			$this->db->where('products.normalized_key', $normalized_key);
		}
	}

	public function get_with_organization($id)
	{
		return $this->db
			->select('products.*, organizations.name AS organization_name')
			->from($this->table)
			->join('organizations', 'organizations.id = products.org_id', 'left')
			->where('products.id', (int) $id)
			->get()
			->row_array();
	}

	public function find_catalog_match($organization_id, $barcode, $name)
	{
		$this->db
			->from($this->table)
			->where('org_id', (int) $organization_id);

		$barcode = trim((string) $barcode);

		if ($barcode !== '')
		{
			$this->db->where('barcode_1d', $barcode);
		}
		else
		{
			$this->db->where('name', trim((string) $name));
		}

		return $this->db
			->limit(1)
			->get()
			->row_array();
	}

	public function find_by_organization_normalized_key($organization_id, $normalized_key)
	{
		$normalized_key = trim((string) $normalized_key);

		if ($normalized_key === '' || $normalized_key === '___')
		{
			return NULL;
		}

		return $this->db
			->from($this->table)
			->where('org_id', (int) $organization_id)
			->where('normalized_key', $normalized_key)
			->limit(1)
			->get()
			->row_array();
	}

	public function list_similarity_fields($organization_id)
	{
		return $this->db
			->select('id, name, brand, standard_number, package_type, normalized_key')
			->from($this->table)
			->where('org_id', (int) $organization_id)
			->get()
			->result_array();
	}

	public function get_barcodes_by_organization($organization_id)
	{
		return $this->db
			->select('barcode_1d')
			->from($this->table)
			->where('org_id', (int) $organization_id)
			->where('barcode_1d IS NOT NULL', NULL, FALSE)
			->where('barcode_1d !=', '')
			->get()
			->result_array();
	}

	public function get_ids_by_barcodes(array $barcodes)
	{
		$normalized = array();

		foreach ($barcodes as $barcode)
		{
			$value = trim((string) $barcode);

			if ($value !== '')
			{
				$normalized[$value] = $value;
			}
		}

		$normalized = array_values($normalized);

		if (empty($normalized))
		{
			return array();
		}

		$ids = array();

		foreach (array_chunk($normalized, 500) as $chunk)
		{
			$rows = $this->db
				->select('id')
				->from($this->table)
				->where_in('barcode_1d', $chunk)
				->get()
				->result_array();

			foreach ($rows as $row)
			{
				$id = (int) $row['id'];

				if ($id > 0)
				{
					$ids[$id] = $id;
				}
			}
		}

		return array_values($ids);
	}
}
