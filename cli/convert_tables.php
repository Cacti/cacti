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

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug;

$innodb      = false;
$utf8        = false;
$latin       = false;
$debug       = false;
$size        = 1000000;
$force       = false;
$rebuild     = false;
$dynamic     = false;
$table_name  = '';
$skip_tables = array();
$installer   = false;
$local       = false;

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
			case '-r':
			case '--rebuild':
				$rebuild = true;

				break;
			case '--dynamic':
				$dynamic = true;

				break;
			case '--local':
				$local = true;

				break;
			case '-s':
			case '--size':
				$size = $value;

				break;
			case '-t':
			case '--table':
				$table_name = $value;

				break;
			case '-i':
			case '--innodb':
				$innodb = true;

				break;
			case '-l':
			case '--latin1':
				$latin = true;
				break;
			case '-n':
			case '--skip-innodb':
				$skip_tables = explode(' ', $value);

				break;
			case '-f':
			case '--force':
				$force = true;

				break;
			case '-u':
			case '--utf8':
				$utf8 = true;

				break;
			case '--installer':
				$installer = true;
				require_once(__DIR__ . '../install/functions.php');

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

if (cacti_sizeof($skip_tables) && $table_name != '') {
	print_or_log($installer,  "ERROR: You can not specify a single table and skip tables at the same time.\n\n");
	display_help();

	exit;
}

if (!($innodb || $utf8 || $latin)) {
	print_or_log($installer,  "ERROR: Must select either UTF8, LATIN1 or InnoDB conversion.\n\n");
	display_help();

	exit;
}

if (!$local && $config['poller_id'] > 1) {
	db_switch_remote_to_main();

	print_or_log($installer, "NOTE: Repairing Tables for Main Database\n");
} else {
	print_or_log($installer, "NOTE: Repairing Tables for Local Database\n");
}

if (cacti_sizeof($skip_tables)) {
	foreach ($skip_tables as $table) {
		if (!db_table_exists($table)) {
			print_or_log($installer,  "ERROR: Skip Table $table does not Exist.  Can not continue.\n\n");
			display_help();

			exit;
		}
	}
}

$convert = $innodb ? 'InnoDB' : '';

if ($utf8) {
	$convert .= (strlen($convert) ? ' and ' : '') . ' utf8';
}

print_or_log($installer,  "Converting Database Tables to $convert with less than '$size' Records\n");

if ($innodb) {
	$engines = db_fetch_assoc('SHOW ENGINES');

	foreach ($engines as $engine) {
		if (strtolower($engine['Engine']) == 'innodb' && strtolower($engine['Support'] == 'off')) {
			print_or_log($installer,  "InnoDB Engine is not enabled\n");

			exit;
		}
	}

	$file_per_table = db_fetch_row("show global variables like 'innodb_file_per_table'");

	if (strtolower($file_per_table['Value']) != 'on') {
		print_or_log($installer,  'innodb_file_per_table not enabled');

		exit;
	}
}

if (strlen($table_name)) {
	$tables = db_fetch_assoc('SHOW TABLE STATUS LIKE \''.$table_name .'\'');
} else {
	$tables = db_fetch_assoc('SHOW TABLE STATUS');
}

if (cacti_sizeof($tables)) {
	foreach ($tables as $table) {
		$canConvert = $rebuild;
		$canInnoDB  = false;

		if (!$canConvert && $innodb) {
			$canConvert = $table['Engine'] == 'MyISAM';
			$canInnoDB  = true;
		}

		if (in_array($table['Name'], $skip_tables, true)) {
			$canInnoDB = false;
		}

		if (!$canConvert && $utf8) {
			$canConvert = $table['Collation'] != 'utf8mb4_unicode_ci';
		}

		if (!$canConvert && $latin) {
			$canConvert = $table['Collation'] != 'latin1';
		}

		if ($dynamic && $table['Row_format'] == 'Compact') {
			$canConvert = true;
		}

		if ($canConvert) {
			if ($table['Rows'] < $size || $force) {
				print_or_log($installer,  "Converting Table -> '" . $table['Name'] . "'");

				$sql = '';

				if ($utf8) {
					$sql .= ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
				} elseif ($latin) {
					$sql .= ' CONVERT TO CHARACTER SET latin1';
				}

				if ($innodb && $canInnoDB) {
					$sql .= (strlen($sql) ? ',' : '') . ' ENGINE=Innodb';
				}

				$status = db_execute('ALTER TABLE `' . $table['Name'] . '`' . ($dynamic ? ' ROW_FORMAT=Dynamic, ':'') . $sql);

				if ($status === false) {
					print_or_log($installer,  ' Failed' . PHP_EOL);

					record_log($installer, "FATAL: Conversion of Table '" . $table['Name'] . "' Failed.  Command: 'ALTER TABLE `" . $table['Name'] . "` $sql'");
				} else {
					print_or_log($installer,  ' Successful' . PHP_EOL);
				}
			} else {
				print_or_log($installer,  "Skipping Table -> '" . $table['Name'] . " too many rows '" . $table['Rows'] . "'" . PHP_EOL);
			}
		} else {
			print_or_log($installer,  "Skipping Table -> '" . $table['Name'] . "'" . PHP_EOL);
		}
	}
}

function print_or_log($installer, $text) {
	if ($installer) {
		log_install_and_file(POLLER_VERBOSITY_MEDIUM, rtrim($text), 'CONVERT', true);
	} else {
		print $text;
	}
}

function record_log($installer, $text) {
	if ($installer) {
		log_install_and_file(POLLER_VERBOSITY_MEDIUM, rtrim($text), 'CONVERT', true);
	} else {
		cacti_log($text, false, 'CONVERT');
	}
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Database Conversion Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help() {
	display_version();

	print "\nusage: convert_tables.php [--debug] [--innodb] [--utf8] [--latin1] [--table=N] [--size=N] [--rebuild] [--dynamic]\n\n";
	print "A utility to convert a Cacti Database from MyISAM to the InnoDB table format.\n";
	print "MEMORY tables are not converted to InnoDB in this process.\n\n";
	print "Required (one or more):\n";
	print "-i | --innodb  - Convert any MyISAM tables to InnoDB\n";
	print "-u | --utf8    - Convert any non-UTF8 tables to utf8mb4_unicode_ci\n";
	print "-l | --latin1  - Convert any non-latin1 tables to latin1\n\n";
	print "Optional:\n";
	print "-t | --table=S - The name of a single table to change\n";
	print "-n | --skip-innodb=\"table1 table2 ...\" - Skip converting tables to InnoDB\n";
	print "-s | --size=N  - The largest table size in records to convert.  Default is 1,000,000 rows.\n";
	print "-r | --rebuild - Will compress/optimize existing InnoDB tables if found\n";
	print "     --dynamic - Convert a table to Dynamic row format if available\n";
	print "     --local   - Perform the action on the Remote Data Collector if run from there\n";
	print "-f | --force   - Proceed with conversion regardless of table size\n\n";
	print "-d | --debug   - Display verbose output during execution\n\n";
}
