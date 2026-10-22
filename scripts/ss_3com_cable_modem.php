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

	[$output, $status] = ss_3com_cable_modem_collect();

	print $output;

	exit($status);
} else {
	include_once(__DIR__ . '/../lib/poller_script.php');
}
/** @codeCoverageIgnoreEnd */

/**
 * Reports whether a 3Com cable modem has its provisioned configuration, as
 * 3com_cable_modem.pl does: the number of lines on the modem's opcfg page
 * that mention the provisioning server 10.18.18.11.
 *
 * The modem address and page are the fixed ones the Perl script used.
 *
 * @return string The line count, or U when the page cannot be fetched.
 */
function ss_3com_cable_modem() : string {
	return ss_3com_cable_modem_collect()[0];
}

/**
 * Fetches the page and returns the count with the exit status the command
 * line entry point uses.
 *
 * The Perl script printed 0 when the modem did not answer, because grep -c
 * counts an empty input; that read as a real measurement, so U is returned
 * instead.
 *
 * @return array{0: string, 1: int}
 */
function ss_3com_cable_modem_collect() : array {
	$page = poller_script_http_get('http://192.168.100.1/cgibin/opcfg');

	if ($page === null) {
		return ['U', 1];
	}

	return [(string) ss_3com_cable_modem_count($page), 0];
}

/**
 * Counts the lines that match the grep pattern 10.18.18.11. As in grep, each
 * '.' matches any character.
 *
 * @param string $page The opcfg page.
 *
 * @return int
 */
function ss_3com_cable_modem_count(string $page) : int {
	$lines = explode("\n", $page);

	if (end($lines) === '') {
		array_pop($lines);
	}

	return count(preg_grep('/10.18.18.11/', $lines) ?: []);
}
