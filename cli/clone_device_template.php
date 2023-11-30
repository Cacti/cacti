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

require(__DIR__ . '/../include/cli_check.php');
require(__DIR__ . '/../lib/api_device.php');
require(__DIR__ . '/../lib/api_graph.php');
require(__DIR__ . '/../lib/api_data_source.php');
require(__DIR__ . '/../lib/data_query.php');

chdir('..');

if ($config['poller_id'] > 1) {
	print "FATAL: This utility is designed for the main Data Collector only" . PHP_EOL;
	exit(1);
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug         = false;
$quiet         = false;
$list_all      = false;
$list_device   = false;
$template_id   = false;
$template_name = '';
$suffix        = '';

$include_gt    = false;
$include_dq    = false;
$include_dt    = false;
$clone_gt      = false;
$clone_dq      = false;
$clone_dt      = false;

$clone_xml     = false;
$clone_script  = false;

if (cacti_sizeof($parms)) {
	$shortopts = 'VvHh';

	$longopts = array(
		// Options without a value
		'debug',
		'copy',
		'quiet',
		'list-all',
		'clone-xml',
		'clone-scripts',
		'version',
		'help',

		// Options that require one option
		'suffix::',
		'list-template::',
		'device-template::',

		// Include Template Options
		'include-data-queries::',
		'include-graph-templates::',
		'include-data-templates::',

		// Clone Template Options
		'clone-data-queries::',
		'clone-graph-templates::',
		'clone-data-templates::',
	);

	$options = getopt($shortopts, $longopts);

	foreach($options as $arg => $value) {
		switch($arg) {
			case 'list-all':
				$list_all = true;

				break;
			case 'list-template':
				$list_device = $value;

				break;
			case 'device-template':
				$template_id = $value;

				break;
			case 'device-template-name':
				$template_name = $value;

				break;
			case 'quiet':
				$quiet = true;

				break;
			case 'debug':
				$debug = true;

				break;
			case 'device-template':
				$device_template_id = $value;

				break;
			case 'include-graph-templates':
				$include_gt = $value;

				break;
			case 'include-data-queries':
				$include_dq = $value;

				break;
			case 'include-data-templates':
				$include_dt = $value;

				break;
			case 'clone-graph-templates':
				$clone_gt = $value;

				break;
			case 'clone-data-queries':
				$clone_dq = $value;

				break;
			case 'clone-data-templates':
				$clone_dt = $value;

				break;
			case 'clone-xml':
				$clone_xml = true;

				break;
			case 'clone-scripts':
				$clone_script = true;

				break;
			case 'version':
			case 'V':
			case 'v':
				display_version();
				exit(0);
			case 'help':
			case 'H':
			case 'h':
				display_help();
				exit(0);
			default:
				print "ERROR: Invalid Argument: ($arg)" . PHP_EOL . PHP_EOL;
				display_help();
				exit(1);
		}
	}

	if ($list_all) {
		list_all_templates();
		exit(0);
	}

	if ($list_device) {
		list_device_template($list_device);
		exit(0);
	}

	/**
	 * Perform some pre-checks before the pre-check routine
	 * the default case in include all.  Set that up now
	 */
	if ($clone_gt === false) {
		if ($include_gt === false) {
			$include_gt = 'all';
		}

		$clone_gt   = '';
	}

	if ($clone_dq === false) {
		if ($include_dq === false) {
			$include_dq = 'all';
		}

		$clone_dq   = '';
	}

	if ($clone_dt === false) {
		if ($include_dt === false) {
			$include_dt = 'all';
		}

		$clone_dt   = '';
	}

	/**
	 * Perform a list of sanity checks and if they all check out, prompt the user, otherwise
	 * list a report of the errors and then drop out of the validation checks.
	 */
	$proceed = false;
	$output  = array(
		'warnings' => array(),
		'errors'   => array()
	);

	printf('---------------------------------------------------------------' . PHP_EOL);
	printf('Performing pre-check for errors in arguments' . PHP_EOL);
	printf('---------------------------------------------------------------' . PHP_EOL);

	if ($template_id) {
		$output = api_clone_device_template_check_for_errors($template_id, $template_name, $include_gt, $clone_gt, $include_dq, $clone_dq,
			$include_dt, $clone_dt, $suffix, $clone_xml, $clone_script);
	} else {
		$output['errors'][] = sprintf('FATAL: You must specify a Device Template ID to continue!');
	}

	if (cacti_sizeof($output['errors']) || cacti_sizeof($output['warnings'])) {
		printf(PHP_EOL);
		printf("WARNING: Clone Device Template found issues.  You may correct these issues or continue." . PHP_EOL);
		printf("         In the case of duplicate name warnings, Cacti will attempt to find a suitable new name." . PHP_EOL);
		printf("         using an ascending numeric suffix starting with ' (1)'.  For scripts and XML files, the" . PHP_EOL);
		printf("         new names will start with an ascending numeric suffix starting with '_01'." . PHP_EOL . PHP_EOL);

		if (cacti_sizeof($output['errors'])) {
			foreach($output['errors'] as $error) {
				printf($error . PHP_EOL);
			}

			exit(1);
		}

		if (cacti_sizeof($output['warnings'])) {
			foreach($output['warnings'] as $warning) {
				printf($warning . PHP_EOL);
			}
		}
	} else {
		printf('NOTE: No Clone Device Template precheck errors Found!' . PHP_EOL);
	}

    printf('------------------------------------------------------' . PHP_EOL);

	if (!$quiet) {
		$fin = fopen('php://stdin', 'r');

		while(true) {
			printf(PHP_EOL . 'Precheck completed with no Errors.  Do you want to continue [Y|N]? ');

			$line = strtolower(trim(fgets($fin)));

			if ($line == 'y') {
				fclose($fin);
				break;
			} elseif ($line == 'n') {
				fclose($fin);
				printf('User does not wish to proceed.  Exiting!' . PHP_EOL);
				exit(1);
			}
		}
	}

	/* proceed with the Clone here */
	printf('Proceeding with Device Template Cloning' . PHP_EOL);
    printf('------------------------------------------------------' . PHP_EOL);

	$success = api_clone_device_template($template_id, $template_name, $include_gt, $clone_gt,
		$include_dq, $clone_dq, $include_dt, $clone_dt, $suffix, $clone_xml, $clone_script);

	if ($success) {
		exit(0);
	} else {
		exit(1);
	}
} else {
	display_help();

	exit(1);
}

function list_all_templates() {
	global $device_classes;

	$device_templates = db_fetch_assoc('SELECT *
		FROM host_template
		ORDER BY name');

	if (cacti_sizeof($device_templates)) {
		printf('Device Templates' . PHP_EOL);
		printf('-----------------------------------------------' . PHP_EOL . PHP_EOL);
		printf('%-8s %-40s %-25s' . PHP_EOL, 'Id', 'Name', 'Class');
		printf('%-8s %-40s %-25s' . PHP_EOL, str_repeat('-', 8), str_repeat('-', 40), str_repeat('-', 25));

		foreach($device_templates as $dt) {
			printf('%-8s %-40s %-25s' . PHP_EOL, $dt['id'], $dt['name'], $device_classes[$dt['class']]);
		}
	} else {
		printf('No Device Templates Found' . PHP_EOL);
	}
}

function list_device_template($device_template_id) {
	global $device_classes;

	$device_template = db_fetch_row_prepared('SELECT *
		FROM host_template
		WHERE id = ?',
		array($device_template_id));

	if (cacti_sizeof($device_template)) {
		printf('-----------------------------------------------' . PHP_EOL);
		printf('Id:    %s' . PHP_EOL, $device_template['id']);
		printf('Name:  %s' . PHP_EOL, $device_template['name']);
		printf('Class: %s' . PHP_EOL, $device_classes[$device_template['class']]);

		$graph_templates = array_rekey(
			db_fetch_assoc_prepared('SELECT id, name
				FROM graph_templates
				WHERE id IN (
					SELECT graph_template_id
					FROM host_template_graph
					WHERE host_template_id = ?
				)
				ORDER BY name',
				array($device_template_id)),
			'id', 'name'
		);

		if (cacti_sizeof($graph_templates)) {
			printf(PHP_EOL);
			printf('-----------------------------------------------' . PHP_EOL);
			printf('Graph Templates' . PHP_EOL);
			printf(PHP_EOL);
			printf('%-8s %-40s' . PHP_EOL, 'Id', 'Name');
			printf('%-8s %-40s' . PHP_EOL, str_repeat('-', 8), str_repeat('-', 40));

			foreach($graph_templates as $id => $name) {
				printf('%-8s %-40s' . PHP_EOL, $id, $name);
			}
		} else {
			printf('No Graph Templates found' . PHP_EOL);
		}

		$data_queries = array_rekey(
			db_fetch_assoc_prepared('SELECT id, name
				FROM snmp_query
				WHERE id IN (
					SELECT snmp_query_id
					FROM host_template_snmp_query
					WHERE host_template_id = ?
				)
				ORDER BY name',
				array($device_template_id)),
			'id', 'name');

		if (cacti_sizeof($data_queries)) {
			printf(PHP_EOL);
			printf('-----------------------------------------------' . PHP_EOL);
			printf('Data Queries' . PHP_EOL);
			printf(PHP_EOL);
			printf('%-8s %-40s' . PHP_EOL, 'Id', 'Name');
			printf('%-8s %-40s' . PHP_EOL, str_repeat('-', 8), str_repeat('-', 40));

			foreach($data_queries as $id => $name) {
				printf('%-8s %-40s' . PHP_EOL, $id, $name);
			}
		} else {
			printf('No Data Queries found' . PHP_EOL);
		}

		$data_query_graph_templates = array_rekey(
			db_fetch_assoc_prepared('SELECT id, name
				FROM graph_templates
				WHERE id IN (
					SELECT graph_template_id
					FROM snmp_query_graph
					WHERE snmp_query_id IN (
						SELECT snmp_query_id
						FROM host_template_snmp_query
						WHERE host_template_id = ?
					)
				)
				ORDER BY name',
				array($device_template_id)),
			'id', 'name'
		);

		if (cacti_sizeof($data_query_graph_templates)) {
			printf(PHP_EOL);
			printf('-----------------------------------------------' . PHP_EOL);
			printf('Data Queries Graph Templates' . PHP_EOL);
			printf(PHP_EOL);
			printf('%-8s %-40s' . PHP_EOL, 'Id', 'Name');
			printf('%-8s %-40s' . PHP_EOL, str_repeat('-', 8), str_repeat('-', 40));

			foreach($data_query_graph_templates as $id => $name) {
				printf('%-8s %-40s' . PHP_EOL, $id, $name);
			}
		} else {
			printf('No Data Query Graph Templates found' . PHP_EOL);
		}

		exit(0);
	} else {
		print "FATAL: Device Template $device_template_id does not exist!" . PHP_EOL;
		exit(1);
	}
}

/**
 *  display_version - displays version information
 */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Device Template Cloning Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

function display_help() {
	display_version();

	print PHP_EOL;
	print 'usage: clone_device_template.php --device-template=N [ --device-template-name=S ] [ --debug ] [ --quiet ]' . PHP_EOL;
	print '       [ --include-graph-templates=all|N,N,... ] [ --clone-graph-templates=all|N,N,N ]' . PHP_EOL;
	print '       [ --include-data-queries=all|N,N,... ] [ --clone-data-queries=all|N,N,N ]' . PHP_EOL;
	print '       [ --include-data-templates=all|N,N,... ] [ --clone-data-templates=all|N,N,N ]' . PHP_EOL;
	print '       [ --clone-scripts ] [ --clone-xml ] [ --suffix=S ] |' . PHP_EOL;
	print '   --list-all | --list-template=N' . PHP_EOL . PHP_EOL;

	print 'Cacti Clone Device Template Utility will clone parts of all of your Device Template' . PHP_EOL;
	print 'so that it will not interfere with previous versions of the Cacti Device Template' . PHP_EOL;
	print 'from which it was based.' . PHP_EOL . PHP_EOL;

	print 'There are two fundamental option sets that you can use when cloning, you can:' . PHP_EOL . PHP_EOL;

	print '1. Include - Objects IDs that specified here will be used inside the cloned Device Template' . PHP_EOL;
	print '             with no changes or cloning of the object.' . PHP_EOL;
	print '2. Clone   - New Objects will be created using the optional --suffix to their name.' . PHP_EOL;
	print '             If the suffix is not specified, it will default to \'_copy\'.' . PHP_EOL . PHP_EOL;

	print 'Default Behavior:' . PHP_EOL;
	print '* The default Device Template Name will be the original with the --suffix specified' . PHP_EOL;
	print '* The default setting for both \'--include-*\' and \'--clone-*\' is \'--include=all\'' . PHP_EOL;
	print '* The default setting for \'--suffix\' id \'_copy\'.' . PHP_EOL . PHP_EOL;
	print '* The \'all\' Options for \'--include-*\' and \'--clone-*\' are mutually exclusive to one another.' . PHP_EOL . PHP_EOL;

	print 'NOTES:' . PHP_EOL;
	print '* CDEFs, VDEFs, GPRINTs, and Data Source Profiles will not be cloned.' . PHP_EOL;
	print '* Data Input Methods will only be cloned when using --clone-scripts option and' . PHP_EOL;
	print '  only when combined with the cloning of the Data Templates.' . PHP_EOL;
	print '* System Data Input Methods are never cloned.' . PHP_EOL . PHP_EOL;

	print 'Base Options:' . PHP_EOL;
	print '  --device-template=N               - The Device Template to be cloned.' . PHP_EOL;
	print '  --device-template-name=S          - The Device Template Name for the clone.' . PHP_EOL;
	print '  --template-name=S                 - The new Device Template Name to use.' . PHP_EOL;
	print '  --include-graph-templates=all|N,N - Comma delimited list of Graph Templates to include.' . PHP_EOL;
	print '  --include-data-queries=all|N,N    - Comma delimited list of Data Queries to include.' . PHP_EOL;
	print '  --include-data-templates=all|N,N  - Comma delimited list of Data Templates to include.' . PHP_EOL;
	print '  --clone-graph-templates=all|N,N   - Comma delimited list of Graph Templates to clone.' . PHP_EOL;
	print '  --clone-data-queries=all|N,N      - Comma delimited list of Data Queries to clone.' . PHP_EOL;
	print '  --clone-data-templates=all|N,N    - Comma delimited list of Data Templates to clone.' . PHP_EOL;

	print '  --suffix=S                        - When cloning the object, the new name suffix.' . PHP_EOL;
	print '  --quiet                           - Do not prompt to confirm, just take action (batch mode).' . PHP_EOL . PHP_EOL;

	print 'Script and XML Handling:' . PHP_EOL;
	print '  --clone-scripts - Will copy the Data Input Scripts to new names when cloning Data Templates.' . PHP_EOL;
	print '  --clone-xml     - Will copy the Data Query XML to new names when cloning Data Queries.' . PHP_EOL . PHP_EOL;

	print 'List Options:' . PHP_EOL;
	print '  --list-all                        - List all Device Templates their names and ids.' . PHP_EOL;
	print '  --list-template=N                 - List a specific Device Template and all its components.' . PHP_EOL . PHP_EOL;

	print 'Debug Options:' . PHP_EOL;
	print '  --debug                           - Be more verbose in during processing' . PHP_EOL;
	print PHP_EOL;
}

