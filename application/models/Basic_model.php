<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Basic_model extends CI_Model {

	protected $table = '';
	protected $primary_key = 'id';

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
}
