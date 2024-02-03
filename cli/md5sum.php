#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

$fail_msg = array();
define_exit('EXIT_UNKNOWN',-1, "ERROR: Failed due to unknown reason\n");
define_exit('EXIT_NORMAL',  0, '');
define_exit('EXIT_ARGERR',  1, "ERROR: Invalid Argument: (%s)\n\n");
define_exit('EXIT_NOTDIR',  2, "ERROR: Path '%s' is not a Cacti root folder\n");
define_exit('EXIT_MD5OVR',  3, "ERROR: MD5 file '%s' exists, but not --confirm to overwrite\n");
define_exit('EXIT_MD5WRI',  4, "ERROR: Failed to write to MD5 file '%s'\n");
define_exit('EXIT_MD5MIS',  5, "ERROR: MD5 file '%s' is missing, cannot verify\n");
define_exit('EXIT_MD5CON',  6, "ERROR: Failed to read from MD5 file '%s'\n");
define_exit('EXIT_MD5LIN',  7, "ERROR: Failed to parse line %d:\n      %s\n");

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

/* setup defaults */
$confirm   = false;
$create    = false;
$quiet     = false;
$debug     = false;
$md5_file  = '';
$show_hash = false;
$base_dir  = __DIR__.'/../';

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-b':
			case '--basedir':
				$base_dir=trim($value);

				break;
			case '-c':
			case '--create':
				$create=true;

				break;
			case '--confirm':
				$confirm=true;

				break;
			case '-d':
			case '--debug':
				display_version();
				$debug = true;

				break;
			case '-q':
			case '--quiet':
				$quiet=true;

				break;
			case '-s':
			case '--show':
			case '--show-hash':
				$show_hash=true;

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

if (substr($base_dir, -1) == '/') {
	$base_dir = substr($base_dir, 0, -1);
}
$base_dir = realpath($base_dir);

if (!file_exists($base_dir.'/include/config.php.dist')) {
	fail(EXIT_NOTDIR,$base_dir);
}

if (!strlen($md5_file)) {
	$md5_file = $base_dir . '/.md5sum';
}

$ignore_files = array(
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
);

$ignore_regex='';

foreach ($ignore_files as $ignore) {
	$ignore_regex .= (strlen($ignore_regex)?'|':'').'('.$ignore.')';
}
$ignore_regex="~($ignore_regex)~";

$file_array = dirToArray('',$base_dir,$ignore_regex);

if ($create) {
	$output = '';

	foreach ($file_array as $filename => $md5) {
		$output .= "$md5  $filename\n";
	}

	if (!$quiet) {
		print 'Writing '.cacti_sizeof($file_array)." entries to $md5_file\n";
	}

	if (!$confirm && file_exists($md5_file)) {
		fail(EXIT_MD5OVR,$md5_file);
	}

	if (file_put_contents($md5_file,$output) === false) {
		fail(EXIT_MD5WRI,$md5_file);
	}
} else {
	if (!file_exists($md5_file)) {
		fail(EXIT_MD5MIS,$md5_file);
	}

	$contents = file_get_contents($md5_file, false);

	if ($contents === false) {
		fail(EXIT_MD5CON,$md5_file);
	}
	$contents     = explode("\n",$contents);
	$line         = 0;
	$verify_array = array();

	foreach ($contents as $md5) {
		$line++;

		if (strlen($md5)) {
			if ($md5[32] != ' ') {
				fail(EXIT_MD5LIN,array($line,$md5));
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
				fail(EXIT_MD5ERR);
			}

			print "$filename: FAILED\n";

			if ($debug || $show_hash) {
				print "  Read: [$hash_read]\n";
				print "  File: [$hash_file]\n";
			}
		}
	}
}

function dirToArray($dir,$base,$ignore) {
	global $debug,$quiet;

	$result = array();

	$fulldir = $base;

	if (isset($dir) && strlen($dir)) {
		$fulldir .= DIRECTORY_SEPARATOR.$dir;
	}
	$fulldir = realpath($fulldir);

	if (strpos($fulldir,$base) !== false) {
		if (is_dir($fulldir)) {
			$cdir = scandir($fulldir);
		} else {
			$cdir = array();
		}

		if (!$quiet && $debug) {
			print "\nSearching '$fulldir' ... \n";
		}

		$dir_list = array();

		foreach ($cdir as $key => $value) {
			$fullpath = $fulldir.DIRECTORY_SEPARATOR.$value;
			$partpath = substr($fullpath,strlen($base));

			if (preg_match($ignore,$partpath) == 0) {
				if (is_dir($fullpath)) {
					$dir_list[] = $partpath;
				} else {
					$md5_sum = @md5_file($fullpath);

					if (!$quiet && $debug) {
						print "[$md5_sum] $value\n";
					}
					$result[substr($partpath,1)] = $md5_sum;
				}
			} else {
				if (!$quiet && $debug) {
					print "[                         Ignored] $value\n";
				}
			}
		}

		foreach ($dir_list as $partpath) {
			$result = array_merge($result,dirToArray($partpath, $base, $ignore));
		}
	} elseif (!$quiet && ($debug || !strlen($dir))) {
		$value = substr($dir,strlen(dirname($dir)) + 1);
		print "[           Outside Base, Ignored] $value\n";
	}

	return $result;
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti md5sum Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	print "\nusage: md5sum.php [option] [filename]\n";
	print "\nOptions:\n";
	print "     -c          When specified used creates a file containing the md5 hash\n";
	print "    --create     followed by the name. Otherwise, the file is verified\n\n";
	print "     -d          logs additional output to the screen to aid in diagnosing\n";
	print "    --debug      potential issues\n\n";
	print "     -b          When specified, sets the base directory to search from. If\n";
	print "    --basedir    not specified, defaults to the directory above this script\n\n";
	print "     -q          When specified, quiet mode only returns an exit value that\n";
	print "    --quiet      corresponds to the point of exit.  Suppresses debug option\n\n";
	print "     -s          When specified, adds extra output to the verify mode which\n";
	print "    --show       shows both the stored and computed hash value that failed\n";
	print "    --show_hash  to match\n\n";
	print "\nWhen no filename is passed, .md5sum is assumed. Only one filename allowed\n";
}

function fail($exit_value,$args = array(),$display_help = 0) {
	global $quiet,$fail_msg;

	if (!$quiet) {
		if (!isset($args)) {
			$args = array();
		} elseif (!is_array($args)) {
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
	$fail_msg[$name]  = $text;
	$fail_msg[$value] = $text;
}
