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

require(__DIR__ . '/../include/cli_check.php');
require_once($config['base_path'] . '/lib/import.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/utility.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $preview_only;

if (cacti_sizeof($parms)) {
	$filename       = '';
	$with_profile   = false;
	$remove_orphans = false;
	$preview_only   = 0;
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
			case '--with-profile':
				$with_profile = true;

				break;
			case '--remove-orphans':
				$remove_orphans = true;

				break;
			case '--preview':
				$preview_only = 1;

				break;
			case '--profile-id':
				$profile_id = trim($value);

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

	if($profile_id > 0) {
		if ($with_profile) {
			print "WARNING: '--with-profile' and '--profile-id=N' are exclusive. Ignoring '--with-profile'\n";
		} else {
			$id = db_fetch_cell_prepared('SELECT id FROM data_source_profiles WHERE id = ?', array($profile_id));

			if (empty($id)) {
				print "WARNING: Data Source Profile ID $profile_id not found. Using System Default\n";
				$id = db_fetch_cell_prepared('SELECT id FROM data_source_profiles ORDER BY `default` DESC LIMIT 1');
			}
		}
	} else {
		$id = db_fetch_cell_prepared('SELECT id FROM data_source_profiles ORDER BY `default` DESC LIMIT 1');
	}

	if (empty($id)) {
		print "FATAL: No valid Data Source Profiles found on the system.  Exiting!\n";
		exit(1);
	}

	if ($filename != '') {
		if(file_exists($filename) && is_readable($filename)) {
			$fp = fopen($filename,'r');
			$xml_data = fread($fp,filesize($filename));
			fclose($fp);

			print 'Read ' . strlen($xml_data) . " bytes of XML data\n";

			$debug_data = import_xml_data($xml_data, false, $id, $remove_orphans);

			import_display_results($debug_data, array(), $preview_only);
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

	print "\nusage: import_template.php --filename=[filename] [--with-profile | --profile-id=N]\n\n";
	print "A utility to allow Cacti Templates to be imported from the command line.\n\n";
	print "Required:\n";
	print "    --filename        The name of the XML file to import\n\n";
	print "Optional:\n";
	print "    --preview         Preview the Template Import, do not import\n";
	print "    --with-profile    Use the default system Data Source Profile\n";
	print "    --profile-id=N    Use the specific profile id when importing\n";
	print "    --remove-orphans  If importing a new version of the template, old\n";
	print "                      elements will be removed, if they do not exist\n";
	print "                      in the new version of the template.\n\n";
}
