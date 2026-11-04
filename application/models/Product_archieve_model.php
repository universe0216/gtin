<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'models/Product_model.php';

class Product_archieve_model extends Product_model {

	protected $table = 'products_archieve';
	protected $searchable_columns = array(
		'products_archieve.name',
		'products_archieve.brand',
		'products_archieve.standard_number',
		'products_archieve.barcode_1d',
		'products_archieve.barcode_2d',
		'products_archieve.package_type',
		'organizations.name',
		'archived_org.name',
	);
	protected $sortable_columns = array(
		'name'              => 'products_archieve.name',
		'brand'             => 'products_archieve.brand',
		'standard_number'   => 'products_archieve.standard_number',
		'barcode_1d'        => 'products_archieve.barcode_1d',
		'barcode_1d_type'   => 'products_archieve.barcode_1d_type',
		'barcode_2d'        => 'products_archieve.barcode_2d',
		'package_type'      => 'products_archieve.package_type',
		'organization_name' => 'COALESCE(organizations.name, archived_org.name)',
		'price'             => 'products_archieve.price',
		'reg_date'          => 'products_archieve.reg_date',
		'test_date'         => 'products_archieve.test_date',
		'test_status'       => 'products_archieve.test_status',
		'deleted_at'        => 'products_archieve.deleted_at',
	);

	protected function prepare_list_query()
	{
		$this->db
			->select('products_archieve.*, COALESCE(organizations.name, archived_org.name) AS organization_name', FALSE)
			->from($this->table)
			->join('organizations', 'organizations.id = products_archieve.org_id', 'left')
			->join(
				'(SELECT origin_id, MAX(id) AS id FROM organization_archieve GROUP BY origin_id) latest_archived_org',
				'latest_archived_org.origin_id = products_archieve.org_id',
				'left',
				FALSE
			)
			->join('organization_archieve archived_org', 'archived_org.id = latest_archived_org.id', 'left');
	}

	protected function list_order_column()
	{
		return 'products_archieve.deleted_at';
	}

	protected function apply_list_filters(array $filters)
	{
		$ids = $this->location_filter_id_list($filters);

		if (empty($ids))
		{
			return;
		}

		$this->db->where(
			'COALESCE(organizations.location_id, archived_org.location_id) IN ('.implode(',', $ids).')',
			NULL,
			FALSE
		);
	}

	protected function apply_list_order($sort)
	{
		if ( ! empty($sort['sql']) && ($sort['key'] ?? '') === 'organization_name')
		{
			$this->db->order_by($sort['sql'], $sort['direction'] === 'asc' ? 'ASC' : 'DESC', FALSE);

			return;
		}

		parent::apply_list_order($sort);
	}

	protected function prepare_organization_products_query($organization_id)
	{
		$this->db
			->from($this->table)
			->where('products_archieve.org_id', (int) $organization_id);
	}

	protected function apply_organization_product_search_filters($search)
	{
		$search = trim((string) $search);

		if ($search === '')
		{
			return;
		}

		$this->db->group_start();
		$this->db->like('products_archieve.name', $search);
		$this->db->or_like('products_archieve.brand', $search);
		$this->db->or_like('products_archieve.standard_number', $search);
		$this->db->or_like('products_archieve.barcode_1d', $search);
		$this->db->or_like('products_archieve.barcode_1d_type', $search);
		$this->db->or_like('products_archieve.barcode_2d', $search);
		$this->db->or_like('products_archieve.package_type', $search);
		$this->db->group_end();
	}

	public function get_with_organization($id)
	{
		$this->prepare_list_query();

		return $this->db
			->where('products_archieve.id', (int) $id)
			->get()
			->row_array();
	}
}
