<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Org_registration_model extends CI_Model {

	protected $table = 'org_registrations';

	public function get_incomplete_by_account($account_id)
	{
		return $this->db
			->select('org_registrations.*, accounts.full_name AS processor_name')
			->join('accounts', 'accounts.id = org_registrations.account_id', 'left')
			->where('org_registrations.account_id', (int) $account_id)
			->where('org_registrations.status !=', 'completed')
			->order_by('org_registrations.id', 'DESC')
			->get($this->table)
			->result_array();
	}

	public function get($id)
	{
		return $this->db
			->select('org_registrations.*, accounts.full_name AS processor_name')
			->join('accounts', 'accounts.id = org_registrations.account_id', 'left')
			->where('org_registrations.id', $id)
			->get($this->table)
			->row_array();
	}

	public function exists_by_file_and_procedure($file_name, $procedure_number)
	{
		return $this->db
			->where('file_name', $file_name)
			->where('procedure_number', $procedure_number)
			->count_all_results($this->table) > 0;
	}

	public function count_all($search = '')
	{
		$this->apply_search_filters($search);

		return (int) $this->db->count_all_results($this->table);
	}

	public function get_all($limit = NULL, $offset = 0, $search = '')
	{
		$this->db
			->select('org_registrations.*, accounts.full_name AS processor_name')
			->join('accounts', 'accounts.id = org_registrations.account_id', 'left');

		$this->apply_search_filters($search);
		$this->db->order_by('org_registrations.id', 'DESC');

		if ($limit !== NULL)
		{
			$this->db->limit((int) $limit, (int) $offset);
		}

		return $this->db->get($this->table)->result_array();
	}

	protected function apply_search_filters($search)
	{
		$search = trim((string) $search);

		if ($search === '')
		{
			return;
		}

		$this->db->group_start();
		$this->db->like('org_registrations.file_name', $search);
		$this->db->or_like('org_registrations.procedure_number', $search);
		$this->db->or_like('accounts.full_name', $search);
		$this->db->group_end();
	}

	public function insert($data)
	{
		$data['created_at'] = date('Y-m-d H:i:s');

		if ($this->db->insert($this->table, $data))
		{
			return $this->db->insert_id();
		}

		return FALSE;
	}

	public function update($id, $data)
	{
		return $this->db
			->where('id', $id)
			->update($this->table, $data);
	}

	public function delete($id)
	{
		return $this->db
			->where('id', $id)
			->delete($this->table);
	}
}
