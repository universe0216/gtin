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
		$left_key = $this->built_key($left);
		$right_key = $this->built_key($right);
		$exact = ($left_key !== '' && $left_key !== '___' && $left_key === $right_key);
		$name_score = $this->name_score(
			isset($left['name']) ? $left['name'] : '',
			isset($right['name']) ? $right['name'] : ''
		);
		$parts = array('name' => $exact ? 1.0 : $name_score);
		$others_match = TRUE;

		foreach (array('brand', 'standard_number', 'package_type') as $field)
		{
			$match = $this->field_part($field, $left) === $this->field_part($field, $right);
			$parts[$field] = $match ? 1.0 : 0.0;

			if ( ! $match)
			{
				$others_match = FALSE;
			}
		}

		$listed = $exact || ($others_match && $name_score >= 0.6);
		$score = 0.0;

		if ($exact)
		{
			$score = 1.0;
		}
		elseif ($listed)
		{
			$score = ($name_score + $parts['brand'] + $parts['standard_number'] + $parts['package_type']) / 4;
		}

		return array(
			'fields'          => $parts,
			'score'           => $score,
			'similar'         => $exact,
			'listed'          => $listed,
			'normalized_key'  => array(
				'left'  => $left_key,
				'right' => $right_key,
			),
		);
	}

	/**
	 * 1 when the names match. Lower when one name contains the other, or when
	 * they share most of the same words, such as "Green Tea 330ml" and
	 * "Green Tea 330ml Teset".
	 */
	public function name_score($left, $right)
	{
		$left_name = $this->normalized_name($left);
		$right_name = $this->normalized_name($right);

		if ($left_name === '' || $right_name === '')
		{
			return 0.0;
		}

		if ($left_name === $right_name)
		{
			return 1.0;
		}

		$containment = 0.0;
		$left_length = $this->text_length($left_name);
		$right_length = $this->text_length($right_name);
		$short = $left_length <= $right_length ? $left_name : $right_name;
		$long = $left_length <= $right_length ? $right_name : $left_name;

		if ($this->text_length($short) >= 8 && $this->text_position($long, $short) !== FALSE)
		{
			$containment = $this->text_length($short) / $this->text_length($long);
		}

		$left_tokens = $this->name_tokens($left);
		$right_tokens = $this->name_tokens($right);
		$jaccard = 0.0;

		if ($left_tokens && $right_tokens)
		{
			$shared = array_intersect($left_tokens, $right_tokens);

			if (count($shared) >= 2)
			{
				$jaccard = count($shared) / count(array_unique(array_merge($left_tokens, $right_tokens)));
			}
		}

		return max($containment, $jaccard);
	}

	protected function built_key(array $product)
	{
		return product_build_normalized_key(
			isset($product['name']) ? $product['name'] : '',
			standard_number_normalize(isset($product['standard_number']) ? $product['standard_number'] : ''),
			isset($product['brand']) ? $product['brand'] : '',
			isset($product['package_type']) ? $product['package_type'] : ''
		);
	}

	protected function field_part($field, array $product)
	{
		$value = isset($product[$field]) ? $product[$field] : '';

		if ($field === 'standard_number')
		{
			$value = standard_number_normalize($value);
		}

		if ($field === 'name')
		{
			return $this->normalized_name($value);
		}

		return product_normalized_key_part($value);
	}

	protected function normalized_name($name)
	{
		return product_normalized_key_part(product_normalize_name_for_key($name));
	}

	protected function name_tokens($name)
	{
		$name = trim((string) preg_replace('/\s+/u', ' ', product_normalize_name_for_key($name)));

		if ($name === '')
		{
			return array();
		}

		$tokens = array();

		foreach (preg_split('/\s+/u', $name) as $part)
		{
			$part = product_normalized_key_part($part);

			if ($part !== '')
			{
				$tokens[$part] = TRUE;
			}
		}

		return array_keys($tokens);
	}

	protected function text_length($value)
	{
		if (function_exists('mb_strlen'))
		{
			return mb_strlen($value, 'UTF-8');
		}

		return strlen($value);
	}

	protected function text_position($haystack, $needle)
	{
		if (function_exists('mb_strpos'))
		{
			return mb_strpos($haystack, $needle, 0, 'UTF-8');
		}

		return strpos($haystack, $needle);
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
