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

ini_set('output_buffering', 'Off');

require(__DIR__ . '/../include/cli_check.php');

require_once(CACTI_PATH_LIBRARY . '/utility.php');

ini_set('max_execution_time', '0');
ini_set('memory_limit', '-1');

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

$php_binary = read_config_option('path_php_binary');

cacti_log('WARNING: Deprecated script push_out_hosts.php. Please use rebuild_poller_cache.php.', false, 'PUSHOUT');

if (in_array('-v', $parms, true) || in_array('-V', $parms, true) || in_array('--version', $parms, true)) {
	// exception for github tests
	print 'Cacti Repopulate poller cache Tool, Version ' . CACTI_VERSION . ' ' . COPYRIGHT_YEARS . PHP_EOL;
} else {
	print 'WARNING: Deprecated script push_out_hosts.php. Please use rebuild_poller_cache.php.' . PHP_EOL;

	exit(push_out_rebuild_cache($parms, $php_binary));
}

function push_out_rebuild_cache(array $parms, mixed $php_binary) : int {
	if (!is_string($php_binary) || trim($php_binary) === '') {
		cacti_log('ERROR: Rejected an empty PHP binary.', false, 'SYSTEM');

		return 1;
	}

	if (str_starts_with(trim($php_binary), '-')) {
		cacti_log('ERROR: Rejected PHP binary starting with dash: ' . $php_binary, false, 'SYSTEM');

		return 1;
	}

	$args = array_merge([CACTI_PATH_CLI . '/rebuild_poller_cache.php'], $parms);

	$command = cacti_escapeshellarg($php_binary) . ' ' . implode(' ', array_map('cacti_escapeshellarg', $args));

	$exit_code = 0;

	passthru($command, $exit_code);

	return $exit_code;
}
