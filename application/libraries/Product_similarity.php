<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Compares a registration product with a catalog product using normalized_key.
 *
 * Keys are built from name, standard_number, brand, and package_type.
 */
class Product_similarity {

	protected $fields = array('name', 'brand', 'standard_number', 'package_type');

	public function __construct()
	{
		$CI =& get_instance();
		$CI->load->helper(array('product_key', 'standard_number'));
	}

	public function is_similar(array $left, array $right)
	{
		$left_key = $this->normalized_key($left);

		if ($left_key === '' || $left_key === '___')
		{
			return FALSE;
		}

		return $left_key === $this->normalized_key($right);
	}

	public function score(array $left, array $right)
	{
		$left_key = $this->normalized_key($left);
		$right_key = $this->normalized_key($right);
		$similar = ($left_key !== '' && $left_key !== '___' && $left_key === $right_key);
		$parts = array();

		foreach ($this->fields as $field)
		{
			$parts[$field] = $similar ? 1.0 : 0.0;
		}

		return array(
			'fields'          => $parts,
			'score'           => $similar ? 1.0 : 0.0,
			'similar'         => $similar,
			'normalized_key'  => array(
				'left'  => $left_key,
				'right' => $right_key,
			),
		);
	}

	/**
	 * Build or reuse a product normalized_key.
	 */
	public function normalized_key(array $product)
	{
		if (isset($product['normalized_key']))
		{
			$key = trim((string) $product['normalized_key']);

			if ($key !== '')
			{
				return $key;
			}
		}

		return product_build_normalized_key(
			isset($product['name']) ? $product['name'] : '',
			standard_number_normalize(isset($product['standard_number']) ? $product['standard_number'] : ''),
			isset($product['brand']) ? $product['brand'] : '',
			isset($product['package_type']) ? $product['package_type'] : ''
		);
	}
}
