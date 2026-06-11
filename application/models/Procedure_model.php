<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Procedure_model extends CI_Model {

	protected $table = 'procedures';

	public function get_all()
	{
		return $this->db
			->select('procedures.*, accounts.full_name AS processor_name')
			->join('accounts', 'accounts.id = procedures.account_id', 'left')
			->order_by('procedures.id', 'DESC')
			->get($this->table)
			->result_array();
	}

	public function get_by_status($status, $limit = NULL, $offset = 0)
	{
		$this->db
			->select('procedures.*, accounts.full_name AS processor_name')
			->join('accounts', 'accounts.id = procedures.account_id', 'left')
			->where('procedures.status', $status)
			->order_by('procedures.id', 'DESC');

		if ($limit !== NULL)
		{
			$this->db->limit((int) $limit, (int) $offset);
		}

		return $this->db->get($this->table)->result_array();
	}

	public function count_by_status($status)
	{
		return (int) $this->db
			->where('status', $status)
			->count_all_results($this->table);
	}

	public function get($id)
	{
		return $this->db
			->select('procedures.*, accounts.full_name AS processor_name')
			->join('accounts', 'accounts.id = procedures.account_id', 'left')
			->where('procedures.id', $id)
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
