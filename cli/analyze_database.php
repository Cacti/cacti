#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug;

$debug = false;
$local = false;
$form  = '';
$start = time();

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-d':
			case '--debug':
				$debug = true;

				break;
			case '--local':
				$local = true;

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

print "NOTE: Analyzing All Cacti Database Tables\n";

if (!$local && $config['poller_id'] > 1) {
	db_switch_remote_to_main();

	print 'NOTE: Repairing Tables for Main Database' . PHP_EOL;
} else {
	print 'NOTE: Repairing Tables for Local Database' . PHP_EOL;
}

$tables = db_fetch_assoc('SHOW TABLES FROM `' . $database_default . '`');

if (cacti_sizeof($tables)) {
	foreach ($tables as $table) {
		if (db_binlog_enabled()) {
			print "NOTE: Analyzing Table -> '" . $table['Tables_in_' . $database_default] . "' without writing to the binlog";
			$status = db_execute('ANALYZE TABLE NO_WRITE_TO_BINLOG ' . $table['Tables_in_' . $database_default] . $form);
		} else {
			print "NOTE: Analyzing Table -> '" . $table['Tables_in_' . $database_default] . "'";
			$status = db_execute('ANALYZE TABLE ' . $table['Tables_in_' . $database_default] . $form);
		}

		print($status == 0 ? ' Failed' : ' Successful') . "\n";
	}

	cacti_log('ANALYSIS STATS: Analyzing Cacti Tables Complete.  Total time ' . (time() - $start) . ' seconds.', false, 'SYSTEM');
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Analyze Database Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help() {
	display_version();

	print "\nusage: analyze_database.php [-d|--debug]\n\n";
	print "A utility to recalculate the cardinality of indexes within the Cacti database.\n";
	print "It's important to periodically run this utility especially on larger systems.\n\n";
	print "Optional:\n";
	print "     --local   - Perform the action on the Remote Data Collector if run from there\n";
	print "-d | --debug   - Display verbose output during execution\n\n";
}
