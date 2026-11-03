<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Product_registration_item_model extends CI_Model {

	protected $table = 'product_registration_items';

	public function get($id)
	{
		return $this->db
			->where('id', $id)
			->get($this->table)
			->row_array();
	}

	public function find_latest_approved_match($barcode, $name)
	{
		$barcode = trim((string) $barcode);
		$name = trim((string) $name);

		if ($barcode === '' && $name === '')
		{
			return NULL;
		}

		$this->db
			->select('product_registration_items.*, product_registrations.procedure_number, product_registrations.created_at AS registration_created_at, product_registrations.organization_name')
			->from($this->table)
			->join('product_registrations', 'product_registrations.id = product_registration_items.product_registration_id', 'inner')
			->where_in('product_registration_items.status', array('approved', 'accepted'));

		if ($barcode !== '')
		{
			$this->db->where('product_registration_items.barcode', $barcode);
		}
		else
		{
			$this->db->where('product_registration_items.name', $name);
		}

		return $this->db
			->order_by('product_registration_items.id', 'DESC')
			->limit(1)
			->get()
			->row_array();
	}

	public function get_by_product_registration($product_registration_id)
	{
		return $this->db
			->where('product_registration_id', $product_registration_id)
			->order_by('id', 'ASC')
			->get($this->table)
			->result_array();
	}

	public function update($id, $data)
	{
		return $this->db
			->where('id', $id)
			->update($this->table, $data);
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
