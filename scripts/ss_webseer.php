#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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
	include_once(__DIR__ . '/../include/cli_check.php');

	array_shift($_SERVER['argv']);

	print call_user_func_array('ss_webseer', $_SERVER['argv']);
}

function ss_webseer($cmd = 'index', $arg1 = '', $arg2 = '') {
	if ($cmd == 'index') {
		if (db_table_exists('plugin_webseer_urls')) {
			$exports = db_fetch_assoc('SELECT id FROM plugin_webseer_urls ORDER BY id');

			if (cacti_sizeof($exports)) {
				foreach ($exports as $export) {
					print $export['id'] . PHP_EOL;
				}
			}
		}
	} elseif ($cmd == 'query') {
		$arg = $arg1;

		if (db_table_exists('plugin_webseer_urls')) {
			if ($arg == 'webseerId') {
				$arr = db_fetch_assoc('SELECT id FROM plugin_webseer_urls ORDER BY id');

				if (cacti_sizeof($arr)) {
					foreach ($arr as $item) {
						print $item['id'] . '!' . $item['id'] . PHP_EOL;
					}
				}
			} elseif ($arg == 'webseerName') {
				$arr = db_fetch_assoc('SELECT id, display_name AS name FROM plugin_webseer_urls ORDER BY id');

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

		if (db_table_exists('plugin_webseer_urls')) {
			switch($arg) {
				case 'lookupTime':
					$value = db_fetch_cell_prepared('SELECT namelookup_time
						FROM plugin_webseer_urls
						WHERE id = ?',
						array($index));

					break;
				case 'connectTime':
					$value = db_fetch_cell_prepared('SELECT connect_time
						FROM plugin_webseer_urls
						WHERE id = ?',
						array($index));

					break;
				case 'redirectTime':
					$value = db_fetch_cell_prepared('SELECT redirect_time
						FROM plugin_webseer_urls
						WHERE id = ?',
						array($index));

					break;
				case 'totalTime':
					$value = db_fetch_cell_prepared('SELECT total_time
						FROM plugin_webseer_urls
						WHERE id = ?',
						array($index));

					break;
				case 'downloadSpeed':
					$value = db_fetch_cell_prepared('SELECT speed_download
						FROM plugin_webseer_urls
						WHERE id = ?',
						array($index));

					break;
				case 'downloadSize':
					$value = db_fetch_cell_prepared('SELECT size_download
						FROM plugin_webseer_urls
						WHERE id = ?',
						array($index));

					break;
				case 'checkStatus':
					$value = db_fetch_cell_prepared('SELECT result
						FROM plugin_webseer_urls
						WHERE id = ?',
						array($index));

					break;
			}
		}

		return (empty($value) ? '0' : $value);
	}
}
