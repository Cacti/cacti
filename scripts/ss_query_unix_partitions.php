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

	list($output, $status) = call_user_func_array('ss_query_unix_partitions_collect', $_SERVER['argv']);

	if ($status != 0) {
		fwrite(STDERR, 'usage:' . PHP_EOL . PHP_EOL .
			'ss_query_unix_partitions.php index' . PHP_EOL .
			'ss_query_unix_partitions.php num_indexes' . PHP_EOL .
			'ss_query_unix_partitions.php query {device,mount,total,used,available,percent}' . PHP_EOL .
			'ss_query_unix_partitions.php get {device,mount,total,used,available,percent} DEVICE' . PHP_EOL);
	}

	print $output;

	exit($status);
} else {
	include_once(dirname(__FILE__) . '/../lib/poller_script.php');
}

/**
 * Data query for the local filesystems reported by 'df', with the same
 * arguments and output as query_unix_partitions.pl.
 *
 * index lists the devices one per line, num_indexes counts them, query
 * returns device:value lines for one field, and get returns one field of one
 * device. The device is the index.
 *
 * @param string $cmd    index, num_indexes, query or get.
 * @param string $field  device, mount, total, used, available or percent.
 * @param string $device The device, for get.
 *
 * @return string
 */
function ss_query_unix_partitions($cmd = '', $field = '', $device = '') {
	return ss_query_unix_partitions_collect($cmd, $field, $device)[0];
}

/**
 * Validates the arguments and runs df, returning the output with the exit
 * status the command line entry point uses.
 *
 * @param string $cmd    The data query command.
 * @param string $field  The field, for query and get.
 * @param string $device The device, for get.
 *
 * @return array{0: string, 1: int}
 */
function ss_query_unix_partitions_collect($cmd = '', $field = '', $device = '') {
	$fields = array('device', 'mount', 'total', 'used', 'available', 'percent');

	if (!in_array($cmd, array('index', 'num_indexes', 'query', 'get'), true)) {
		return array('U', 1);
	}

	if (($cmd === 'query' || $cmd === 'get') && !in_array($field, $fields, true)) {
		return array('U', 1);
	}

	/* The '.' is allowed so that devices such as /dev/mapper/vg.root work */
	if ($cmd === 'get' && !preg_match('/^[\w\/.-]+$/', $device)) {
		return array('U', 1);
	}

	$dirs = array('/bin', '/usr/bin');
	$df   = poller_script_exec('df', array('-P', '-k', '-l'), 10, $dirs);

	if ($df === null || $df['timed_out']) {
		return array('U', 1);
	}

	$output = ss_query_unix_partitions_format(ss_query_unix_partitions_parse($df['stdout']), $cmd, $field, $device);

	if ($cmd === 'get' && $output === '') {
		return array('U', 1);
	}

	return array($output, 0);
}

/**
 * Reads the device-backed filesystems from 'df -P -k -l' output.
 *
 * @param string $df The output of df.
 *
 * @return array<int,array<string,string>> One row per filesystem.
 */
function ss_query_unix_partitions_parse($df) {
	$rows = array();

	foreach (explode("\n", $df) as $line) {
		/* /dev/hda2             20157744  18553884    579860  97% /var */
		if (preg_match('/^(\/\S+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)%\s+(\/\S*)$/', $line, $matches)) {
			$rows[] = array(
				'device'    => $matches[1],
				'mount'     => $matches[6],
				'total'     => $matches[2],
				'used'      => $matches[3],
				'available' => $matches[4],
				'percent'   => $matches[5]
			);
		}
	}

	return $rows;
}

/**
 * Formats parsed df rows for a data query command.
 *
 * @param array<int,array<string,string>> $rows   The filesystems.
 * @param string                          $cmd    The data query command.
 * @param string                          $field  The field, for query and get.
 * @param string                          $device The device, for get.
 *
 * @return string The output, or an empty string for get on an unknown device.
 */
function ss_query_unix_partitions_format($rows, $cmd, $field, $device) {
	$output = '';

	foreach ($rows as $row) {
		if ($cmd === 'index') {
			$output .= $row['device'] . "\n";
		} elseif ($cmd === 'query') {
			$output .= $row['device'] . ':' . $row[$field] . "\n";
		} elseif ($cmd === 'get' && $row['device'] === $device) {
			return $row[$field];
		}
	}

	if ($cmd === 'num_indexes') {
		return count($rows) . "\n";
	}

	return $output;
}
