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

	array_shift($_SERVER['argv']);

	[$output, $status] = call_user_func_array('ss_ping_collect', $_SERVER['argv']);

	print $output;

	exit($status);
} else {
	include_once(__DIR__ . '/../lib/poller_script.php');
}
/** @codeCoverageIgnoreEnd */

/**
 * Returns the round trip time of one ping in milliseconds, as ping.pl does.
 *
 * @param string $host A hostname or address, optionally prefixed with tcp:,
 *                     udp:, tcp6: or udp6: as Cacti stores it.
 *
 * @return string The time in ms, or U.
 */
function ss_ping(string $host = '') : string {
	return ss_ping_collect($host)[0];
}

/**
 * Validates the host and pings it once, falling back to ping6 when the name
 * does not resolve for IPv4. Returns the time with the exit status the
 * command line entry point uses: 1 for a rejected host, 0 otherwise.
 *
 * @param string $host The host.
 *
 * @return array{0: string, 1: int}
 */
function ss_ping_collect(string $host = '') : array {
	$host = ss_ping_host($host);

	if ($host === null) {
		return ['U', 1];
	}

	if (PHP_OS === 'SunOS') {
		$args = ['-s', $host, '64', '1'];
	} else {
		$args = ['-c', '1', $host];
	}

	$line = ss_ping_line(ss_ping_run('ping', $args), true);

	if (preg_match('/unknown host|Unknown host|not supported|Name or service not known|No address associated with name/', $line)
		&& (is_file('/bin/ping6') || is_file('/sbin/ping6'))) {
		$line6 = ss_ping_line(ss_ping_run('ping6', ['-c', '1', $host]), false);

		if ($line6 !== '') {
			$line = $line6;
		}
	}

	return [ss_ping_time($line), 0];
}

/**
 * Strips the transport prefix Cacti stores with a host and validates what
 * is left.
 *
 * ping.pl also tries to strip a ':port' suffix, but only when the host holds
 * no colon, so that strip never applies and is not repeated here.
 *
 * @param string $host The host as passed by the data input method.
 *
 * @return string|null The host, or null when it is rejected. A leading '-'
 *                     is rejected because ping would read it as an option.
 */
function ss_ping_host(string $host) : ?string {
	$host = (string) preg_replace('/^(?:tcp|udp)6?:/i', '', $host);

	if (!preg_match('/^[\w.:][\w.:-]*$/', $host)) {
		return null;
	}

	return $host;
}

/**
 * Runs ping or ping6 and returns stdout followed by stderr.
 *
 * @param string            $name 'ping' or 'ping6'.
 * @param array<int,string> $args The arguments.
 *
 * @return string
 */
function ss_ping_run(string $name, array $args) : string {
	// ping -c 1 waits up to 10 seconds for a reply, so allow a little more
	$output = poller_script_exec($name, $args, 12);

	if ($output === null) {
		return '';
	}

	return $output['stdout'] . $output['stderr'];
}

/**
 * Picks the first line of ping output that carries a reply or a name error.
 *
 * @param string $output         The ping output.
 * @param bool   $include_errors Whether resolver errors also match.
 *
 * @return string The line, or an empty string.
 */
function ss_ping_line(string $output, bool $include_errors) : string {
	$pattern = '(?:icmp_)?[sr]eq=.*t(ime|emps)';

	if ($include_errors) {
		$pattern .= '|unknown host|Unknown host|not supported|Name or service not known|No address associated with name';
	}

	foreach (explode("\n", $output) as $line) {
		if (preg_match('/(' . $pattern . ')/', $line)) {
			return $line;
		}
	}

	return '';
}

/**
 * Extracts the round trip time from a ping reply line, converting usec to ms.
 *
 * @param string $line A reply line such as 'icmp_seq=1 ttl=64 time=0.045 ms'.
 *
 * @return string The time in ms, or U.
 */
function ss_ping_time(string $line) : string {
	if (!preg_match('/(.*t(?:ime|emps)=)([\w\.]*).*(ms|usec)/', $line, $matches)) {
		return 'U';
	}

	if ($matches[2] === '') {
		return 'U';
	}

	if ($matches[3] === 'usec') {
		// Perl prints numbers with 15 significant digits
		return sprintf('%.15g', (float) $matches[2] / 1000);
	}

	return $matches[2];
}
