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

require(__DIR__ . '/../include/cli_check.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (str_contains($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
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
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();

				exit(1);
		}
	}
}

// issue warnings and start message if applicable
print 'NOTE: Updating csrf_secret file with new information' . PHP_EOL;

if (!file_exists(CACTI_CSRF_SECRET)) {
	print 'WARNING: csrf_secret.php file does not exist!' . PHP_EOL;
} elseif (!is_writable(CACTI_CSRF_SECRET)) {
	print 'FATAL: unable to unlink csrf_secret.php!' . PHP_EOL;

	exit(1);
} else {
	print 'NOTE: Removing old csrf_secret.php file.' . PHP_EOL;
	unlink(CACTI_CSRF_SECRET);
}

$new_secret = csrf_generate_secret();

if (csrf_writable(CACTI_CSRF_SECRET)) {
	umask(0027);
	$fh = fopen(CACTI_CSRF_SECRET, 'w');
	fwrite($fh, '<?php $secret = "' . $new_secret . '";' . PHP_EOL);
	fclose($fh);
	print 'NOTE: New csrf_secret.php file written.' . PHP_EOL;

	exit(0);
} else {
	print 'FATAL: Unable to write new csrf_secret.php file.' . PHP_EOL;

	exit(1);
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	$version = get_cacti_cli_version();
	print "Cacti CSRF File Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays the usage of the function
 *
 * @return void
 */
function display_help() : void {
	display_version();

	print PHP_EOL . 'usage: refresh_csrf.php' . PHP_EOL . PHP_EOL;
	print 'A utility to update the csrf_secret() key on a the Cacti system.  Updating' . PHP_EOL;
	print 'this key should happen periodically during non-production hours as it can' . PHP_EOL;
	print 'impact the user experience.' . PHP_EOL . PHP_EOL;
}
