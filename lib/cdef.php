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

require_once __DIR__ . '/Cdef/DatabaseCdefRepository.php';
require_once __DIR__ . '/Cdef/CdefResolver.php';

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
	return (new \Cacti\Cdef\DatabaseCdefRepository())->itemName($cdef_item_id);
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
	$resolver = new \Cacti\Cdef\CdefResolver(new \Cacti\Cdef\DatabaseCdefRepository(), static function (string $message) : void {
		cacti_log($message, false, 'CDEF');
	});

	return $resolver->resolve($cdef_id);
}

/**
 * Resolves nested CDEFs while rejecting cycles and unreasonable depth.
 *
 * @param int               $cdef_id     The ID of the CDEF to retrieve.
 * @param array<int,true>   $visited     IDs active in the current recursion path.
 * @param int               $expansion   Number of definitions expanded by this call.
 * @param array<int,string> $cache       Successfully resolved definitions.
 * @param int               $cache_bytes Total bytes retained by the resolver cache.
 *
 * @return string|null The CDEF string, or null when recursion is unsafe.
 */
function get_cdef_recursive(int $cdef_id, array &$visited, int &$expansion, array &$cache, int &$cache_bytes) : ?string {
	$state              = new \Cacti\Cdef\CdefResolutionState();
	$state->visited     = & $visited;
	$state->expansion   = & $expansion;
	$state->cache       = & $cache;
	$state->cache_bytes = & $cache_bytes;
	$resolver           = new \Cacti\Cdef\CdefResolver(new \Cacti\Cdef\DatabaseCdefRepository(), static function (string $message) : void {
		cacti_log($message, false, 'CDEF');
	});

	return $resolver->resolveWithState($cdef_id, $state);
}

/**
 * Determines whether deleting a CDEF would leave a live reference behind.
 *
 * References owned by another CDEF in the same delete request are ignored.
 * Database errors fail closed so an uncertain dependency cannot be deleted.
 *
 * @param int        $cdef_id      CDEF being considered for deletion.
 * @param array<int> $deleting_ids All CDEF IDs in the delete request.
 */
function cdef_is_in_use(int $cdef_id, array $deleting_ids = []) : bool {
	foreach (['graph_templates_item', 'aggregate_graph_templates_item', 'aggregate_graphs_graph_item'] as $table) {
		$references = db_fetch_assoc_prepared("SELECT cdef_id FROM $table WHERE cdef_id = ? LIMIT 1", [$cdef_id]);

		if ($references === false || cacti_sizeof($references) > 0) {
			return true;
		}
	}

	$parents = db_fetch_assoc_prepared('SELECT DISTINCT cdef_id FROM cdef_items WHERE type = 5 AND value = ?', [$cdef_id]);

	if ($parents === false) {
		return true;
	}

	$deleting = array_fill_keys(array_map('intval', $deleting_ids), true);

	foreach ($parents as $parent) {
		if (!isset($deleting[(int) $parent['cdef_id']])) {
			return true;
		}
	}

	return false;
}
