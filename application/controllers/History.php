<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

class History extends AuthenticatedController {

	protected $history_per_page = 15;

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
		$this->load->model('procedure_model');
		$this->load->model('procedure_item_model');
		$this->load->library('procedure_processor');
	}

	public function procedure($id = NULL)
	{
		if ($id !== NULL)
		{
			if ($this->input->is_ajax_request())
			{
				return $this->json_procedure_items($id);
			}

			show_404();
		}

		$per_page = $this->history_per_page;
		$page = max(1, (int) $this->input->get('page'));
		$total = $this->procedure_model->count_by_status('completed');
		$total_pages = max(1, (int) ceil($total / $per_page));

		if ($page > $total_pages)
		{
			$page = $total_pages;
		}

		$offset = ($page - 1) * $per_page;
		$procedures = $this->procedure_model->get_by_status('completed', $per_page, $offset);

		$this->load->library('pagination');
		$this->pagination->initialize(array(
			'base_url'             => site_url('history/procedure'),
			'total_rows'           => $total,
			'per_page'             => $per_page,
			'use_page_numbers'     => TRUE,
			'page_query_string'    => TRUE,
			'query_string_segment' => 'page',
			'reuse_query_string'   => TRUE,
			'full_tag_open'        => '<nav aria-label="Procedure history pages"><ul class="pagination pagination-sm mb-0">',
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
		));

		$range_start = $total > 0 ? $offset + 1 : 0;
		$range_end = min($offset + count($procedures), $total);

		$data = array(
			'title'         => 'Procedure History',
			'nav_active'    => 'history_procedure',
			'procedures'    => $procedures,
			'pagination'    => $this->pagination->create_links(),
			'total'         => $total,
			'page'          => $page,
			'per_page'      => $per_page,
			'row_offset'    => $offset,
			'range_start'   => $range_start,
			'range_end'     => $range_end,
		);

		$data['content'] = $this->load->view('history/procedure', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	protected function build_history_tab($id)
	{
		$procedure = $this->procedure_model->get($id);

		if ( ! $procedure || $procedure['status'] !== 'completed')
		{
			return NULL;
		}

		$items = $this->procedure_item_model->get_by_procedure($id);
		$tab = $this->procedure_processor->format_procedure_tab($procedure, $items);

		foreach ($tab['rows'] as $index => $row)
		{
			$item = $items[$index] ?? array();
			$tab['rows'][$index]['status'] = $item['status'] ?? '';
			$tab['rows'][$index]['message'] = $item['message'] ?? '';
			$tab['rows'][$index]['barcode'] = $item['barcode'] ?? '';
		}

		return $tab;
	}

	protected function json_procedure_items($id)
	{
		$tab = $this->build_history_tab($id);

		if ($tab === NULL)
		{
			return $this->json_response(array(
				'success' => FALSE,
				'message' => 'Procedure not found.',
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
