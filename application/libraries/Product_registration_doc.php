<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Fills application/templates/product_registration.doc.
 * Open that file in Word to change fonts, sizes, and layout.
 * Keep these placeholders: {factory_name} {product_name} {standard} {date}
 * Resize the two pictures in the template. Do not delete them.
 */
class Product_registration_doc {

	public function render($fields, $barcode_png, $barcode_2d_png)
	{
		$template = APPPATH.'templates/product_registration.doc';

		if ( ! is_file($template))
		{
			throw new RuntimeException('Document template was not found.');
		}

		$values = array(
			'{factory_name}' => $this->xml_text($fields['factory_name'] ?? ''),
			'{product_name}' => $this->xml_text($fields['product_name'] ?? ''),
			'{standard}'     => $this->xml_text($fields['standard'] ?? ''),
			'{date}'         => $this->xml_text($fields['date'] ?? ''),
		);

		return $this->fill_docx($template, $values, array($barcode_png, $barcode_2d_png));
	}

	protected function fill_docx($template, $values, $images)
	{
		$temp = tempnam(sys_get_temp_dir(), 'regdoc');

		if ($temp === FALSE || ! copy($template, $temp))
		{
			throw new RuntimeException('Unable to prepare the document.');
		}

		$zip = new ZipArchive();

		if ($zip->open($temp) !== TRUE)
		{
			@unlink($temp);
			throw new RuntimeException('Unable to open the document template.');
		}

		$entries = array();

		for ($index = 0; $index < $zip->numFiles; $index++)
		{
			$name = $zip->getNameIndex($index);
			$entries[$name] = $zip->getFromIndex($index);
		}

		$targets = $this->image_targets(
			isset($entries['word/document.xml']) ? $entries['word/document.xml'] : '',
			isset($entries['word/_rels/document.xml.rels']) ? $entries['word/_rels/document.xml.rels'] : ''
		);
		$zip->close();
		@unlink($temp);

		foreach ($images as $index => $binary)
		{
			if ($binary === NULL || $binary === '' || ! isset($targets[$index]))
			{
				continue;
			}

			$entries[$targets[$index]] = $binary;
		}

		if ($zip->open($temp, ZipArchive::CREATE) !== TRUE)
		{
			throw new RuntimeException('Unable to prepare the document.');
		}

		foreach ($entries as $name => $data)
		{
			if (preg_match('#^word/(document|header\d+|footer\d+|footnotes|endnotes)\.xml$#', $name))
			{
				$data = strtr($this->join_placeholders($data), $values);
			}

			$zip->addFromString($name, $data);
		}

		$zip->close();
		$binary = file_get_contents($temp);
		@unlink($temp);

		if ($binary === FALSE)
		{
			throw new RuntimeException('Unable to prepare the document.');
		}

		return $binary;
	}

	protected function image_targets($document, $relationships)
	{
		$by_id = array();

		if (preg_match_all('/<Relationship\b[^>]*>/', (string) $relationships, $tags))
		{
			foreach ($tags[0] as $tag)
			{
				if (strpos($tag, '/image') === FALSE)
				{
					continue;
				}

				if ( ! preg_match('/\bId="([^"]+)"/', $tag, $id) || ! preg_match('/\bTarget="([^"]+)"/', $tag, $target))
				{
					continue;
				}

				$by_id[$id[1]] = $this->media_path($target[1]);
			}
		}

		$ordered = array();

		if (preg_match_all('/\br:embed="([^"]+)"/', (string) $document, $embeds))
		{
			foreach ($embeds[1] as $id)
			{
				if (isset($by_id[$id]))
				{
					$ordered[] = $by_id[$id];
				}
			}
		}

		return $ordered;
	}

	protected function media_path($target)
	{
		$target = str_replace('\\', '/', $target);
		$target = preg_replace('#^(\.\./)+#', '', $target);

		if (strpos($target, 'word/') !== 0)
		{
			$target = 'word/'.ltrim($target, '/');
		}

		return $target;
	}

	protected function join_placeholders($xml)
	{
		foreach (array('factory_name', 'product_name', 'standard', 'date') as $name)
		{
			$parts = str_split($name);
			$gap = '(?:<[^>]*>|\s)*';
			$pattern = '/\{'.$gap.implode($gap, $parts).$gap.'\}/';
			$xml = preg_replace($pattern, '{'.$name.'}', $xml);
		}

		return $xml;
	}

	protected function xml_text($value)
	{
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}
}
