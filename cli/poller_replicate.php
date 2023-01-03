#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

require(__DIR__ . '/../include/cli_check.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');

if ($config['poller_id'] > 1) {
	print 'FATAL: This utility is designed for the main Data Collector only' . PHP_EOL;

	exit(1);
}

$poller_id = 0;
$class     = 'all';

/* performing a full sync can take a lot of memory and time */
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '900');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--poller':
			case '-P':
			case '-p':
				$poller_id = $value;

				break;
			case '--class':
			case '-C':
			case '-c':
				$class = $value;

				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();

				exit(0);
			case '--help':
			case '-H':
			case '-h':
				display_help();

				exit(0);

			default:
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();

				exit(1);
		}
	}
}

if (!preg_match('/(all|data|auth|settings)/', $class)) {
	print 'FATAL: The class ' . $class . ' is NOT valid!' . PHP_EOL;

	exit(1);
}

/* record the start time */
$start = microtime(true);

if ($poller_id < 0) {
	print 'FATAL: The poller needs to be greater than 0!' . PHP_EOL;

	exit(1);
}

if ($poller_id == 0) {
	$pollers = db_fetch_assoc('SELECT id
		FROM poller
		WHERE id > 1
		AND disabled=""');
} else {
	$pollers = db_fetch_assoc_prepared('SELECT id
		FROM poller
		WHERE id != 1
		AND id = ?
		AND disabled=""',
		array($poller_id));
}

if (cacti_sizeof($pollers)) {
	foreach ($pollers as $poller) {
		db_execute_prepared('UPDATE poller
			SET last_sync = NOW(), requires_sync=""
			WHERE id = ?',
			array($poller['id']));

		replicate_out($poller['id'], $class);

		cacti_log('STATS: Poller ID ' . $poller['id'] . ' fully Replicated', false, 'POLLER');
	}
} else {
	print 'FATAL: The poller specified ' . $poller_id . ' is either disabled, or does not exist!' . PHP_EOL;

	exit(1);
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Poller Full Sync Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help() {
	display_version();

	print "\nA utility to fully Synchronize Remote Data Collectors.\n\n";
	print "usage: poller_replicate.php [--poller=N] [--class=all|data|auth|settings]\n\n";
	print "Optional:\n";
	print "    --poller=N  The numeric id of the poller to replicate out.  Otherwise all\n";
	print "                pollers.  The default is all.\n";
	print "    --class=S   The class of data to replicate.  Includes all, data, auth\n";
	print "                settings.  The default is all.\n";
}
