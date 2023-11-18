#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

ini_set('zlib.output_compression', '0');

require(__DIR__ . '/../include/cli_check.php');
require_once(CACTI_PATH_LIBRARY . '/import.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');
require_once(CACTI_PATH_LIBRARY . '/template.php');

if ($config['poller_id'] > 1) {
	print 'FATAL: This utility is designed for the main Data Collector only' . PHP_EOL;

	exit(1);
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $preview_only;

if (cacti_sizeof($parms)) {
	$filename        = '';
	$use_profile     = false;
	$remove_orphans  = false;
	$replace_svalues = false;
	$preview_only    = false;
	$info_only       = false;
	$profile_id      = '';

	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--filename':
				$filename = trim($value);

				break;
			case '--use-profile':
				$use_profile = true;

				break;
			case '--remove-orphans':
				$remove_orphans = true;

				break;
			case '--replace-svalues':
				$replace_svalues = true;

				break;
			case '--profile-id':
				$profile_id = trim($value);

				break;
			case '--preview':
				$preview_only = true;

				break;
			case '--info':
				$info_only = true;

				break;
			case '--help':
			case '-H':
			case '-h':
				display_help();

				exit(0);
			case '--version':
			case '-V':
			case '-v':
				display_version();

				exit(0);

			default:
				print "ERROR: Invalid Argument: ($arg)" . PHP_EOL . PHP_EOL;

				exit(1);
		}
	}

	if ($info_only) {
		if ($filename != '' && is_readable($filename) && file_exists($filename) && !is_dir($filename)) {
			$result = import_package($filename, $profile_id, $remove_orphans, $replace_svalues, $preview_only, $info_only);

			if ($result !== false && cacti_sizeof($result)) {
				print json_encode($result);

				exit(0);
			} else {
				print 'FATAL: Error processing package file.  Info not returned' . PHP_EOL;

				exit(1);
			}
		} else {
			print "ERROR: file $filename is not readable, is a directory or does not exist" . PHP_EOL . PHP_EOL;

			exit(1);
		}
	}

	if ($profile_id != '') {
		$exists = db_fetch_cell_prepared('SELECT id
			FROM data_source_profiles
			WHERE id = ?',
			array($profile_id));

		if (empty($exists)) {
			print 'FATAL: Data Source Profile ID ' . $profile_id . ' does not exist!' . PHP_EOL;

			exit(1);
		}
	} else {
		$profile_id = db_fetch_cell('SELECT id FROM data_source_profiles ORDER BY `default` DESC LIMIT 1');
	}

	if ($filename != '') {
		if (file_exists($filename) && is_readable($filename) && file_exists($filename) && !is_dir($filename)) {
			$fp   = fopen($filename, 'r');
			$data = fread($fp, filesize($filename));

			fclose($fp);

			print 'Read ' . strlen($data) . ' bytes of Package data' . PHP_EOL;

			$result = import_package($filename, $profile_id, $remove_orphans, $replace_svalues, $preview_only);

			if ($result !== false) {
				$debug_data = $result[0];
				$filestatus = $result[1];

				import_display_results($debug_data, $filestatus, false, $preview_only);
			} else {
				print "ERROR: file $filename import process failed due to errors with the XML file" . PHP_EOL . PHP_EOL;

				exit(1);
			}
		} else {
			print "ERROR: file $filename is not readable, is a directory or does not exist" . PHP_EOL . PHP_EOL;

			exit(1);
		}
	} else {
		print 'ERROR: no filename specified' . PHP_EOL . PHP_EOL;
		display_help();

		exit(1);
	}
} else {
	print 'ERROR: no parameters given' . PHP_EOL . PHP_EOL;
	display_help();

	exit(1);
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Import Template Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

function display_help() {
	display_version();

	print PHP_EOL . 'usage: import_package.php --filename=[filename] [--info] [--remove-orphans] [--replace-svalues] [--with-profile] [--profile-id=N' . PHP_EOL . PHP_EOL;
	print 'A utility to allow signed Cacti Packages to be imported from the command line.' . PHP_EOL . PHP_EOL;
	print 'Required:' . PHP_EOL;
	print '    --filename              The name of the gzipped package file to import' . PHP_EOL . PHP_EOL;
	print 'Optional:' . PHP_EOL;
	print '    --info            Output the info section of the package, do not import' . PHP_EOL;
	print '    --preview         Preview the Template Import, do not import' . PHP_EOL;
	print '    --with-profile    Use the default system Data Source Profile' . PHP_EOL;
	print '    --profile-id=N    Use the specific profile id when importing' . PHP_EOL;
	print '    --remove-orphans  If importing a new version of the template, old' . PHP_EOL;
	print '                      elements will be removed, if they do not exist' . PHP_EOL;
	print '                      in the new version of the template.' . PHP_EOL;
	print '    --replace-svalues If replacing an old version of either a Device' . PHP_EOL;
	print '                      Template with Data Queries or a Data Query, when' . PHP_EOL;
	print '                      you select this option, all Data Query Suggested' . PHP_EOL;
	print '                      Value Patterns will be replaced with that of the' . PHP_EOL;
	print '                      Package Data Queries.' . PHP_EOL . PHP_EOL;
}
