<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Data_log_model extends CI_Model {

	public function insert($data)
	{
		$data['created_at'] = date('Y-m-d H:i:s');

		if ( ! $this->db->insert('data_log', $data))
		{
			return FALSE;
		}

		return (int) $this->db->insert_id();
	}

	public function get($id)
	{
		return $this->db
			->select('data_log.*, accounts.full_name AS account_name, accounts.username')
			->from('data_log')
			->join('accounts', 'accounts.id = data_log.account_id', 'left')
			->where('data_log.id', (int) $id)
			->get()
			->row_array();
	}

	public function list_by_entity($entity, $entity_id, $limit = 100)
	{
		return $this->db
			->select('data_log.*, accounts.full_name AS account_name, accounts.username')
			->from('data_log')
			->join('accounts', 'accounts.id = data_log.account_id', 'left')
			->where('data_log.entity', $entity)
			->where('data_log.entity_id', (int) $entity_id)
			->order_by('data_log.id', 'DESC')
			->limit((int) $limit)
			->get()
			->result_array();
	}

	public function count_filtered($search = '', $entity = '', $action = '')
	{
		$this->prepare_list_query($search, $entity, $action);

		return (int) $this->db->count_all_results();
	}

	public function get_paginated($limit, $offset = 0, $search = '', $entity = '', $action = '')
	{
		$this->prepare_list_query($search, $entity, $action);

		return $this->db
			->order_by('data_log.id', 'DESC')
			->limit((int) $limit, (int) $offset)
			->get()
			->result_array();
	}

	protected function prepare_list_query($search = '', $entity = '', $action = '')
	{
		$this->db
			->from('data_log')
			->join('accounts', 'accounts.id = data_log.account_id', 'left');

		$entity = trim((string) $entity);

		if ($entity !== '')
		{
			$this->db->where('data_log.entity', $entity);
		}

		$action = trim((string) $action);

		if ($action !== '')
		{
			$this->db->where('data_log.action', $action);
		}

		$search = trim((string) $search);

		if ($search === '')
		{
			return;
		}

		$this->db->group_start();
		$this->db->like('data_log.entity', $search);
		$this->db->or_like('data_log.action', $search);
		$this->db->or_like('accounts.username', $search);
		$this->db->or_like('accounts.full_name', $search);

		if (ctype_digit($search))
		{
			$this->db->or_where('data_log.entity_id', (int) $search);
			$this->db->or_where('data_log.id', (int) $search);
		}

		$this->db->group_end();
	}
}
