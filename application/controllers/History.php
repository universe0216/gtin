<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class History extends AuthenticatedController {

	protected $history_per_page = 15;

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
		$this->load->model('product_registration_model');
		$this->load->model('product_registration_item_model');
		$this->load->model('organization_registration_model');
		$this->load->model('organization_registration_item_model');
		$this->load->library('product_registration_processor');
		$this->load->library('organization_registration_processor');
	}

	public function products($id = NULL)
	{
		if ($id !== NULL)
		{
			if ($this->input->is_ajax_request())
			{
				return $this->json_product_registration_items($id);
			}

			show_404();
		}

		return $this->render_product_registration_history(
			'Products History',
			'history_products',
			'history/products'
		);
	}

	public function procedure($id = NULL)
	{
		if ($id !== NULL)
		{
			if ($this->input->is_ajax_request())
			{
				return $this->json_product_registration_items($id);
			}

			show_404();
		}

		return $this->render_product_registration_history(
			'Procedure History',
			'history_procedure',
			'history/procedure'
		);
	}

	public function organizations($id = NULL)
	{
		if ($id !== NULL)
		{
			if ($this->input->is_ajax_request())
			{
				return $this->json_organization_items($id);
			}

			show_404();
		}

		$per_page = $this->history_per_page;
		$page = max(1, (int) $this->input->get('page'));
		$total = $this->organization_registration_model->count_all();
		$total_pages = max(1, (int) ceil($total / $per_page));

		if ($page > $total_pages)
		{
			$page = $total_pages;
		}

		$offset = ($page - 1) * $per_page;
		$registrations = $this->organization_registration_model->get_all($per_page, $offset);

		foreach ($registrations as $index => $registration)
		{
			$registrations[$index]['organization_name'] = $this->organization_name_from_file($registration['file_name']);
		}

		$this->load->library('pagination');
		$this->pagination->initialize($this->pagination_config(
			site_url('history/organizations'),
			$total,
			$per_page
		));

		$range_start = $total > 0 ? $offset + 1 : 0;
		$range_end = min($offset + count($registrations), $total);

		$data = array(
			'title'         => 'Organizations History',
			'nav_active'    => 'history_organizations',
			'registrations' => $registrations,
			'pagination'    => $this->pagination->create_links(),
			'total'         => $total,
			'page'          => $page,
			'per_page'      => $per_page,
			'row_offset'    => $offset,
			'range_start'   => $range_start,
			'range_end'     => $range_end,
		);

		$data['content'] = $this->load->view('history/organizations', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	protected function render_product_registration_history($title, $nav_active, $route)
	{
		$per_page = $this->history_per_page;
		$page = max(1, (int) $this->input->get('page'));
		$total = $this->product_registration_model->count_by_status('completed');
		$total_pages = max(1, (int) ceil($total / $per_page));

		if ($page > $total_pages)
		{
			$page = $total_pages;
		}

		$offset = ($page - 1) * $per_page;
		$registrations = $this->product_registration_model->get_by_status('completed', $per_page, $offset);

		$this->load->library('pagination');
		$this->pagination->initialize($this->pagination_config(
			site_url($route),
			$total,
			$per_page
		));

		$range_start = $total > 0 ? $offset + 1 : 0;
		$range_end = min($offset + count($registrations), $total);

		$data = array(
			'title'         => $title,
			'nav_active'    => $nav_active,
			'registrations' => $registrations,
			'pagination'    => $this->pagination->create_links(),
			'total'         => $total,
			'page'          => $page,
			'per_page'      => $per_page,
			'row_offset'    => $offset,
			'range_start'   => $range_start,
			'range_end'     => $range_end,
			'history_route' => $route,
		);

		$data['content'] = $this->load->view('history/products', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	protected function pagination_config($base_url, $total, $per_page)
	{
		return array(
			'base_url'             => $base_url,
			'total_rows'           => $total,
			'per_page'             => $per_page,
			'use_page_numbers'     => TRUE,
			'page_query_string'    => TRUE,
			'query_string_segment' => 'page',
			'reuse_query_string'   => TRUE,
			'full_tag_open'        => '<nav aria-label="History pages"><ul class="pagination pagination-sm mb-0">',
			'full_tag_close'       => '</ul></nav>',
			'first_tag_open'       => '<li class="page-item">',
			'first_tag_close'      => '</li>',
			'last_tag_open'        => '<li class="page-item">',
			'last_tag_close'       => '</li>',
			'next_tag_open'        => '<li class="page-item">',
			'next_tag_close'       => '</li>',
			'prev_tag_open'        => '<li class="page-item">',
			'prev_tag_close'       => '</li>',
			'cur_tag_open'         => '<li class="page-item active"><span class="page-link">',
			'cur_tag_close'        => '</span></li>',
			'num_tag_open'         => '<li class="page-item">',
			'num_tag_close'        => '</li>',
			'anchor_class'         => 'page-link',
		);
	}

	protected function build_history_tab($id)
	{
		$registration = $this->product_registration_model->get($id);

		if ( ! $registration || $registration['status'] !== 'completed')
		{
			return NULL;
		}

		$items = $this->product_registration_item_model->get_by_product_registration($id);
		$tab = $this->product_registration_processor->format_product_registration_tab($registration, $items);

		foreach ($tab['rows'] as $index => $row)
		{
			$item = $items[$index] ?? array();
			$tab['rows'][$index]['status'] = $item['status'] ?? '';
			$tab['rows'][$index]['message'] = $item['message'] ?? '';
			$tab['rows'][$index]['barcode'] = $item['barcode'] ?? '';
		}

		return $tab;
	}

	protected function build_organization_history_tab($id)
	{
		$registration = $this->organization_registration_model->get($id);

		if ( ! $registration)
		{
			return NULL;
		}

		$items = $this->organization_registration_item_model->get_by_organization_registration($id);

		return $this->organization_registration_processor->format_registration_tab($registration, $items);
	}

	protected function organization_name_from_file($file_name)
	{
		$basename = pathinfo($file_name, PATHINFO_FILENAME);

		if (preg_match('/^\d+_(.+)$/u', $basename, $matches))
		{
			return trim($matches[1]);
		}

		return '';
	}

	protected function json_product_registration_items($id)
	{
		$tab = $this->build_history_tab($id);

		if ($tab === NULL)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => 'Registration not found.',
			), 404);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'tab'     => $tab,
		));
	}

	protected function json_organization_items($id)
	{
		$tab = $this->build_organization_history_tab($id);

		if ($tab === NULL)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => 'Registration not found.',
			), 404);
		}

		return $this->json_response(array(
			'success' => TRUE,
			'tab'     => $tab,
		));
	}

	protected function json_response($payload, $status = 200)
	{
		return $this->output
			->set_content_type('application/json')
			->set_status_header($status)
			->set_output(json_encode($payload));
	}
}
