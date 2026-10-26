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

require_once __DIR__ . '/Vdef/DatabaseVdefRepository.php';
require_once __DIR__ . '/Vdef/VdefResolver.php';

/**
 * get_vdef_item_name - resolves a single VDEF item into its text-based representation
 *
 * @param int $vdef_item_id The id of the individual vdef item
 *
 * @return string A text-based representation of the vdef item
 */
function get_vdef_item_name(int $vdef_item_id) : string {
	global $vdef_functions, $vdef_item_types;

	$vdef_item          = db_fetch_row_prepared('SELECT type, value FROM vdef_items WHERE id = ?', [$vdef_item_id]);
	$current_vdef_value = $vdef_item['value'];

	switch ($vdef_item['type']) {
		case '1': return $vdef_functions[$current_vdef_value];
		case '4': return $current_vdef_value;
		case '6': return $current_vdef_value;
	}

	return '';
}

/**
 * get_vdef - resolves an entire VDEF into its text-based representation for use in the RRDtool 'graph'
 * string. this name will be resolved recursively if necessary
 *
 * @param int  $vdef_id The id of the vdef to resolve
 * @param bool $display
 *
 * @return string A text-based representation of the vdef
 */
function get_vdef(int $vdef_id, bool $display = false) : string {
	return (new \Cacti\Vdef\VdefResolver(new \Cacti\Vdef\DatabaseVdefRepository()))->resolve($vdef_id, $display);
}

function preset_vdef_form_list() : array {
	$fields_vdef_edit = [
		'name' => [
			'method'        => 'textbox',
			'friendly_name' => __('Name'),
			'description'   => __('A useful name for this VDEF.'),
			'value'         => '|arg1:name|',
			'max_length'    => '255',
		],
		'id' => [
			'method' => 'hidden_zero',
			'value'  => '|arg1:id|'
		],
		'save_component_vdeef' => [
			'method' => 'hidden',
			'value'  => '1'
		]
	];

	return $fields_vdef_edit;
}

function preset_vdef_item_form_list() : array {
	$fields_vdef_item_edit = [
		'sequence' => 'sequence',
		'type'     => 'type',
		'value'    => 'value'
	];

	return $fields_vdef_item_edit;
}
