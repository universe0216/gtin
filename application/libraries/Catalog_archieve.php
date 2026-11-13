<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Moves organizations and products into the archive tables and restores them.
 * Archive rows keep the original column values, plus origin_id and deleted_at.
 */
class Catalog_archieve {

	protected $ci;

	public function __construct()
	{
		$this->ci =& get_instance();
	}

	public function archive_product($id)
	{
		$id = (int) $id;
		$this->ci->db->trans_begin();
		$row = $this->row('products', $id);

		if ( ! $row)
		{
			$this->ci->db->trans_rollback();

			return array('ok' => FALSE, 'code' => 'not_found');
		}

		if ( ! $this->move_row('products', 'products_archieve', $row))
		{
			$this->ci->db->trans_rollback();

			return array('ok' => FALSE, 'code' => 'failed');
		}

		$this->ci->db->trans_commit();
		$this->ensure_data_logger();
		$this->log_archive(Data_logger::ENTITY_PRODUCT, $id, $row);

		return array('ok' => TRUE);
	}

	public function archive_organization($id)
	{
		$id = (int) $id;
		$this->ci->db->trans_begin();
		$organization = $this->row('organizations', $id);

		if ( ! $organization)
		{
			$this->ci->db->trans_rollback();

			return array('ok' => FALSE, 'code' => 'not_found');
		}

		$products = $this->ci->db
			->where('org_id', $id)
			->get('products')
			->result_array();

		foreach ($products as $product)
		{
			if ( ! $this->move_row('products', 'products_archieve', $product))
			{
				$this->ci->db->trans_rollback();

				return array('ok' => FALSE, 'code' => 'failed');
			}
		}

		if ( ! $this->move_row('organizations', 'organization_archieve', $organization))
		{
			$this->ci->db->trans_rollback();

			return array('ok' => FALSE, 'code' => 'failed');
		}

		$this->ci->db->trans_commit();
		$this->ensure_data_logger();

		foreach ($products as $product)
		{
			$this->log_archive(Data_logger::ENTITY_PRODUCT, (int) $product['id'], $product);
		}

		$this->log_archive(Data_logger::ENTITY_ORGANIZATION, $id, $organization);

		return array(
			'ok'            => TRUE,
			'product_count' => count($products),
		);
	}

	public function describe_product_restore($id)
	{
		$row = $this->row('products_archieve', $id);

		if ( ! $row)
		{
			return array('ok' => FALSE, 'code' => 'not_found');
		}

		$organization = $this->archived_organization_for_product($row);

		return array(
			'ok'                   => TRUE,
			'name'                 => $row['name'],
			'needs_organization'   => $organization !== NULL,
			'organization_name'    => $organization ? $organization['name'] : '',
			'organization_archive_id' => $organization ? (int) $organization['id'] : 0,
		);
	}

	public function restore_product($id, $restore_organization = FALSE)
	{
		$id = (int) $id;
		$this->ci->db->trans_begin();
		$row = $this->row('products_archieve', $id);

		if ( ! $row)
		{
			$this->ci->db->trans_rollback();

			return array('ok' => FALSE, 'code' => 'not_found');
		}

		$organization = $this->archived_organization_for_product($row);

		if ($organization && ! $restore_organization)
		{
			$this->ci->db->trans_rollback();

			return array(
				'ok'                => FALSE,
				'code'              => 'needs_organization',
				'organization_name' => $organization['name'],
			);
		}

		if ($organization && ! $this->restore_row('organization_archieve', 'organizations', $organization))
		{
			$this->ci->db->trans_rollback();

			return array('ok' => FALSE, 'code' => $this->restore_failure_code('organizations', $organization));
		}

		if ( ! $this->restore_row('products_archieve', 'products', $row))
		{
			$this->ci->db->trans_rollback();

			return array('ok' => FALSE, 'code' => $this->restore_failure_code('products', $row));
		}

		$this->ci->db->trans_commit();
		$this->ensure_data_logger();

		if ($organization)
		{
			$origin_org_id = (int) ($organization['origin_id'] ?? 0);
			$restored_org = $origin_org_id > 0 ? $this->row('organizations', $origin_org_id) : NULL;

			if ($restored_org)
			{
				$this->log_restore(Data_logger::ENTITY_ORGANIZATION, $origin_org_id, $organization, $restored_org);
			}
		}

		$origin_product_id = (int) ($row['origin_id'] ?? 0);
		$restored_product = $origin_product_id > 0 ? $this->row('products', $origin_product_id) : NULL;

		if ($restored_product)
		{
			$this->log_restore(Data_logger::ENTITY_PRODUCT, $origin_product_id, $row, $restored_product);
		}

		return array(
			'ok'                    => TRUE,
			'restored_organization' => $organization !== NULL,
		);
	}

	public function restore_organization($id)
	{
		$id = (int) $id;
		$this->ci->db->trans_begin();
		$row = $this->row('organization_archieve', $id);

		if ( ! $row)
		{
			$this->ci->db->trans_rollback();

			return array('ok' => FALSE, 'code' => 'not_found');
		}

		if ( ! $this->restore_row('organization_archieve', 'organizations', $row))
		{
			$this->ci->db->trans_rollback();

			return array('ok' => FALSE, 'code' => $this->restore_failure_code('organizations', $row));
		}

		$this->ci->db->trans_commit();
		$this->ensure_data_logger();

		$origin_id = (int) ($row['origin_id'] ?? 0);
		$restored = $origin_id > 0 ? $this->row('organizations', $origin_id) : NULL;

		if ($restored)
		{
			$this->log_restore(Data_logger::ENTITY_ORGANIZATION, $origin_id, $row, $restored);
		}

		return array('ok' => TRUE);
	}

	protected function ensure_data_logger()
	{
		$this->ci->load->library('data_logger');
	}

	protected function log_archive($entity, $entity_id, array $original_data)
	{
		$this->ensure_data_logger();
		$this->ci->data_logger->log_archive($entity, (int) $entity_id, $original_data);
	}

	protected function log_restore($entity, $entity_id, array $original_data, array $new_data)
	{
		$this->ensure_data_logger();
		$this->ci->data_logger->log_restore($entity, (int) $entity_id, $original_data, $new_data);
	}

	protected function archived_organization_for_product(array $product)
	{
		$organization_id = (int) ($product['org_id'] ?? 0);

		if ($organization_id < 1)
		{
			return NULL;
		}

		$live = $this->row('organizations', $organization_id);

		if ($live)
		{
			return NULL;
		}

		return $this->ci->db
			->where('origin_id', $organization_id)
			->order_by('deleted_at', 'DESC')
			->order_by('id', 'DESC')
			->limit(1)
			->get('organization_archieve')
			->row_array() ?: NULL;
	}

	protected function move_row($source, $archive, array $row)
	{
		$data = $this->copy_columns($archive, $row);
		$data['origin_id'] = (int) $row['id'];
		$data['deleted_at'] = date('Y-m-d H:i:s');
		unset($data['id']);

		if ( ! $this->ci->db->insert($archive, $data))
		{
			return FALSE;
		}

		$this->ci->db->where('id', (int) $row['id'])->delete($source);

		return $this->ci->db->affected_rows() > 0;
	}

	protected function restore_row($archive, $destination, array $row)
	{
		$origin_id = (int) ($row['origin_id'] ?? 0);

		if ($origin_id < 1)
		{
			return FALSE;
		}

		if ($this->row($destination, $origin_id))
		{
			return FALSE;
		}

		$data = $this->copy_columns($destination, $row);
		$data['id'] = $origin_id;

		if ( ! $this->ci->db->insert($destination, $data))
		{
			return FALSE;
		}

		$this->ci->db->where('id', (int) $row['id'])->delete($archive);

		return $this->ci->db->affected_rows() > 0;
	}

	protected function restore_failure_code($destination, array $row)
	{
		$origin_id = (int) ($row['origin_id'] ?? 0);

		if ($origin_id > 0 && $this->row($destination, $origin_id))
		{
			return 'id_taken';
		}

		return 'failed';
	}

	protected function copy_columns($table, array $row)
	{
		$data = array();

		foreach ($this->ci->db->list_fields($table) as $field)
		{
			if ($field === 'id' || $field === 'origin_id' || $field === 'deleted_at')
			{
				continue;
			}

			if (array_key_exists($field, $row))
			{
				$data[$field] = $row[$field];
			}
		}

		return $data;
	}

	protected function row($table, $id)
	{
		return $this->ci->db
			->where('id', (int) $id)
			->get($table)
			->row_array();
	}
}
