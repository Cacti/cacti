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
 | but WITHOUT ANY WARRANTY; without even the implied warranty or          |
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

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

// setup defaults
$quiet = false;
$debug = false;
$dev   = false;

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (str_contains($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--dev':
			case '-d':
				$dev = true;

				break;
			case '--debug':
				display_version();
				$debug = true;

				break;
			case '-q':
			case '--quiet':
				$quiet = true;

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
				display_help();
				printf('ERROR: Invalid Argument: (%s)' . PHP_EOL . PHP_EOL, $arg);

				exit(1);
		}
	}
}

if ($debug) {
	$tests = [
		'CACTI_VERSION'          => CACTI_VERSION,
		'CACTI_VERSION_FULL'     => CACTI_VERSION_FULL,
		'get_cacti_db_version()' => get_cacti_db_version(),
		'is_install_needed()'    => is_install_needed(),
	];

	foreach ($tests as $name => $value) {
		printf('%35s = (Rel %1s, Dev %1s) %s' . PHP_EOL, $name, is_cacti_release($value), is_cacti_develop($value), $value);
	}

	print PHP_EOL;

	$tests = [
		'CACTI_VERSION_TEXT'            => CACTI_VERSION_TEXT,
		'CACTI_VERSION_TEXT_FULL'       => CACTI_VERSION_TEXT_FULL,
		'CACTI_VERSION_TEXT_CLI'        => CACTI_VERSION_TEXT_CLI,
		'get_cacti_version_text(false)' => get_cacti_version_text(false),
		'get_cacti_version_text(true)'  => get_cacti_version_text(true),
	];

	foreach ($tests as $name => $value) {
		printf('%35s = %s' . PHP_EOL, $name, $value);
	}

	print PHP_EOL;

	$tests  = [
		'Fresh'       => 'new_install',
		//		'1.3.0'	   => '1.3.0',
		//		'Develop'	 => CACTI_VERSION_FULL,
		//		'1.3 Dev 569' => '1.3.0.99.1553092569.fab5112a',
		'1.3 Dev 328' => '1.3.0.99.1553092328.12f20874',
		//		'1.3 Beta 2'  => '1.3.0.2',
		//		'1.3 Beta 1'  => '1.3.0.1',
		//		'1.2 Dev 329' => '1.2.3.99.1553092329.0d39f3ad',
		//		'1.2.2'	   => '1.2.2',
		//		'1.2.0'	   => '1.2.0',
		//		'1.2 Beta 1'  => '1.2.0.1',
		'0.8.8h'	  => '0.8.8h',
		//		'0.8.8b'	  => '0.8.8b',
		//		'0.8.8'	   => '0.8.8',
	];

	$sources = $tests;

	$keys = [];

	foreach (array_keys($tests) as $index => $key) {
		$keys[$key] = chr($index + ord('a'));
	}

	$matrix = [];
	$dkeys  = $keys;

	foreach ($keys as $key) {
		foreach ($dkeys as $dkey) {
			$matrix[$key][$dkey] = ' ';
		}
	}

	foreach ($tests as $test => $version) {
		$key       = $keys[$test];
		$formatted = format_cacti_version($version);

		printf(
			'%15s (Rel %1s, Dev %1s) => %s (%s)' . PHP_EOL,
			$test,
			is_cacti_release($formatted),
			is_cacti_develop($formatted),
			$formatted,
			version_to_bits($formatted, false)
		);

		foreach ($sources as $name => $source) {
			$dkey                = $keys[$name];
			$matrix[$key][$dkey] = cacti_version_compare($formatted, $source, '<') ? '+' : '.';

			printf(
				'  =>  %15s = %-15s (%20s)' . PHP_EOL,
				$name,
				cacti_version_compare($formatted, $source, '<') ? 'Upgrade' : 'Not Required',
				version_to_bits($source, false)
			);
		}
		print PHP_EOL;
	}

	print '  ';

	foreach ($keys as $key) {
		print $key . ' ';
	}
	print PHP_EOL;

	foreach ($keys as $name => $key) {
		print $key;

		foreach ($dkeys as $dkey) {
			print ' ' . $matrix[$key][$dkey];
		}
		print ' ' . $name . PHP_EOL;
	}

	exit;
}

if ($dev) {
	print CACTI_VERSION . '.99.' . time() . PHP_EOL;
} elseif ($quiet) {
	print CACTI_VERSION_FULL . PHP_EOL;
} else {
	display_version();

	print PHP_EOL;
	print 'Full: ' . CACTI_VERSION_TEXT_FULL . PHP_EOL;
	print 'Code: ' . CACTI_VERSION_FULL . PHP_EOL;
	print 'Data: ' . format_cacti_version(get_cacti_db_version()) . PHP_EOL;
	print 'Dev.: ' . CACTI_VERSION . '.99.' . time() . PHP_EOL;
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	$version = get_cacti_cli_version();
	print "Cacti Version Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays help information
 *
 * @return void
 */
function display_help() : void {
	display_version();

	print PHP_EOL;
	print 'usage: version.php [option]' . PHP_EOL . PHP_EOL;
	print 'Options:' . PHP_EOL;
	print '     -d, --dev      show development upgrade version (generated)' . PHP_EOL;
	print '     -q, --quiet    no headers' . PHP_EOL;
	print '         --debug    show debug testing and matrix' . PHP_EOL . PHP_EOL;
}
