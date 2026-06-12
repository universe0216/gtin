<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Organization_registration_item_model extends CI_Model {

	protected $table = 'organization_registration_items';

	public function get_by_organization_registration($organization_registration_id)
	{
		return $this->db
			->where('organization_registration_id', $organization_registration_id)
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
