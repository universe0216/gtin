<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Draw a QR code as a PNG without the GD extension.
 */
class Qr_png {

	protected $exp = array();

	protected $log = array();

	public function png($text)
	{
		$modules = $this->matrix((string) $text);

		if ($modules === NULL)
		{
			return NULL;
		}

		require_once APPPATH.'libraries/Barcode_png.php';
		$barcode = new Barcode_png();

		return $barcode->matrix_png($modules, 6, 4);
	}

	public function matrix($text)
	{
		$bytes = array_values(unpack('C*', $text));
		$length = count($bytes);
		$spec = $this->version_spec($length);

		if ($spec === NULL)
		{
			return NULL;
		}

		$this->init_field();
		$data = $this->data_codewords($bytes, $spec['data']);
		$blocks = $this->split_blocks($data, $spec['blocks'], $spec['data_block'], $spec['ec']);
		$codewords = $this->interleave($blocks, $spec['ec']);
		$size = 21 + ($spec['version'] - 1) * 4;
		$matrix = array();
		$reserved = array();

		for ($y = 0; $y < $size; $y++)
		{
			$matrix[$y] = array_fill(0, $size, 0);
			$reserved[$y] = array_fill(0, $size, FALSE);
		}

		$this->function_patterns($matrix, $reserved, $size, $spec['version']);
		$this->place_data($matrix, $reserved, $size, $codewords, $spec['remainder']);

		return $this->apply_best_mask($matrix, $reserved, $size);
	}

	protected function version_spec($length)
	{
		$versions = array(
			1 => array('data' => 16, 'ec' => 10, 'blocks' => 1, 'data_block' => 16, 'remainder' => 0),
			2 => array('data' => 28, 'ec' => 16, 'blocks' => 1, 'data_block' => 28, 'remainder' => 7),
			3 => array('data' => 44, 'ec' => 26, 'blocks' => 1, 'data_block' => 44, 'remainder' => 7),
			4 => array('data' => 64, 'ec' => 18, 'blocks' => 2, 'data_block' => 32, 'remainder' => 7),
			5 => array('data' => 86, 'ec' => 24, 'blocks' => 2, 'data_block' => 43, 'remainder' => 7),
			6 => array('data' => 108, 'ec' => 16, 'blocks' => 4, 'data_block' => 27, 'remainder' => 7),
		);

		foreach ($versions as $version => $spec)
		{
			if ($length <= $spec['data'] - 2)
			{
				$spec['version'] = $version;

				return $spec;
			}
		}

		return NULL;
	}

	protected function data_codewords($bytes, $data_length)
	{
		$bits = '0100'.$this->bits(count($bytes), 8);

		foreach ($bytes as $byte)
		{
			$bits .= $this->bits($byte, 8);
		}

		$capacity = $data_length * 8;
		$bits .= str_repeat('0', min(4, $capacity - strlen($bits)));

		if (strlen($bits) % 8 !== 0)
		{
			$bits .= str_repeat('0', 8 - (strlen($bits) % 8));
		}

		$pads = array('11101100', '00010001');
		$pad_index = 0;

		while (strlen($bits) < $capacity)
		{
			$bits .= $pads[$pad_index];
			$pad_index = 1 - $pad_index;
		}

		$codewords = array();

		for ($i = 0; $i < $data_length; $i++)
		{
			$codewords[] = bindec(substr($bits, $i * 8, 8));
		}

		return $codewords;
	}

	protected function split_blocks($data, $blocks, $data_block, $ec_length)
	{
		$offset = 0;
		$result = array();

		for ($i = 0; $i < $blocks; $i++)
		{
			$block = array_slice($data, $offset, $data_block);
			$offset += $data_block;
			$result[] = array(
				'data' => $block,
				'ec'   => $this->reed_solomon($block, $ec_length),
			);
		}

		return $result;
	}

	protected function interleave($blocks, $ec_length)
	{
		$max = 0;

		foreach ($blocks as $block)
		{
			$max = max($max, count($block['data']));
		}

		$codewords = array();

		for ($i = 0; $i < $max; $i++)
		{
			foreach ($blocks as $block)
			{
				if (isset($block['data'][$i]))
				{
					$codewords[] = $block['data'][$i];
				}
			}
		}

		for ($i = 0; $i < $ec_length; $i++)
		{
			foreach ($blocks as $block)
			{
				$codewords[] = $block['ec'][$i];
			}
		}

		return $codewords;
	}

	protected function function_patterns(&$matrix, &$reserved, $size, $version)
	{
		$this->finder($matrix, $reserved, 0, 0);
		$this->finder($matrix, $reserved, $size - 7, 0);
		$this->finder($matrix, $reserved, 0, $size - 7);

		for ($i = 0; $i < 8; $i++)
		{
			$this->reserve($matrix, $reserved, 7, $i, 0);
			$this->reserve($matrix, $reserved, $i, 7, 0);
			$this->reserve($matrix, $reserved, 7, $size - 1 - $i, 0);
			$this->reserve($matrix, $reserved, $size - 1 - $i, 7, 0);
			$this->reserve($matrix, $reserved, $size - 8, $i, 0);
			$this->reserve($matrix, $reserved, $i, $size - 8, 0);
		}

		$centers = $this->alignment_centers($version);

		foreach ($centers as $y)
		{
			foreach ($centers as $x)
			{
				if ($reserved[$y][$x])
				{
					continue;
				}

				$this->alignment($matrix, $reserved, $x, $y);
			}
		}

		for ($i = 8; $i < $size - 8; $i++)
		{
			if ( ! $reserved[6][$i])
			{
				$this->reserve($matrix, $reserved, 6, $i, $i % 2 === 0 ? 1 : 0);
			}

			if ( ! $reserved[$i][6])
			{
				$this->reserve($matrix, $reserved, $i, 6, $i % 2 === 0 ? 1 : 0);
			}
		}

		$this->reserve($matrix, $reserved, $size - 8, 8, 1);

		for ($i = 0; $i < 9; $i++)
		{
			if ( ! $reserved[8][$i])
			{
				$this->reserve($matrix, $reserved, 8, $i, 0);
			}

			if ( ! $reserved[$i][8])
			{
				$this->reserve($matrix, $reserved, $i, 8, 0);
			}
		}

		for ($i = 0; $i < 8; $i++)
		{
			$this->reserve($matrix, $reserved, 8, $size - 1 - $i, 0);
			$this->reserve($matrix, $reserved, $size - 1 - $i, 8, $matrix[$size - 1 - $i][8]);
		}
	}

	protected function finder(&$matrix, &$reserved, $x, $y)
	{
		for ($row = 0; $row < 7; $row++)
		{
			for ($col = 0; $col < 7; $col++)
			{
				$edge = $row === 0 || $row === 6 || $col === 0 || $col === 6;
				$core = $row >= 2 && $row <= 4 && $col >= 2 && $col <= 4;
				$this->reserve($matrix, $reserved, $y + $row, $x + $col, ($edge || $core) ? 1 : 0);
			}
		}
	}

	protected function alignment(&$matrix, &$reserved, $x, $y)
	{
		for ($row = -2; $row <= 2; $row++)
		{
			for ($col = -2; $col <= 2; $col++)
			{
				$dark = max(abs($row), abs($col)) !== 1;
				$this->reserve($matrix, $reserved, $y + $row, $x + $col, $dark ? 1 : 0);
			}
		}
	}

	protected function alignment_centers($version)
	{
		if ($version < 2)
		{
			return array();
		}

		return array(6, 10 + $version * 4);
	}

	protected function place_data(&$matrix, $reserved, $size, $codewords, $remainder)
	{
		$bits = '';

		foreach ($codewords as $codeword)
		{
			$bits .= $this->bits($codeword, 8);
		}

		$bits .= str_repeat('0', $remainder);
		$index = 0;
		$up = TRUE;

		for ($col = $size - 1; $col > 0; $col -= 2)
		{
			if ($col === 6)
			{
				$col--;
			}

			for ($step = 0; $step < $size; $step++)
			{
				$row = $up ? $size - 1 - $step : $step;

				for ($offset = 0; $offset < 2; $offset++)
				{
					$x = $col - $offset;

					if ($reserved[$row][$x])
					{
						continue;
					}

					$matrix[$row][$x] = isset($bits[$index]) && $bits[$index] === '1' ? 1 : 0;
					$index++;
				}
			}

			$up = ! $up;
		}
	}

	protected function apply_best_mask($matrix, $reserved, $size)
	{
		$best = NULL;
		$best_score = NULL;

		for ($mask = 0; $mask < 8; $mask++)
		{
			$candidate = $this->masked($matrix, $reserved, $size, $mask);
			$this->format_bits($candidate, $size, $mask);
			$score = $this->penalty($candidate, $size);

			if ($best_score === NULL || $score < $best_score)
			{
				$best = $candidate;
				$best_score = $score;
			}
		}

		return $best;
	}

	protected function masked($matrix, $reserved, $size, $mask)
	{
		$result = $matrix;

		for ($y = 0; $y < $size; $y++)
		{
			for ($x = 0; $x < $size; $x++)
			{
				if ($reserved[$y][$x])
				{
					continue;
				}

				if ($this->mask_bit($mask, $y, $x))
				{
					$result[$y][$x] ^= 1;
				}
			}
		}

		return $result;
	}

	protected function mask_bit($mask, $y, $x)
	{
		switch ($mask)
		{
			case 0: return ($y + $x) % 2 === 0;
			case 1: return $y % 2 === 0;
			case 2: return $x % 3 === 0;
			case 3: return ($y + $x) % 3 === 0;
			case 4: return (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0;
			case 5: return ($y * $x) % 2 + ($y * $x) % 3 === 0;
			case 6: return (($y * $x) % 2 + ($y * $x) % 3) % 2 === 0;
			default: return (($y + $x) % 2 + ($y * $x) % 3) % 2 === 0;
		}
	}

	protected function format_bits(&$matrix, $size, $mask)
	{
		$data = (0 << 3) | $mask;
		$bits = $data << 10;

		for ($i = 14; $i >= 10; $i--)
		{
			if ((($bits >> $i) & 1) === 1)
			{
				$bits ^= 0x537 << ($i - 10);
			}
		}

		$format = (($data << 10) | $bits) ^ 0x5412;
		$primary = array(
			array(8, 0), array(8, 1), array(8, 2), array(8, 3), array(8, 4), array(8, 5), array(8, 7), array(8, 8),
			array(7, 8), array(5, 8), array(4, 8), array(3, 8), array(2, 8), array(1, 8), array(0, 8),
		);

		for ($i = 0; $i < 15; $i++)
		{
			$dark = (($format >> $i) & 1) === 1 ? 1 : 0;
			$matrix[$primary[$i][0]][$primary[$i][1]] = $dark;

			if ($i < 7)
			{
				$matrix[$size - 1 - $i][8] = $dark;
			}
			else
			{
				$matrix[8][$size - 15 + $i] = $dark;
			}
		}
	}

	protected function penalty($matrix, $size)
	{
		$score = 0;

		for ($y = 0; $y < $size; $y++)
		{
			$score += $this->run_penalty($matrix[$y]);
		}

		for ($x = 0; $x < $size; $x++)
		{
			$column = array();

			for ($y = 0; $y < $size; $y++)
			{
				$column[] = $matrix[$y][$x];
			}

			$score += $this->run_penalty($column);
		}

		for ($y = 0; $y < $size - 1; $y++)
		{
			for ($x = 0; $x < $size - 1; $x++)
			{
				$cell = $matrix[$y][$x];

				if ($cell === $matrix[$y][$x + 1] && $cell === $matrix[$y + 1][$x] && $cell === $matrix[$y + 1][$x + 1])
				{
					$score += 3;
				}
			}
		}

		$dark = 0;

		for ($y = 0; $y < $size; $y++)
		{
			for ($x = 0; $x < $size; $x++)
			{
				$dark += $matrix[$y][$x];
			}
		}

		$score += (int) (abs($dark * 100 / ($size * $size) - 50) / 5) * 10;

		return $score;
	}

	protected function run_penalty($line)
	{
		$score = 0;
		$run = 1;
		$count = count($line);

		for ($i = 1; $i < $count; $i++)
		{
			if ($line[$i] === $line[$i - 1])
			{
				$run++;
				continue;
			}

			if ($run >= 5)
			{
				$score += 3 + ($run - 5);
			}

			$run = 1;
		}

		if ($run >= 5)
		{
			$score += 3 + ($run - 5);
		}

		$bits = implode('', $line);
		$finder = '00001011101';
		$reversed = '10111010000';
		$score += (substr_count($bits, $finder) + substr_count($bits, $reversed)) * 40;

		return $score;
	}

	protected function reserve(&$matrix, &$reserved, $y, $x, $value)
	{
		$matrix[$y][$x] = $value;
		$reserved[$y][$x] = TRUE;
	}

	protected function reed_solomon($data, $ec_length)
	{
		$generator = array(1);

		for ($i = 0; $i < $ec_length; $i++)
		{
			$generator = $this->poly_mul($generator, array(1, $this->exp[$i]));
		}

		$result = array_merge($data, array_fill(0, $ec_length, 0));
		$data_length = count($data);

		for ($i = 0; $i < $data_length; $i++)
		{
			$coef = $result[$i];

			if ($coef === 0)
			{
				continue;
			}

			$log = $this->log[$coef];

			foreach ($generator as $offset => $term)
			{
				$result[$i + $offset] ^= $this->exp[$this->log[$term] + $log];
			}
		}

		return array_slice($result, $data_length);
	}

	protected function poly_mul($left, $right)
	{
		$result = array_fill(0, count($left) + count($right) - 1, 0);

		foreach ($left as $i => $a)
		{
			if ($a === 0)
			{
				continue;
			}

			foreach ($right as $j => $b)
			{
				if ($b === 0)
				{
					continue;
				}

				$result[$i + $j] ^= $this->exp[$this->log[$a] + $this->log[$b]];
			}
		}

		return $result;
	}

	protected function init_field()
	{
		if ($this->exp)
		{
			return;
		}

		$this->log = array_fill(0, 256, 0);
		$value = 1;

		for ($i = 0; $i < 255; $i++)
		{
			$this->exp[$i] = $value;
			$this->log[$value] = $i;
			$value <<= 1;

			if ($value & 0x100)
			{
				$value ^= 0x11d;
			}
		}

		for ($i = 255; $i < 512; $i++)
		{
			$this->exp[$i] = $this->exp[$i - 255];
		}
	}

	protected function bits($value, $length)
	{
		return str_pad(decbin($value), $length, '0', STR_PAD_LEFT);
	}
}
