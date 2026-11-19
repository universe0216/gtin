<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'libraries/Product_registration_export.php';
require_once APPPATH.'libraries/Product_registration_doc.php';

/**
 * Organization detail download: template document plus mapped JSON.
 */
class Organization_publication_export extends Product_registration_export {

	public function build_download($organization)
	{
		$mapper = $this->publication_mapper('org_pub_json_mapper');

		if ($mapper === NULL)
		{
			$mapper = array(
				'Org_Name' => 'name',
				'Prefix'   => 'gs1_prefix',
			);
		}

		$name = trim((string) ($organization['name'] ?? ''));
		$date = trim((string) ($organization['reg_date'] ?? ''));

		if ( ! preg_match('/^\d{4}-\d{2}-\d{2}/', $date) || strpos($date, '0000') === 0)
		{
			$date = date('Y-m-d');
		}
		else
		{
			$date = substr($date, 0, 10);
		}

		$document = new Product_registration_doc();
		$base = $this->file_part($name, 'organization');
		$entries = array(
			array(
				'name' => $base.'.json',
				'data' => $this->json_document($this->mapped_payload($organization, $mapper)),
			),
			array(
				'name' => $base.'.doc',
				'data' => $document->render_template(
					APPPATH.'templates/organization_registration.doc',
					array(
						'factory_name' => $name,
						'product_name' => '',
						'standard'     => '',
						'date'         => $date,
					)
				),
			),
		);
		$prefix = $this->file_part($organization['gs1_prefix'] ?? '', '');
		$filename = $prefix === '' ? $base.'.zip' : $base.'_'.$prefix.'.zip';

		return array(
			'filename' => $filename,
			'binary'   => $this->zip_entries($entries),
			'count'    => count($entries),
		);
	}
}
