<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * YAML support that needs only ext-json, which Cacti already requires.
 *
 * cacti_yaml_encode() writes block-style YAML 1.2. Every string is written
 * as a JSON string literal, which is also a valid YAML double-quoted scalar,
 * so no value can be re-typed by YAML 1.1 rules (yes, no, on, ~, 0123, 1e3).
 *
 * cacti_yaml_decode() reads only the JSON form of YAML. JSON is a subset of
 * YAML 1.2, so these files are YAML, but block-style input, including the
 * output of cacti_yaml_encode(), is rejected rather than parsed.
 */

const CACTI_YAML_MAX_DEPTH = 64;
const CACTI_YAML_MAX_BYTES = 16 * 1024 * 1024;

/* Characters json_encode() leaves as-is under JSON_UNESCAPED_UNICODE that
   YAML does not allow unescaped (DEL, the C1 controls and the
   noncharacters U+FFFE and U+FFFF) or that YAML 1.1 parsers read as a line
   break (NEL, U+0085). On valid UTF-8 these byte sequences can only start
   at a character boundary. */
const CACTI_YAML_ESCAPES = [
	"\x7F"         => '\u007F',
	"\xC2\x80"     => '\u0080',
	"\xC2\x81"     => '\u0081',
	"\xC2\x82"     => '\u0082',
	"\xC2\x83"     => '\u0083',
	"\xC2\x84"     => '\u0084',
	"\xC2\x85"     => '\u0085',
	"\xC2\x86"     => '\u0086',
	"\xC2\x87"     => '\u0087',
	"\xC2\x88"     => '\u0088',
	"\xC2\x89"     => '\u0089',
	"\xC2\x8A"     => '\u008A',
	"\xC2\x8B"     => '\u008B',
	"\xC2\x8C"     => '\u008C',
	"\xC2\x8D"     => '\u008D',
	"\xC2\x8E"     => '\u008E',
	"\xC2\x8F"     => '\u008F',
	"\xC2\x90"     => '\u0090',
	"\xC2\x91"     => '\u0091',
	"\xC2\x92"     => '\u0092',
	"\xC2\x93"     => '\u0093',
	"\xC2\x94"     => '\u0094',
	"\xC2\x95"     => '\u0095',
	"\xC2\x96"     => '\u0096',
	"\xC2\x97"     => '\u0097',
	"\xC2\x98"     => '\u0098',
	"\xC2\x99"     => '\u0099',
	"\xC2\x9A"     => '\u009A',
	"\xC2\x9B"     => '\u009B',
	"\xC2\x9C"     => '\u009C',
	"\xC2\x9D"     => '\u009D',
	"\xC2\x9E"     => '\u009E',
	"\xC2\x9F"     => '\u009F',
	"\xEF\xBF\xBE" => '\uFFFE',
	"\xEF\xBF\xBF" => '\uFFFF',
];

/**
 * cacti_yaml_encode - Write an array as block-style YAML
 *
 * Lists become '- item' sequences and other arrays become 'key: value'
 * mappings, indented two spaces per level. Values may be arrays, strings,
 * integers, finite floats, booleans and null.
 *
 * @param array<mixed> $data      The data to write
 * @param int          $max_depth The maximum number of nested array levels
 *
 * @throws InvalidArgumentException When $max_depth is below 1, the data is
 *                                  nested too deeply, or the data holds
 *                                  an unsupported value or a string that is
 *                                  not valid UTF-8
 *
 * @return string The YAML document, ending with a newline
 */
function cacti_yaml_encode(array $data, int $max_depth = CACTI_YAML_MAX_DEPTH) : string {
	if ($max_depth < 1) {
		throw new InvalidArgumentException('YAML nesting limit must be at least 1');
	}

	if ($data === []) {
		return "[]\n";
	}

	return cacti_yaml_encode_block($data, 0, 1, $max_depth);
}

/**
 * cacti_yaml_encode_block - Write one non-empty array at the given indent
 *
 * @param array<mixed> $data      The array to write
 * @param int          $indent    The number of spaces before each line
 * @param int          $depth     The nesting level of $data, starting at 1
 * @param int          $max_depth The maximum nesting level
 *
 * @return string The YAML lines for $data
 */
function cacti_yaml_encode_block(array $data, int $indent, int $depth, int $max_depth) : string {
	if ($depth > $max_depth) {
		throw new InvalidArgumentException("YAML data is nested more than $max_depth levels deep");
	}

	$pad     = str_repeat(' ', $indent);
	$is_list = array_is_list($data);
	$output  = '';

	foreach ($data as $key => $value) {
		$prefix = $is_list ? $pad . '-' : $pad . cacti_yaml_encode_key($key) . ':';

		if (is_array($value) && $value !== []) {
			$child = cacti_yaml_encode_block($value, $indent + 2, $depth + 1, $max_depth);

			if ($is_list) {
				// Put the first line of the item on the dash line.
				$output .= $prefix . ' ' . substr($child, $indent + 2);
			} else {
				$output .= $prefix . "\n" . $child;
			}
		} else {
			$output .= $prefix . ' ' . cacti_yaml_encode_scalar($value) . "\n";
		}
	}

	return $output;
}

/**
 * cacti_yaml_encode_key - Write a mapping key
 *
 * Identifier-like keys are written bare for readability unless YAML 1.1
 * would read them as a boolean or null. Every other key is quoted.
 *
 * @param int|string $key The array key
 *
 * @return string The YAML key
 */
function cacti_yaml_encode_key(int|string $key) : string {
	$key = (string) $key;

	if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) &&
		!preg_match('/^(?:y|n|yes|no|on|off|true|false|null)$/i', $key)) {
		return $key;
	}

	return cacti_yaml_encode_string($key);
}

/**
 * cacti_yaml_encode_scalar - Write a scalar value or an empty array
 *
 * @param mixed $value The value to write
 *
 * @return string The YAML scalar
 */
function cacti_yaml_encode_scalar(mixed $value) : string {
	return match (true) {
		$value === null                        => 'null',
		is_bool($value)                        => $value ? 'true' : 'false',
		is_int($value)                         => (string) $value,
		is_float($value) && !is_finite($value) => throw new InvalidArgumentException('YAML data cannot hold INF or NAN'),
		is_float($value)                       => json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR),
		is_string($value)                      => cacti_yaml_encode_string($value),
		$value === []                          => '[]',
		default                                => throw new InvalidArgumentException('YAML data cannot hold a value of type ' . get_debug_type($value)),
	};
}

/**
 * cacti_yaml_encode_string - Write a string as a double-quoted scalar
 *
 * @param string $value The string, which must be valid UTF-8
 *
 * @return string The quoted, escaped string
 */
function cacti_yaml_encode_string(string $value) : string {
	try {
		$quoted = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
	} catch (JsonException $e) {
		throw new InvalidArgumentException('YAML data holds a string that is not valid UTF-8', 0, $e);
	}

	return strtr($quoted, CACTI_YAML_ESCAPES);
}

/**
 * cacti_yaml_decode - Read the JSON form of a YAML document
 *
 * Accepts a JSON object or array, with an optional UTF-8 byte order mark.
 * Integers too large for PHP are returned as strings. Duplicate keys are
 * not detected; the last value wins. Callers must still validate the
 * structure and values of the result before using them.
 *
 * @param string $text      The document
 * @param int    $max_bytes The largest document accepted
 * @param int    $max_depth The maximum number of nested array levels
 *
 * @throws InvalidArgumentException When $max_depth is below 1, or the
 *                                  document is too large, is not
 *                                  in the JSON form, is nested too deeply,
 *                                  or is not a mapping or sequence
 *
 * @return array<mixed> The decoded data
 */
function cacti_yaml_decode(string $text, int $max_bytes = CACTI_YAML_MAX_BYTES, int $max_depth = CACTI_YAML_MAX_DEPTH) : array {
	if ($max_depth < 1) {
		throw new InvalidArgumentException('YAML nesting limit must be at least 1');
	}

	if (strlen($text) > $max_bytes) {
		throw new InvalidArgumentException("YAML document is larger than $max_bytes bytes");
	}

	if (str_starts_with($text, "\xEF\xBB\xBF")) {
		$text = substr($text, 3);
	}

	/* json_decode() counts the scalar level too, so N nested arrays need a
	   depth of N + 1. */
	try {
		$data = json_decode($text, true, $max_depth + 1, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
	} catch (JsonException $e) {
		throw new InvalidArgumentException('YAML document is not in the supported JSON form: ' . $e->getMessage(), 0, $e);
	}

	if (!is_array($data)) {
		throw new InvalidArgumentException('YAML document must be a mapping or a sequence');
	}

	return $data;
}
