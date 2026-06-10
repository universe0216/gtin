<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Account_model extends CI_Model {

	public function get_all()
	{
		return $this->db
			->select('id, username, full_name, is_admin, is_active, created_at, updated_at')
			->order_by('id', 'ASC')
			->get('accounts')
			->result_array();
	}

	public function get($id)
	{
		$account = $this->db
			->where('id', $id)
			->get('accounts')
			->row_array();

		if ( ! $account)
		{
			return NULL;
		}

		unset($account['password']);
		$account['permissions'] = $this->get_permissions($id);
		$account['is_admin'] = (bool) $account['is_admin'];
		$account['is_active'] = (bool) $account['is_active'];

		return $account;
	}

	public function get_by_username($username)
	{
		return $this->db
			->where('username', $username)
			->get('accounts')
			->row_array();
	}

	public function get_permissions($account_id)
	{
		$rows = $this->db
			->select('permission')
			->where('account_id', $account_id)
			->get('account_permissions')
			->result_array();

		return array_column($rows, 'permission');
	}

	public function insert($data, $permissions = array())
	{
		$data['created_at'] = date('Y-m-d H:i:s');
		$data['updated_at'] = date('Y-m-d H:i:s');

		if ($this->db->insert('accounts', $data))
		{
			$id = $this->db->insert_id();
			$this->save_permissions($id, $permissions, ! empty($data['is_admin']));

			return $id;
		}

		return FALSE;
	}

	public function update($id, $data, $permissions = NULL)
	{
		$data['updated_at'] = date('Y-m-d H:i:s');

		if ( ! $this->db->where('id', $id)->update('accounts', $data))
		{
			return FALSE;
		}

		if ($permissions !== NULL)
		{
			$account = $this->db->where('id', $id)->get('accounts')->row_array();
			$is_admin = isset($data['is_admin'])
				? ! empty($data['is_admin'])
				: ! empty($account['is_admin']);

			$this->save_permissions($id, $permissions, $is_admin);
		}

		return TRUE;
	}

	public function delete($id)
	{
		return $this->db->where('id', $id)->delete('accounts');
	}

	public function username_exists($username, $exclude_id = NULL)
	{
		$this->db->where('username', $username);

		if ($exclude_id)
		{
			$this->db->where('id !=', $exclude_id);
		}

		return $this->db->count_all_results('accounts') > 0;
	}

	protected function save_permissions($account_id, $permissions, $is_admin)
	{
		$this->db->where('account_id', $account_id)->delete('account_permissions');

		if ($is_admin || empty($permissions))
		{
			return;
		}

		foreach ($permissions as $permission)
		{
			$this->db->insert('account_permissions', array(
				'account_id' => $account_id,
				'permission' => $permission,
			));
		}
	}
}
