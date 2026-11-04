<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Compares a registration product with a catalog product.
 *
 * Name, brand, standard number, and package type all have to be close.
 * Spaces, brackets, and other punctuation are ignored, and small edits
 * still count as the same value. A different package type or a clearly
 * different name does not.
 */
class Product_similarity {

	const FIELD_THRESHOLD = 0.88;

	protected $fields = array('name', 'brand', 'standard_number', 'package_type');

	public function is_similar(array $left, array $right)
	{
		if ($this->compact(isset($left['name']) ? $left['name'] : '') === '')
		{
			return FALSE;
		}

		$score = $this->score($left, $right);

		return ! empty($score['similar']);
	}

	public function score(array $left, array $right)
	{
		$parts = array();

		foreach ($this->fields as $field)
		{
			$parts[$field] = $this->field_similarity(
				isset($left[$field]) ? $left[$field] : '',
				isset($right[$field]) ? $right[$field] : ''
			);
		}

		$min = min($parts);

		return array(
			'fields'  => $parts,
			'score'   => array_sum($parts) / count($parts),
			'similar' => $min >= self::FIELD_THRESHOLD,
		);
	}

	public function field_similarity($left, $right)
	{
		$compact_left = $this->compact($left);
		$compact_right = $this->compact($right);

		if ($compact_left === '' && $compact_right === '')
		{
			return 1.0;
		}

		if ($compact_left === '' || $compact_right === '')
		{
			return 0.0;
		}

		if ($compact_left === $compact_right)
		{
			return 1.0;
		}

		return max(
			$this->ratio($compact_left, $compact_right),
			$this->token_similarity($left, $right)
		);
	}

	public function compact($value)
	{
		$tokens = $this->tokens($value);

		return implode('', $tokens);
	}

	protected function token_similarity($left, $right)
	{
		$left_tokens = $this->tokens($left);
		$right_tokens = $this->tokens($right);

		if ( ! $left_tokens || ! $right_tokens)
		{
			return 0.0;
		}

		$left_sorted = $left_tokens;
		$right_sorted = $right_tokens;
		sort($left_sorted, SORT_STRING);
		sort($right_sorted, SORT_STRING);

		return max(
			$this->jaccard($left_tokens, $right_tokens),
			$this->ratio(implode('', $left_sorted), implode('', $right_sorted))
		);
	}

	protected function jaccard(array $left, array $right)
	{
		$left_set = array_fill_keys($left, TRUE);
		$right_set = array_fill_keys($right, TRUE);
		$intersection = array_intersect_key($left_set, $right_set);
		$union = $left_set + $right_set;
		$union_count = count($union);

		if ($union_count === 0)
		{
			return 1.0;
		}

		return count($intersection) / $union_count;
	}

	protected function tokens($value)
	{
		$value = trim((string) $value);

		if ($value === '')
		{
			return array();
		}

		if (function_exists('mb_strtolower'))
		{
			$value = mb_strtolower($value, 'UTF-8');
		}
		else
		{
			$value = strtolower($value);
		}

		$parts = preg_split('/[^\p{L}\p{N}]+/u', $value, -1, PREG_SPLIT_NO_EMPTY);

		return is_array($parts) ? array_values($parts) : array();
	}

	protected function ratio($left, $right)
	{
		$left_chars = $this->chars($left);
		$right_chars = $this->chars($right);
		$max = max(count($left_chars), count($right_chars));

		if ($max === 0)
		{
			return 1.0;
		}

		if ($max > 80)
		{
			similar_text($left, $right, $percent);

			return ((float) $percent) / 100;
		}

		$distance = $this->levenshtein_chars($left_chars, $right_chars);

		return max(0, 1 - ($distance / $max));
	}

	protected function chars($value)
	{
		if (function_exists('mb_strlen') && function_exists('mb_substr'))
		{
			$chars = array();
			$length = mb_strlen($value, 'UTF-8');

			for ($index = 0; $index < $length; $index++)
			{
				$chars[] = mb_substr($value, $index, 1, 'UTF-8');
			}

			return $chars;
		}

		return preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
	}

	protected function levenshtein_chars(array $left, array $right)
	{
		$left_length = count($left);
		$right_length = count($right);
		$previous = range(0, $right_length);

		for ($i = 1; $i <= $left_length; $i++)
		{
			$current = array($i);

			for ($j = 1; $j <= $right_length; $j++)
			{
				$cost = ($left[$i - 1] === $right[$j - 1]) ? 0 : 1;
				$current[$j] = min(
					$current[$j - 1] + 1,
					$previous[$j] + 1,
					$previous[$j - 1] + $cost
				);
			}

			$previous = $current;
		}

		return $previous[$right_length];
	}
}
