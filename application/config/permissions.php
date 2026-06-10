<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['permission_groups'] = array(
	'organization' => array(
		'label'       => 'Organization',
		'permissions' => array(
			'organization.view' => 'View',
			'organization.edit' => 'Edit',
		),
	),
	'product' => array(
		'label'       => 'Product',
		'permissions' => array(
			'product.view' => 'View',
			'product.edit' => 'Edit',
		),
	),
	'primary' => array(
		'label'       => 'Primary',
		'permissions' => array(
			'primary' => 'Access',
		),
	),
);

$config['all_permissions'] = array(
	'organization.view',
	'organization.edit',
	'product.view',
	'product.edit',
	'primary',
);
