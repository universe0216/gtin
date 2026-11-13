<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Records page visits and mutating API/form requests into activity_log.
 */
class Activity_logger {

	const ACTION_VISIT = 'visit';
	const ACTION_API = 'api';

	protected $CI;
	protected $sensitive_keys = array(
		'password',
		'current_password',
		'new_password',
		'confirm_password',
		'csrf_test_name',
		'csrf_token',
	);

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function capture_request()
	{
		if (is_cli())
		{
			return FALSE;
		}

		$method = strtoupper((string) $this->CI->input->method(TRUE));
		$is_ajax = (bool) $this->CI->input->is_ajax_request();
		$action = NULL;

		if (in_array($method, array('POST', 'PUT', 'PATCH', 'DELETE'), TRUE))
		{
			$action = self::ACTION_API;
		}
		elseif ($method === 'GET' && ! $is_ajax)
		{
			$action = self::ACTION_VISIT;
		}

		if ($action === NULL)
		{
			return FALSE;
		}

		$path = $this->request_path();

		if ($this->should_skip_path($path))
		{
			return FALSE;
		}

		$account_id = $this->current_account_id();
		$controller = $this->limit_text($this->CI->router->fetch_class(), 100);
		$method_name = $this->limit_text($this->CI->router->fetch_method(), 100);
		$ip_address = $this->client_ip();

		// Collapse accidental multi-hits (refresh storms / duplicate document loads).
		if ($action === self::ACTION_VISIT && $this->is_duplicate_visit($account_id, $path, $method, $ip_address))
		{
			return FALSE;
		}

		$this->CI->load->model('activity_log_model');

		return (bool) $this->CI->activity_log_model->insert(array(
			'account_id'    => $account_id,
			'action'        => $action,
			'method'        => $method,
			'path'          => $this->limit_text($path, 500),
			'controller'    => $controller,
			'method_name'   => $method_name,
			'ip_address'    => $ip_address,
			'user_agent'    => $this->limit_text((string) $this->CI->input->user_agent(), 500),
			'request_data'  => $action === self::ACTION_API ? $this->encode_request_data() : NULL,
		));
	}

	protected function is_duplicate_visit($account_id, $path, $method, $ip_address)
	{
		$this->CI->load->model('activity_log_model');

		$this->CI->db
			->from('activity_log')
			->where('action', self::ACTION_VISIT)
			->where('method', $method)
			->where('path', $this->limit_text($path, 500))
			->where('ip_address', $ip_address)
			->where('created_at >=', date('Y-m-d H:i:s', time() - 3));

		if ($account_id === NULL)
		{
			$this->CI->db->where('account_id IS NULL', NULL, FALSE);
		}
		else
		{
			$this->CI->db->where('account_id', (int) $account_id);
		}

		return (int) $this->CI->db->count_all_results() > 0;
	}

	protected function request_path()
	{
		$path = trim((string) $this->CI->uri->uri_string(), '/');

		if ($path === '')
		{
			$path = '/';
		}

		$query = isset($_SERVER['QUERY_STRING']) ? trim((string) $_SERVER['QUERY_STRING']) : '';

		if ($query !== '')
		{
			$path .= '?'.$query;
		}

		return $path;
	}

	protected function should_skip_path($path)
	{
		$bare = strtolower(strtok($path, '?'));

		$skip = array(
			'activity_log',
			'history/activity_log',
			'history/activity_log_list',
			'history/activity_log_detail',
			'history/data_log',
			'history/data_log_list',
			'history/data_log_detail',
			'data_patch/export_start',
			'data_patch/export_chunk',
			'data_patch/status',
		);

		foreach ($skip as $prefix)
		{
			if ($bare === $prefix || strpos($bare, $prefix.'/') === 0)
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	protected function encode_request_data()
	{
		$payload = array(
			'post'  => $this->sanitize_array((array) $this->CI->input->post(NULL, TRUE)),
			'get'   => $this->sanitize_array((array) $this->CI->input->get(NULL, TRUE)),
			'files' => $this->file_summary(),
		);

		if (empty($payload['post']) && empty($payload['get']) && empty($payload['files']))
		{
			return NULL;
		}

		return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	protected function sanitize_array(array $data)
	{
		$clean = array();

		foreach ($data as $key => $value)
		{
			$key_name = strtolower((string) $key);

			if (in_array($key_name, $this->sensitive_keys, TRUE) || strpos($key_name, 'password') !== FALSE)
			{
				$clean[$key] = '[redacted]';
				continue;
			}

			if (is_array($value))
			{
				$clean[$key] = $this->sanitize_array($value);
				continue;
			}

			$clean[$key] = $this->limit_text((string) $value, 2000);
		}

		return $clean;
	}

	protected function file_summary()
	{
		if (empty($_FILES) || ! is_array($_FILES))
		{
			return array();
		}

		$files = array();

		foreach ($_FILES as $field => $info)
		{
			if (isset($info['name']) && is_array($info['name']))
			{
				$names = array();

				foreach ($info['name'] as $name)
				{
					$names[] = $this->limit_text((string) $name, 255);
				}

				$files[$field] = array(
					'names' => $names,
					'count' => count($names),
				);
				continue;
			}

			$files[$field] = array(
				'name' => $this->limit_text((string) ($info['name'] ?? ''), 255),
				'size' => isset($info['size']) ? (int) $info['size'] : NULL,
				'error' => isset($info['error']) ? (int) $info['error'] : NULL,
			);
		}

		return $files;
	}

	protected function client_ip()
	{
		$ip = $this->CI->input->ip_address();

		if ( ! is_string($ip) || $ip === '')
		{
			return '0.0.0.0';
		}

		return $this->limit_text($ip, 45);
	}

	protected function current_account_id()
	{
		if ( ! isset($this->CI->auth) || ! is_object($this->CI->auth))
		{
			return NULL;
		}

		if ( ! $this->CI->auth->is_logged_in())
		{
			return NULL;
		}

		$user = $this->CI->auth->user();

		return $user && ! empty($user['id']) ? (int) $user['id'] : NULL;
	}

	protected function limit_text($value, $max)
	{
		$value = (string) $value;

		if (function_exists('mb_substr'))
		{
			return mb_substr($value, 0, $max);
		}

		return substr($value, 0, $max);
	}
}
