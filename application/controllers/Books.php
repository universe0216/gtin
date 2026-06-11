<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AuthenticatedController.php';

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once APPPATH.'libraries/Spreadsheet_value_binder.php';

class Books extends AuthenticatedController {

	public function gtin_country_code()
	{
		$data = array(
			'title'         => 'GTIN Country Codes',
			'nav_active'    => 'books_gtin_country_code',
			'country_codes' => $this->get_country_codes(),
		);

		$data['content'] = $this->load->view('books/gtin_country_code', $data, TRUE);
		$this->load->view('layouts/main', $data);
	}

	public function gtin_country_code_export()
	{
		if ( ! class_exists(Spreadsheet::class))
		{
			show_error('Spreadsheet library is not available. Run composer install.', 500);
		}

		$country_codes = $this->get_country_codes();

		Cell::setValueBinder(new Spreadsheet_value_binder());

		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('GTIN Country Codes');

		$sheet->fromArray(array('#', 'Prefix', 'Country / Region'), NULL, 'A1', TRUE);

		$row = 2;

		foreach ($country_codes as $index => $entry)
		{
			$sheet->fromArray(array(
				$index + 1,
				$entry['prefix'],
				$entry['country'],
			), NULL, 'A'.$row, TRUE);
			$row++;
		}

		$sheet->getColumnDimension('A')->setWidth(6);
		$sheet->getColumnDimension('B')->setWidth(18);
		$sheet->getColumnDimension('C')->setWidth(52);
		$sheet->getStyle('A1:C1')->getFont()->setBold(TRUE);

		$filename = 'gtin-country-codes-'.date('Y-m-d').'.xlsx';

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header('Cache-Control: max-age=0');

		$writer = new Xlsx($spreadsheet);
		$writer->save('php://output');
		$spreadsheet->disconnectWorksheets();
		exit;
	}

	protected function get_country_codes()
	{
		$this->config->load('gtin_country_codes');

		return $this->config->item('gtin_country_codes');
	}
}
