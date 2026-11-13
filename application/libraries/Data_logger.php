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
