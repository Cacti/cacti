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
	print call_user_func_array('ss_mikrotik_interfaces', $_SERVER['argv']);
}

/**
 * Data query backend for resource/script_server/mikrotik_interfaces.xml.
 *
 * Interfaces are indexed by their numeric interface index.  Data sources
 * created by earlier versions, which indexed them by name, still resolve.
 *
 * @param int|string $host_id The Cacti host id
 * @param string     $cmd     One of index, num_indexes, query or get
 * @param string     $arg1    The query field for query and get
 * @param string     $arg2    The interface index for get
 *
 * @return string The value for get and num_indexes, otherwise empty
 */
function ss_mikrotik_interfaces($host_id, $cmd = 'index', $arg1 = '', $arg2 = '') {
	// the table only exists when the mikrotik plugin is installed
	if (!db_table_exists('plugin_mikrotik_interfaces', false)) {
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
		foreach (ss_mikrotik_interfaces_getnames($host_id) as $index) {
			print $index . PHP_EOL;
		}
	} elseif ($cmd == 'num_indexes') {
		// counted with the same filters as the index list
		return (string) db_fetch_cell_prepared("SELECT COUNT(DISTINCT `index`)
			FROM plugin_mikrotik_interfaces
			WHERE host_id = ?
			AND name != ''
			AND present = 1",
			[$host_id]);
	} elseif ($cmd == 'query') {
		foreach (ss_mikrotik_interfaces_getinfo($host_id, $arg1) as $index => $value) {
			print $index . '!' . $value . PHP_EOL;
		}
	} elseif ($cmd == 'get') {
		return ss_mikrotik_interfaces_getvalue($host_id, $arg2, $arg1);
	}

	return '';
}

/**
 * Return the current rate of an interface counter.
 *
 * @param int|string $host_id The Cacti host id
 * @param string     $index   The interface index
 * @param string     $column  The data query output field, e.g. RxBytes
 *
 * @return string The counter value, U when there is no data
 */
function ss_mikrotik_interfaces_getvalue($host_id, $index, $column) {
	$fields = [
		'RxBytes', 'RxPackets', 'RxTooShort', 'RxTo64', 'Rx65to127', 'Rx128to255',
		'Rx256to511', 'Rx512to1023', 'Rx1024to1518', 'Rx1519toMax', 'RxTooLong',
		'RxBroadcast', 'RxPause', 'RxMulticast', 'RxFCFSError', 'RxAlignError',
		'RxFragment', 'RxOverflow', 'RxControl', 'RxUnknownOp', 'RxLengthError',
		'RxCodeError', 'RxCarrierError', 'RxJabber', 'RxDrop',
		'TxBytes', 'TxPackets', 'TxTooShort', 'TxTo64', 'Tx65to127', 'Tx128to255',
		'Tx256to511', 'Tx512to1023', 'Tx1024to1518', 'Tx1519toMax', 'TxTooLong',
		'TxBroadcast', 'TxPause', 'TxMulticast', 'TxUnderrun', 'TxCollision',
		'TxExCollision', 'TxMultCollision', 'TxSingCollision', 'TxExDeferred',
		'TxDeferred', 'TxLateCollision', 'TxTotalCollision', 'TxPauseHonored',
		'TxDrop', 'TxJabber', 'TxFCFSError', 'TxControl', 'TxFragment',
	];

	if (!in_array($column, $fields, true)) {
		return 'U';
	}

	// numeric indexes are current, names were emitted by earlier versions
	if (ctype_digit((string) $index)) {
		$where  = '`index` = ?';
		$params = [$index, (int) $host_id];
	} else {
		$where  = 'name IN (?, ?)';
		$params = [$index, str_replace('_', ' ', $index), (int) $host_id];
	}

	// a renamed interface leaves its absent row behind with the same index
	$value = db_fetch_cell_prepared('SELECT `cur' . $column . '`
		FROM plugin_mikrotik_interfaces
		WHERE ' . $where . '
		AND host_id = ?
		ORDER BY present DESC
		LIMIT 1',
		$params);

	// no row for this interface: zero to prevent logging
	return is_numeric($value) ? (string) $value : '0';
}

/**
 * Return the indexes of the named interfaces of a device.
 *
 * @param int|string $host_id The Cacti host id
 *
 * @return array<int|string, int|string>
 */
function ss_mikrotik_interfaces_getnames($host_id) {
	$return_arr = [];

	$arr = db_fetch_assoc_prepared("SELECT `index`
		FROM plugin_mikrotik_interfaces
		WHERE host_id = ?
		AND name != ''
		AND present = 1
		ORDER BY name",
		[(int) $host_id]);

	if (cacti_sizeof($arr)) {
		foreach ($arr as $a) {
			$return_arr[$a['index']] = $a['index'];
		}
	}

	return $return_arr;
}

/**
 * Return a data query input field for every named interface of a device.
 *
 * @param int|string $host_id        The Cacti host id
 * @param string     $info_requested The data query input field
 *
 * @return array<int|string, string>
 */
function ss_mikrotik_interfaces_getinfo($host_id, $info_requested) {
	$return_arr = [];

	if ($info_requested != 'intName') {
		return $return_arr;
	}

	$arr = db_fetch_assoc_prepared("SELECT `index`, name
		FROM plugin_mikrotik_interfaces
		WHERE host_id = ?
		AND name != ''
		AND present = 1
		ORDER BY name",
		[(int) $host_id]);

	if (cacti_sizeof($arr)) {
		foreach ($arr as $a) {
			$return_arr[$a['index']] = $a['name'];
		}
	}

	return $return_arr;
}
