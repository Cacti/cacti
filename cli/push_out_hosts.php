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
require_once(CACTI_PATH_LIBRARY . '/CactiProcess.php');

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

	// Pass through with no shell interpretation; deprecated wrapper just
	// forwards argv to the real script.
	$argv_forward = array_merge([$php_binary, CACTI_PATH_CLI . '/rebuild_poller_cache.php'], $parms);

	// propagates child exit status, unlike the prior passthru() approach
	$exit = CactiProcess::runStreaming(
		$argv_forward,
		['timeout' => null, 'expected_exit_codes' => range(0, 255)],
		static function ($line) {
			print $line . PHP_EOL;
		},
		static function ($chunk) {
			fwrite(STDERR, $chunk);
		}
	);

	exit($exit);
}
