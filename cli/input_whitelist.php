#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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

// do NOT run this script through a web browser
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

// we are not talking to the browser
$no_http_headers = true;

// start initialization section
include(dirname(__FILE__) . '/../include/global.php');
include(dirname(__FILE__) . '/../lib/utility.php');
include(dirname(__FILE__) . '/../lib/template.php');

$audit  = false;
$update = false;
$push   = false;

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

if (sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--audit':
			case '-A':
				$audit = true;
				break;
			case '--update':
			case '-U':
				$update = true;
				break;
			case '--push':
			case '-P':
				$push = true;
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
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();
				exit;
		}
	}
}

if (!isset($config['input_whitelist'])) {
	print 'NOTICE: Data Input Whitelist file not defined in config.php.' . PHP_EOL;
	exit(0);
}

if ($audit) {
	if (isset($config['input_whitelist']) && !file_exists($config['input_whitelist'])) {
		print 'ERROR: Data Input Whitelist file \'' . $config['input_whitelist'] . '\' does not exist.  Please run with the \'--update\' option.' . PHP_EOL;
		exit(1);
	}

	$input = json_decode(file_get_contents($config['input_whitelist']), true);

	$totals = 0;
	$items = sizeof($input);

	if ($items) {
		print 'Data Input Methods Whitelist Verification' . PHP_EOL . PHP_EOL;
		print '------------------------------------------------------------------------------------------------------------' . PHP_EOL;

		foreach($input as $hash => $input_string) {
			$aud = verify_data_input($hash, $input_string);
			if ($aud['status'] == true) {
				print 'ID: ' . $aud['id'] . ', Name: ' . $aud['name'] . ', Status: ' . 'Success' . PHP_EOL;
				print '------------------------------------------------------------------------------------------------------------' . PHP_EOL;
				print 'Command:   ' . $aud['input_string'] . PHP_EOL . 'Whitelist: ' . $input_string . PHP_EOL;
			} else {
				print 'ID: ' . $aud['id'] . ', Name: ' . $aud['name'] . ', Status: ' . 'Failed' . PHP_EOL;
				print '------------------------------------------------------------------------------------------------------------' . PHP_EOL;
				print 'Command:   ' . $aud['input_string'] . PHP_EOL . 'Whitelist: ' . $input_string . PHP_EOL;

				$totals++;
			}

			print '------------------------------------------------------------------------------------------------------------' . PHP_EOL . PHP_EOL;
		}

		if ($totals) {
			print 'ERROR: ' . $totals . ' audits failed out of a total of ' . $items . ' Data Input Methods' . PHP_EOL;
		} else {
			print 'SUCCESS: Audits successfull for total of ' . $items . ' Data Input Methods' . PHP_EOL;
		}
	}
} elseif ($update) {
	if (!is_writable(dirname($config['input_whitelist']))) {
		print 'ERROR: Data Input whitelist file \'' . $config['input_whitelist'] . '\' not writeable.' . PHP_EOL;
		exit(1);
	}

	$input_db = db_fetch_assoc('SELECT id, name, hash, input_string
		FROM data_input
		WHERE input_string != ""');

	if (file_exists($config['input_whitelist'])) {
		$input_ws = json_decode(file_get_contents($config['input_whitelist']), true);
	} else {
		$input_ws = array();
	}

	if (sizeof($input_db)) {
		// format data for easier consumption
		$input = array();
		foreach ($input_db as $value) {
			if ($push && isset($input_ws[$value['hash']])) {
				if ($value['input_string'] != $input_ws[$value['hash']]) {
					print 'NOTE: Pushing Out Data Input Method: ' . $value['name'] . ' (' . $value['id'] . ')' . PHP_EOL;
					push_out_data_input_method($value['id']);
				}
			}
			$input[$value['hash']] = $value['input_string'];
		}

		file_put_contents($config['input_whitelist'], json_encode($input));
		print 'SUCCESS: Data Input Whitelist file \'' . $config['input_whitelist'] . '\' successfully updated.' . PHP_EOL;
	} else {
		print 'ERROR: No Data Input records found.' . PHP_EOL;
		exit(1);
	}
} else {
	display_help();
}

exit(0);

/*
 * display_version - displays version information
 */
function display_version() {
	$version = get_cacti_version();
	print "Cacti Data Input Whitelist Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/*
 * display_help - displays the usage of the function
 */
function display_help () {
	display_version();

	print PHP_EOL . "usage: input_whitelist.php [--audit | --update [--push]]" . PHP_EOL . PHP_EOL;

	print "A utility audit and update the Data Input whitelist status and" . PHP_EOL;
	print "Data Input protection file." . PHP_EOL . PHP_EOL;

	print "Optional:" . PHP_EOL;
	print "    --audit       Audit but do not update the whitelist file." . PHP_EOL;
	print "    --update      Update the whitelist file with latest information." . PHP_EOL;
	print "    --push        If any input strings are being updated to new values," . PHP_EOL;
	print "                  push out the Data Input Methods with new input strings." . PHP_EOL . PHP_EOL;
}
