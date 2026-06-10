<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Procedure_item_model extends CI_Model {

	protected $table = 'procedure_items';

	public function get_by_procedure($procedure_id)
	{
		return $this->db
			->where('procedure_id', $procedure_id)
			->order_by('id', 'ASC')
			->get($this->table)
			->result_array();
	}

	public function insert_batch($items)
	{
		if (empty($items))
		{
			return TRUE;
		}

		return $this->db->insert_batch($this->table, $items);
	}
}
