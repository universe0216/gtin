<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Build the completed-registration download archive.
 */
class Product_registration_export {

	public function download_name($file_name)
	{
		$file_name = trim(str_replace(array('\\', '/'), '', (string) $file_name));

		if ($file_name === '')
		{
			$file_name = 'registration.zip';
		}

		$name = 'DONE_'.$file_name;

		if ( ! preg_match('/\.zip$/i', $name))
		{
			$name = preg_replace('/\.(xls|xlsx|csv)$/i', '', $name).'.zip';
		}

		return $name;
	}

	public function build($registration, $items)
	{
		$entries = $this->collect_entries($registration, $items);

		return array(
			'filename' => $this->download_name($registration['file_name'] ?? ''),
			'binary'   => $this->zip_entries($entries),
			'count'    => count($entries),
		);
	}

	public function build_catalog($product, $source = NULL)
	{
		$registration = array(
			'procedure_number'  => '',
			'organization_name' => trim((string) ($product['organization_name'] ?? '')),
			'created_at'        => trim((string) ($product['reg_date'] ?? ($product['created_at'] ?? ''))),
		);
		$item = NULL;

		if (is_array($source))
		{
			$item = $source;
			$item['status'] = 'approved';
			$registration['procedure_number'] = trim((string) ($source['procedure_number'] ?? ''));

			if ($registration['procedure_number'] === '')
			{
				$info = json_decode((string) ($source['info'] ?? ''), TRUE);

				if (is_array($info))
				{
					$registration['procedure_number'] = trim((string) ($info['procedure_number'] ?? ''));
				}
			}

			if ( ! empty($source['registration_created_at']))
			{
				$registration['created_at'] = $source['registration_created_at'];
			}
		}

		$entries = $item ? $this->collect_entries($registration, array($item)) : array();

		if ( ! $entries)
		{
			$entries = $this->collect_entries($registration, array($this->catalog_item($product)));
		}

		$barcode = trim((string) ($product['barcode_1d'] ?? ''));

		return array(
			'filename' => $this->file_part($product['name'] ?? '').'_'.$this->file_part($barcode, 'barcode').'.zip',
			'binary'   => $this->zip_entries($entries),
			'count'    => count($entries),
		);
	}

	protected function collect_entries($registration, $items)
	{
		require_once APPPATH.'libraries/Barcode_png.php';
		require_once APPPATH.'libraries/Qr_png.php';
		require_once APPPATH.'libraries/Product_registration_doc.php';

		$barcode = new Barcode_png();
		$qr = new Qr_png();
		$document = new Product_registration_doc();
		$procedure_number = trim((string) ($registration['procedure_number'] ?? ''));
		$organization_name = trim((string) ($registration['organization_name'] ?? ''));
		$entries = array();
		$used = array();

		foreach ($items as $item)
		{
			$status = strtolower(trim((string) ($item['status'] ?? '')));
			$info = json_decode((string) ($item['info'] ?? ''), TRUE);

			if ( ! is_array($info))
			{
				$info = array();
			}

			$columns = isset($info['columns']) && is_array($info['columns']) ? $info['columns'] : array();
			$cells = isset($info['cells']) && is_array($info['cells']) ? $info['cells'] : array();
			$product_name = $this->cell($columns, $cells, array('product name'));

			if ($product_name === '')
			{
				$product_name = trim((string) ($item['name'] ?? ''));
			}

			if ($status === 'approved' || $status === 'accepted')
			{
				$barcode_value = $this->cell($columns, $cells, array('1d barcode', 'barcode'));

				if ($barcode_value === '')
				{
					$barcode_value = trim((string) ($item['barcode'] ?? ''));
				}

				if ($barcode_value === '')
				{
					continue;
				}

				$barcode_type = $this->cell($columns, $cells, array('type of 1d barcode'));
				$factory_name = $this->factory_name($columns, $cells);
				$standard = $this->cell($columns, $cells, array('standard'));
				$barcode_2d = $this->cell($columns, $cells, array('2d barcode'));
				$base = $this->file_part($product_name).'_'.$this->file_part($procedure_number);
				$payload = array(
					'ProductName'           => $product_name,
					'TradeMark'             => $this->cell($columns, $cells, array('brand')),
					'FactoryName'           => $factory_name,
					'Norm'                  => $standard,
					'PackingType'           => $this->cell($columns, $cells, array('package type')),
					'KeepDate'              => $this->cell($columns, $cells, array('perios before expired', 'period before expired')),
					'Barcode_1D_RegNumber'  => $barcode_value,
					'Barcode_2D_Info'       => $barcode_2d,
					'Barcode_2D_Category'   => $this->cell($columns, $cells, array('type of 2d barcode')),
				);
				$entries[] = array(
					'name' => $this->unique_name($base, 'json', $used),
					'data' => $this->json_document($payload),
				);
				$png = $barcode->png($barcode_type !== '' ? $barcode_type : 'code128', $barcode_value);

				if ($png !== NULL)
				{
					$entries[] = array(
						'name' => $this->unique_name($base, 'png', $used),
						'data' => $png,
					);
				}

				$qr_png = $qr->png($barcode_2d !== '' ? $barcode_2d : $barcode_value);
				$doc_base = $this->file_part($procedure_number).'_R_'.$this->file_part($factory_name, 'factory').'_'.$this->file_part($product_name);
				$entries[] = array(
					'name' => $this->unique_name($doc_base, 'doc', $used),
					'data' => $document->render(array(
						'factory_name' => $factory_name,
						'product_name' => $product_name,
						'standard'     => $standard,
						'date'         => $this->document_date($columns, $cells, $registration),
					), $png, $qr_png),
				);

				continue;
			}

			if ($status !== 'rejected')
			{
				continue;
			}

			$base = $this->file_part($procedure_number).'_'.$this->file_part($organization_name).'_'.$this->file_part($product_name);
			$entries[] = array(
				'name' => $this->unique_name($base, 'json', $used),
				'data' => $this->json_document(array(
					'ID'   => $procedure_number,
					'Note' => trim((string) ($item['message'] ?? '')),
				)),
			);
		}

		return $entries;
	}

	protected function catalog_item($product)
	{
		return array(
			'status'  => 'approved',
			'name'    => $product['name'] ?? '',
			'barcode' => $product['barcode_1d'] ?? '',
			'info'    => json_encode(array(
				'columns' => array(
					'Product Name',
					'Brand',
					'Standard',
					'Package Type',
					'Perios before expired',
					'1D barcode',
					'type of 1D barcode',
					'2D barcode',
					'type of 2d Barcode',
					'factory name',
					'ReceiveDate',
				),
				'cells' => array(
					$product['name'] ?? '',
					$product['brand'] ?? '',
					$product['standard_number'] ?? '',
					$product['package_type'] ?? '',
					$product['effective_period'] ?? '',
					$product['barcode_1d'] ?? '',
					$product['barcode_1d_type'] ?? '',
					$product['barcode_2d'] ?? '',
					$product['barcode_2d_type'] ?? '',
					$product['organization_name'] ?? '',
					$product['reg_date'] ?? '',
				),
			)),
		);
	}

	protected function factory_name($columns, $cells)
	{
		$name = $this->cell($columns, $cells, array('factory name'));

		if ($name === '' && isset($cells[22]))
		{
			$name = trim((string) $cells[22]);
		}

		return $name;
	}

	protected function cell($columns, $cells, $names)
	{
		$index = $this->column_index($columns, $names);

		if ($index === NULL || ! isset($cells[$index]))
		{
			return '';
		}

		return trim((string) $cells[$index]);
	}

	protected function column_index($columns, $names)
	{
		$normalized = array();

		foreach (array_values((array) $columns) as $index => $column)
		{
			$normalized[$index] = $this->normalize_column($column);
		}

		foreach ((array) $names as $name)
		{
			$target = $this->normalize_column($name);

			foreach ($normalized as $index => $column)
			{
				if ($column === $target)
				{
					return (int) $index;
				}
			}
		}

		return NULL;
	}

	protected function normalize_column($column)
	{
		$name = strtolower(trim((string) $column));

		return preg_replace('/\s+/', ' ', $name);
	}

	protected function document_date($columns, $cells, $registration)
	{
		$date = $this->cell($columns, $cells, array('receivedate', 'receive date'));

		if ($date !== '')
		{
			return $date;
		}

		$created = trim((string) ($registration['created_at'] ?? ''));

		if ($created !== '')
		{
			return substr($created, 0, 10);
		}

		return date('Y-m-d');
	}

	protected function file_part($value, $fallback = 'product')
	{
		$value = trim((string) $value);
		$value = preg_replace('/[\\\\\\/:\*\?"<>\|]+/u', ' ', $value);
		$value = preg_replace('/\s+/u', ' ', $value);
		$value = trim($value, " .\t");

		if (function_exists('mb_substr'))
		{
			$value = mb_substr($value, 0, 80);
		}
		else
		{
			$value = substr($value, 0, 80);
		}

		return $value === '' ? $fallback : $value;
	}

	protected function unique_name($base, $extension, &$used)
	{
		$name = $base.'.'.$extension;
		$index = 2;

		while (isset($used[strtolower($name)]))
		{
			$name = $base.'_'.$index.'.'.$extension;
			$index++;
		}

		$used[strtolower($name)] = TRUE;

		return $name;
	}

	protected function json_document($payload)
	{
		$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($json === FALSE)
		{
			$json = '{}';
		}

		return $json."\n";
	}

	protected function zip_entries($entries)
	{
		$temp = tempnam(sys_get_temp_dir(), 'regdl');

		if ($temp === FALSE)
		{
			throw new RuntimeException('Unable to create the download archive.');
		}

		$zip = new ZipArchive();

		if ($zip->open($temp, ZipArchive::OVERWRITE) !== TRUE)
		{
			@unlink($temp);
			throw new RuntimeException('Unable to create the download archive.');
		}

		foreach ($entries as $entry)
		{
			$zip->addFromString($entry['name'], $entry['data']);
		}

		$zip->close();
		$binary = file_get_contents($temp);
		@unlink($temp);

		if ($binary === FALSE)
		{
			throw new RuntimeException('Unable to create the download archive.');
		}

		return $binary;
	}
}
