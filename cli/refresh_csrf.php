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
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/utility.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter, 2);
		} else {
			$arg = $parameter;
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

/* issue warnings and start message if applicable */
print "NOTE: Updating the Cacti CSRF secret" . PHP_EOL;

if (!empty($config['path_csrf_secret'])) {
	$path_csrf_secret = cacti_csrf_external_secret_path($config['path_csrf_secret']);
	if (!cacti_csrf_external_path_is_safe($path_csrf_secret)) {
		print "FATAL: The configured CSRF secret must be outside the Cacti document root." . PHP_EOL;
		exit(1);
	}

	if (!csrf_write_secret_atomic($path_csrf_secret, csrf_generate_secret())) {
		print "FATAL: Unable to atomically write the configured external CSRF secret." . PHP_EOL;
		exit(1);
	}
} else {
	$new_secret = csrf_generate_secret();
	set_config_option('csrf_secret', $new_secret, true);
	$stored_secret = read_config_option('csrf_secret', true);
	if (!is_string($stored_secret) || !hash_equals($new_secret, $stored_secret)) {
		print "FATAL: Unable to verify the updated CSRF secret in the database." . PHP_EOL;
		exit(1);
	}
}

$legacy_path = $config['base_path'] . '/include/vendor/csrf/csrf-secret.php';
if (file_exists($legacy_path) && is_writable($legacy_path)) {
	@unlink($legacy_path);
}

print "NOTE: New CSRF secret installed." . PHP_EOL;
exit(0);

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Rebuild Poller Cache Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print PHP_EOL . "usage: refresh_csrf.php" . PHP_EOL . PHP_EOL;
	print "A utility to update the csrf_secret() key on a the Cacti system.  Updating" . PHP_EOL;
	print "this key should happen periodically during non-production hours as it can" . PHP_EOL;
	print "impact the user experience." . PHP_EOL . PHP_EOL;
}
