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

	list($output, $status) = call_user_func_array('ss_diskfree_collect', $_SERVER['argv']);

	print $output;

	exit($status);
} else {
	include_once(dirname(__FILE__) . '/../lib/poller_script.php');
}

/**
 * Returns the free space and percent used of a filesystem, as diskfree.pl
 * and diskfree.sh do.
 *
 * 'megabytes' holds df's available column in 1024-byte blocks; the label is
 * kept from the originals so existing data sources read the same number.
 * Free space and percent used come from df rather than disk_free_space(),
 * because df's percentage is computed from the free block count, which PHP
 * does not expose.
 *
 * @param string $path A path on the filesystem, or empty for every filesystem.
 *
 * @return string megabytes:N percent:N, or megabytes:U percent:U.
 */
function ss_diskfree($path = '') {
	return ss_diskfree_collect($path)[0];
}

/**
 * Validates the path and runs df, returning the output with the exit status
 * the command line entry point uses.
 *
 * A path that fails validation returns U rather than listing every
 * filesystem. A leading '-' is rejected because df would read it as an option.
 *
 * @param string $path The path.
 *
 * @return array{0: string, 1: int}
 */
function ss_diskfree_collect($path = '') {
	$unknown = 'megabytes:U percent:U';

	if ($path !== '' && !preg_match('/^[\w.\/][\w.\/-]*$/', $path)) {
		return array($unknown, 1);
	}

	/* POSIX df: -k with -P reports 1024-byte blocks on GNU, BSD, macOS and
	 * busybox alike. */
	$args = array('-k', '-P');

	if ($path !== '') {
		$args[] = $path;
	}

	$df = poller_script_exec('df', $args);

	if ($df === null || $df['timed_out']) {
		return array($unknown, 1);
	}

	$output = ss_diskfree_parse($df['stdout'], $path !== '');

	if ($output === '') {
		return array($unknown, 1);
	}

	return array($output, 0);
}

/**
 * Formats 'df -k -P' output.
 *
 * With a path, df prints one filesystem. Without one, every filesystem is
 * printed back to back with no separator, as diskfree.pl does.
 *
 * @param string $df         The output of df.
 * @param bool   $first_only Stop after the first filesystem.
 *
 * @return string The formatted fields, or an empty string if none matched.
 */
function ss_diskfree_parse($df, $first_only) {
	$output = '';

	foreach (explode("\n", $df) as $line) {
		if (preg_match('/^Filesystem\s+/', $line)) {
			continue;
		}

		if (preg_match('/^(\S+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)%\s+(\S+)/', $line, $matches)) {
			$output .= 'megabytes:' . $matches[4] . ' percent:' . $matches[5];

			if ($first_only) {
				break;
			}
		}
	}

	return $output;
}
