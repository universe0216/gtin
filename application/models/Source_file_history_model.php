<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Source_file_history_model extends CI_Model {

	public function get($id)
	{
		return $this->db
			->select('source_file_history.*, accounts.full_name AS account_name, accounts.username')
			->from('source_file_history')
			->join('accounts', 'accounts.id = source_file_history.account_id', 'left')
			->where('source_file_history.id', (int) $id)
			->get()
			->row_array();
	}

	public function list_by_path($relative_path)
	{
		return $this->db
			->select('source_file_history.*, accounts.full_name AS account_name, accounts.username')
			->from('source_file_history')
			->join('accounts', 'accounts.id = source_file_history.account_id', 'left')
			->where('source_file_history.relative_path', $relative_path)
			->order_by('source_file_history.created_at', 'DESC')
			->order_by('source_file_history.id', 'DESC')
			->get()
			->result_array();
	}

	public function insert($data)
	{
		$data['created_at'] = date('Y-m-d H:i:s');

		if ( ! $this->db->insert('source_file_history', $data))
		{
			return FALSE;
		}

		return (int) $this->db->insert_id();
	}
}
