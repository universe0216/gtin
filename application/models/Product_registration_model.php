<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Product_registration_model extends CI_Model {

	protected $table = 'product_registrations';

	public function get_all()
	{
		return $this->db
			->select('product_registrations.*, accounts.full_name AS processor_name')
			->join('accounts', 'accounts.id = product_registrations.account_id', 'left')
			->order_by('product_registrations.id', 'DESC')
			->get($this->table)
			->result_array();
	}

	public function get_incomplete_by_account($account_id)
	{
		return $this->db
			->select('product_registrations.*, accounts.full_name AS processor_name')
			->join('accounts', 'accounts.id = product_registrations.account_id', 'left')
			->where('product_registrations.account_id', (int) $account_id)
			->where('product_registrations.status !=', 'completed')
			->order_by('product_registrations.id', 'DESC')
			->get($this->table)
			->result_array();
	}

	public function get_by_status($status, $limit = NULL, $offset = 0, $search = '')
	{
		$this->db
			->select('product_registrations.*, accounts.full_name AS processor_name')
			->join('accounts', 'accounts.id = product_registrations.account_id', 'left')
			->where('product_registrations.status', $status);

		$this->apply_search_filters($search);

		$this->db->order_by('product_registrations.id', 'DESC');

		if ($limit !== NULL)
		{
			$this->db->limit((int) $limit, (int) $offset);
		}

		return $this->db->get($this->table)->result_array();
	}

	public function count_by_status($status, $search = '')
	{
		$this->db
			->join('accounts', 'accounts.id = product_registrations.account_id', 'left')
			->where('product_registrations.status', $status);

		$this->apply_search_filters($search);

		return (int) $this->db->count_all_results($this->table);
	}

	protected function apply_search_filters($search)
	{
		$search = trim((string) $search);

		if ($search === '')
		{
			return;
		}

		$this->db->group_start();
		$this->db->like('product_registrations.file_name', $search);
		$this->db->or_like('product_registrations.procedure_number', $search);
		$this->db->or_like('product_registrations.organization_name', $search);
		$this->db->or_like('accounts.full_name', $search);
		$this->db->group_end();
	}

	public function get($id)
	{
		return $this->db
			->select('product_registrations.*, accounts.full_name AS processor_name')
			->join('accounts', 'accounts.id = product_registrations.account_id', 'left')
			->where('product_registrations.id', $id)
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
