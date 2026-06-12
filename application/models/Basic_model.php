<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Basic_model extends CI_Model {

	protected $table = '';
	protected $primary_key = 'id';
	protected $searchable_columns = array();
	protected $sortable_columns = array();

	public function get_all()
	{
		return $this->db
			->order_by($this->primary_key, 'DESC')
			->get($this->table)
			->result_array();
	}

	public function get($id)
	{
		return $this->db
			->where($this->primary_key, $id)
			->get($this->table)
			->row_array();
	}

	public function count_filtered($search = '')
	{
		$this->prepare_list_query();
		$this->apply_search_filters($search);

		return (int) $this->db->count_all_results();
	}

	public function get_paginated($limit, $offset = 0, $search = '', $sort = array())
	{
		$this->prepare_list_query();
		$this->apply_search_filters($search);
		$this->apply_list_order($sort);

		return $this->db
			->limit((int) $limit, (int) $offset)
			->get()
			->result_array();
	}

	public function get_sortable_columns()
	{
		return $this->sortable_columns;
	}

	public function resolve_sort($sort_key, $direction, $sortable_columns = NULL)
	{
		$sort_key = trim((string) $sort_key);
		$direction = strtolower(trim((string) $direction));
		$map = $sortable_columns ?? $this->sortable_columns;

		if ($sort_key === '' || ! isset($map[$sort_key]))
		{
			return array();
		}

		if ($direction !== 'asc' && $direction !== 'desc')
		{
			return array();
		}

		return array(
			'key'       => $sort_key,
			'direction' => $direction,
			'sql'       => $map[$sort_key],
		);
	}

	public function insert($data)
	{
		$data['created_at'] = date('Y-m-d H:i:s');
		$data['updated_at'] = date('Y-m-d H:i:s');

		if ($this->db->insert($this->table, $data))
		{
			return $this->db->insert_id();
		}

		return FALSE;
	}

	public function update($id, $data)
	{
		$data['updated_at'] = date('Y-m-d H:i:s');

		return $this->db
			->where($this->primary_key, $id)
			->update($this->table, $data);
	}

	public function delete($id)
	{
		return $this->db
			->where($this->primary_key, $id)
			->delete($this->table);
	}

	protected function prepare_list_query()
	{
		$this->db->from($this->table);
	}

	protected function list_order_column()
	{
		return $this->table.'.'.$this->primary_key;
	}

	protected function apply_list_order($sort)
	{
		if (empty($sort['sql']) || empty($sort['direction']))
		{
			$this->db->order_by($this->list_order_column(), 'DESC');
			return;
		}

		$this->db->order_by($sort['sql'], $sort['direction'] === 'asc' ? 'ASC' : 'DESC');
	}

	protected function apply_search_filters($search)
	{
		$search = trim((string) $search);

		if ($search === '' || empty($this->searchable_columns))
		{
			return;
		}

		$this->db->group_start();

		foreach ($this->searchable_columns as $index => $column)
		{
			if ($index === 0)
			{
				$this->db->like($column, $search);
			}
			else
			{
				$this->db->or_like($column, $search);
			}
		}

		$this->db->group_end();
	}
}
