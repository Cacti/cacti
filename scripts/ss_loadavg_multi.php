#!/usr/bin/env php
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

error_reporting(0);

if (!isset($called_by_script_server)) {
	include_once(dirname(__FILE__) . '/../include/cli_check.php');

	list($output, $status) = ss_loadavg_multi_collect();

	print $output;

	exit($status);
}

/**
 * Returns the 1, 5 and 15 minute load averages as loadavg_multi.pl does.
 *
 * The third field is labelled 10min, as in loadavg_multi.pl and the
 * 'Unix - Get Load Average' data input method, although it is the 15 minute
 * average.
 *
 * @return string 1min:N 5min:N 10min:N
 */
function ss_loadavg_multi() {
	return ss_loadavg_multi_collect()[0];
}

/**
 * Reads the load averages and returns the output with the exit status the
 * command line entry point uses.
 *
 * @return array{0: string, 1: int}
 */
function ss_loadavg_multi_collect() {
	$loadavg  = @file_get_contents('/proc/loadavg');
	$averages = ($loadavg === false ? null : ss_loadavg_multi_split($loadavg));

	if ($averages === null) {
		$system = function_exists('sys_getloadavg') ? sys_getloadavg() : false;

		if ($system !== false) {
			$averages = array_map(function ($average) {
				return sprintf('%.2f', $average);
			}, $system);
		}
	}

	if ($averages === null) {
		return array(ss_loadavg_multi_format(array('U', 'U', 'U')), 1);
	}

	return array(ss_loadavg_multi_format($averages), 0);
}

/**
 * Takes the three averages from /proc/loadavg text.
 *
 * @param string $loadavg The contents of /proc/loadavg.
 *
 * @return array<int,string>|null The three averages, or null if malformed.
 */
function ss_loadavg_multi_split($loadavg) {
	$fields = preg_split('/\s+/', trim($loadavg));

	if ($fields === false || count($fields) < 3) {
		return null;
	}

	foreach (array_slice($fields, 0, 3) as $field) {
		if (!preg_match('/^\d+\.\d+$/', $field)) {
			return null;
		}
	}

	return array_slice($fields, 0, 3);
}

/**
 * @param array<int,string> $averages The 1, 5 and 15 minute averages.
 *
 * @return string
 */
function ss_loadavg_multi_format($averages) {
	return '1min:' . $averages[0] . ' 5min:' . $averages[1] . ' 10min:' . $averages[2];
}
