<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Activity_log_hook {

	public function record()
	{
		$CI =& get_instance();

		if ( ! isset($CI->db) || ! is_object($CI->db))
		{
			return;
		}

		try
		{
			$CI->load->library('activity_logger');
			$CI->activity_logger->capture_request();
		}
		catch (Exception $exception)
		{
			log_message('error', 'activity_log failed: '.$exception->getMessage());
		}
	}
}
