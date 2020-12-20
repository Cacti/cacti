#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2020 The Cacti Group                                 |
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

error_reporting(0);

if (!isset($called_by_script_server)) {
	include_once(dirname(__FILE__) . '/../include/cli_check.php');

	array_shift($_SERVER['argv']);

	print call_user_func_array('ss_gexport', $_SERVER['argv']);
}

function ss_gexport($cmd, $arg1 = '', $arg2 = '') {
	if ($cmd == 'index') {
		if (db_table_exists('graph_exports')) {
			$exports = db_fetch_assoc('SELECT id FROM graph_exports ORDER BY id');

			if (cacti_sizeof($exports)) {
				foreach ($exports as $export) {
					print $export['id'] . PHP_EOL;
				}
			}
		}
	} elseif ($cmd == 'query') {
		$arg = $arg1;

		if ($arg1 == 'exportId') {
			if (db_table_exists('graph_exports')) {
				$arr = db_fetch_assoc('SELECT id FROM graph_exports ORDER BY id');

				if (cacti_sizeof($arr)) {
					foreach ($arr as $item) {
						print $item['id'] . '!' . $item['id'] . PHP_EOL;
					}
				}
			}
		} elseif ($arg1 == 'exportName') {
			if (db_table_exists('graph_exports')) {
				$arr = db_fetch_assoc('SELECT id, name FROM graph_exports ORDER BY id');

				if (cacti_sizeof($arr)) {
					foreach ($arr as $item) {
						print $item['id'] . '!' . $item['name'] . PHP_EOL;
					}
				}
			}
		}
	} elseif ($cmd == 'get') {
		$arg   = $arg1;
		$index = $arg2;
		$value = '0';

		if (db_table_exists('graph_exports')) {
			switch($arg) {
				case 'lastRuntime':
					$value = db_fetch_cell_prepared('SELECT last_runtime
						FROM graph_exports
						WHERE id = ?',
						array($index));

					break;
				case 'totalGraphs':
					$value = db_fetch_cell_prepared('SELECT total_graphs
						FROM graph_exports
						WHERE id = ?',
						array($index));

					break;
			}
		}

		return ($value == '' ? '0' : $value);
	}
}

