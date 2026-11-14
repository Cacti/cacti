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

/* display no errors */
error_reporting(0);

if (!isset($called_by_script_server)) {
	include(__DIR__ . '/../include/cli_check.php');
	array_shift($_SERVER['argv']);
	print call_user_func_array('ss_mikrotik_interfaces', $_SERVER['argv']);
}

function ss_mikrotik_interfaces($host_id, $cmd = 'index', $arg1 = '', $arg2 = '') {
	/* the table only exists when the mikrotik plugin is installed */
	if (!db_table_exists('plugin_mikrotik_interfaces', false)) {
		if ($cmd == 'num_indexes') {
			print '0';
		} elseif ($cmd == 'get') {
			return 'U';
		}

		return;
	}

	if ($cmd == 'index') {
		$return_arr = ss_mikrotik_interfaces_getnames($host_id, $arg1);

		if (cacti_sizeof($return_arr)) {
			foreach($return_arr as $a) {
				print $a . PHP_EOL;
			}
		}
	} elseif ($cmd == 'num_indexes') {
		print db_fetch_cell_prepared('SELECT COUNT(*)
			FROM plugin_mikrotik_interfaces
			WHERE host_id = ?
			AND name != ""',
			array($host_id));
	} elseif ($cmd == 'query') {
		$arr = ss_mikrotik_interfaces_getinfo($host_id, $arg1, $arg2);

		if (cacti_sizeof($arr)) {
			foreach($arr as $index => $name) {
				print $index . '!' . $name . PHP_EOL;
			}
		}
	} elseif ($cmd == 'get') {
		$arg   = $arg1;
		$index = $arg2;

		return ss_mikrotik_interfaces_getvalue($host_id, $index, $arg);
	}
}

function ss_mikrotik_interfaces_getvalue($host_id, $index, $column) {
	$return_arr = array();

	$column = 'cur' . $column;

	if (!db_is_safe_identifier($column)) {
		return '0';
	}

	$value = db_fetch_cell_prepared("SELECT
		`$column` AS value
		FROM plugin_mikrotik_interfaces
		WHERE `index` = ?
		AND host_id = ?",
		array($index, $host_id));

	if ($value === false) {
		return '0';
	} else {
		return $value;
	}
}

function ss_mikrotik_interfaces_getnames($host_id) {
	$return_arr = array();

	$arr = db_fetch_assoc_prepared("SELECT `index`, name
		FROM plugin_mikrotik_interfaces
		WHERE host_id = ?
		AND name != ''
		ORDER BY name",
		array($host_id));

	if (cacti_sizeof($arr)) {
		foreach($arr as $a) {
			$return_arr[$a['index']] = $a['index'];
		}
	}

	return $return_arr;
}

function ss_mikrotik_interfaces_getinfo($host_id, $info_requested) {
	$return_arr = array();

	switch($info_requested) {
	case 'intName':
		$column = 'name';
		break;
	default:
		return $return_arr;
	}

	$arr = db_fetch_assoc_prepared("SELECT `index`, $column AS qry_value
		FROM plugin_mikrotik_interfaces
		WHERE host_id = ?
		AND name != ''
		ORDER BY name",
		array($host_id));

	if (cacti_sizeof($arr)) {
		foreach($arr as $a) {
			$return_arr[$a['index']] = $a['qry_value'];
		}
	}

	return $return_arr;
}

