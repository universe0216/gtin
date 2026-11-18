<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'models/Basic_model.php';

class Category_model extends Basic_model {

	protected $table = 'categories';

	public function list_for_tree()
	{
		$rows = $this->db
			->select('id, parent_id, gpc_code, name, original_name, level, sort_order')
			->order_by('level', 'ASC')
			->order_by('sort_order', 'ASC')
			->order_by('name', 'ASC')
			->get($this->table)
			->result_array();
		$counts = $this->assignment_count_maps();
		$records = array();

		foreach ($rows as $row)
		{
			$id = (int) $row['id'];
			$row['standard_count'] = $counts['standards'][$id] ?? 0;
			$row['product_count'] = $counts['products'][$id] ?? 0;
			$records[] = $this->present_row($row);
		}

		return $records;
	}

	public function assignment_counts_for(array $category_ids)
	{
		$ids = array();

		foreach ($category_ids as $category_id)
		{
			$category_id = (int) $category_id;

			if ($category_id > 0)
			{
				$ids[$category_id] = TRUE;
			}
		}

		$ids = array_keys($ids);

		if (empty($ids))
		{
			return array();
		}

		$counts = $this->assignment_count_maps($ids);
		$rows = array();

		foreach ($ids as $id)
		{
			$rows[] = array(
				'id'             => $id,
				'standard_count' => $counts['standards'][$id] ?? 0,
				'product_count'  => $counts['products'][$id] ?? 0,
			);
		}

		return $rows;
	}

	protected function assignment_count_maps(array $category_ids = array())
	{
		return array(
			'standards' => $this->grouped_assignment_counts('standard_category', $category_ids),
			'products'  => $this->grouped_assignment_counts('product_category', $category_ids),
		);
	}

	protected function grouped_assignment_counts($table, array $category_ids)
	{
		$query = $this->db
			->select('category_id, COUNT(*) AS total', FALSE)
			->group_by('category_id');

		if ( ! empty($category_ids))
		{
			$query->where_in('category_id', $category_ids);
		}

		$counts = array();

		foreach ($query->get($table)->result_array() as $row)
		{
			$counts[(int) $row['category_id']] = (int) $row['total'];
		}

		return $counts;
	}

	public function present_row(array $row)
	{
		return array(
			'id'             => (int) $row['id'],
			'parent_id'      => $row['parent_id'] === NULL ? NULL : (int) $row['parent_id'],
			'gpc_code'       => $row['gpc_code'],
			'name'           => $row['name'],
			'original_name'  => $row['original_name'],
			'level'          => (int) $row['level'],
			'sort_order'     => (int) $row['sort_order'],
			'standard_count' => (int) ($row['standard_count'] ?? 0),
			'product_count'  => (int) ($row['product_count'] ?? 0),
		);
	}

	public function tree_nodes($max_level = 3)
	{
		$max_level = max(1, (int) $max_level);
		$rows = $this->db
			->select('id, parent_id, gpc_code, name, original_name, level, sort_order')
			->where('level <=', $max_level)
			->order_by('level', 'ASC')
			->order_by('sort_order', 'ASC')
			->order_by('name', 'ASC')
			->get($this->table)
			->result_array();

		$nodes = array();

		foreach ($rows as $row)
		{
			$nodes[] = array(
				'id'        => (int) $row['id'],
				'parent_id' => $row['parent_id'] === NULL ? NULL : (int) $row['parent_id'],
				'name'      => $row['name'],
				'gpc_code'  => $row['gpc_code'],
				'level'     => (int) $row['level'],
			);
		}

		return $nodes;
	}

	public function filter_ids($raw)
	{
		$raw = trim((string) $raw);

		if ($raw === '' || ! ctype_digit($raw))
		{
			return array();
		}

		$rows = $this->db
			->select('id, parent_id')
			->get($this->table)
			->result_array();
		$children = array();
		$known = FALSE;

		foreach ($rows as $row)
		{
			$id = (string) $row['id'];

			if ($id === $raw)
			{
				$known = TRUE;
			}

			$parent = $row['parent_id'] === NULL ? '' : (string) $row['parent_id'];
			$children[$parent][] = $id;
		}

		if ( ! $known)
		{
			return array(-1);
		}

		$ids = array();
		$walk = function ($id) use (&$walk, &$ids, $children) {
			$ids[] = (int) $id;

			foreach ($children[$id] ?? array() as $child_id)
			{
				$walk($child_id);
			}
		};
		$walk($raw);

		return $ids;
	}

	public function path_label($category_id)
	{
		$category_id = (int) $category_id;

		if ($category_id < 1)
		{
			return '';
		}

		$names = array();
		$seen = array();
		$current = $category_id;

		while ($current > 0 && ! isset($seen[$current]))
		{
			$seen[$current] = TRUE;
			$row = $this->db
				->select('id, parent_id, name')
				->where('id', $current)
				->get($this->table)
				->row_array();

			if ( ! $row)
			{
				break;
			}

			array_unshift($names, $row['name']);
			$current = $row['parent_id'] === NULL ? 0 : (int) $row['parent_id'];
		}

		return implode(' / ', $names);
	}
}
