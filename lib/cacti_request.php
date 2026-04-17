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

/**
 * Request-layer helpers for safe sorting, ID extraction, and LIKE escaping.
 *
 * These helpers eliminate repetitive inline validation that is easy to
 * get wrong. Each function returns a safe-by-construction value.
 */

/**
 * Extract and validate a sort column/direction pair from the request.
 *
 * Only column names present in $allowed are accepted. Direction is
 * forced to ASC or DESC regardless of user input.
 *
 * @param  string $col_param  Request variable name for the sort column
 * @param  string $dir_param  Request variable name for the sort direction
 * @param  string $default_col  Default column if input is missing/invalid
 * @param  string $default_dir  Default direction (ASC or DESC)
 * @param  array  $allowed      Whitelist of valid column names
 *
 * @return array Two-element array: [column, direction]
 */
function get_request_sort($col_param, $dir_param, $default_col, $default_dir, $allowed) {
	$col = get_nfilter_request_var($col_param);
	$dir = strtoupper(get_nfilter_request_var($dir_param));

	if (!in_array($col, $allowed, true)) {
		$col = $default_col;
	}

	if ($dir !== 'ASC' && $dir !== 'DESC') {
		$dir = $default_dir;
	}

	return array($col, $dir);
}

/**
 * Extract an array of integer IDs from a request variable.
 *
 * Filters out non-numeric values and casts all results to int.
 * Returns an empty array if the variable is missing or empty.
 *
 * @param  string $name The request variable name
 *
 * @return array Array of integer IDs
 */
function get_request_ids($name) {
	$raw = get_nfilter_request_var($name);

	if (!is_array($raw)) {
		if (is_numeric($raw)) {
			return array((int) $raw);
		}

		return array();
	}

	$ids = array();

	foreach ($raw as $val) {
		if (is_numeric($val)) {
			$ids[] = (int) $val;
		}
	}

	return $ids;
}

/**
 * Escape a user-supplied string for safe use in a SQL LIKE clause.
 *
 * Wraps the escaped value in percent signs for substring matching
 * and passes the result through db_qstr() for quoting.
 *
 * @param  string $value  The raw user input
 *
 * @return string A db_qstr()-quoted, LIKE-safe value (e.g. '%term%')
 */
function db_qstr_like($value) {
	$escaped = str_replace(
		array('%', '_', '\\'),
		array('\\%', '\\_', '\\\\'),
		$value
	);

	return db_qstr('%' . $escaped . '%');
}
