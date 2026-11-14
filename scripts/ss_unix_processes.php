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
	include_once(__DIR__ . '/../lib/poller_script.php');

	[$output, $status] = ss_unix_processes_collect();

	print $output;

	exit($status);
} else {
	include_once(__DIR__ . '/../lib/poller_script.php');
}
/** @codeCoverageIgnoreEnd */

/**
 * Counts the processes on the host, as unix_processes.pl does, leaving out
 * the process doing the counting.
 *
 * @return string The count, or U.
 */
function ss_unix_processes() : string {
	return ss_unix_processes_collect()[0];
}

/**
 * Counts processes from /proc where it exists and from 'ps ax' elsewhere,
 * returning the count with the exit status the command line entry point uses.
 *
 * @return array{0: string, 1: int}
 */
function ss_unix_processes_collect() : array {
	$self = getmypid();

	if (is_dir('/proc/self')) {
		$entries = @scandir('/proc');

		if ($entries !== false) {
			return [(string) ss_unix_processes_count_proc($entries, (int) $self), 0];
		}
	}

	$ps = poller_script_exec('ps', ['ax']);

	if ($ps === null || $ps['timed_out']) {
		return ['U', 1];
	}

	return [(string) ss_unix_processes_count_ps($ps['stdout'], [(int) $self, $ps['pid']]), 0];
}

/**
 * Counts the process directories in a /proc listing.
 *
 * 'ps ax' lists one line per process, and that is one numeric /proc entry
 * per process; threads are not listed at the top of /proc.
 *
 * @param array<int,string> $entries The names in /proc.
 * @param int               $self    The pid to leave out.
 *
 * @return int
 */
function ss_unix_processes_count_proc(array $entries, int $self) : int {
	$count = 0;

	foreach ($entries as $entry) {
		if (ctype_digit($entry) && (int) $entry !== $self) {
			$count++;
		}
	}

	return $count;
}

/**
 * Counts the process lines in 'ps ax' output.
 *
 * A process line carries a TIME column with a colon and the header does
 * not. The listed pids, this process and its ps child, are left out; the old
 * 'ps ax | grep -c :' pipeline counted its own shell, ps and grep.
 *
 * @param string         $ps      The output of 'ps ax'.
 * @param array<int,int> $exclude Pids to leave out.
 *
 * @return int
 */
function ss_unix_processes_count_ps(string $ps, array $exclude) : int {
	$count = 0;

	foreach (explode("\n", $ps) as $line) {
		if (!str_contains($line, ':')) {
			continue;
		}

		if (preg_match('/^\s*(\d+)\s/', $line, $matches) && in_array((int) $matches[1], $exclude, true)) {
			continue;
		}

		$count++;
	}

	return $count;
}
