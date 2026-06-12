<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'models/Basic_model.php';

class Product_model extends Basic_model {

	protected $table = 'products';
	protected $searchable_columns = array(
		'products.name',
		'products.standard_number',
		'products.barcode',
		'products.barcode_type',
		'products.package_type',
		'organizations.name',
	);
	protected $sortable_columns = array(
		'name'                => 'products.name',
		'standard_number'     => 'products.standard_number',
		'barcode'             => 'products.barcode',
		'barcode_type'        => 'products.barcode_type',
		'package_type'        => 'products.package_type',
		'organization_name'   => 'organizations.name',
		'registration_date'   => 'products.registration_date',
		'expiry_date'         => 'products.expiry_date',
	);

	protected function prepare_list_query()
	{
		$this->db
			->select('products.*, organizations.name AS organization_name')
			->from($this->table)
			->join('organizations', 'organizations.id = products.organization_id', 'left');
	}

	protected function list_order_column()
	{
		return 'products.'.$this->primary_key;
	}

	public function count_by_organization($organization_id, $search = '')
	{
		$this->prepare_organization_products_query($organization_id);
		$this->apply_organization_product_search_filters($search);

		return (int) $this->db->count_all_results();
	}

	public function get_paginated_by_organization($organization_id, $limit, $offset = 0, $search = '', $sort = array())
	{
		$this->prepare_organization_products_query($organization_id);
		$this->apply_organization_product_search_filters($search);
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

	protected function prepare_organization_products_query($organization_id)
	{
		$this->db
			->from($this->table)
			->where('products.organization_id', (int) $organization_id);
	}

	protected function apply_organization_product_search_filters($search)
	{
		$search = trim((string) $search);

		if ($search === '')
		{
			return;
		}

		$this->db->group_start();
		$this->db->like('products.name', $search);
		$this->db->or_like('products.standard_number', $search);
		$this->db->or_like('products.barcode', $search);
		$this->db->or_like('products.barcode_type', $search);
		$this->db->or_like('products.package_type', $search);
		$this->db->or_like('products.barcode_2d_value', $search);
		$this->db->group_end();
	}

	public function get_barcodes_by_organization($organization_id)
	{
		return $this->db
			->select('barcode')
			->from($this->table)
			->where('organization_id', (int) $organization_id)
			->where('barcode IS NOT NULL', NULL, FALSE)
			->where('barcode !=', '')
			->get()
			->result_array();
	}
}
