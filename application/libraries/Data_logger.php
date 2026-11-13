<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Writes entity change history into data_log (whole-row JSON).
 */
class Data_logger {

	const ACTION_CREATE = 'create';
	const ACTION_UPDATE = 'update';
	const ACTION_ARCHIVE = 'archive';
	const ACTION_RESTORE = 'restore';

	const ENTITY_PRODUCT = 'product';
	const ENTITY_ORGANIZATION = 'organization';

	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->model('data_log_model');
	}

	public function log($entity, $entity_id, $action, $original_data = NULL, $new_data = NULL)
	{
		$entity = trim((string) $entity);
		$action = trim((string) $action);
		$entity_id = (int) $entity_id;

		if ($entity === '' || $action === '' || $entity_id < 1)
		{
			return FALSE;
		}

		return (bool) $this->CI->data_log_model->insert(array(
			'entity'         => $entity,
			'entity_id'      => $entity_id,
			'action'         => $action,
			'original_data'  => $this->encode_row($original_data),
			'new_data'       => $this->encode_row($new_data),
			'account_id'     => $this->current_account_id(),
		));
	}

	public function log_create($entity, $entity_id, array $new_data)
	{
		return $this->log($entity, $entity_id, self::ACTION_CREATE, NULL, $new_data);
	}

	public function log_update($entity, $entity_id, $original_data, array $new_data)
	{
		return $this->log($entity, $entity_id, self::ACTION_UPDATE, $original_data, $new_data);
	}

	public function log_archive($entity, $entity_id, array $original_data)
	{
		return $this->log($entity, $entity_id, self::ACTION_ARCHIVE, $original_data, NULL);
	}

	public function log_restore($entity, $entity_id, array $original_data, array $new_data)
	{
		return $this->log($entity, $entity_id, self::ACTION_RESTORE, $original_data, $new_data);
	}

	/**
	 * History rows for an entity: who, action, when, and field-level changes.
	 */
	public function history_for($entity, $entity_id, $limit = 200)
	{
		$entity = trim((string) $entity);
		$entity_id = (int) $entity_id;

		if ($entity === '' || $entity_id < 1)
		{
			return array();
		}

		$rows = $this->CI->data_log_model->list_by_entity($entity, $entity_id, $limit);
		$formatted = array();

		foreach ($rows as $row)
		{
			$formatted[] = $this->format_history_row($row);
		}

		return $formatted;
	}

	public function format_history_row(array $row)
	{
		$original = $this->decode_row($row['original_data'] ?? NULL);
		$new = $this->decode_row($row['new_data'] ?? NULL);

		return array(
			'id'            => (int) ($row['id'] ?? 0),
			'entity'        => (string) ($row['entity'] ?? ''),
			'entity_id'     => (int) ($row['entity_id'] ?? 0),
			'action'        => (string) ($row['action'] ?? ''),
			'created_at'    => (string) ($row['created_at'] ?? ''),
			'account_id'    => isset($row['account_id']) ? (int) $row['account_id'] : NULL,
			'account_label' => $this->account_label($row),
			'changes'       => $this->diff_rows($original, $new),
		);
	}

	protected function decode_row($value)
	{
		if ($value === NULL || $value === '')
		{
			return NULL;
		}

		if (is_array($value))
		{
			return $value;
		}

		$decoded = json_decode((string) $value, TRUE);

		return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : NULL;
	}

	protected function diff_rows($original, $new)
	{
		$original = is_array($original) ? $original : array();
		$new = is_array($new) ? $new : array();

		if (empty($original) && empty($new))
		{
			return array();
		}

		$skip = array(
			'updated_at' => TRUE,
			'deleted_at' => TRUE,
			'origin_id'  => TRUE,
			'password'   => TRUE,
		);

		$keys = array_unique(array_merge(array_keys($original), array_keys($new)));
		$changes = array();

		foreach ($keys as $key)
		{
			$key = (string) $key;

			if ($key === '' || isset($skip[$key]))
			{
				continue;
			}

			$from = array_key_exists($key, $original) ? $this->normalize_diff_value($original[$key]) : NULL;
			$to = array_key_exists($key, $new) ? $this->normalize_diff_value($new[$key]) : NULL;

			if ($from === $to)
			{
				continue;
			}

			$changes[] = array(
				'field' => $key,
				'from'  => $from,
				'to'    => $to,
			);
		}

		return $changes;
	}

	protected function normalize_diff_value($value)
	{
		if ($value === NULL)
		{
			return NULL;
		}

		if (is_bool($value))
		{
			return $value ? '1' : '0';
		}

		if (is_array($value) || is_object($value))
		{
			return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		$text = trim((string) $value);

		return $text === '' ? NULL : $text;
	}

	protected function account_label(array $row)
	{
		$name = trim((string) ($row['account_name'] ?? ''));
		$username = trim((string) ($row['username'] ?? ''));

		if ($name !== '' && $username !== '')
		{
			return $name.' ('.$username.')';
		}

		if ($name !== '')
		{
			return $name;
		}

		if ($username !== '')
		{
			return $username;
		}

		return ! empty($row['account_id']) ? '#'.$row['account_id'] : '—';
	}

	protected function encode_row($row)
	{
		if ($row === NULL)
		{
			return NULL;
		}

		if ( ! is_array($row))
		{
			return NULL;
		}

		return json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	protected function current_account_id()
	{
		if ( ! isset($this->CI->auth) || ! is_object($this->CI->auth))
		{
			return NULL;
		}

		$user = $this->CI->auth->user();

		if ( ! $user || empty($user['id']))
		{
			return NULL;
		}

		return (int) $user['id'];
	}
}
