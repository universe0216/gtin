<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Settings_model extends CI_Model {

	public function get_all()
	{
		return $this->db
			->select('key, description, settings')
			->order_by('key', 'ASC')
			->get('settings')
			->result_array();
	}

	public function list_keys()
	{
		return $this->db
			->select('key, description')
			->order_by('key', 'ASC')
			->get('settings')
			->result_array();
	}

	public function get($key)
	{
		$row = $this->db
			->where('key', (string) $key)
			->get('settings')
			->row_array();

		return $row ?: NULL;
	}

	public function get_json($key, $default = array())
	{
		$row = $this->get($key);

		if ( ! $row || ! isset($row['settings']) || $row['settings'] === NULL || $row['settings'] === '')
		{
			return is_array($default) ? $default : array();
		}

		$decoded = json_decode((string) $row['settings'], TRUE);

		return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : (is_array($default) ? $default : array());
	}

	public function set_json($key, array $settings, $description = NULL)
	{
		$payload = array(
			'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		);

		if ($description !== NULL)
		{
			$payload['description'] = $this->limit_text($description, 500);
		}

		$existing = $this->get($key);

		if ($existing)
		{
			return $this->db->where('key', (string) $key)->update('settings', $payload);
		}

		$payload['key'] = (string) $key;

		if ( ! isset($payload['description']))
		{
			$payload['description'] = NULL;
		}

		return $this->db->insert('settings', $payload);
	}

	public function update_description($key, $description)
	{
		return $this->db->where('key', (string) $key)->update('settings', array(
			'description' => $this->limit_text($description, 500),
		));
	}

	public function create_setting($key, $description = '', array $settings = array())
	{
		$key = trim((string) $key);

		if ($key === '')
		{
			return FALSE;
		}

		if ($this->get($key))
		{
			return FALSE;
		}

		return $this->db->insert('settings', array(
			'key'         => $key,
			'description' => $this->limit_text($description, 500),
			'settings'    => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		));
	}

	public function delete_setting($key)
	{
		return $this->db->where('key', (string) $key)->delete('settings');
	}

	protected function limit_text($value, $max)
	{
		$value = trim((string) $value);

		if (function_exists('mb_substr'))
		{
			return mb_substr($value, 0, (int) $max);
		}

		return substr($value, 0, (int) $max);
	}
}
