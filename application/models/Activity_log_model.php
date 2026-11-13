<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Activity_log_model extends CI_Model {

	public function insert($data)
	{
		$data['created_at'] = date('Y-m-d H:i:s');

		if ( ! $this->db->insert('activity_log', $data))
		{
			return FALSE;
		}

		return (int) $this->db->insert_id();
	}

	public function get($id)
	{
		return $this->db
			->select('activity_log.*, accounts.full_name AS account_name, accounts.username')
			->from('activity_log')
			->join('accounts', 'accounts.id = activity_log.account_id', 'left')
			->where('activity_log.id', (int) $id)
			->get()
			->row_array();
	}

	public function count_filtered($search = '', $action = '')
	{
		$this->prepare_list_query($search, $action);

		return (int) $this->db->count_all_results();
	}

	public function get_paginated($limit, $offset = 0, $search = '', $action = '')
	{
		$this->prepare_list_query($search, $action);

		return $this->db
			->order_by('activity_log.id', 'DESC')
			->limit((int) $limit, (int) $offset)
			->get()
			->result_array();
	}

	protected function prepare_list_query($search = '', $action = '')
	{
		$this->db
			->from('activity_log')
			->join('accounts', 'accounts.id = activity_log.account_id', 'left');

		$action = trim((string) $action);

		if ($action !== '')
		{
			$this->db->where('activity_log.action', $action);
		}

		$search = trim((string) $search);

		if ($search === '')
		{
			return;
		}

		$this->db->group_start();
		$this->db->like('activity_log.path', $search);
		$this->db->or_like('activity_log.controller', $search);
		$this->db->or_like('activity_log.method_name', $search);
		$this->db->or_like('activity_log.ip_address', $search);
		$this->db->or_like('activity_log.action', $search);
		$this->db->or_like('accounts.username', $search);
		$this->db->or_like('accounts.full_name', $search);
		$this->db->group_end();
	}
}
