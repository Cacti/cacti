#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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

$fail_msg = array();
define_exit('EXIT_UNKNOWN',-1, "ERROR: Failed due to unknown reason\n");
define_exit('EXIT_NORMAL',  0, "");
define_exit('EXIT_ARGERR',  1, "ERROR: Invalid Argument: (%s)\n\n");

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

/* setup defaults */
$quiet = false;
$debug = false;
$dev   = false;

if (cacti_sizeof($parms)) {

	foreach($parms as $parameter) {

	        if (strpos($parameter, '=')) {
	                list($arg, $value) = explode('=', $parameter);
	        } else {
	                $arg = $parameter;
	                $value = '';
	        }

	        switch ($arg) {

			case '--dev':
		        case '-d':
				$dev=true;
				break;
		        case '--debug':
		                display_version();
		                $debug=true;
		                break;

			case '-q':
			case '--quiet':
				$quiet=true;
				break;

		        case '--version':
		        case '-V':
		        case '-v':
				display_version();
				fail(EXIT_NORMAL);

		        case '--help':
		        case '-H':
		        case '-h':
				display_help();
				fail(EXIT_NORMAL);

		        default:
				if (strlen($md5_file)) {
					fail(EXIT_ARGERR,$arg,true);
				}
				$md5_file=strlen($value)?"$arg=$value":"$arg";
				break;
		}
	}
}

if ($debug) {
	$tests = array(
		'CACTI_VERSION' => CACTI_VERSION,
		'CACTI_VERSION_FULL' => CACTI_VERSION_FULL,
		'get_cacti_db_version()' => get_cacti_db_version(),
	);

	foreach ($tests as $name => $value) {
		printf ("%35s = (Rel %1s, Dev %1s) %s\n", $name, is_cacti_release($value), is_cacti_develop($value), $value);
	}

	print PHP_EOL;

	$tests = array(
		'CACTI_VERSION_TEXT' => CACTI_VERSION_TEXT,
		'CACTI_VERSION_TEXT_FULL' => CACTI_VERSION_TEXT_FULL,
		'CACTI_VERSION_TEXT_CLI' => CACTI_VERSION_TEXT_CLI,
		'get_cacti_version_text(false)' => get_cacti_version_text(false),
		'get_cacti_version_text(true)' => get_cacti_version_text(true),
	);

	foreach ($tests as $name => $value) {
		printf ("%35s = %s\n", $name, $value);
	}

	print PHP_EOL;

	$tests  = array(
		'1.3.0'       => '1.3.0',
		'Develop'     => CACTI_VERSION_FULL,
		'1.3 Dev 569' => '1.3.0.99.1553092569.fab5112a',
		'1.3 Dev 328' => '1.3.0.99.1553092328.12f20874',
		'1.3 Beta 2'  => '1.3.0.2',
		'1.3 Beta 1'  => '1.3.0.1',
		'1.2 Dev 329' => '1.2.3.99.1553092329.0d39f3ad',
		'1.2.2'       => '1.2.2',
		'1.2.0'       => '1.2.0',
		'1.2 Beta 1'  => '1.2.0.1',
		'0.8.8h'      => '0.8.8h',
		'0.8.8b'      => '0.8.8b',
		'0.8.8'       => '0.8.8',
	);

	$sources = $tests;

	$keys = array();
	foreach (array_keys($tests) as $index => $key) {
		$keys[$key] = chr($index + ord('a'));
	}

	$matrix = array();
	$dkeys = $keys;
	foreach ($keys as $key) {
		foreach ($dkeys as $dkey) {
			$matrix[$key][$dkey] = ' ';
		}
	}

	foreach ($tests as $test => $version) {
		$key = $keys[$test];
		$formatted = format_cacti_version($version);

		printf ("%15s (Rel %1s, Dev %1s) => %s\n",
			$test, is_cacti_release($formatted), is_cacti_develop($formatted), version_to_decimal($version, 8, false));

		foreach ($sources as $name => $source) {
			$dkey = $keys[$name];
			$matrix[$key][$dkey] = cacti_version_compare($formatted, $source, '<') ? '+' : '.';

			printf ("  =>  %15s = %-15s (%20s)\n",
				$name, cacti_version_compare($formatted, $source, '<') ? 'Upgrade' : 'Not Required',
				version_to_decimal($source, 8, false));
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
	echo PHP_EOL;
	print "Full: " . CACTI_VERSION_TEXT_FULL . PHP_EOL;
	print "Code: " . CACTI_VERSION_FULL . PHP_EOL;
	print "Data: " . format_cacti_version(get_cacti_db_version()) . PHP_EOL;
	print "Dev.: " . CACTI_VERSION . '.99.' . time() . PHP_EOL;
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Version Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	print "\nusage: version.php [option]\n";
	print "\nOptions:\n";
	print "     -d, --dev        show development upgrade version (generated)\n";
	print "     -q, --quiet      no headers\n";
	print "         --debug      show debug testing and matrix\n\n";
}

function fail($exit_value,$args = array(),$display_help = 0) {
	global $quiet,$fail_msg;

	if (!$quiet) {
		if (!isset($args)) {
			$args = array();
		} else if (!is_array($args)) {
			$args = array($args);
		}

		if (!array_key_exists($exit_value,$fail_msg)) {
			$format = $fail_msg[EXIT_UNKNOWN];
		} else {
			$format = $fail_msg[$exit_value];
		}
		call_user_func_array('printf', array_merge((array)$format, $args));

		if ($display_help) {
			display_help();
		}
	}

	exit($exit_value);
}

function define_exit($name, $value, $text) {
	global $fail_msg;

	if (!isset($fail_msg)) {
		$fail_msg = array();
	}

	define($name,$value);
	$fail_msg[$name] = $text;
	$fail_msg[$value] = $text;
}
