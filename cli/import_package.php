#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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
require_once($config['base_path'] . '/lib/import.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/utility.php');
require_once($config['base_path'] . '/lib/template.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $preview_only;

if (cacti_sizeof($parms)) {
	$filename       = '';
	$use_profile    = false;
	$remove_orphans = false;
	$preview_only   = false;
	$info_only      = false;
	$profile_id     = '';

	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
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
			case '--profile-id':
				$profile_id = trim($value);

				break;
			case '--preview':
				$preview_only = true;

				break;
			case '--info-only':
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
				print "ERROR: Invalid Argument: ($arg)\n\n";
				exit(1);
		}
	}

	if ($info_only) {
		if ($filename != '' && is_readable($filename)) {
			$result = import_package($filename, $profile_id, $remove_orphans, $preview_only, $info_only);

			if ($result !== false && cacti_sizeof($result)) {
				print json_encode($result);
				exit(0);
			} else {
				print "FATAL: Error processing package file.  Info not returned\n";
				exit(1);
			}
		}
	}

	if ($profile_id != '') {
		$exists = db_fetch_cell_prepared('SELECT id
			FROM data_source_profiles
			WHERE id = ?',
			array($profile_id));

		if (empty($exists)) {
			print "FATAL: Data Source Profile ID " . $profile_id . " does not exist!\n";
			exit(1);
		}
	} else {
		$profile_id = db_fetch_cell('SELECT id FROM data_source_profiles ORDER BY `default` DESC LIMIT 1');
	}

	if ($filename != '') {
		if (file_exists($filename) && is_readable($filename)) {
			$fp = fopen($filename,'r');
			$data = fread($fp,filesize($filename));
			fclose($fp);

			print 'Read ' . strlen($data) . " bytes of Package data\n";

			$result = import_package($filename, $profile_id, $remove_orphans, $preview_only);

			if ($result !== false) {
				$debug_data = $result[0];
				$filestatus = $result[1];

				import_display_results($debug_data, $filestatus, false, $preview_only);
			} else {
				print "ERROR: file $filename import process failed due to errors with the XML file\n\n";
				exit(1);
			}
		} else {
			print "ERROR: file $filename is not readable, or does not exist\n\n";
			exit(1);
		}
	} else {
		print "ERROR: no filename specified\n\n";
		display_help();
		exit(1);
	}
} else {
	print "ERROR: no parameters given\n\n";
	display_help();
	exit(1);
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Import Template Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	print "\nusage: import_package.php --filename=[filename] [--only-info] [--remove-orphans] [--with-profile] [--profile-id=N\n\n";
	print "A utility to allow signed Cacti Packages to be imported from the command line.\n\n";
	print "Required:\n";
	print "    --filename              The name of the gziped package file to import\n\n";
	print "Optional:\n";
	print "    --only-info       Output the info section of the package, do not import\n";
	print "    --preview         Preview the Template Import, do not import\n";
	print "    --with-profile    Use the default system Data Source Profile\n";
	print "    --profile-id=N    Use the specific profile id when importing\n";
	print "    --remove-orphans  If importing a new version of the template, old\n";
	print "                      elements will be removed, if they do not exist\n";
	print "                      in the new version of the template.\n\n";
}
