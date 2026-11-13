<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Export_history_model extends CI_Model {

	public function insert(array $data)
	{
		$now = date('Y-m-d H:i:s');

		if (empty($data['created_at']))
		{
			$data['created_at'] = $now;
		}

		if (empty($data['started_at']))
		{
			$data['started_at'] = $now;
		}

		if ( ! $this->db->insert('export_history', $data))
		{
			return FALSE;
		}

		return (int) $this->db->insert_id();
	}

	public function update($id, array $data)
	{
		return $this->db
			->where('id', (int) $id)
			->update('export_history', $data);
	}

	public function get($id)
	{
		return $this->db
			->where('id', (int) $id)
			->get('export_history')
			->row_array();
	}

	public function latest_completed($type = NULL)
	{
		$this->db
			->from('export_history')
			->where('status', 'completed')
			->order_by('id', 'DESC')
			->limit(1);

		$type = trim((string) $type);

		if ($type !== '')
		{
			$this->db->where('type', $type);
		}

		return $this->db->get()->row_array();
	}

	public function list_recent($limit = 20)
	{
		return $this->db
			->order_by('id', 'DESC')
			->limit((int) $limit)
			->get('export_history')
			->result_array();
	}
}
