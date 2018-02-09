#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

$no_http_headers = true;

include('../include/global.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug;

$debug = false;
$size  = 300000;
$rebuild = false;

if (sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
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
			case '-s':
			case '--size':
				$size = $value;
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit;
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit;
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit;
		}
	}
}
echo "Converting All Non-Memory Cacti Database Tables to Innodb with Less than '$size' Records\n";

$engines = db_fetch_assoc('SHOW ENGINES');

foreach($engines as $engine) {
	if (strtolower($engine['Engine']) == 'innodb' && strtolower($engine['Support'] == 'off')) {
		echo "InnoDB Engine is not enabled\n";
		exit;
	}
}

$file_per_table = db_fetch_row("show global variables like 'innodb_file_per_table'");

if (strtolower($file_per_table['Value']) != 'on') {
	echo 'innodb_file_per_table not enabled';
	exit;
}

$tables = db_fetch_assoc('SHOW TABLE STATUS');

if (sizeof($tables)) {
	foreach($tables AS $table) {
		if ($table['Engine'] == 'MyISAM' || ($table['Engine'] == 'InnoDB' && $rebuild)) {
			if ($table['Rows'] < $size) {
				echo "Converting Table -> '" . $table['Name'] . "'";
				$status = db_execute('ALTER TABLE ' . $table['Name'] . ' ENGINE=Innodb');
				echo ($status == 0 ? ' Failed' : ' Successful') . "\n";
			} else {
				echo "Skipping Table -> '" . $table['Name'] . " too many rows '" . $table['Rows'] . "'\n";
			}
		} else {
			echo "Skipping Table ->'" . $table['Name'] . "\n";
		}
	}
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	echo "Cacti Database Conversion Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	echo "\nusage: convert_innodb.php [--debug] [--site=N] \n\n";
	echo "A utility to convert a Cacti Database from MyISAM to the InnoDB table format\n\n";
	echo "Optional:\n";
	echo "-s | --size=N  - The largest table size in records to convert\n";
	echo "-r | --rebuild - Will compress/optimize existing InnoDB tables if found\n";
	echo "-d | --debug   - Display verbose output during execution\n\n";
}
