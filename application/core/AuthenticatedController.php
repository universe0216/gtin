<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class AuthenticatedController extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('auth');
		$this->auth->require_login();
	}

	protected function pagination_config($base_url, $total, $per_page, $aria_label = 'Pages')
	{
		return array(
			'base_url'             => $base_url,
			'total_rows'           => $total,
			'per_page'             => $per_page,
			'use_page_numbers'     => TRUE,
			'page_query_string'    => TRUE,
			'query_string_segment' => 'page',
			'reuse_query_string'   => TRUE,
			'full_tag_open'        => '<nav class="app-pagination-nav" aria-label="'.html_escape($aria_label).'"><ul class="pagination pagination-sm pagination-circle mb-0">',
			'full_tag_close'       => '</ul></nav>',
			'first_tag_open'       => '<li class="page-item">',
			'first_tag_close'      => '</li>',
			'last_tag_open'        => '<li class="page-item">',
			'last_tag_close'       => '</li>',
			'next_tag_open'        => '<li class="page-item">',
			'next_tag_close'       => '</li>',
			'prev_tag_open'        => '<li class="page-item">',
			'prev_tag_close'       => '</li>',
			'cur_tag_open'         => '<li class="page-item active" aria-current="page"><span class="page-link">',
			'cur_tag_close'        => '</span></li>',
			'num_tag_open'         => '<li class="page-item">',
			'num_tag_close'        => '</li>',
			'anchor_class'         => 'page-link',
		);
	}
}
