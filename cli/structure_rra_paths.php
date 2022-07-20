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

$hostId  = NULL;
$proceed = false;

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--proceed':
				$proceed = true;

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
			case '--host-id':
			case '--hostId':
				$hostId = $value;
				break;
			default:
				print "ERROR: Invalid Argument: ($arg)\n\n";
				display_help();
				exit(1);
		}
	}
}

if ($proceed == false) {
	print "\nFATAL: You Must Explicitally Instruct This Script to Proceed with the '--proceed' Option\n\n";
	display_help();
	exit -1;
}

/* check ownership of the current base path */
$base_rra_path = $config["rra_path"];
$owner_id      = fileowner($base_rra_path);
$group_id      = filegroup($base_rra_path);

/* turn off the poller */
disable_poller();

$poller_running = shell_exec("ps -ef | grep poller.php | wc -l");
if ($poller_running == "1") {
	/* turn on the poller */
	enable_poller();

	print "FATAL: The Poller is Currently Running\n";
	exit -4;
}

/* turn on extended paths from in the database */
set_config_option('extended_paths', 'on');

/* fetch all DS having wrong path */
$data_sources = db_fetch_assoc("SELECT local_data_id, host_id, data_source_path,
	CONCAT('<path_rra>/', host_id, '/', local_data_id, '.rrd') AS new_data_source_path,
	REPLACE(data_source_path, '<path_rra>', '$base_rra_path') AS rrd_path,
	REPLACE(CONCAT('<path_rra>/', host_id, '/', local_data_id, '.rrd'), '<path_rra>', '$base_rra_path') AS new_rrd_path
	FROM data_template_data
	INNER JOIN data_local ON data_local.id=data_template_data.local_data_id
	INNER JOIN host ON host.id=data_local.host_id
	WHERE data_source_path != CONCAT('<path_rra>/', host_id, '/', local_data_id, '.rrd')"
	. ($hostId === NULL ? '' : " AND host_id=$hostId"));

/* setup some counters */
$done_count   = 0;
$warn_count   = 0;

/* scan all data sources */
foreach ($data_sources as $info) {
	$new_base_path = $base_rra_path . '/' . $info['host_id'];
	$new_rrd_path  = $info['new_rrd_path'];
	$old_rrd_path  = $info['rrd_path'];

	/* create one subfolder for every host */
	if (!is_dir($new_base_path)) {
		/* see if we can create the dirctory for the new file */
		if (mkdir($new_base_path, 0775)) {
			print "NOTE: New Directory '$new_base_path' Created for RRD Files\n";
			if ($config['cacti_server_os'] != 'win32') {
				if (chown($new_base_path, $owner_id) && chgrp($new_base_path, $group_id)) {
					print "NOTE: New Directory '$new_base_path' Permissions Set\n";
				} else {
					/* turn on the poller */
					enable_poller();

					print "FATAL: Could not Set Permissions for Directory '$new_base_path'\n";
					exit -5;
				}
			}
		} else {
			/* turn on the poller */
			enable_poller();

			print "FATAL: Could NOT Make New Directory '$new_base_path'\n";
			exit -1;
		}
	}

	/* copy the file, update the database and remove the old file */
	if (!file_exists($old_rrd_path)) {
		$warn_count++;

		print "WARNING: Legacy RRA Path '$old_rrd_path' Does not exist, Skipping\n";

		/* alter database */
		update_database($info);
	} elseif (link($old_rrd_path, $new_rrd_path)) {
		$done_count++;

		print "NOTE: HardLink Complete:'" . $old_rrd_path . "' -> '" . $new_rrd_path . "'\n";
		if ($config['cacti_server_os'] != 'win32') {
			if (chown($new_rrd_path, $owner_id) && chgrp($new_rrd_path, $group_id)) {
				print "NOTE: Permissions set for '$new_rrd_path'\n";
			} else {
				/* turn on the poller */
				enable_poller();

				print "FATAL: Could not Set Permissions for File '$new_rrd_path'\n";
				exit -6;
			}
		}

		/* alter database */
		update_database($info);

		if (unlink($old_rrd_path)) {
			print "NOTE: Old File '$old_rrd_path' Removed\n";
		} else {
			/* turn on the poller */
			enable_poller();

			print "FATAL: Old File '$old_rrd_path' Could not be removed\n";
			exit -2;
		}
	} else {
		/* turn on the poller */
		enable_poller();

		print "FATAL: Could not Copy RRD File '$old_rrd_path' to '$new_rrd_path'\n";
		exit -3;
	}
}

/* finally re-enable the poller */
enable_poller();

print "NOTE: Process Complete, '$done_count' Completed, '$warn_count' Skipped\n";

/* update database */
function update_database($info) {
	/* upate table poller_item */
	db_execute("UPDATE poller_item
		SET rrd_path = '" . $info['new_rrd_path'] . "'
		WHERE local_data_id=" . $info['local_data_id']);

	/* update table data_template_data */
	db_execute("UPDATE data_template_data
		SET data_source_path='" . $info['new_data_source_path'] . "'
		WHERE local_data_id=" . $info['local_data_id']);

	print "NOTE: Database Changes Complete for File '" . $info['new_rrd_path'] . "'\n";
}

/* turn on the poller */
function enable_poller() {
	set_config_option('poller_enabled', 'on');
}

/* turn off the poller */
function disable_poller() {
	set_config_option('poller_enabled', '');
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Structured Paths Creation Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	print "\nusage: structure_rra_paths.php [--host-id=N] [--proceed] [--version|-V|-v] [--help|-H|-h]\n\n";
	print "A simple interactive command line utility that converts a Cacti system from using\n";
	print "legacy RRA paths to using structured RRA paths with the following\n";
	print "naming convention: <path_rra>/host_id/local_data_id.rrd\n\n";
	print "This utility is designed for very large Cacti systems.\n\n";
	print "On Linux OS, superuser is required to apply file ownership.\n\n";
	print "The utility follows the process below:\n";
	print "  1) Disables the Cacti Poller\n";
	print "  2) Checks for a Running Poller.\n\n";
	print "If it Finds a Running Poller, it will:\n";
	print "  1) Re-enable the Poller\n";
	print "  2) Exit\n\n";
	print "Else, it will:\n";
	print "  1) Enable Structured Paths in the Console (Settings->Paths)\n\n";
	print "Then, for Each File, it will:\n";
	print "  1) Create the Structured Path, if Necessary\n";
	print "  2) Copy the File to the Strucured Path Using the New Name\n";
	print "  3) Alter the two Database Tables Required\n";
	print "  4) Remove the Old File\n\n";
	print "Once all Files are Complete, it will\n";
	print "  1) Re-enable the Cacti Poller\n\n";
	print "If the utility encounters a problem along the way, it will:\n";
	print "  1) Re-enable the poller\n";
	print "  2) Exit\n\n";
}
