<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'models/Basic_model.php';

class Product_model extends Basic_model {

	protected $table = 'products';

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('product_key');
		$category_sql = $this->category_name_sql();
		$this->sortable_columns['category_name'] = $category_sql;
		$this->filterable_columns['category_name'] = $category_sql;
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
				$this->assign_category_by_standard((int) $id, $row['standard_number'] ?? '');
			}
		}

		return $id;
	}

	public function update($id, $data)
	{
		$original = $this->get($id) ?: array();
		$data = $this->with_normalized_key($data, $original);

		if ( ! $this->payload_has_changes($original, $data))
		{
			return TRUE;
		}

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

	protected function category_name_sql()
	{
		$product_id = $this->table.'.id';
		$links = $this->db->dbprefix('product_category');
		$categories = $this->db->dbprefix('categories');

		return '(SELECT CONCAT_WS(\' / \', c5.name, c4.name, c3.name, c2.name, c1.name, c0.name)'
			.' FROM '.$links.' pc'
			.' INNER JOIN '.$categories.' c0 ON c0.id = pc.category_id'
			.' LEFT JOIN '.$categories.' c1 ON c1.id = c0.parent_id'
			.' LEFT JOIN '.$categories.' c2 ON c2.id = c1.parent_id'
			.' LEFT JOIN '.$categories.' c3 ON c3.id = c2.parent_id'
			.' LEFT JOIN '.$categories.' c4 ON c4.id = c3.parent_id'
			.' LEFT JOIN '.$categories.' c5 ON c5.id = c4.parent_id'
			.' WHERE pc.product_id = '.$product_id
			.' ORDER BY c0.level DESC, c0.id ASC'
			.' LIMIT 1)';
	}

	protected function prepare_list_query()
	{
		$this->db
			->select('products.*, organizations.name AS organization_name')
			->select($this->category_name_sql().' AS category_name', FALSE)
			->from($this->table)
			->join('organizations', 'organizations.id = products.org_id', 'left');
	}

	protected function list_order_column()
	{
		return 'products.created_at';
	}

	protected function apply_list_filters(array $filters)
	{
		$ids = $this->location_filter_id_list($filters);

		if ( ! empty($ids))
		{
			$this->db->where_in('organizations.location_id', $ids);
		}

		$this->apply_category_list_filter($filters);
	}

	protected function apply_column_filters(array $filters)
	{
		$columns = $filters['columns'] ?? array();
		$category = trim((string) ($columns['category_name'] ?? ''));

		if ($category !== '')
		{
			unset($columns['category_name']);
			$filters['columns'] = $columns;
			$this->db->like($this->category_name_sql(), $category, 'both', FALSE);
		}

		parent::apply_column_filters($filters);
	}

	protected function apply_list_order($sort)
	{
		if (empty($sort['sql']) || empty($sort['direction']))
		{
			$this->db->order_by('products.created_at', 'DESC');
			$this->db->order_by('products.id', 'DESC');

			return;
		}

		if (($sort['key'] ?? '') === 'category_name')
		{
			$this->db->order_by($sort['sql'], $sort['direction'] === 'asc' ? 'ASC' : 'DESC', FALSE);

			return;
		}

		parent::apply_list_order($sort);
	}

	protected function apply_category_list_filter(array $filters)
	{
		$ids = array();

		foreach ((array) ($filters['category_ids'] ?? array()) as $id)
		{
			if (is_numeric($id))
			{
				$ids[] = (int) $id;
			}
		}

		if (empty($ids))
		{
			return;
		}

		$table = $this->db->dbprefix('product_category');
		$this->db->where(
			$this->table.'.id IN (SELECT product_id FROM '.$table.' WHERE category_id IN ('.implode(',', $ids).'))',
			NULL,
			FALSE
		);
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

	public function count_by_organization($organization_id, $search = '', $normalized_key = '', array $filters = array())
	{
		$this->prepare_organization_products_query($organization_id);
		$this->apply_organization_product_search_filters($search, $normalized_key);
		$this->apply_column_filters($filters);

		return (int) $this->db->count_all_results();
	}

	public function get_by_organization($organization_id)
	{
		$this->prepare_organization_products_query($organization_id);
		$this->apply_list_order(array());

		return $this->db
			->get()
			->result_array();
	}

	public function get_paginated_by_organization($organization_id, $limit, $offset = 0, $search = '', $sort = array(), $normalized_key = '', array $filters = array())
	{
		$this->prepare_organization_products_query($organization_id);
		$this->apply_organization_product_search_filters($search, $normalized_key);
		$this->apply_column_filters($filters);
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
		$row = $this->db
			->select('products.*, organizations.name AS organization_name')
			->from($this->table)
			->join('organizations', 'organizations.id = products.org_id', 'left')
			->where('products.id', (int) $id)
			->get()
			->row_array();

		if ( ! $row)
		{
			return NULL;
		}

		$this->load->model('product_category_model');
		$this->load->model('category_model');
		$category_id = $this->product_category_model->get_primary_category_id($id);
		$row['category_id'] = $category_id;
		$row['category_name'] = $category_id
			? $this->category_model->path_label($category_id)
			: '';

		return $row;
	}

	public function assign_category_by_standard($product_id, $standard_number)
	{
		$product_id = (int) $product_id;
		$standard_number = trim((string) $standard_number);

		if ($product_id < 1 || $standard_number === '')
		{
			return FALSE;
		}

		$this->load->model('standard_category_model');
		$this->load->model('product_category_model');
		$category_id = $this->standard_category_model->get_category_id($standard_number);

		if ( ! $category_id)
		{
			return FALSE;
		}

		return $this->product_category_model->assign_category_to_products(array($product_id), $category_id);
	}

	public function find_catalog_match($organization_id, $barcode, $name)
	{
		$barcode = trim((string) $barcode);

		if ($barcode !== '')
		{
			return $this->find_by_barcode_1d($barcode);
		}

		$this->db
			->from($this->table)
			->where('org_id', (int) $organization_id)
			->where('name', trim((string) $name));

		return $this->db
			->limit(1)
			->get()
			->row_array();
	}

	public function find_by_barcode_1d($barcode)
	{
		$barcode = trim((string) $barcode);

		if ($barcode === '')
		{
			return NULL;
		}

		return $this->db
			->from($this->table)
			->where('barcode_1d', $barcode)
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

	public function registration_statistics($from, $to, $group, $level)
	{
		$group = $group === 'category' ? 'category' : 'location';
		$max_level = $group === 'category' ? 4 : 3;
		$level = (int) $level;

		if ($level < 1 || $level > $max_level)
		{
			$level = 1;
		}

		$counts = $group === 'category'
			? $this->category_registration_counts($from, $to)
			: $this->location_registration_counts($from, $to);
		$nodes = $this->statistics_nodes($group === 'category' ? 'categories' : 'locations');

		return array(
			'total'  => array_sum($counts),
			'groups' => $this->rollup_statistic_groups($counts, $nodes, $level),
		);
	}

	protected function location_registration_counts($from, $to)
	{
		$this->db
			->select('organizations.location_id AS group_id, COUNT(*) AS total', FALSE)
			->from($this->table)
			->join('organizations', 'organizations.id = products.org_id', 'left');
		$this->apply_reg_date_period($from, $to);

		return $this->statistic_count_map(
			$this->db->group_by('organizations.location_id')->get()->result_array()
		);
	}

	protected function category_registration_counts($from, $to)
	{
		$category_sql = '(SELECT pc.category_id FROM product_category pc'
			.' INNER JOIN categories c ON c.id = pc.category_id'
			.' WHERE pc.product_id = products.id'
			.' ORDER BY c.level DESC, c.id ASC LIMIT 1)';
		$where = array();
		$binds = array();
		$from = $this->statistic_date($from);
		$to = $this->statistic_date($to);

		if ($from !== NULL)
		{
			$where[] = 'products.reg_date >= ?';
			$binds[] = $from;
		}

		if ($to !== NULL)
		{
			$where[] = 'products.reg_date <= ?';
			$binds[] = $to;
		}

		if ($from !== NULL || $to !== NULL)
		{
			$where[] = 'products.reg_date IS NOT NULL';
		}

		$sql = 'SELECT group_id, COUNT(*) AS total FROM ('
			.'SELECT '.$category_sql.' AS group_id FROM products';

		if ( ! empty($where))
		{
			$sql .= ' WHERE '.implode(' AND ', $where);
		}

		$sql .= ') AS product_categories GROUP BY group_id';

		return $this->statistic_count_map($this->db->query($sql, $binds)->result_array());
	}

	protected function apply_reg_date_period($from, $to)
	{
		$from = $this->statistic_date($from);
		$to = $this->statistic_date($to);

		if ($from !== NULL)
		{
			$this->db->where('products.reg_date >=', $from);
		}

		if ($to !== NULL)
		{
			$this->db->where('products.reg_date <=', $to);
		}

		if ($from !== NULL || $to !== NULL)
		{
			$this->db->where('products.reg_date IS NOT NULL', NULL, FALSE);
		}
	}

	protected function statistic_date($value)
	{
		$value = trim((string) $value);

		if ($value === '')
		{
			return NULL;
		}

		$date = DateTime::createFromFormat('Y-m-d', $value);

		if ( ! $date || $date->format('Y-m-d') !== $value)
		{
			return NULL;
		}

		return $value;
	}

	protected function statistic_count_map(array $rows)
	{
		$counts = array();

		foreach ($rows as $row)
		{
			$id = (int) ($row['group_id'] ?? 0);
			$counts[$id] = ($counts[$id] ?? 0) + (int) $row['total'];
		}

		return $counts;
	}

	protected function statistics_nodes($table)
	{
		$rows = $this->db
			->select('id, parent_id, level, name')
			->from($table)
			->get()
			->result_array();
		$nodes = array();

		foreach ($rows as $row)
		{
			$id = (int) $row['id'];
			$nodes[$id] = array(
				'id'        => $id,
				'parent_id' => (int) $row['parent_id'],
				'level'     => (int) $row['level'],
				'name'      => (string) $row['name'],
			);
		}

		return $nodes;
	}

	protected function rollup_statistic_groups(array $counts, array $nodes, $level)
	{
		$groups = array();

		foreach ($counts as $id => $count)
		{
			$count = (int) $count;

			if ($count < 1)
			{
				continue;
			}

			$target_id = 0;
			$name = '';

			if ($id > 0 && isset($nodes[$id]))
			{
				$node = $this->statistic_ancestor($nodes, $id, $level);
				$target_id = (int) $node['id'];
				$name = $node['name'];
			}

			if ( ! isset($groups[$target_id]))
			{
				$groups[$target_id] = array(
					'id'    => $target_id,
					'name'  => $name,
					'total' => 0,
				);
			}

			$groups[$target_id]['total'] += $count;
		}

		$rows = array_values($groups);
		usort($rows, function ($left, $right) {
			if ($left['total'] !== $right['total'])
			{
				return $right['total'] - $left['total'];
			}

			return strcasecmp($left['name'], $right['name']);
		});

		return $rows;
	}

	protected function statistic_ancestor(array $nodes, $id, $level)
	{
		$current = $nodes[$id];
		$seen = array();

		while ((int) $current['level'] > (int) $level)
		{
			$parent_id = (int) $current['parent_id'];

			if ($parent_id < 1 || ! isset($nodes[$parent_id]) || isset($seen[(int) $current['id']]))
			{
				break;
			}

			$seen[(int) $current['id']] = TRUE;
			$current = $nodes[$parent_id];
		}

		return $current;
	}
}
