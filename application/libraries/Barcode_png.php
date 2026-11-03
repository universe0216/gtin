<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Draw a 1D barcode as a PNG without the GD extension.
 */
class Barcode_png {

	public function png($type, $value)
	{
		$bits = $this->modules($type, $value);

		if ($bits === '')
		{
			return NULL;
		}

		return $this->render($bits, trim((string) $value));
	}

	public function matrix_png($modules, $scale = 6, $quiet = 4)
	{
		$modules = array_values($modules);
		$count = count($modules);

		if ($count < 1)
		{
			return NULL;
		}

		$scale = max(1, (int) $scale);
		$quiet = max(0, (int) $quiet);
		$size = $count + $quiet * 2;
		$pixels = $size * $scale;
		$white = array(255, 255, 255);
		$black = array(0, 0, 0);
		$rows = array();

		for ($y = 0; $y < $pixels; $y++)
		{
			$row = array();
			$module_y = (int) floor($y / $scale) - $quiet;

			for ($x = 0; $x < $pixels; $x++)
			{
				$module_x = (int) floor($x / $scale) - $quiet;
				$dark = $module_y >= 0 && $module_y < $count && $module_x >= 0 && $module_x < $count && ! empty($modules[$module_y][$module_x]);
				$row[] = $dark ? $black : $white;
			}

			$rows[] = $row;
		}

		return $this->encode_png($pixels, $pixels, $rows);
	}

	public function modules($type, $value)
	{
		$key = strtolower(preg_replace('/[\s\-]+/', '', trim((string) $type)));
		$value = trim((string) $value);

		switch ($key)
		{
			case 'ean13':
				return $this->ean13($value);
			case 'ean8':
				return $this->ean8($value);
			case 'upca':
				return $this->upca($value);
			case 'upce':
				return $this->upce($value);
			case 'itf14':
				return $this->itf($value);
			case 'code39':
				return $this->code39($value);
			case 'gs1128':
				return $this->code128($this->gs1_payload($value), TRUE);
			case 'code128':
				return $this->code128($value, FALSE);
			default:
				return $this->code128($value, FALSE);
		}
	}

	protected function ean13($value)
	{
		$digits = $this->digits($value, 13);

		if ($digits === NULL)
		{
			return '';
		}

		$parity = array(
			'0' => 'LLLLLL',
			'1' => 'LLGLGG',
			'2' => 'LLGGLG',
			'3' => 'LLGGGL',
			'4' => 'LGLLGG',
			'5' => 'LGGLLG',
			'6' => 'LGGGLL',
			'7' => 'LGLGLG',
			'8' => 'LGLGGL',
			'9' => 'LGGLGL',
		);
		$pattern = $parity[$digits[0]];
		$bits = '101';

		for ($i = 0; $i < 6; $i++)
		{
			$bits .= $this->ean_left($digits[$i + 1], $pattern[$i] === 'G');
		}

		$bits .= '01010';

		for ($i = 7; $i < 13; $i++)
		{
			$bits .= $this->ean_right($digits[$i]);
		}

		return $bits.'101';
	}

	protected function ean8($value)
	{
		$digits = $this->digits($value, 8);

		if ($digits === NULL)
		{
			return '';
		}

		$bits = '101';

		for ($i = 0; $i < 4; $i++)
		{
			$bits .= $this->ean_left($digits[$i], FALSE);
		}

		$bits .= '01010';

		for ($i = 4; $i < 8; $i++)
		{
			$bits .= $this->ean_right($digits[$i]);
		}

		return $bits.'101';
	}

	protected function upca($value)
	{
		$digits = $this->digits($value, 12);

		if ($digits === NULL)
		{
			return '';
		}

		return $this->ean13('0'.$digits);
	}

	protected function upce($value)
	{
		if ( ! preg_match('/^[01]\d{7}$/', $value))
		{
			return '';
		}

		$parity = array(
			'0' => array('EEEOOO', 'OOOEEE'),
			'1' => array('EEOEOO', 'OOEOEE'),
			'2' => array('EEOOEO', 'OOEEOE'),
			'3' => array('EEOOOE', 'OOEEEO'),
			'4' => array('EOEEOO', 'OEOOEE'),
			'5' => array('EOOEEO', 'OEEOOE'),
			'6' => array('EOOOEE', 'OEEEOO'),
			'7' => array('EOEOEO', 'OEOEOE'),
			'8' => array('EOEOOE', 'OEOEEO'),
			'9' => array('EOOEOE', 'OEEOEO'),
		);
		$number_system = $value[0] === '1' ? 1 : 0;
		$pattern = $parity[$value[7]][$number_system];
		$bits = '101';

		for ($i = 0; $i < 6; $i++)
		{
			$bits .= $this->ean_left($value[$i + 1], $pattern[$i] === 'E');
		}

		return $bits.'010101';
	}

	protected function itf($value)
	{
		$digits = $this->digits($value, 14);

		if ($digits === NULL || strlen($digits) % 2 !== 0)
		{
			return '';
		}

		$codes = array(
			'0' => 'nnwwn',
			'1' => 'wnnnw',
			'2' => 'nwnnw',
			'3' => 'wwnnn',
			'4' => 'nnwnw',
			'5' => 'wnwnn',
			'6' => 'nwwnn',
			'7' => 'nnnww',
			'8' => 'wnnwn',
			'9' => 'nwnwn',
		);
		$bits = '1010';

		for ($i = 0; $i < strlen($digits); $i += 2)
		{
			$bars = $codes[$digits[$i]];
			$spaces = $codes[$digits[$i + 1]];

			for ($j = 0; $j < 5; $j++)
			{
				$bits .= str_repeat('1', $bars[$j] === 'w' ? 3 : 1);
				$bits .= str_repeat('0', $spaces[$j] === 'w' ? 3 : 1);
			}
		}

		return $bits.'11101';
	}

	protected function code39($value)
	{
		$value = strtoupper($value);
		$patterns = array(
			'0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
			'4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
			'8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw',
			'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn',
			'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
			'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww',
			'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn',
			'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn', 'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw',
			'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
			'-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '$' => 'nwnwnwnnn',
			'/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn', '%' => 'nnnwnwnwn', '*' => 'nwnnwnwnn',
		);
		$symbols = '*'.$value.'*';
		$bits = '';

		for ($i = 0; $i < strlen($symbols); $i++)
		{
			$char = $symbols[$i];

			if ( ! isset($patterns[$char]))
			{
				return '';
			}

			$pattern = $patterns[$char];

			for ($j = 0; $j < 9; $j++)
			{
				$width = $pattern[$j] === 'w' ? 3 : 1;
				$bits .= str_repeat(($j % 2 === 0) ? '1' : '0', $width);
			}

			$bits .= '0';
		}

		return $bits;
	}

	protected function code128($value, $gs1)
	{
		if ($value === '')
		{
			return '';
		}

		$patterns = $this->code128_patterns();
		$codes = array(104);

		if ($gs1)
		{
			$codes[] = 102;
		}

		$length = strlen($value);

		for ($i = 0; $i < $length; $i++)
		{
			$ord = ord($value[$i]);

			if ($ord < 32 || $ord > 126)
			{
				return '';
			}

			$codes[] = $ord - 32;
		}

		$sum = $codes[0];

		for ($i = 1; $i < count($codes); $i++)
		{
			$sum += $codes[$i] * $i;
		}

		$codes[] = $sum % 103;
		$codes[] = 106;
		$bits = '';

		foreach ($codes as $code)
		{
			$pattern = $patterns[$code];
			$width_count = strlen($pattern);

			for ($i = 0; $i < $width_count; $i++)
			{
				$bits .= str_repeat(($i % 2 === 0) ? '1' : '0', (int) $pattern[$i]);
			}
		}

		return $bits;
	}

	protected function gs1_payload($value)
	{
		return preg_replace('/[()]|\s+/', '', $value);
	}

	protected function digits($value, $length)
	{
		if (preg_match('/^\d{'.($length - 1).'}$/', $value))
		{
			$value .= $this->check_digit($value);
		}

		if ( ! preg_match('/^\d{'.$length.'}$/', $value))
		{
			return NULL;
		}

		return $value;
	}

	protected function check_digit($digits)
	{
		$sum = 0;
		$length = strlen($digits);

		for ($i = 0; $i < $length; $i++)
		{
			$sum += (int) $digits[$length - 1 - $i] * ($i % 2 === 0 ? 3 : 1);
		}

		return (string) ((10 - ($sum % 10)) % 10);
	}

	protected function ean_left($digit, $even)
	{
		$odd = array(
			'0' => '0001101', '1' => '0011001', '2' => '0010011', '3' => '0111101', '4' => '0100011',
			'5' => '0110001', '6' => '0101111', '7' => '0111011', '8' => '0110111', '9' => '0001011',
		);
		$even_codes = array(
			'0' => '0100111', '1' => '0110011', '2' => '0011011', '3' => '0100001', '4' => '0011101',
			'5' => '0111001', '6' => '0000101', '7' => '0010001', '8' => '0001001', '9' => '0010111',
		);

		return $even ? $even_codes[$digit] : $odd[$digit];
	}

	protected function ean_right($digit)
	{
		$codes = array(
			'0' => '1110010', '1' => '1100110', '2' => '1101100', '3' => '1000010', '4' => '1011100',
			'5' => '1001110', '6' => '1010000', '7' => '1000100', '8' => '1001000', '9' => '1110100',
		);

		return $codes[$digit];
	}

	protected function code128_patterns()
	{
		return array(
			'212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
			'221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
			'221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
			'212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
			'231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
			'231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
			'314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
			'112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
			'111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
			'214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
			'114131', '311141', '411131', '211412', '211214', '211232', '2331112',
		);
	}

	protected function render($bits, $caption)
	{
		$scale = 2;
		$bar_height = 90;
		$margin_x = 10 * $scale;
		$margin_y = 12;
		$font_scale = 2;
		$glyph_width = 5 * $font_scale + $font_scale;
		$text_height = 7 * $font_scale;
		$gap = 8;
		$module_count = strlen($bits);
		$width = $margin_x * 2 + $module_count * $scale;
		$caption_width = max(1, strlen($caption)) * $glyph_width;

		if ($caption_width + $margin_x * 2 > $width)
		{
			$extra = $caption_width + $margin_x * 2 - $width;
			$margin_x += (int) ceil($extra / 2);
			$width = $margin_x * 2 + $module_count * $scale;
		}

		$height = $margin_y + $bar_height + $gap + $text_height + $margin_y;
		$white = array(255, 255, 255);
		$black = array(0, 0, 0);
		$rows = array();

		for ($y = 0; $y < $height; $y++)
		{
			$rows[$y] = array_fill(0, $width, $white);
		}

		$bar_top = $margin_y;

		for ($i = 0; $i < $module_count; $i++)
		{
			if ($bits[$i] !== '1')
			{
				continue;
			}

			$x0 = $margin_x + $i * $scale;

			for ($x = $x0; $x < $x0 + $scale; $x++)
			{
				for ($y = $bar_top; $y < $bar_top + $bar_height; $y++)
				{
					$rows[$y][$x] = $black;
				}
			}
		}

		$text_width = strlen($caption) * $glyph_width;
		$text_x = (int) max(0, ($width - $text_width) / 2);
		$text_y = $bar_top + $bar_height + $gap;

		for ($i = 0; $i < strlen($caption); $i++)
		{
			$this->draw_glyph($rows, $caption[$i], $text_x + $i * $glyph_width, $text_y, $font_scale, $black);
		}

		return $this->encode_png($width, $height, $rows);
	}

	protected function draw_glyph(&$rows, $char, $x, $y, $scale, $color)
	{
		$glyph = $this->glyph($char);

		for ($row = 0; $row < 7; $row++)
		{
			for ($col = 0; $col < 5; $col++)
			{
				if ((($glyph[$row] >> (4 - $col)) & 1) !== 1)
				{
					continue;
				}

				for ($sy = 0; $sy < $scale; $sy++)
				{
					for ($sx = 0; $sx < $scale; $sx++)
					{
						$px = $x + $col * $scale + $sx;
						$py = $y + $row * $scale + $sy;

						if (isset($rows[$py][$px]))
						{
							$rows[$py][$px] = $color;
						}
					}
				}
			}
		}
	}

	protected function glyph($char)
	{
		$font = array(
			' ' => array(0, 0, 0, 0, 0, 0, 0),
			'-' => array(0, 0, 0, 31, 0, 0, 0),
			'.' => array(0, 0, 0, 0, 0, 12, 12),
			'$' => array(4, 30, 5, 14, 20, 15, 4),
			'/' => array(1, 2, 4, 8, 16, 0, 0),
			'+' => array(0, 4, 4, 31, 4, 4, 0),
			'%' => array(25, 25, 2, 4, 8, 19, 19),
			'0' => array(14, 17, 19, 21, 25, 17, 14),
			'1' => array(4, 12, 4, 4, 4, 4, 14),
			'2' => array(14, 17, 1, 2, 4, 8, 31),
			'3' => array(14, 17, 1, 6, 1, 17, 14),
			'4' => array(2, 6, 10, 18, 31, 2, 2),
			'5' => array(31, 16, 30, 1, 1, 17, 14),
			'6' => array(6, 8, 16, 30, 17, 17, 14),
			'7' => array(31, 1, 2, 4, 8, 8, 8),
			'8' => array(14, 17, 17, 14, 17, 17, 14),
			'9' => array(14, 17, 17, 15, 1, 2, 12),
			'A' => array(14, 17, 17, 31, 17, 17, 17),
			'B' => array(30, 17, 17, 30, 17, 17, 30),
			'C' => array(14, 17, 16, 16, 16, 17, 14),
			'D' => array(30, 17, 17, 17, 17, 17, 30),
			'E' => array(31, 16, 16, 30, 16, 16, 31),
			'F' => array(31, 16, 16, 30, 16, 16, 16),
			'G' => array(14, 17, 16, 23, 17, 17, 14),
			'H' => array(17, 17, 17, 31, 17, 17, 17),
			'I' => array(14, 4, 4, 4, 4, 4, 14),
			'J' => array(7, 2, 2, 2, 2, 18, 12),
			'K' => array(17, 18, 20, 24, 20, 18, 17),
			'L' => array(16, 16, 16, 16, 16, 16, 31),
			'M' => array(17, 27, 21, 21, 17, 17, 17),
			'N' => array(17, 25, 21, 19, 17, 17, 17),
			'O' => array(14, 17, 17, 17, 17, 17, 14),
			'P' => array(30, 17, 17, 30, 16, 16, 16),
			'Q' => array(14, 17, 17, 17, 21, 18, 13),
			'R' => array(30, 17, 17, 30, 20, 18, 17),
			'S' => array(14, 17, 16, 14, 1, 17, 14),
			'T' => array(31, 4, 4, 4, 4, 4, 4),
			'U' => array(17, 17, 17, 17, 17, 17, 14),
			'V' => array(17, 17, 17, 17, 17, 10, 4),
			'W' => array(17, 17, 17, 21, 21, 27, 17),
			'X' => array(17, 17, 10, 4, 10, 17, 17),
			'Y' => array(17, 17, 10, 4, 4, 4, 4),
			'Z' => array(31, 1, 2, 4, 8, 16, 31),
		);
		$char = strtoupper($char);

		return isset($font[$char]) ? $font[$char] : $font[' '];
	}

	protected function encode_png($width, $height, $rows)
	{
		$raw = '';

		for ($y = 0; $y < $height; $y++)
		{
			$raw .= "\x00";

			for ($x = 0; $x < $width; $x++)
			{
				$raw .= chr($rows[$y][$x][0]).chr($rows[$y][$x][1]).chr($rows[$y][$x][2]);
			}
		}

		$compressed = gzcompress($raw, 9);
		$ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

		return "\x89PNG\r\n\x1a\n"
			.$this->png_chunk('IHDR', $ihdr)
			.$this->png_chunk('IDAT', $compressed)
			.$this->png_chunk('IEND', '');
	}

	protected function png_chunk($type, $data)
	{
		return pack('N', strlen($data)).$type.$data.hash('crc32b', $type.$data, TRUE);
	}
}
