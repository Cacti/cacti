#!/usr/bin/env php
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

error_reporting(0);

// @codeCoverageIgnoreStart
if (!isset($called_by_script_server)) {
	include_once(__DIR__ . '/../include/cli_check.php');
	include_once(__DIR__ . '/../lib/poller_script.php');

	array_shift($_SERVER['argv']);

	[$output, $status, $error] = call_user_func_array('ss_motorola_surfboard_SB6141_collect', $_SERVER['argv']);

	if ($error !== '') {
		fwrite(STDERR, $error . PHP_EOL);
	}

	// motorola_surfboard_SB6141.py ends its output with a newline
	print $output . PHP_EOL;

	exit($status);
} else {
	include_once(__DIR__ . '/../lib/poller_script.php');
}
/** @codeCoverageIgnoreEnd */

/**
 * Returns signal readings from a Motorola/ARRIS SurfBoard SB6141 cable modem,
 * as motorola_surfboard_SB6141.py does.
 *
 * @param string $category Downstream, Upstream or Stats.
 * @param string $host     The modem's hostname or address.
 *
 * @return string Space separated name:value pairs, or U.
 */
function ss_motorola_surfboard_SB6141(string $category = '', string $host = '') : string {
	return ss_motorola_surfboard_SB6141_collect($category, $host)[0];
}

/**
 * Validates the arguments, fetches the modem's signal page and returns the
 * readings with the exit status and error message the command line entry
 * point uses.
 *
 * @param string $category The reading category.
 * @param string $host     The modem.
 *
 * @return array{0: string, 1: int, 2: string}
 */
function ss_motorola_surfboard_SB6141_collect(string $category = '', string $host = '') : array {
	$usage = 'usage: ss_motorola_surfboard_SB6141.php [Downstream|Upstream|Stats] <hostname>';

	if (!in_array($category, ['Downstream', 'Upstream', 'Stats'], true)) {
		return ['U', 1, $usage];
	}

	if (!preg_match('/^(?:[A-Za-z0-9][\w.-]*|\[[0-9A-Fa-f:.]+\])(?::\d{1,5})?$/', $host)) {
		return ['U', 1, 'category and hostname or IP of the modem are required!' . PHP_EOL . $usage];
	}

	$page = poller_script_http_get('http://' . $host . '/cmSignalData.htm');

	if ($page === null) {
		return ['U', 1, 'Unable to retrieve data from modem!'];
	}

	return [ss_motorola_surfboard_SB6141_format(ss_motorola_surfboard_SB6141_parse($page), $category), 0, ''];
}

/**
 * Returns the text that leads a node, before its first child element. This
 * is what lxml calls the element's text, which the Python script read.
 *
 * @param DOMNode $node The node.
 *
 * @return string
 */
function ss_motorola_surfboard_SB6141_text(DOMNode $node) : string {
	$text = '';

	foreach ($node->childNodes as $child) {
		if ($child->nodeType !== XML_TEXT_NODE && $child->nodeType !== XML_CDATA_SECTION_NODE) {
			break;
		}

		$text .= $child->nodeValue;
	}

	return $text;
}

/**
 * Strips leading and trailing whitespace as Python's str.strip() does, which
 * includes the non-breaking spaces the modem pads its cells with.
 *
 * @param string $text UTF-8 text.
 *
 * @return string
 */
function ss_motorola_surfboard_SB6141_strip(string $text) : string {
	$space    = '[\s\x{1c}-\x{1f}\x{85}\x{a0}\x{1680}\x{2000}-\x{200a}\x{2028}\x{2029}\x{202f}\x{205f}\x{3000}]+';
	$stripped = preg_replace('/^' . $space . '|' . $space . '$/u', '', $text);

	return ($stripped === null ? trim($text) : $stripped);
}

/**
 * Returns the element children of a node.
 *
 * @param DOMNode $node The node.
 *
 * @return array<int,DOMElement>
 */
function ss_motorola_surfboard_SB6141_children(DOMNode $node) : array {
	$children = [];

	foreach ($node->childNodes as $child) {
		if ($child instanceof DOMElement) {
			$children[] = $child;
		}
	}

	return $children;
}

/**
 * Reads the modem's cmSignalData.htm tables.
 *
 * Each table body starts with a header row naming the category in a font
 * element, then a 'Channel ID' row that lists the channels, then one row per
 * reading with the label in the first cell. The readings are returned by
 * category and label, keyed by channel, with each category's channel ids
 * under 'channels'.
 *
 * @param string $page The page HTML.
 *
 * @return array<string,array<string,array<int|string,string>>>
 */
function ss_motorola_surfboard_SB6141_parse(string $page) : array {
	$data = [];

	// loadHTML() throws on an empty document
	if (trim($page) === '') {
		return $data;
	}

	$dom = new DOMDocument();

	$previous = libxml_use_internal_errors(true);
	$loaded   = $dom->loadHTML($page, LIBXML_NONET);

	libxml_clear_errors();
	libxml_use_internal_errors($previous);

	if (!$loaded) {
		return $data;
	}

	$xpath  = new DOMXPath($dom);
	$tables = $xpath->query('//table/tbody');

	if ($tables === false) {
		return $data;
	}

	foreach ($tables as $table) {
		if (!$table instanceof DOMElement) {
			continue;
		}

		$category       = null;
		$channels       = [];
		$channels_ready = false;
		$data_row       = false;

		foreach (ss_motorola_surfboard_SB6141_children($table) as $row) {
			$have_label    = false;
			$label         = null;
			$channel_count = 0;

			foreach (ss_motorola_surfboard_SB6141_children($row) as $cell) {
				if (!$data_row) {
					$font = null;

					foreach (ss_motorola_surfboard_SB6141_children($cell) as $child) {
						if ($child->nodeName === 'font') {
							$font = $child;

							break;
						}
					}

					$font_text = ($font === null ? '' : ss_motorola_surfboard_SB6141_strip(ss_motorola_surfboard_SB6141_text($font)));

					if (!$have_label && in_array($font_text, ['Downstream', 'Upstream', 'Signal Stats (Codewords)'], true)) {
						$category = ($font_text === 'Signal Stats (Codewords)' ? 'Stats' : $font_text);

						$data[$category] = [];
					} elseif (!$have_label && $category !== null && !$channels_ready && ss_motorola_surfboard_SB6141_strip(ss_motorola_surfboard_SB6141_text($cell)) === 'Channel ID') {
						$channels_ready = true;
						$channels       = [];
					} elseif ($have_label && $channels_ready) {
						$channels[] = ss_motorola_surfboard_SB6141_strip(ss_motorola_surfboard_SB6141_text($cell));
					}
				} elseif ($have_label) {
					if ($label !== null && $channel_count < count($channels)) {
						$data[$category][$label][$channels[$channel_count]] = ss_motorola_surfboard_SB6141_strip(ss_motorola_surfboard_SB6141_text($cell));
						$channel_count++;
					}
				} else {
					$label = ss_motorola_surfboard_SB6141_strip(ss_motorola_surfboard_SB6141_text($cell));

					$data[$category][$label] = [];
				}

				// the first column holds the label, the rest hold data
				$have_label = true;
			}

			// once the channels are known, the remaining rows are data rows
			if ($channels_ready && $category !== null) {
				$data_row = true;

				$data[$category]['channels'] = $channels;
			}
		}
	}

	return $data;
}

/**
 * Returns (channel, value) pairs for every channel the modem reported, in
 * numeric channel order.
 *
 * Channel ids come from the modem's own table rather than a fixed list, so a
 * modem bonded to different channels does not lose the whole poll. A channel
 * with no reading for this label is reported as U.
 *
 * @param array<string,array<string,array<int|string,string>>> $data     Parsed readings.
 * @param string                                               $category The category.
 * @param string                                               $label    The reading label.
 * @param string                                               $suffix   A unit suffix to remove.
 *
 * @return array<int,array{0: string, 1: string}>
 */
function ss_motorola_surfboard_SB6141_channel_values(array $data, string $category, string $label, string $suffix = '') : array {
	$section  = $data[$category] ?? [];
	$readings = $section[$label] ?? [];
	$channels = array_map('strval', $section['channels'] ?? []);

	usort($channels, static function (string $a, string $b) : int {
		$a_digit = ctype_digit($a);
		$b_digit = ctype_digit($b);

		if ($a_digit && $b_digit) {
			return (int) $a <=> (int) $b;
		}

		if ($a_digit !== $b_digit) {
			return $a_digit ? -1 : 1;
		}

		return strcmp($a, $b);
	});

	$values = [];

	foreach ($channels as $channel) {
		$value = (string) ($readings[$channel] ?? '');

		if ($suffix !== '') {
			$value = str_replace($suffix, '', $value);
		}

		$values[] = [$channel, ($value !== '' ? $value : 'U')];
	}

	return $values;
}

/**
 * Formats the readings of one category as the data input method expects.
 *
 * @param array<string,array<string,array<int|string,string>>> $data     Parsed readings.
 * @param string                                               $category Downstream, Upstream or Stats.
 *
 * @return string
 */
function ss_motorola_surfboard_SB6141_format(array $data, string $category) : string {
	$results = [];

	if ($category === 'Downstream') {
		foreach (ss_motorola_surfboard_SB6141_channel_values($data, 'Downstream', 'Power Level', ' dBmV') as [$channel, $value]) {
			$results[] = 'DownstreamPower' . $channel . ':' . $value;
		}

		foreach (ss_motorola_surfboard_SB6141_channel_values($data, 'Downstream', 'Signal to Noise Ratio', ' dB') as [$channel, $value]) {
			$results[] = 'DownstreamNoiseRatio' . $channel . ':' . $value;
		}
	} elseif ($category === 'Upstream') {
		foreach (ss_motorola_surfboard_SB6141_channel_values($data, 'Upstream', 'Power Level', ' dBmV') as [$channel, $value]) {
			$results[] = 'UpstreamPower' . $channel . ':' . $value;
		}
	} elseif ($category === 'Stats') {
		$unerrored     = array_column(ss_motorola_surfboard_SB6141_channel_values($data, 'Stats', 'Total Unerrored Codewords'), 1, 0);
		$correctable   = array_column(ss_motorola_surfboard_SB6141_channel_values($data, 'Stats', 'Total Correctable Codewords'), 1, 0);
		$uncorrectable = array_column(ss_motorola_surfboard_SB6141_channel_values($data, 'Stats', 'Total Uncorrectable Codewords'), 1, 0);

		foreach ($unerrored as $channel => $value) {
			$results[] = 'StatsUnerrored' . $channel . ':' . $value;
			$results[] = 'StatsCorrectable' . $channel . ':' . $correctable[$channel];
			$results[] = 'StatsUncorrectable' . $channel . ':' . $uncorrectable[$channel];
		}
	}

	return implode(' ', $results);
}
