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

	list($output, $status) = call_user_func_array('ss_unix_tcp_connections_collect', $_SERVER['argv']);

	print $output;

	exit($status);
} else {
	include_once(dirname(__FILE__) . '/../lib/poller_script.php');
}

/**
 * Counts TCP connections, as unix_tcp_connections.pl does.
 *
 * @param string $filter Count only connections whose line contains this text,
 *                       such as a state or port, or all when empty.
 *
 * @return string The count, or U.
 */
function ss_unix_tcp_connections($filter = '') {
	return ss_unix_tcp_connections_collect($filter)[0];
}

/**
 * Maps the state names ss prints to the names netstat prints. Graphs and
 * existing grepstr values were built against netstat.
 *
 * @return array<string,string>
 */
function ss_unix_tcp_connections_states() {
	return array(
		'ESTAB'      => 'ESTABLISHED',
		'SYN-SENT'   => 'SYN_SENT',
		'SYN-RECV'   => 'SYN_RECV',
		'FIN-WAIT-1' => 'FIN_WAIT1',
		'FIN-WAIT-2' => 'FIN_WAIT2',
		'TIME-WAIT'  => 'TIME_WAIT',
		'CLOSE-WAIT' => 'CLOSE_WAIT',
		'LAST-ACK'   => 'LAST_ACK'
	);
}

/**
 * Validates the filter and runs netstat, or ss where netstat is missing,
 * returning the count with the exit status the command line entry point uses.
 *
 * A filter that fails validation returns U rather than an unfiltered count.
 *
 * @param string $filter The connection filter.
 *
 * @return array{0: string, 1: int}
 */
function ss_unix_tcp_connections_collect($filter = '') {
	if ($filter !== '') {
		if (!preg_match('/^[\w.:-]+$/', $filter)) {
			return array('U', 1);
		}

		$filter = ss_unix_tcp_connections_states()[$filter] ?? $filter;
	}

	$dirs = array('/bin', '/usr/bin', '/usr/sbin');

	if (poller_script_find('netstat', $dirs) !== null) {
		$mode   = 'netstat';
		$output = poller_script_exec('netstat', array('-n'), 10, $dirs);
	} elseif (poller_script_find('ss', $dirs) !== null) {
		/* ss without -a also hides TIME-WAIT and SYN-RECV, which netstat -n
		 * reports, so list everything and drop the listeners when counting. */
		$mode   = 'ss';
		$output = poller_script_exec('ss', array('-tan'), 10, $dirs);
	} else {
		return array('U', 1);
	}

	if ($output === null || $output['timed_out']) {
		return array('U', 1);
	}

	return array((string) ss_unix_tcp_connections_count($output['stdout'], $mode, $filter), 0);
}

/**
 * Counts connection lines in 'netstat -n' or 'ss -tan' output.
 *
 * ss lines have their state rewritten to the netstat spelling before the
 * filter is applied, and ss listeners are left out because netstat -n does
 * not list them. The filter matches as literal text.
 *
 * @param string $output The command output.
 * @param string $mode   'netstat' or 'ss'.
 * @param string $filter Text a line must contain, or empty for every line.
 *
 * @return int
 */
function ss_unix_tcp_connections_count($output, $mode, $filter) {
	$states = ss_unix_tcp_connections_states();
	$count  = 0;

	foreach (explode("\n", $output) as $line) {
		if ($mode === 'netstat') {
			if (strpos($line, 'tcp') === false) {
				continue;
			}
		} else {
			if ($line === '' || preg_match('/^(?:State|LISTEN)\s+/', $line)) {
				continue;
			}

			$line = (string) preg_replace_callback('/^(\S+)/', function ($state) use ($states) {
				return isset($states[$state[1]]) ? $states[$state[1]] : $state[1];
			}, $line);
		}

		if ($filter !== '' && strpos($line, $filter) === false) {
			continue;
		}

		$count++;
	}

	return $count;
}
