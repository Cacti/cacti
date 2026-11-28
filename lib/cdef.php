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
 * Resolves a single CDEF item into its text-based representation.
 *
 * @param int        $cdef_item_id The id of the individual cdef item.
 * @param array|null $cdef_item    Optional pre-fetched item row with type and value keys.
 *
 * @return string|null A text-based representation of the cdef item, or null on invalid/missing data.
 */
function get_cdef_item_name($cdef_item_id, $cdef_item = null) {
	global $cdef_functions, $cdef_operators;

	if (!is_array($cdef_item)) {
		$cdef_item = db_fetch_row_prepared('SELECT type, value FROM cdef_items WHERE id = ?', [$cdef_item_id]);
	}

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

			return $cdef_functions[$current_cdef_value];
		case '2':
			if (!isset($cdef_operators[$current_cdef_value])) {
				cacti_log(sprintf('ERROR: CDEF item %d references an unknown operator.', $cdef_item_id), false, 'CDEF');

				return null;
			}

			return $cdef_operators[$current_cdef_value];
		case '4':
		case '6':
			return $current_cdef_value;
		case '5':
			$cdef_name = db_fetch_cell_prepared('SELECT name FROM cdef WHERE id = ?', [$current_cdef_value]);

			if ($cdef_name === false || $cdef_name === null || $cdef_name === '') {
				cacti_log(sprintf('ERROR: CDEF item %d references a missing definition.', $cdef_item_id), false, 'CDEF');

				return null;
			}

			return $cdef_name;
	}

	cacti_log(sprintf('ERROR: CDEF item %d has an unknown type.', $cdef_item_id), false, 'CDEF');

	return null;
}

/**
 * Resolves an entire CDEF into its text-based representation for use in the RRDtool 'graph'
 * string. Nested CDEF references are resolved recursively with cycle and resource guards.
 *
 * @param int        $cdef_id     The id of the cdef to resolve.
 * @param array|null $definitions Optional snapshot keyed by existing CDEF ID with ordered item rows.
 *
 * @return string|null A text-based representation of the cdef, or null on invalid/cyclic definitions.
 */
function get_cdef($cdef_id, $definitions = null) {
	$visited     = [];
	$expansion   = 0;
	$cache       = [];
	$cache_bytes = 0;

	/* Existing callers such as cdef.php pass a legacy boolean second argument. */
	if (!is_array($definitions)) {
		$definitions = null;
	}

	return get_cdef_recursive($cdef_id, $visited, $expansion, $cache, $cache_bytes, $definitions);
}

/**
 * Verifies that an empty CDEF definition exists.
 *
 * @param mixed      $cdef_id     The CDEF ID.
 * @param array|null $definitions Optional snapshot keyed by existing CDEF ID.
 *
 * @return bool True if the CDEF exists, false otherwise.
 */
function cdef_verify_empty($cdef_id, ?array $definitions) : bool {
	if ($definitions !== null) {
		return true;
	}

	$cdef_exists = db_fetch_assoc_prepared('SELECT id FROM cdef WHERE id = ?', [$cdef_id]);

	if ($cdef_exists === false) {
		cacti_log(sprintf('ERROR: Unable to verify CDEF %d.', $cdef_id), false, 'CDEF');

		return false;
	}

	if (!cacti_sizeof($cdef_exists)) {
		cacti_log(sprintf('ERROR: CDEF %d does not exist.', $cdef_id), false, 'CDEF');

		return false;
	}

	return true;
}

/**
 * Resolves a single item row during recursive CDEF expansion.
 *
 * @param mixed      $cdef_id      Parent CDEF ID.
 * @param array      $cdef_item    Item row containing id, type, and value.
 * @param array      &$visited     Active recursion stack keyed by CDEF ID.
 * @param int        &$expansion   Cumulative expansion step counter.
 * @param array      &$cache       Per-call memoization cache keyed by CDEF ID.
 * @param int        &$cache_bytes Cumulative byte size stored in $cache.
 * @param array|null $definitions  Optional snapshot of ordered item rows.
 *
 * @return string|null Resolved fragment or null on error.
 */
function cdef_resolve_item($cdef_id, array $cdef_item, array &$visited, int &$expansion, array &$cache, int &$cache_bytes, ?array $definitions) : ?string {
	if ($cdef_item['type'] == 5) {
		$nested = get_cdef_recursive($cdef_item['value'], $visited, $expansion, $cache, $cache_bytes, $definitions);

		if ($nested === '') {
			cacti_log(sprintf('ERROR: CDEF %d references an empty definition.', $cdef_id), false, 'CDEF');

			return null;
		}

		return $nested;
	}

	/* Pass the already-fetched row to avoid an N+1 query per item. */
	$item_name = get_cdef_item_name($cdef_item['id'], $cdef_item);

	if ($item_name === null || $item_name === false) {
		return null;
	}

	if ($item_name === '') {
		cacti_log(sprintf('ERROR: CDEF %d contains an empty item.', $cdef_id), false, 'CDEF');

		return null;
	}

	return (string) $item_name;
}

/**
 * Resolves nested CDEFs while rejecting cycles, excessive depth, and runaway expansion.
 *
 * @param mixed      $cdef_id      The CDEF ID.
 * @param array      &$visited     Active recursion stack keyed by CDEF ID.
 * @param int        &$expansion   Cumulative expansion step counter.
 * @param array      &$cache       Per-call memoization cache keyed by CDEF ID.
 * @param int        &$cache_bytes Cumulative byte size stored in $cache.
 * @param array|null $definitions  Optional snapshot of ordered item rows.
 *
 * @return string|null Resolved CDEF string, or null on failure.
 */
function get_cdef_recursive($cdef_id, &$visited, &$expansion, &$cache, &$cache_bytes, $definitions = null) {
	if (isset($visited[$cdef_id])) {
		cacti_log(sprintf('ERROR: CDEF %d contains a recursive cycle.', $cdef_id), false, 'CDEF');

		return null;
	}

	if (array_key_exists($cdef_id, $cache)) {
		return $cache[$cdef_id];
	}

	if (cacti_sizeof($visited) >= 64) {
		cacti_log(sprintf('ERROR: CDEF %d exceeds the resolver nesting depth.', $cdef_id), false, 'CDEF');

		return null;
	}

	if (++$expansion > 4096) {
		cacti_log(sprintf('ERROR: CDEF %d exceeds the resolver expansion budget.', $cdef_id), false, 'CDEF');

		return null;
	}

	$visited[$cdef_id] = true;
	$cdef_items        = $definitions === null
		? db_fetch_assoc_prepared('SELECT id, type, value FROM cdef_items WHERE cdef_id = ? ORDER BY sequence', [$cdef_id])
		: ($definitions[$cdef_id] ?? false);

	if ($cdef_items === false) {
		unset($visited[$cdef_id]);
		cacti_log(sprintf('ERROR: Unable to load CDEF %d.', $cdef_id), false, 'CDEF');

		return null;
	}

	if (!cacti_sizeof($cdef_items)) {
		unset($visited[$cdef_id]);

		if (!cdef_verify_empty($cdef_id, $definitions)) {
			return null;
		}

		$cache[$cdef_id] = '';

		return '';
	}

	$parts  = [];
	$length = 0;

	foreach ($cdef_items as $cdef_item) {
		$part = cdef_resolve_item($cdef_id, $cdef_item, $visited, $expansion, $cache, $cache_bytes, $definitions);

		if ($part === null) {
			unset($visited[$cdef_id]);

			return null;
		}

		$length += strlen($part) + ($parts === [] ? 0 : 1);

		if ($length > 1048576) {
			cacti_log(sprintf('ERROR: CDEF %d exceeds the resolver output budget.', $cdef_id), false, 'CDEF');
			unset($visited[$cdef_id]);

			return null;
		}

		$parts[] = $part;
	}

	unset($visited[$cdef_id]);

	$resolved = implode(',', $parts);

	if ($cache_bytes + strlen($resolved) <= 8388608) {
		$cache_bytes    += strlen($resolved);
		$cache[$cdef_id] = $resolved;
	}

	return $resolved;
}

