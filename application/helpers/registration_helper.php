<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Product registration item statuses from config.
 */
function registration_item_statuses()
{
	static $loaded = FALSE;
	$CI =& get_instance();

	if ( ! $loaded)
	{
		$CI->config->load('registration_statuses', FALSE, TRUE);
		$loaded = TRUE;
	}

	$statuses = $CI->config->item('registration_item_statuses');

	return is_array($statuses) && $statuses
		? array_values($statuses)
		: array('pending', 'approved', 'rejected', 'warning');
}

/**
 * Return the status when it is one of the configured values, otherwise pending.
 */
function registration_item_status($status)
{
	$status = strtolower(trim((string) $status));

	return in_array($status, registration_item_statuses(), TRUE) ? $status : 'pending';
}
