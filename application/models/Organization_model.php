<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'models/Basic_model.php';

class Organization_model extends Basic_model {

	protected $table = 'organizations';
	protected $searchable_columns = array(
		'organizations.name',
		'organizations.reg_number',
		'organizations.gs1_prefix',
		'organizations.parent_org',
		'organizations.phone',
		'organizations.c_code',
		'organizations.address',
		'locations.name',
	);
	protected $sortable_columns = array(
		'name'        => 'organizations.name',
		'reg_number'  => 'organizations.reg_number',
		'parent_org'  => 'organizations.parent_org',
		'gs1_prefix'  => 'organizations.gs1_prefix',
		'init_date'   => 'organizations.init_date',
		'reg_date'    => 'organizations.reg_date',
		'expiry_date' => 'organizations.expiry_date',
		'phone'       => 'organizations.phone',
		'c_code'      => 'organizations.c_code',
		'currency'    => 'organizations.currency',
		'address'     => 'organizations.address',
		'location_id' => 'locations.name',
	);
	protected $filterable_columns = array(
		'name'        => 'organizations.name',
		'reg_number'  => 'organizations.reg_number',
		'gs1_prefix'  => 'organizations.gs1_prefix',
		'parent_org'  => 'organizations.parent_org',
		'phone'       => 'organizations.phone',
		'currency'    => 'organizations.currency',
		'init_date'   => 'organizations.init_date',
		'reg_date'    => 'organizations.reg_date',
		'expiry_date' => 'organizations.expiry_date',
	);

	protected function prepare_list_query()
	{
		$this->db
			->select($this->table.'.*, locations.name AS location_name', FALSE)
			->from($this->table)
			->join('locations', 'locations.id = '.$this->table.'.location_id', 'left');
	}

	protected function apply_list_filters(array $filters)
	{
		$ids = array();

		foreach ((array) ($filters['location_ids'] ?? array()) as $id)
		{
			if (is_numeric($id))
			{
				$ids[] = (int) $id;
			}
		}

		if ( ! empty($ids))
		{
			$this->db->where_in($this->table.'.location_id', $ids);
		}

		$this->apply_expiry_status_filter($filters['expiry_status'] ?? '');
	}

	protected function apply_expiry_status_filter($status)
	{
		$status = trim((string) $status);
		$column = $this->table.'.expiry_date';
		$today = date('Y-m-d');

		if ($status === 'expired')
		{
			$this->db
				->where($column.' IS NOT NULL', NULL, FALSE)
				->where($column.' <', $today);

			return;
		}

		if ($status === 'expiring_1_month')
		{
			$this->db
				->where($column.' IS NOT NULL', NULL, FALSE)
				->where($column.' >=', $today)
				->where($column.' <=', date('Y-m-d', strtotime('+1 month')));

			return;
		}

		if ($status === 'expiring_2_months')
		{
			$this->db
				->where($column.' IS NOT NULL', NULL, FALSE)
				->where($column.' >=', $today)
				->where($column.' <=', date('Y-m-d', strtotime('+2 months')));
		}
	}

	protected function apply_column_filters(array $filters)
	{
		$columns = $filters['columns'] ?? array();

		if ( ! is_array($columns) || empty($this->filterable_columns))
		{
			return;
		}

		foreach ($columns as $key => $value)
		{
			$key = trim((string) $key);
			$value = trim((string) $value);

			if ($key === '' || $value === '' || ! isset($this->filterable_columns[$key]))
			{
				continue;
			}

			if ($key === 'currency')
			{
				if ($value === '1' || $value === '2')
				{
					$this->db->where($this->filterable_columns[$key], $value);
				}

				continue;
			}

			$this->db->like($this->filterable_columns[$key], $value);
		}
	}

	public function get_by_name($name)
	{
		$name = trim((string) $name);

		if ($name === '')
		{
			return NULL;
		}

		return $this->db
			->where('name', $name)
			->get($this->table)
			->row_array();
	}

	public function missing_names(array $names)
	{
		$wanted = array();

		foreach ($names as $name)
		{
			$name = trim((string) $name);

			if ($name === '')
			{
				continue;
			}

			$wanted[strtolower($name)] = $name;
		}

		if (empty($wanted))
		{
			return array();
		}

		$rows = $this->db
			->select('name')
			->where_in('name', array_values($wanted))
			->get($this->table)
			->result_array();

		foreach ($rows as $row)
		{
			unset($wanted[strtolower(trim((string) $row['name']))]);
		}

		return array_values($wanted);
	}
}
