<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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

/* get_cdef_item_name - resolves a single CDEF item into its text-based representation
   @arg $cdef_item_id - the id of the individual cdef item
   @returns - a text-based representation of the cdef item */
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

/* get_cdef - resolves an entire CDEF into its text-based representation for use in the RRDtool 'graph'
	 string. this name will be resolved recursively if necessary
   @arg $cdef_id - the id of the cdef to resolve
   @returns - a text-based representation of the cdef */
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
