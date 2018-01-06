#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* We are not talking to the browser */
$no_http_headers = true;

include(dirname(__FILE__).'/../include/global.php');
include_once($config['base_path'] . '/lib/import.php');
include_once($config['base_path'] . '/lib/utility.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $preview_only;

if (sizeof($parms)) {
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
				exit;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit;
			default:
				echo "ERROR: Invalid Argument: ($arg)\n\n";
				exit(1);
		}
	}
	
	if($profile_id > 0) {
		if ($with_profile) {
			echo "WARNING: '--with-profile' and '--profile-id=N' are exclusive. Ignoring '--with-profile'\n";
		} else {
			$id = db_fetch_cell_prepared('SELECT id FROM data_source_profiles WHERE id = ?', array($profile_id));

			if (empty($id)) {
				echo "WARNING: Data Source Profile ID $profile_id not found. Using System Default\n";
				$id = db_fetch_cell_prepared('SELECT id FROM data_source_profiles ORDER BY `default` DESC LIMIT 1');
			}
		}
	} else {
		$id = db_fetch_cell_prepared('SELECT id FROM data_source_profiles ORDER BY `default` DESC LIMIT 1');
	}

	if (empty($id)) {
		echo "FATAL: No valid Data Source Profiles found on the system.  Exiting!\n";
		exit(1);
	}

	if ($filename != '') {
		if(file_exists($filename) && is_readable($filename)) {
			$fp = fopen($filename,'r');
			$xml_data = fread($fp,filesize($filename));
			fclose($fp);

			echo 'Read ' . strlen($xml_data) . " bytes of XML data\n";

			$debug_data = import_xml_data($xml_data, false, $id, $remove_orphans);

			import_display_results($debug_data, array(), $preview_only);
		} else {
			echo "ERROR: file $filename is not readable, or does not exist\n\n";
			exit(1);
		}
	} else {
		echo "ERROR: no filename specified\n\n";
		display_help();
		exit(1);
	}
} else {
	echo "ERROR: no parameters given\n\n";
	display_help();
	exit(1);
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	echo "Cacti Import Template Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	echo "\nusage: import_template.php --filename=[filename] [--with-profile | --profile-id=N]\n\n";
	echo "A utility to allow Cacti Templates to be imported from the command line.\n\n";
	echo "Required:\n";
	echo "    --filename        The name of the XML file to import\n\n";
	echo "Optional:\n";
	echo "    --preview         Preview the Template Import, do not import\n";
	echo "    --with-profile    Use the default system Data Source Profile\n";
	echo "    --profile-id=N    Use the specific profile id when importing\n";
	echo "    --remove-orphans  If importing a new version of the template, old\n";
	echo "                      elements will be removed, if they do not exist\n";
	echo "                      in the new version of the template.\n\n";
}
