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

	[$output, $status] = call_user_func_array('ss_linux_memory_collect', $_SERVER['argv']);

	print $output;

	exit($status);
}
/** @codeCoverageIgnoreEnd */

/**
 * Returns one /proc/meminfo value in kB, as linux_memory.pl does.
 *
 * @param string $key A meminfo key such as 'MemFree', with or without the
 *                    trailing colon the data input method passes.
 *
 * @return string The value, or U.
 */
function ss_linux_memory(string $key = '') : string {
	return ss_linux_memory_collect($key)[0];
}

/**
 * Validates the key, reads /proc/meminfo and returns the value with the exit
 * status the command line entry point uses.
 *
 * @param string $key The meminfo key.
 *
 * @return array{0: string, 1: int}
 */
function ss_linux_memory_collect(string $key = '') : array {
	if (!preg_match('/^([\w().]+):?$/', $key, $matches)) {
		return ['U', 1];
	}

	$meminfo = @file_get_contents('/proc/meminfo');

	if ($meminfo === false) {
		return ['U', 1];
	}

	return [ss_linux_memory_parse($meminfo, $matches[1]), 0];
}

/**
 * Finds a key in /proc/meminfo text.
 *
 * The whole key must match, so that 'Active' does not also pick up
 * 'Active(anon)' and 'Active(file)'.
 *
 * @param string $meminfo The contents of /proc/meminfo.
 * @param string $key     The key without its colon.
 *
 * @return string The value in kB, or U when the key is absent.
 */
function ss_linux_memory_parse(string $meminfo, string $key) : string {
	foreach (explode("\n", $meminfo) as $line) {
		if (preg_match('/^' . preg_quote($key, '/') . ':\s+(\d+) kB/', $line, $matches)) {
			return $matches[1];
		}
	}

	return 'U';
}
