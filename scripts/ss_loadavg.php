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

// @codeCoverageIgnoreStart
if (!isset($called_by_script_server)) {
	include_once(__DIR__ . '/../include/cli_check.php');

	array_shift($_SERVER['argv']);

	[$output, $status] = call_user_func_array('ss_loadavg_collect', $_SERVER['argv']);

	print $output;

	exit($status);
}
/** @codeCoverageIgnoreEnd */

/**
 * Returns the load average the way loadavg.pl prints it.
 *
 * '5', '15' and '30' select the first, second and third average. These are
 * the 1, 5 and 15 minute values; the labels are kept from loadavg.pl so
 * existing data sources read the same number. Any other number, or no
 * argument, returns all three averages.
 *
 * @param string $minutes '5', '15', '30', another number, or empty.
 *
 * @return string The average or averages, or U.
 */
function ss_loadavg(string $minutes = '') : string {
	return ss_loadavg_collect($minutes)[0];
}

/**
 * Validates the argument and reads the load averages, returning the output
 * with the exit status the command line entry point uses.
 *
 * @param string $minutes The selector.
 *
 * @return array{0: string, 1: int}
 */
function ss_loadavg_collect(string $minutes = '') : array {
	if ($minutes !== '' && !preg_match('/^\d+$/', $minutes)) {
		return ['U', 1];
	}

	$loadavg  = @file_get_contents('/proc/loadavg');
	$averages = ($loadavg === false ? null : ss_loadavg_split($loadavg));

	if ($averages === null) {
		$system = sys_getloadavg();

		if ($system === false) {
			return ['U', 1];
		}

		$averages = array_map(static fn (float $average) : string => sprintf('%.2f', $average), $system);
	}

	return [ss_loadavg_format($averages, $minutes, PHP_OS_FAMILY), 0];
}

/**
 * Takes the three averages from /proc/loadavg text. The kernel already
 * prints them with two decimals, which is what uptime shows.
 *
 * @param string $loadavg The contents of /proc/loadavg.
 *
 * @return array<int,string>|null The three averages, or null if malformed.
 */
function ss_loadavg_split(string $loadavg) : ?array {
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
 * Formats the averages as loadavg.pl prints them from uptime.
 *
 * Linux and the BSDs separate the averages with ', ' and loadavg.pl removes
 * only the first comma; macOS separates them with spaces.
 *
 * @param array<int,string> $averages  The 1, 5 and 15 minute averages.
 * @param string            $minutes   The selector.
 * @param string            $os_family PHP_OS_FAMILY of the host.
 *
 * @return string
 */
function ss_loadavg_format(array $averages, string $minutes, string $os_family) : string {
	if ($minutes === '5') {
		return $averages[0];
	}

	if ($minutes === '15') {
		return $averages[1];
	}

	if ($minutes === '30') {
		return $averages[2];
	}

	if ($os_family === 'Darwin') {
		return implode(' ', $averages);
	}

	return $averages[0] . ' ' . $averages[1] . ', ' . $averages[2];
}
