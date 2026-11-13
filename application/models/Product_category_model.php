<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Product_category_model extends CI_Model {

	protected $table = 'product_category';

	public function get_category_ids($product_id)
	{
		$rows = $this->db
			->select('category_id')
			->where('product_id', (int) $product_id)
			->get($this->table)
			->result_array();

		return array_map(function ($row) {
			return (int) $row['category_id'];
		}, $rows);
	}

	public function get_primary_category_id($product_id)
	{
		$row = $this->db
			->select('product_category.category_id')
			->from($this->table)
			->join('categories', 'categories.id = product_category.category_id', 'inner')
			->where('product_category.product_id', (int) $product_id)
			->order_by('categories.level', 'DESC')
			->order_by('categories.id', 'ASC')
			->limit(1)
			->get()
			->row_array();

		return $row ? (int) $row['category_id'] : NULL;
	}

	public function set_product_category($product_id, $category_id)
	{
		$product_id = (int) $product_id;
		$category_id = (int) $category_id;

		$this->db->where('product_id', $product_id)->delete($this->table);

		if ($product_id < 1 || $category_id < 1)
		{
			return TRUE;
		}

		return (bool) $this->db->insert($this->table, array(
			'product_id'  => $product_id,
			'category_id' => $category_id,
		));
	}

	public function assign_category_to_products(array $product_ids, $category_id)
	{
		$category_id = (int) $category_id;

		if ($category_id < 1 || empty($product_ids))
		{
			return 0;
		}

		$count = 0;

		foreach ($product_ids as $product_id)
		{
			$product_id = (int) $product_id;

			if ($product_id < 1)
			{
				continue;
			}

			$exists = $this->db
				->where('product_id', $product_id)
				->where('category_id', $category_id)
				->count_all_results($this->table);

			if ($exists)
			{
				continue;
			}

			if ($this->db->insert($this->table, array(
				'product_id'  => $product_id,
				'category_id' => $category_id,
			)))
			{
				$count++;
			}
		}

		return $count;
	}

	public function remove_category_for_products(array $product_ids, $category_id)
	{
		$category_id = (int) $category_id;
		$ids = array();

		foreach ($product_ids as $product_id)
		{
			$product_id = (int) $product_id;

			if ($product_id > 0)
			{
				$ids[] = $product_id;
			}
		}

		if ($category_id < 1 || empty($ids))
		{
			return 0;
		}

		$this->db
			->where('category_id', $category_id)
			->where_in('product_id', $ids)
			->delete($this->table);

		return (int) $this->db->affected_rows();
	}
}
