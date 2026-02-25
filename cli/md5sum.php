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

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

// setup defaults
$confirm   = false;
$create    = false;
$quiet     = false;
$debug     = false;
$md5_file  = '';
$show_hash = false;
$base_dir  = __DIR__ . '/../';

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (str_contains($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-b':
			case '--basedir':
				$base_dir = trim($value);

				break;
			case '-c':
			case '--create':
				$create = true;

				break;
			case '--confirm':
				$confirm = true;

				break;
			case '-d':
			case '--debug':
				display_version();
				$debug = true;

				break;
			case '-q':
			case '--quiet':
				$quiet = true;

				break;
			case '-s':
			case '--show':
			case '--show-hash':
				$show_hash = true;

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
				if (strlen($md5_file)) {
					printf('ERROR: Invalid Argument: (%s)' . PHP_EOL . PHP_EOL, $arg);
					exit(1);
				}

				$md5_file = strlen($value) ? "$arg=$value" : "$arg";

				break;
		}
	}
}

if (substr($base_dir, -1) == '/') {
	$base_dir = substr($base_dir, 0, -1);
}
$base_dir = realpath($base_dir);

if (!file_exists($base_dir . '/include/config.php.dist')) {
	printf('ERROR: Path \'%s\' is not a Cacti root folder' . PHP_EOL, $base_dir);
	exit(2);
}

if (!strlen($md5_file)) {
	$md5_file = $base_dir . '/.md5sum';
}

$ignore_files = [
	// Ignore . and .. folders
	'\/\.$',
	'\/\.\.$',

	// Ignore .md5sum since it always changes
	'/.md5sum$',

	// Ignore temporary files
	'\.tmp$',
	'\.swp$',
	'\.log$',

	// Ignore .git folder since that is source control and might not exist
	'.git$',

	// Ignore .htaccess as web admins may modify
	'.htaccess$',

	// Ignore cache, log, plugins and rra since they are dynamic directories
	'/cache$',
	'/log$',
	'/plugins$',
	'/rra$'
];

$ignore_regex = '';

foreach ($ignore_files as $ignore) {
	$ignore_regex .= (strlen($ignore_regex) ? '|' : '') . '(' . $ignore . ')';
}
$ignore_regex = "~($ignore_regex)~";

$file_array = dirToArray('', $base_dir, $ignore_regex);

if ($create) {
	$output = '';

	foreach ($file_array as $filename => $md5) {
		$output .= "$md5  $filename" . PHP_EOL;
	}

	if (!$quiet) {
		print 'Writing ' . cacti_sizeof($file_array) . " entries to $md5_file" . PHP_EOL;
	}

	if (!$confirm && file_exists($md5_file)) {
		printf('ERROR: MD5 file \'%s\' exists, but not --confirm to overwrite', $md5_file);
		exit(3);
	}

	if (file_put_contents($md5_file,$output) === false) {
		printf('ERROR: Failed to write to MD5 file \'%s\'' . PHP_EOL, $md5_file);
		exit(4);
	}
} else {
	if (!file_exists($md5_file)) {
		printf('ERROR: MD5 file \'%s\' is missing, cannot verify' . PHP_EOL, $md5_file);
		exit(5);
	}

	$contents = file_get_contents($md5_file, false);

	if ($contents === false) {
		printf('ERROR: Failed to read from MD5 file \'%s\'' . PHP_EOL, $md5_file);
		exit(6);
	}

	$contents     = explode("\n",$contents);
	$line         = 0;
	$verify_array = [];

	foreach ($contents as $md5) {
		$line++;

		if (strlen($md5)) {
			if ($md5[32] != ' ') {
				printf('ERROR: Failed to parse line %d:' . PHP_EOL . '      %s' . PHP_EOL, $line, $md5);
				exit(7);
			}

			$filename                = trim(substr($md5,33));
			$verify_array[$filename] = substr($md5,0,32);
		}
	}

	$all_keys = array_unique(array_merge(array_keys($file_array),array_keys($verify_array)));

	foreach ($all_keys as $filename) {
		$hash_read = sprintf('%32s','Missing');

		if (array_key_exists($filename, $file_array)) {
			$hash_read = $file_array[$filename];
		}

		$hash_file = sprintf('%32s','Missing');

		if (array_key_exists($filename, $verify_array)) {
			$hash_file = $verify_array[$filename];
		}

		if ($hash_read != $hash_file) {
			if ($quiet) {
				exit(8);
			}

			print "$filename: FAILED" . PHP_EOL;

			if ($debug || $show_hash) {
				print "  Read: [$hash_read]" . PHP_EOL;
				print "  File: [$hash_file]" . PHP_EOL;
			}
		}
	}
}

function dirToArray(mixed $dir, string $base, string $ignore) : array {
	global $debug, $quiet;

	$result = [];

	$fulldir = $base;

	if ($dir != '') {
		$fulldir .= DIRECTORY_SEPARATOR . $dir;
	}

	$fulldir = realpath($fulldir);

	if (str_contains($fulldir, $base)) {
		if (is_dir($fulldir)) {
			$cdir = scandir($fulldir);
		} else {
			$cdir = [];
		}

		if (!$quiet && $debug) {
			print PHP_EOL . "Searching '$fulldir' ..." . PHP_EOL;
		}

		$dir_list = [];

		foreach ($cdir as $key => $value) {
			$fullpath = $fulldir . DIRECTORY_SEPARATOR . $value;
			$partpath = substr($fullpath,strlen($base));

			if (preg_match($ignore,$partpath) == 0) {
				if (is_dir($fullpath)) {
					$dir_list[] = $partpath;
				} else {
					$md5_sum = @md5_file($fullpath);

					if (!$quiet && $debug) {
						print "[$md5_sum] $value" . PHP_EOL;
					}
					$result[substr($partpath,1)] = $md5_sum;
				}
			} else {
				if (!$quiet && $debug) {
					print "[                         Ignored] $value" . PHP_EOL;
				}
			}
		}

		foreach ($dir_list as $partpath) {
			$result = array_merge($result, dirToArray($partpath, $base, $ignore));
		}
	} elseif (!$quiet && ($debug || !strlen($dir))) {
		$value = substr($dir,strlen(dirname($dir)) + 1);
		print "[           Outside Base, Ignored] $value" . PHP_EOL;
	}

	return $result;
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	$version = get_cacti_cli_version();
	print "Cacti md5sum Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_version - displays help information
 *
 * @return void
 */
function display_help() : void {
	display_version();

	print PHP_EOL;
	print 'usage: md5sum.php [option] [filename]' . PHP_EOL . PHP_EOL;

	print 'Options:' . PHP_EOL;
	print '--create     When specified used creates a file containing the md5 hash' . PHP_EOL;
	print '             followed by the name. Otherwise, the file is verified' . PHP_EOL;
	print '--debug      logs additional output to the screen to aid in diagnosing' . PHP_EOL;
	print '             potential issues' . PHP_EOL . PHP_EOL;
	print '--basedir    When specified, sets the base directory to search from. If' . PHP_EOL;
	print '             not specified, defaults to the directory above this script' . PHP_EOL;
	print '--quiet      When specified, quiet mode only returns an exit value that' . PHP_EOL;
	print '             corresponds to the point of exit.  Suppresses debug option' . PHP_EOL;
	print '--show       When specified, adds extra output to the verify mode which' . PHP_EOL;
	print '             shows both the stored and computed hash value that failed' . PHP_EOL;
	print '             to match' . PHP_EOL . PHP_EOL;

	print 'When no filename is passed, .md5sum is assumed. Only one filename allowed' . PHP_EOL;
}
