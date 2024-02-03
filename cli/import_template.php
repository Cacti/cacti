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
require_once($config['base_path'] . '/lib/import.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/utility.php');

/* switch to main database for cli's */
if ($config['poller_id'] > 1) {
	db_switch_remote_to_main();
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $preview_only;

if (cacti_sizeof($parms)) {
	$filename        = '';
	$with_profile    = false;
	$remove_orphans  = false;
	$replace_svalues = false;
	$preview_only    = 0;
	$profile_id      = '';

	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter, 2);
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
			case '--replace-svalues':
				$replace_svalues = true;

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
				print 'ERROR: Invalid Argument: ($arg)' . PHP_EOL . PHP_EOL;
				exit(1);
		}
	}

	if($profile_id > 0) {
		if ($with_profile) {
			print "WARNING: '--with-profile' and '--profile-id=N' are exclusive. Ignoring '--with-profile'" . PHP_EOL;
		} else {
			$id = db_fetch_cell_prepared('SELECT id FROM data_source_profiles WHERE id = ?', array($profile_id));

			if (empty($id)) {
				print "WARNING: Data Source Profile ID $profile_id not found. Using System Default" . PHP_EOL;
				$id = db_fetch_cell_prepared('SELECT id FROM data_source_profiles ORDER BY `default` DESC LIMIT 1');
			}
		}
	} else {
		$id = db_fetch_cell_prepared('SELECT id FROM data_source_profiles ORDER BY `default` DESC LIMIT 1');
	}

	if (empty($id)) {
		print 'FATAL: No valid Data Source Profiles found on the system.  Exiting!' . PHP_EOL;
		exit(1);
	}

	if ($filename != '') {
		if(file_exists($filename) && is_readable($filename)) {
			$fp = fopen($filename,'r');
			$xml_data = fread($fp,filesize($filename));
			fclose($fp);

			print 'Read ' . strlen($xml_data) . ' bytes of XML data' . PHP_EOL;

			$debug_data = import_xml_data($xml_data, false, $id, $remove_orphans, $replace_svalues);

			import_display_results($debug_data, array(), $preview_only);
		} else {
			print "ERROR: file $filename is not readable, or does not exist" . PHP_EOL . PHP_EOL;
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

	print PHP_EOL;
	print 'usage: import_template.php --filename=[filename] [--with-profile | --profile-id=N]' . PHP_EOL . PHP_EOL;
	print 'A utility to allow Cacti Templates to be imported from the command line.' . PHP_EOL . PHP_EOL;
	print 'Required:' . PHP_EOL;
	print '    --filename        The name of the XML file to import' . PHP_EOL . PHP_EOL;
	print 'Optional:' . PHP_EOL;
	print '    --preview         Preview the Template Import, do not import' . PHP_EOL;
	print '    --with-profile    Use the default system Data Source Profile' . PHP_EOL;
	print '    --profile-id=N    Use the specific profile id when importing' . PHP_EOL;
	print '    --remove-orphans  If importing a new version of the template, old' . PHP_EOL;
	print '                      elements will be removed, if they do not exist' . PHP_EOL;
	print '                      in the new version of the template.';
	print '    --replace-svalues If replacing an old version of either a Device' . PHP_EOL;
	print '                      Template with Data Queries or a Data Query, when' . PHP_EOL;
	print '                      you select this option, all Data Query Suggested' . PHP_EOL;
	print '                      Value Patterns will be replaced with that of the' . PHP_EOL;
	print '                      Package Data Queries.' . PHP_EOL . PHP_EOL;
}
