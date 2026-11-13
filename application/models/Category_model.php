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
		$records = array();

		foreach ($rows as $row)
		{
			$records[] = $this->present_row($row);
		}

		return $records;
	}

	public function present_row(array $row)
	{
		return array(
			'id'            => (int) $row['id'],
			'parent_id'     => $row['parent_id'] === NULL ? NULL : (int) $row['parent_id'],
			'gpc_code'      => $row['gpc_code'],
			'name'          => $row['name'],
			'original_name' => $row['original_name'],
			'level'         => (int) $row['level'],
			'sort_order'    => (int) $row['sort_order'],
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
