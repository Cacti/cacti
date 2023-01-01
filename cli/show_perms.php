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

$output_json   = false;
$output_grants = false;

if (cacti_sizeof($parms)) {
	$shortopts = 'JjGgVvHh';

	$longopts = array(
		'grant',
		'json',
		'version',
		'help'
	);

	$options = getopt($shortopts, $longopts);

	foreach ($options as $arg => $value) {
		switch ($arg) {
			case 'json':
				$output_json = true;

				break;
			case 'grants':
			case 'G':
			case 'g':
				$output_grants = true;

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

$perms = db_get_permissions(true);

$count = 0;

if ($output_json) {
	print strtolower(json_encode($perms, JSON_PRETTY_PRINT));
} else {
	if ($output_grants) {
		$pad = str_repeat('-', 20);
		printf("\n %20s %-12s %20s\n\n", $pad, '   Grants', $pad);
		$grants = db_get_grants();

		foreach ($grants as $grant_row) {
			foreach ($grant_row as $grant) {
				print $grant . PHP_EOL;
			}
		}
		printf("\n %20s %-12s %20s\n\n", $pad, ' Permission', $pad);
	}

	foreach ($perms as $perm => $value) {
		$count++;
		printf('%25s %5s    ', $perm, $value ? 'Yes' : 'No');

		if ($count % 2 == 0) {
			print PHP_EOL;
		}
	}
}

if ($count % 2 == 1) {
	print PHP_EOL;
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Database Permission Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

function display_help() {
	display_version();

	print PHP_EOL . 'usage: show_perms.php [--json]' . PHP_EOL . PHP_EOL;
	print 'Cacti utility for auditing your Cacti database permissions.  This utility' . PHP_EOL;
	print 'will scan your Cacti database and report any permissons that it finds.' . PHP_EOL . PHP_EOL;
	print 'Options:' . PHP_EOL;
	print '    --json - Report on any permissions found in the database' . PHP_EOL;
}
