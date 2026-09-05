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
 * Retrieves the name of a CDEF item based on its ID.
 *
 * This function fetches the type and value of a CDEF item from the database and returns
 * the corresponding name or value based on the item's type.
 *
 * @param int $cdef_item_id The ID of the CDEF item.
 *
 * @return string|null The name/value, or null when the stored item is invalid.
 */
function get_cdef_item_name(int $cdef_item_id) : ?string {
	global $cdef_functions, $cdef_operators;

	$cdef_item          = db_fetch_row_prepared('SELECT type, value FROM cdef_items WHERE id = ?', [$cdef_item_id]);

	if (!is_array($cdef_item) || !array_key_exists('type', $cdef_item) || !array_key_exists('value', $cdef_item)) {
		cacti_log(sprintf('ERROR: CDEF item %d is missing or corrupt.', $cdef_item_id), false, 'CDEF');

		return null;
	}

	$current_cdef_value = $cdef_item['value'];

	switch ($cdef_item['type']) {
		case '1':
			if (!isset($cdef_functions[$current_cdef_value])) {
				cacti_log(sprintf('ERROR: CDEF item %d references an unknown function.', $cdef_item_id), false, 'CDEF');

				return null;
			}

			return (string) $cdef_functions[$current_cdef_value];
		case '2':
			if (!isset($cdef_operators[$current_cdef_value])) {
				cacti_log(sprintf('ERROR: CDEF item %d references an unknown operator.', $cdef_item_id), false, 'CDEF');

				return null;
			}

			return (string) $cdef_operators[$current_cdef_value];
		case '4':
			return (string) $current_cdef_value;
		case '5':
			$cdef_name = db_fetch_cell_prepared('SELECT name FROM cdef WHERE id = ?', [$current_cdef_value]);

			if ($cdef_name === false || $cdef_name === null || $cdef_name === '') {
				cacti_log(sprintf('ERROR: CDEF item %d references a missing definition.', $cdef_item_id), false, 'CDEF');

				return null;
			}

			return (string) $cdef_name;
		case '6':
			return (string) $current_cdef_value;
	}

	cacti_log(sprintf('ERROR: CDEF item %d has an unknown type.', $cdef_item_id), false, 'CDEF');

	return null;
}
/**
 * Resolves an entire CDEF into its text-based representation for use in the RRDtool 'graph'
 * string. this name will be resolved recursively if necessary
 *
 * This function fetches the CDEF items associated with the provided CDEF ID from the database,
 * constructs the CDEF string by iterating through the items, and handles nested CDEFs recursively.
 *
 * @param int $cdef_id The ID of the CDEF to retrieve.
 *
 * @return string|null The constructed CDEF string, or null on invalid data.
 */
function get_cdef(int $cdef_id) : ?string {
	$visited   = [];
	$expansion = 0;
	$cache     = [];

	return get_cdef_recursive($cdef_id, $visited, $expansion, $cache);
}

/**
 * Resolves nested CDEFs while rejecting cycles and unreasonable depth.
 *
 * @param int               $cdef_id   The ID of the CDEF to retrieve.
 * @param array<int,true>   $visited   IDs active in the current recursion path.
 * @param int               $expansion Number of definitions expanded by this call.
 * @param array<int,string> $cache     Successfully resolved definitions.
 *
 * @return string|null The CDEF string, or null when recursion is unsafe.
 */
function get_cdef_recursive(int $cdef_id, array &$visited, int &$expansion, array &$cache) : ?string {
	if (isset($visited[$cdef_id])) {
		cacti_log(sprintf('ERROR: CDEF %d contains a recursive cycle.', $cdef_id), false, 'CDEF');

		return null;
	}

	if (array_key_exists($cdef_id, $cache)) {
		return $cache[$cdef_id];
	}

	if (count($visited) >= 64) {
		cacti_log(sprintf('ERROR: CDEF %d exceeds the resolver nesting depth.', $cdef_id), false, 'CDEF');

		return null;
	}

	if (++$expansion > 4096) {
		cacti_log(sprintf('ERROR: CDEF %d exceeds the resolver expansion budget.', $cdef_id), false, 'CDEF');

		return null;
	}

	$visited[$cdef_id] = true;
	$cdef_items        = db_fetch_assoc_prepared('SELECT id, type, value FROM cdef_items WHERE cdef_id = ? ORDER BY sequence', [$cdef_id]);

	if (cacti_sizeof($cdef_items) === 0) {
		$cdef_exists = db_fetch_cell_prepared('SELECT id FROM cdef WHERE id = ?', [$cdef_id]);

		unset($visited[$cdef_id]);

		if ($cdef_exists === false || $cdef_exists === null || $cdef_exists === '') {
			cacti_log(sprintf('ERROR: CDEF %d does not exist.', $cdef_id), false, 'CDEF');

			return null;
		}

		$cache[$cdef_id] = '';

		return $cache[$cdef_id];
	}

	$parts  = [];
	$length = 0;

	foreach ($cdef_items as $cdef_item) {
		if ($cdef_item['type'] == 5) {
			$current_cdef_id = $cdef_item['value'];
			$nested          = get_cdef_recursive((int) $current_cdef_id, $visited, $expansion, $cache);

			if ($nested === null) {
				unset($visited[$cdef_id]);

				return null;
			}

			if ($nested !== '') {
				$length += strlen($nested) + ($parts === [] ? 0 : 1);

				if ($length > 1048576) {
					cacti_log(sprintf('ERROR: CDEF %d exceeds the resolver output budget.', $cdef_id), false, 'CDEF');
					unset($visited[$cdef_id]);

					return null;
				}

				$parts[] = $nested;
			}
		} else {
			$item_name = get_cdef_item_name($cdef_item['id']);

			if ($item_name === null) {
				unset($visited[$cdef_id]);

				return null;
			}

			if ($item_name !== '') {
				$length += strlen($item_name) + ($parts === [] ? 0 : 1);

				if ($length > 1048576) {
					cacti_log(sprintf('ERROR: CDEF %d exceeds the resolver output budget.', $cdef_id), false, 'CDEF');
					unset($visited[$cdef_id]);

					return null;
				}

				$parts[] = $item_name;
			}
		}
	}

	unset($visited[$cdef_id]);

	$cache[$cdef_id] = implode(',', $parts);

	return $cache[$cdef_id];
}
