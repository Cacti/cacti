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
	include_once(dirname(__FILE__) . '/../lib/poller_script.php');

	array_shift($_SERVER['argv']);

	list($output, $status) = call_user_func_array('ss_unix_users_collect', $_SERVER['argv']);

	print $output;

	exit($status);
} else {
	include_once(dirname(__FILE__) . '/../lib/poller_script.php');
}

/**
 * Counts logged in sessions, as unix_users.pl does.
 *
 * @param string $filter Count only sessions whose 'who' line contains this
 *                       text, or every session when empty.
 *
 * @return string The count, or U.
 */
function ss_unix_users($filter = '') {
	return ss_unix_users_collect($filter)[0];
}

/**
 * Validates the filter and runs 'who', returning the count with the exit
 * status the command line entry point uses.
 *
 * A filter that fails validation returns U rather than an unfiltered count,
 * which would graph every session as if it belonged to this user.
 *
 * @param string $filter The user filter.
 *
 * @return array{0: string, 1: int}
 */
function ss_unix_users_collect($filter = '') {
	if ($filter !== '' && !preg_match('/^[\w.-]+$/', $filter)) {
		return array('U', 1);
	}

	$who = poller_script_exec('who');

	if ($who === null || $who['timed_out']) {
		return array('U', 1);
	}

	return array((string) ss_unix_users_count($who['stdout'], $filter), 0);
}

/**
 * Counts session lines in 'who' output. The filter matches as literal text.
 *
 * @param string $who    The output of 'who'.
 * @param string $filter Text a line must contain, or empty for every line.
 *
 * @return int
 */
function ss_unix_users_count($who, $filter) {
	$count = 0;

	foreach (explode("\n", $who) as $line) {
		if (strpos($line, ':') === false) {
			continue;
		}

		if ($filter !== '' && strpos($line, $filter) === false) {
			continue;
		}

		$count++;
	}

	return $count;
}
