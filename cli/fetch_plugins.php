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
require_once(CACTI_PATH_LIBRARY . '/plugins.php');

ini_set('max_execution_time', '0');
ini_set('memory_limit', '-1');

chdir('..');

if (POLLER_ID > 1) {
	print 'FATAL: This utility is designed for the main Data Collector only' . PHP_EOL;

	exit(1);
}

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

$debug = false;
$force = false;

if (cacti_sizeof($parms)) {
	$shortopts = 'VvHh';

	$longopts = [
		// Options without a value
		'debug',
		'force',
		'version',
		'help',
	];

	$options = getopt($shortopts, $longopts);

	foreach ($options as $arg => $value) {
		switch($arg) {
			case 'debug':
				$debug = true;

				break;
			case 'force':
				$force = true;

				break;
			case 'version':
			case 'V':
			case 'v':
				display_version();

				exit(0);
			case 'help':
			case 'H':
			case 'h':
				display_help();

				exit(0);

			default:
				print "ERROR: Invalid Argument: ($arg)" . PHP_EOL . PHP_EOL;
				display_help();

				exit(1);
		}
	}
}

$fstart = microtime(true);

$php_binary = read_config_option('path_php_binary');

if (!$force) {
	if (!register_process_start('pfetch', 'master', 0, 900)) {
		print 'WARNING: Another plugin fetch process is running' . PHP_EOL;

		exit(0);
	}
} else {
	debug('Fetch Process is being forced');
}

debug('About to fetch Cacti Plugins from GitHub');

plugin_fetch_latest_plugins();

if (!$force) {
	unregister_process('pfetch', 'master', 0);
}

$fend = microtime(true);

debug(sprintf('Fetch Process has Completed in %0.2f seconds', $fend - $fstart));

exit(0);

function debug(string $message) : void {
	global $debug;

	if ($debug) {
		print('DEBUG: ' . trim($message) . PHP_EOL);
	}
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	$version = get_cacti_cli_version();
	print "Cacti Fetch Latest Plugins Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays the usage of the function
 *
 * @return void
 */
function display_help() : void {
	display_version();

	print PHP_EOL;
	print 'usage: fetch_plugins.php [ --debug ]' . PHP_EOL . PHP_EOL;
	print 'A utility gathers the latest official plugins from the Cacti Group GitHub' . PHP_EOL;
	print 'site and prepares them from loading and install' . PHP_EOL;
}
