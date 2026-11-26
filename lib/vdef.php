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
 * Resolves a single VDEF item into its text-based representation. Used as part of Cacti's lib
 * functionality.
 *
 * @param int $vdef_item_id The id of the individual vdef item.
 *
 * @return string A text-based representation of the vdef item.
 */
function get_vdef_item_name($vdef_item_id) 	{
	global $vdef_functions, $vdef_item_types;

	$vdef_item = db_fetch_row_prepared('SELECT type, value FROM vdef_items WHERE id = ?', array($vdef_item_id));
	if (!cacti_sizeof($vdef_item)) {
		return '';
	}

	$current_vdef_value = $vdef_item['value'];

	switch ($vdef_item['type']) {
		case '1':
			return isset($vdef_functions[$current_vdef_value]) ? $vdef_functions[$current_vdef_value] : '';
		case '4':
			return $current_vdef_value;
		case '6':
			return $current_vdef_value;
		default:
			return '';
	}
}

/**
 * Resolves an entire VDEF into its text-based representation for use in the RRDtool 'graph'
 * string. this name will be resolved recursively if necessary. Used as part of Cacti's lib
 * functionality.
 *
 * @param int $vdef_id The id of the vdef to resolve.
 * @param bool $display The display.
 *
 * @return string A text-based representation of the vdef.
 */
function get_vdef($vdef_id, $display = false) {
	$visited   = array();
	$expansion = 0;

	return get_vdef_recursive($vdef_id, $display, $visited, $expansion);
}

/**
 * Resolves a VDEF with cycle detection, nesting depth limits, and an expansion cap.
 *
 * @param int $vdef_id The id of the vdef to resolve.
 * @param bool $display Whether to format for display.
 * @param array &$visited Currently active VDEF IDs on the call stack.
 * @param int &$expansion Total recursive expansions performed.
 *
 * @return string A text-based representation of the vdef, or empty string on error.
 */
function get_vdef_recursive($vdef_id, $display, &$visited, &$expansion) {
	$vdef_id = (int) $vdef_id;

	if ($vdef_id <= 0) {
		return '';
	}

	if (isset($visited[$vdef_id])) {
		cacti_log(sprintf('ERROR: VDEF %d contains a recursive cycle.', $vdef_id), false, 'VDEF');

		return '';
	}

	if (cacti_sizeof($visited) >= 64) {
		cacti_log(sprintf('ERROR: VDEF %d exceeds the resolver nesting depth.', $vdef_id), false, 'VDEF');

		return '';
	}

	if (++$expansion > 4096) {
		cacti_log(sprintf('ERROR: VDEF %d exceeds the resolver expansion budget.', $vdef_id), false, 'VDEF');

		return '';
	}

	$visited[$vdef_id] = true;
	$vdef_items = db_fetch_assoc_prepared('SELECT * FROM vdef_items WHERE vdef_id = ? ORDER BY sequence', array($vdef_id));

	$i = 0;
	$vdef_string = '';

	if (cacti_sizeof($vdef_items)) {
		foreach ($vdef_items as $vdef_item) {
			if ($i > 0) {
				$vdef_string .= ($display ? ', ':',');
			}

			if ($vdef_item['type'] == 5) {
				$current_vdef_id = (int) $vdef_item['value'];
				$vdef_string .= get_vdef_recursive($current_vdef_id, $display, $visited, $expansion);
			} else {
				$vdef_string .= get_vdef_item_name($vdef_item['id']);
			}

			if (strlen($vdef_string) > 65536) {
				cacti_log(sprintf('ERROR: VDEF %d exceeds the maximum output size.', $vdef_id), false, 'VDEF');
				unset($visited[$vdef_id]);

				return '';
			}

			$i++;
		}
	}

	unset($visited[$vdef_id]);

	return $vdef_string;
}

/**
 * Handles the preset vdef form list. Used as part of Cacti's lib functionality.
 *
 * @return array An array of results.
 */
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

/**
 * Handles the preset vdef item form list. Used as part of Cacti's lib functionality.
 *
 * @return array An array of results.
 */
function preset_vdef_item_form_list() {
	$fields_vdef_item_edit = array(
		'sequence' => 'sequence',
		'type' => 'type',
		'value' => 'value'
	);

	return $fields_vdef_item_edit;
}
