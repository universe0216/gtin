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
}
