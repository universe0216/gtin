<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'models/Basic_model.php';

class Location_model extends Basic_model {

	protected $table = 'locations';
	protected $tree_nodes = NULL;
	protected $path_index = NULL;

	public function list_for_tree()
	{
		$rows = $this->db
			->order_by('level', 'ASC')
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

	public function count_children($id)
	{
		return (int) $this->db
			->where('parent_id', (int) $id)
			->count_all_results($this->table);
	}

	public function next_sort_order($parent_id)
	{
		$this->db->select_max('sort_order', 'max_order');
		$this->apply_parent_filter($parent_id);
		$row = $this->db->get($this->table)->row_array();

		return (int) ($row['max_order'] ?? 0) + 1;
	}

	public function siblings($parent_id)
	{
		$this->apply_parent_filter($parent_id);

		return $this->db
			->order_by('sort_order', 'ASC')
			->order_by('id', 'ASC')
			->get($this->table)
			->result_array();
	}

	public function build_tag($id, $parent_id)
	{
		$parts = array((int) $id);
		$current_id = $parent_id === NULL ? 0 : (int) $parent_id;

		while ($current_id > 0)
		{
			$parent = $this->get($current_id);

			if ( ! $parent)
			{
				break;
			}

			array_unshift($parts, (int) $parent['id']);
			$current_id = $parent['parent_id'] === NULL ? 0 : (int) $parent['parent_id'];
		}

		return implode('.', $parts);
	}

	public function move_sibling($id, $direction)
	{
		$row = $this->get($id);

		if ( ! $row)
		{
			return FALSE;
		}

		$siblings = $this->siblings($row['parent_id']);
		$index = NULL;

		foreach ($siblings as $position => $sibling)
		{
			if ((int) $sibling['id'] === (int) $id)
			{
				$index = $position;
				break;
			}
		}

		if ($index === NULL)
		{
			return FALSE;
		}

		$target = $direction === 'up' ? $index - 1 : $index + 1;

		if ($target < 0 || $target >= count($siblings))
		{
			return FALSE;
		}

		$this->db->trans_begin();

		foreach ($siblings as $position => $sibling)
		{
			$this->update($sibling['id'], array('sort_order' => $position + 1));
		}

		$this->update($siblings[$index]['id'], array('sort_order' => $target + 1));
		$this->update($siblings[$target]['id'], array('sort_order' => $index + 1));

		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();

			return FALSE;
		}

		$this->db->trans_commit();

		return array(
			$this->get($siblings[$index]['id']),
			$this->get($siblings[$target]['id']),
		);
	}

	public function tree_nodes()
	{
		if ($this->tree_nodes !== NULL)
		{
			return $this->tree_nodes;
		}

		$nodes = array();

		foreach ($this->list_for_tree() as $row)
		{
			$nodes[] = array(
				'id'         => (string) $row['id'],
				'parent_id'  => $row['parent_id'] === NULL ? '' : (string) $row['parent_id'],
				'name'       => $row['name'],
				'sort_order' => (int) $row['sort_order'],
			);
		}

		$this->tree_nodes = $nodes;

		return $nodes;
	}

	public function filter_ids($raw)
	{
		$raw = trim((string) $raw);

		if ($raw === '' || ! ctype_digit($raw))
		{
			return array();
		}

		$children = array();
		$known = FALSE;

		foreach ($this->tree_nodes() as $node)
		{
			if ((string) $node['id'] === $raw)
			{
				$known = TRUE;
			}

			$parent = (string) $node['parent_id'];
			$children[$parent][] = (string) $node['id'];
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

	public function path_name($id)
	{
		$id = (int) $id;

		if ($id < 1)
		{
			return '';
		}

		$this->load_path_index();

		if ( ! isset($this->path_index[$id]))
		{
			return '';
		}

		$names = array();
		$seen = array();
		$current = $id;

		while ($current > 0 && isset($this->path_index[$current]) && ! isset($seen[$current]))
		{
			$seen[$current] = TRUE;
			array_unshift($names, $this->path_index[$current]['name']);
			$current = (int) $this->path_index[$current]['parent_id'];
		}

		return implode(' ', $names);
	}

	protected function load_path_index()
	{
		if ($this->path_index !== NULL)
		{
			return;
		}

		$this->path_index = array();
		$rows = $this->db
			->select('id, parent_id, name')
			->get($this->table)
			->result_array();

		foreach ($rows as $row)
		{
			$this->path_index[(int) $row['id']] = array(
				'name'      => $row['name'],
				'parent_id' => $row['parent_id'] === NULL ? 0 : (int) $row['parent_id'],
			);
		}
	}

	public function present_row(array $row)
	{
		return array(
			'id'          => (int) $row['id'],
			'parent_id'   => $row['parent_id'] === NULL ? NULL : (int) $row['parent_id'],
			'level'       => (int) $row['level'],
			'sort_order'  => (int) $row['sort_order'],
			'name'        => $row['name'],
			'tag'         => $row['tag'],
			'description' => $row['description'],
		);
	}

	protected function apply_parent_filter($parent_id)
	{
		if ($parent_id === NULL || $parent_id === '')
		{
			$this->db->where('parent_id IS NULL', NULL, FALSE);
			return;
		}

		$this->db->where('parent_id', (int) $parent_id);
	}
}
