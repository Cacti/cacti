<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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
 * @return string The name or value of the CDEF item.
 */
function get_cdef_item_name($cdef_item_id) {
	global $cdef_functions, $cdef_operators;

	$cdef_item          = db_fetch_row_prepared('SELECT type, value FROM cdef_items WHERE id = ?', array($cdef_item_id));
	$current_cdef_value = $cdef_item['value'];

	switch ($cdef_item['type']) {
		case '1': return $cdef_functions[$current_cdef_value];

			break;
		case '2': return $cdef_operators[$current_cdef_value];

			break;
		case '4': return $current_cdef_value;

			break;
		case '5': return db_fetch_cell_prepared('SELECT name FROM cdef WHERE id = ?', array($current_cdef_value));

			break;
		case '6': return $current_cdef_value;

			break;
	}
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
 * @return string The constructed CDEF string.
 */
function get_cdef($cdef_id) {
	$cdef_items = db_fetch_assoc_prepared('SELECT id, type, value FROM cdef_items WHERE cdef_id = ? ORDER BY sequence', array($cdef_id));

	$i           = 0;
	$cdef_string = '';

	if (cacti_sizeof($cdef_items) > 0) {
		foreach ($cdef_items as $cdef_item) {
			if ($i > 0) {
				$cdef_string .= ',';
			}

			if ($cdef_item['type'] == 5) {
				$current_cdef_id = $cdef_item['value'];
				$cdef_string .= get_cdef($current_cdef_id);
			} else {
				$cdef_string .= get_cdef_item_name($cdef_item['id']);
			}
			$i++;
		}
	}

	return $cdef_string;
}
