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

/**
 * Data query backend for resource/script_server/mikrotik_trees.xml.
 *
 * Queue trees are indexed by their name.
 *
 * @param int|string $host_id The Cacti host id
 * @param string     $cmd     One of index, num_indexes, query or get
 * @param string     $arg1    The query field for query and get
 * @param string     $arg2    The queue tree index for get
 *
 * @return string The value for get and num_indexes, otherwise empty
 */
function ss_mikrotik_qtrees($host_id, $cmd = 'index', $arg1 = '', $arg2 = '') {
	// the table only exists when the mikrotik plugin is installed
	if (!db_table_exists('plugin_mikrotik_trees', false)) {
		if ($cmd == 'num_indexes') {
			return '0';
		}

		if ($cmd == 'get') {
			return 'U';
		}

		return '';
	}

	$host_id = (int) $host_id;

	if ($cmd == 'index') {
		foreach (ss_mikrotik_qtrees_getnames($host_id) as $index) {
			print $index . PHP_EOL;
		}
	} elseif ($cmd == 'num_indexes') {
		return (string) cacti_sizeof(ss_mikrotik_qtrees_getnames($host_id));
	} elseif ($cmd == 'query') {
		foreach (ss_mikrotik_qtrees_getinfo($host_id, $arg1) as $index => $value) {
			print $index . '!' . $value . PHP_EOL;
		}
	} elseif ($cmd == 'get') {
		return ss_mikrotik_qtrees_getvalue($host_id, $arg2, $arg1);
	}

	return '';
}

/**
 * Return the current rate of a queue tree counter.
 *
 * @param int|string $host_id The Cacti host id
 * @param string     $index   The queue tree index
 * @param string     $column  The data query output field, qtBytes or qtPackets
 *
 * @return string The counter value, U when there is no data
 */
function ss_mikrotik_qtrees_getvalue($host_id, $index, $column) {
	$columns = [
		'qtBytes'   => 'curHCBytes',
		'qtPackets' => 'curPackets',
	];

	if (!isset($columns[$column])) {
		return 'U';
	}

	// RouterOS may report the tree name wrapped in angle brackets
	$value = db_fetch_cell_prepared('SELECT `' . $columns[$column] . '`
		FROM plugin_mikrotik_trees
		WHERE name IN (?, ?)
		AND host_id = ?',
		[$index, '<' . $index . '>', (int) $host_id]);

	// no row for this tree: unknown, not zero
	return is_numeric($value) ? (string) $value : 'U';
}

/**
 * Return the indexes of the queue trees of a device.
 *
 * @param int|string $host_id The Cacti host id
 *
 * @return array<int, string>
 */
function ss_mikrotik_qtrees_getnames($host_id) {
	$return_arr = [];

	$arr = db_fetch_assoc_prepared('SELECT name
		FROM plugin_mikrotik_trees
		WHERE host_id = ?
		ORDER BY name',
		[(int) $host_id]);

	if (cacti_sizeof($arr)) {
		foreach ($arr as $a) {
			$return_arr[] = $a['name'];
		}
	}

	return $return_arr;
}

/**
 * Return a data query input field for every queue tree of a device.
 *
 * @param int|string $host_id        The Cacti host id
 * @param string     $info_requested The data query input field
 *
 * @return array<string, string>
 */
function ss_mikrotik_qtrees_getinfo($host_id, $info_requested) {
	$columns = [
		'qtName'   => 'name',
		'qtFlow'   => 'flow',
		'qtParent' => 'parentIndex',
	];

	$return_arr = [];

	if (!isset($columns[$info_requested])) {
		return $return_arr;
	}

	$arr = db_fetch_assoc_prepared('SELECT name AS qry_index,
		`' . $columns[$info_requested] . '` AS qry_value
		FROM plugin_mikrotik_trees
		WHERE host_id = ?
		ORDER BY name',
		[(int) $host_id]);

	if (cacti_sizeof($arr)) {
		foreach ($arr as $a) {
			$return_arr[$a['qry_index']] = $a['qry_value'];
		}
	}

	return $return_arr;
}
