<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Standard_category_model extends CI_Model {

	protected $table = 'standard_category';

	public function list_by_category($category_id)
	{
		$rows = $this->db
			->select('standard_number')
			->where('category_id', (int) $category_id)
			->order_by('standard_number', 'ASC')
			->get($this->table)
			->result_array();

		return array_map(function ($row) {
			return (string) $row['standard_number'];
		}, $rows);
	}

	public function get_category_id($standard_number)
	{
		$standard_number = trim((string) $standard_number);

		if ($standard_number === '')
		{
			return NULL;
		}

		$row = $this->db
			->select('category_id')
			->where('standard_number', $standard_number)
			->limit(1)
			->get($this->table)
			->row_array();

		return $row ? (int) $row['category_id'] : NULL;
	}

	/**
	 * Replace all standard_number links for a category.
	 * Returns [added => [], removed => []].
	 */
	public function sync_for_category($category_id, array $standard_numbers)
	{
		$category_id = (int) $category_id;
		$normalized = array();

		foreach ($standard_numbers as $value)
		{
			$value = trim((string) $value);

			if ($value === '')
			{
				continue;
			}

			$normalized[$value] = TRUE;
		}

		$desired = array_keys($normalized);
		$existing = $this->list_by_category($category_id);
		$existing_map = array_fill_keys($existing, TRUE);

		$added = array();
		$removed = array();
		$moved = array();

		foreach ($desired as $standard_number)
		{
			if (isset($existing_map[$standard_number]))
			{
				continue;
			}

			$previous = $this->db
				->select('category_id')
				->where('standard_number', $standard_number)
				->limit(1)
				->get($this->table)
				->row_array();
			$previous_category_id = $previous ? (int) $previous['category_id'] : 0;

			// One standard_number maps to one category.
			$this->db->where('standard_number', $standard_number)->delete($this->table);
			$this->db->insert($this->table, array(
				'standard_number' => $standard_number,
				'category_id'     => $category_id,
			));
			$added[] = $standard_number;

			if ($previous_category_id > 0 && $previous_category_id !== $category_id)
			{
				$moved[$standard_number] = $previous_category_id;
			}
		}

		foreach ($existing as $standard_number)
		{
			if (isset($normalized[$standard_number]))
			{
				continue;
			}

			$this->db
				->where('category_id', $category_id)
				->where('standard_number', $standard_number)
				->delete($this->table);
			$removed[] = $standard_number;
		}

		return array(
			'added'   => $added,
			'removed' => $removed,
			'moved'   => $moved,
			'current' => $desired,
		);
	}
}
