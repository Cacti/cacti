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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

// display no errors
error_reporting(0);

if (!isset($called_by_script_server)) {
	include(__DIR__ . '/../include/cli_check.php');
	array_shift($_SERVER['argv']);
	print call_user_func_array('ss_mikrotik_qtrees', $_SERVER['argv']);
}

function ss_mikrotik_qtrees(int $host_id, string $cmd = 'index', string $arg1 = '', string $arg2 = '') : mixed {
	if ($cmd == 'index') {
		$return_arr = ss_mikrotik_qtrees_getnames($host_id);

		for ($i = 0; ($i < cacti_sizeof($return_arr)); $i++) {
			print $return_arr[$i] . "\n";
		}
	} elseif ($cmd == 'query') {
		$arr_index = ss_mikrotik_qtrees_getnames($host_id);
		$arr       = ss_mikrotik_qtrees_getinfo($host_id, $arg1);

		for ($i = 0; ($i < cacti_sizeof($arr_index)); $i++) {
			if (isset($arr[$arr_index[$i]])) {
				print $arr_index[$i] . '!' . $arr[$arr_index[$i]] . "\n";
			}
		}
	} elseif ($cmd == 'get') {
		$arg   = $arg1;
		$index = $arg2;

		return ss_mikrotik_qtrees_getvalue($host_id, $index, $arg);
	}

	return null;
}

function ss_mikrotik_qtrees_getvalue(int $host_id, string $index, string $column) : string {
	switch ($column) {
		case 'qtBytes':
			$column = 'curHCBytes';

			break;
		case 'qtPackets':
			$column = 'curPackets';

			break;
		default:
			$column = 'curHCBytes';

			break;
	}

	$index2 = str_replace('_', ' ', $index);

	$value = db_fetch_cell_prepared('SELECT
		' . $column . ' AS value
		FROM plugin_mikrotik_trees
		WHERE name IN (' . db_qstr($index) . ', ' . db_qstr($index2) . ', ' . db_qstr('<' . $index . '>') . ')
		AND host_id = ?',
		[$host_id]);

	return $value;
}

function ss_mikrotik_qtrees_getnames(int $host_id) : array {
	$return_arr = [];

	$arr = db_fetch_assoc_prepared("SELECT REPLACE(name, ' ', '_') AS name
		FROM plugin_mikrotik_trees
		WHERE host_id = ?
		ORDER BY name",
		[$host_id]);

	for ($i = 0; ($i < cacti_sizeof($arr)); $i++) {
		$return_arr[$i] = $arr[$i]['name'];
	}

	return $return_arr;
}

function ss_mikrotik_qtrees_getinfo(int $host_id, string $info_requested) : array {
	$return_arr = [];

	switch($info_requested) {
		case 'qtName':
			$column = 'name';

			break;
		case 'qtFlow':
			$column = 'flow';

			break;
		case 'qtParent':
			$column = 'parentIndex';

			break;
		default:
			$column = 'name';

			break;
	}

	$arr = db_fetch_assoc_prepared("SELECT
		REPLACE(name, ' ', '_') AS qry_index,
		$column AS qry_value
		FROM plugin_mikrotik_trees
		WHERE host_id = ?
		ORDER BY name",
		[$host_id]);

	for ($i = 0; ($i < cacti_sizeof($arr)); $i++) {
		$return_arr[$arr[$i]['qry_index']] = $arr[$i]['qry_value'];
	}

	return $return_arr;
}
