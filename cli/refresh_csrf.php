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

// CSRF tokens are session-bound random values now, not HMACs over this
// file, so rotating it no longer invalidates anything outstanding.  Kept
// only so scripts and cron entries that still invoke it do not break; it
// will be removed in a future release once the pre-upgrade grace window
// (see CactiCsrfGuard::validateLegacyHmac()) is retired.
print 'NOTE: CSRF tokens are now session-bound and are not derived from a shared secret,' . PHP_EOL;
print '      so rotating the secret no longer invalidates anything.' . PHP_EOL;
print '      This file is retained only to validate tokens issued before the upgrade' . PHP_EOL;
print '      and will be removed in a future release.' . PHP_EOL;

exit(0);

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
	print 'Deprecated.  CSRF tokens are session-bound and are not derived from a' . PHP_EOL;
	print 'shared secret, so this utility no longer rotates anything.  Retained' . PHP_EOL;
	print 'only to avoid breaking scripts that still invoke it.' . PHP_EOL . PHP_EOL;
}
