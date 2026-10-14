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

/** Validate optional RRDtool 1.11 axis settings, including imported values. */
function rrd_graph_axis_option_is_valid(string $field, mixed $value) : bool {
	if (!is_string($value)) {
		return false;
	}

	if ($value === '') {
		return true;
	}

	if ($field === 'vertical_label_angle' || $field === 'right_axis_label_angle') {
		return in_array($value, ['0', '90', '180', '270'], true);
	}

	if ($field !== 'right_axis_range' || strlen($value) > 64) {
		return false;
	}

	$number = '-?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+\-]?[0-9]+)?';

	if (!preg_match('/\A(' . $number . ')?:(' . $number . ')?\z/', $value)) {
		return false;
	}

	[$minimum, $maximum] = explode(':', $value);

	if ($minimum === '' && $maximum === '') {
		return false;
	}

	foreach ([$minimum, $maximum] as $bound) {
		if ($bound !== '' && !is_finite((float) $bound)) {
			return false;
		}
	}

	return $minimum === '' || $maximum === '' || (float) $minimum <= (float) $maximum;
}

/** Blank defaults preserve RRDtool's layout; older releases omit these flags. */
function rrd_graph_axis_options(array $graph, string $version) : string {
	if (version_compare($version, '1.11.0', '<')) {
		return '';
	}

	$options = '';

	foreach (['vertical_label_angle', 'right_axis_label_angle', 'right_axis_range'] as $field) {
		$value = $graph[$field] ?? '';

		if ($value !== '' && rrd_graph_axis_option_is_valid($field, $value)) {
			$options .= '--' . str_replace('_', '-', $field) . ' ' . cacti_escapeshellarg($value) . RRD_NL;
		}
	}

	return $options;
}
