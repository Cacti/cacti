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
	print call_user_func_array('ss_mikrotik_queues', $_SERVER['argv']);
}

/**
 * Data query backend for resource/script_server/mikrotik_queues.xml.
 *
 * Simple queues are indexed by their name with spaces replaced by underscores.
 *
 * @param int|string $host_id The Cacti host id
 * @param string     $cmd     One of index, num_indexes, query or get
 * @param string     $arg1    The query field for query and get
 * @param string     $arg2    The queue index for get
 *
 * @return string The value for get and num_indexes, otherwise empty
 */
function ss_mikrotik_queues($host_id, $cmd = 'index', $arg1 = '', $arg2 = '') {
	// the table only exists when the mikrotik plugin is installed
	if (!db_table_exists('plugin_mikrotik_queues', false)) {
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
		foreach (ss_mikrotik_queues_getnames($host_id) as $index) {
			print $index . PHP_EOL;
		}
	} elseif ($cmd == 'num_indexes') {
		// counted with the same filters as the index list
		return (string) db_fetch_cell_prepared("SELECT COUNT(DISTINCT REPLACE(name, ' ', '_'))
			FROM plugin_mikrotik_queues
			WHERE host_id = ?",
			[$host_id]);
	} elseif ($cmd == 'query') {
		foreach (ss_mikrotik_queues_getinfo($host_id, $arg1) as $index => $value) {
			print $index . '!' . $value . PHP_EOL;
		}
	} elseif ($cmd == 'get') {
		return ss_mikrotik_queues_getvalue($host_id, $arg2, $arg1);
	}

	return '';
}

/**
 * Return the current rate of a simple queue counter.
 *
 * @param int|string $host_id The Cacti host id
 * @param string     $index   The queue index
 * @param string     $column  The data query output field, e.g. qsInOctets
 *
 * @return string The counter value, U when there is no data
 */
function ss_mikrotik_queues_getvalue($host_id, $index, $column) {
	$columns = [
		'qsInOctets'   => 'curBytesIn',
		'qsOutOctets'  => 'curBytesOut',
		'qsInPackets'  => 'curPacketsIn',
		'qsOutPackets' => 'curPacketsOut',
		'qsInQueued'   => 'curQueuesIn',
		'qsOutQueued'  => 'curQueuesOut',
		'qsInDropped'  => 'curDroppedIn',
		'qsOutDropped' => 'curDroppedOut',
	];

	if (!isset($columns[$column])) {
		return 'U';
	}

	$index2 = str_replace('_', ' ', $index);

	$value = db_fetch_cell_prepared('SELECT `' . $columns[$column] . '`
		FROM plugin_mikrotik_queues
		WHERE name IN (?, ?)
		AND host_id = ?',
		[$index, $index2, (int) $host_id]);

	// no row for this queue: zero to prevent logging
	return is_numeric($value) ? (string) $value : '0';
}

/**
 * Return the indexes of the simple queues of a device.
 *
 * @param int|string $host_id The Cacti host id
 *
 * @return array<int, string>
 */
function ss_mikrotik_queues_getnames($host_id) {
	$return_arr = [];

	$arr = db_fetch_assoc_prepared("SELECT DISTINCT REPLACE(name, ' ', '_') AS name
		FROM plugin_mikrotik_queues
		WHERE host_id = ?
		ORDER BY name",
		[(int) $host_id]);

	if (cacti_sizeof($arr)) {
		foreach ($arr as $a) {
			$return_arr[] = $a['name'];
		}
	}

	return $return_arr;
}

/**
 * Return a data query input field for every simple queue of a device.
 *
 * @param int|string $host_id        The Cacti host id
 * @param string     $info_requested The data query input field
 *
 * @return array<string, string>
 */
function ss_mikrotik_queues_getinfo($host_id, $info_requested) {
	$columns = [
		'qsName'       => 'name',
		'qsInterface'  => 'iFace',
		'qsTargetIp'   => 'srcAddr',
		'qsTargetMask' => 'srcMask',
		'qsDstIp'      => 'dstAddr',
		'qsDstMask'    => 'dstMask',
	];

	$return_arr = [];

	if (!isset($columns[$info_requested])) {
		return $return_arr;
	}

	$arr = db_fetch_assoc_prepared("SELECT REPLACE(name, ' ', '_') AS qry_index,
		`" . $columns[$info_requested] . '` AS qry_value
		FROM plugin_mikrotik_queues
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
