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

	[$output, $status] = call_user_func_array('ss_webhits_collect', $_SERVER['argv']);

	print $output;

	exit($status);
}
/** @codeCoverageIgnoreEnd */

/**
 * Returns the number of lines in a web server access log, as webhits.pl
 * does with 'wc -l'.
 *
 * @param string $log_path The log file, or empty for /var/log/httpd/access_log.
 *
 * @return string The line count, or U.
 */
function ss_webhits(string $log_path = '') : string {
	return ss_webhits_collect($log_path)[0];
}

/**
 * Counts the lines in the log and returns the count with the exit status the
 * command line entry point uses.
 *
 * Only regular files are read. 'wc -l' on a FIFO or a device such as
 * /dev/zero would block the script server.
 *
 * @param string $log_path The log file.
 *
 * @return array{0: string, 1: int}
 */
function ss_webhits_collect(string $log_path = '') : array {
	if ($log_path === '') {
		$log_path = '/var/log/httpd/access_log';
	}

	if (!is_file($log_path) || !is_readable($log_path)) {
		return ['U', 1];
	}

	$log = @fopen($log_path, 'rb');

	if ($log === false) {
		return ['U', 1];
	}

	$count = ss_webhits_count($log);

	fclose($log);

	return [(string) $count, 0];
}

/**
 * Counts newline characters in a stream, which is what 'wc -l' reports.
 *
 * @param resource $stream An open, readable stream.
 *
 * @return int
 */
function ss_webhits_count($stream) : int {
	$count = 0;

	while (!feof($stream)) {
		$chunk = fread($stream, 1048576);

		if ($chunk === false) {
			break;
		}

		$count += substr_count($chunk, "\n");
	}

	return $count;
}
