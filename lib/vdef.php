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

/* get_vdef_item_value - formats an already-fetched VDEF item row into its text-based representation
   @param $vdef_item - associative array with 'type' and 'value' keys
   @returns - a text-based representation of the vdef item */
function get_vdef_item_value($vdef_item) {
	global $vdef_functions;

	if (!cacti_sizeof($vdef_item) || !isset($vdef_item['type']) || !array_key_exists('value', $vdef_item)) {
		return '';
	}

	$current_vdef_value = $vdef_item['value'];

	switch ($vdef_item['type']) {
		case '1':
			return isset($vdef_functions[$current_vdef_value]) ? $vdef_functions[$current_vdef_value] : '';
		case '4':
		case '6':
			return $current_vdef_value;
	}

	return '';
}

/* get_vdef_item_name - resolves a single VDEF item into its text-based representation
   @param $vdef_item_id - the id of the individual vdef item
   @returns - a text-based representation of the vdef item */
function get_vdef_item_name($vdef_item_id) {
	$vdef_item = db_fetch_row_prepared('SELECT type, value FROM vdef_items WHERE id = ?', array($vdef_item_id));

	return get_vdef_item_value($vdef_item);
}

/* get_vdef - resolves an entire VDEF into its text-based representation for use in the RRDtool 'graph'
     string. this name will be resolved recursively if necessary
   @param $vdef_id - the id of the vdef to resolve
   @param $display - whether to include spaces after commas for display
   @param $visited - internal map of VDEF IDs active in the current recursion stack
   @param $cache - internal per-call cache of resolved VDEF strings
   @returns - a text-based representation of the vdef */
function get_vdef($vdef_id, $display = false, $visited = array(), &$cache = array()) {
	$vdef_key = (int) $vdef_id;

	if (isset($visited[$vdef_key])) {
		cacti_log(sprintf('ERROR: VDEF %d contains a recursive cycle.', $vdef_key), false, 'VDEF');

		return '';
	}

	if (array_key_exists($vdef_key, $cache)) {
		return $cache[$vdef_key];
	}

	if (cacti_sizeof($visited) >= 64) {
		cacti_log(sprintf('ERROR: VDEF %d exceeds maximum nesting depth.', $vdef_key), false, 'VDEF');

		return '';
	}

	$visited[$vdef_key] = true;

	$vdef_items = db_fetch_assoc_prepared('SELECT * FROM vdef_items WHERE vdef_id = ? ORDER BY sequence', array($vdef_id));

	$parts     = array();
	$cacheable = true;

	if (cacti_sizeof($vdef_items)) {
		foreach ($vdef_items as $vdef_item) {
			if ($vdef_item['type'] == 5) {
				$current_vdef_id = $vdef_item['value'];
				$item_value      = get_vdef($current_vdef_id, $display, $visited, $cache);

				if ($item_value === '') {
					$cacheable = false;
				}
			} else {
				$item_value = get_vdef_item_value($vdef_item);
			}

			if ($item_value !== '' && $item_value !== null) {
				$parts[] = $item_value;
			}
		}
	}

	$vdef_string = implode($display ? ', ' : ',', $parts);

	if ($cacheable) {
		$cache[$vdef_key] = $vdef_string;
	}

	return $vdef_string;
}

function preset_vdef_form_list() {
	$fields_vdef_edit = array(
		'name' => array(
			'method'        => 'textbox',
			'friendly_name' => __('Name'),
			'description'   => __('A useful name for this VDEF.'),
			'value'         => '|arg1:name|',
			'max_length'    => '255',
		),
	);

	return $fields_vdef_edit;
}

function preset_vdef_item_form_list() {
	$fields_vdef_item_edit = array(
		'sequence' => 'sequence',
		'type' => 'type',
		'value' => 'value'
	);

	return $fields_vdef_item_edit;
}
