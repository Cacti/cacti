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

if ($config['poller_id'] > 1) {
	print 'FATAL: This utility is designed for the main Data Collector only' . PHP_EOL;
	exit(1);
}

$storage_location = read_config_option('storage_location');
if ($storage_location > 0) {
	print 'FATAL: This utility is designed for local RRDfile storage and is not compatible with the RRDProxy.' . PHP_EOL;
	exit(1);
}

define('PHP_DEOL', PHP_EOL . PHP_EOL);

$host_id          = false;
$host_template_id = false;
$proceed          = false;

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
				$host_id = $value;
				break;
			case '--host-template-id':
				$host_template_id = $value;
				break;
			default:
				print "ERROR: Invalid Argument: ($arg)" . PHP_DEOL;
				display_help();
				exit(1);
		}
	}
}

$start = microtime(true);

if (read_config_option('boost_rrd_update_enable') !== 'on') {
	print PHP_EOL . 'FATAL: Cacti\'s Performance Booster required to run this utility.'. PHP_DEOL;
	display_help();
	exit -1;
}

if ($host_id !== false && ($host_id <= 0 || !is_numeric($host_id))) {
	print PHP_EOL . 'FATAL: When specifying a Device ID, you must pick on greater or equal than zero.' . PHP_DEOL;
	display_help();
	exit -1;
}

if ($host_template_id !== false && ($host_template_id <= 0 || !is_numeric($host_template_id))) {
	print PHP_EOL . 'FATAL: When specifying a Device Template ID, you must pick on greater or equal than zero.' . PHP_DEOL;
	display_help();
	exit -1;
}

if ($proceed == false) {
	print PHP_EOL . 'FATAL: You Must Explicitly Instruct This Script to Proceed with the \'--proceed\' Option' . PHP_DEOL;
	display_help();
	exit -1;
}

/* check ownership of the current base path */
$base_rra_path = $config['rra_path'];
$owner_id      = fileowner($base_rra_path);
$group_id      = filegroup($base_rra_path);

/* turn on extended paths from in the database */
set_config_option('extended_paths', 'on');

$pattern = read_config_option('extended_paths_type');
$maxdirs = read_config_option('extended_paths_hashes');
if (empty($maxdirs) || $maxdirs < 0 || !is_numeric($maxdirs)) {
	$maxdirs = 100;
}

if ($pattern == '' || $pattern == 'device') {
	$pattern1  = "CONCAT('<path_rra>/', host_id, '/', local_data_id, '.rrd') AS new_data_source_path,";
	$pattern2  = "REPLACE(CONCAT('<path_rra>/', '/', host_id, '/', local_data_id, '.rrd'), '<path_rra>', '$base_rra_path') AS new_rrd_path";
	$sql_where = "WHERE dtd.data_source_path != CONCAT('<path_rra>/', dl.host_id, '/', dtd.local_data_id, '.rrd')";
} elseif ($pattern == 'device_dq') {
	$pattern1  = "CONCAT('<path_rra>/', host_id, '/', snmp_query_id, '/', local_data_id, '.rrd') AS new_data_source_path,";
	$pattern2  = "REPLACE(CONCAT('<path_rra>/', host_id, '/', snmp_query_id, '/', local_data_id, '.rrd'), '<path_rra>', '$base_rra_path') AS new_rrd_path";
	$sql_where = "WHERE dtd.data_source_path != CONCAT('<path_rra>/', dl.host_id, '/', IF(dl.snmp_query_id > 0, CONCAT(dl.snmp_query_id, '/'), ''), dtd.local_data_id, '.rrd')";
} elseif ($pattern == 'hash_device') {
	$pattern1  = "CONCAT('<path_rra>/', host_id % $maxdirs, '/', host_id, '/', local_data_id, '.rrd') AS new_data_source_path,";
	$pattern2  = "REPLACE(CONCAT('<path_rra>/', host_id % $maxdirs, '/', host_id, '/', local_data_id, '.rrd'), '<path_rra>', '$base_rra_path') AS new_rrd_path";
	$sql_where = "WHERE dtd.data_source_path != CONCAT('<path_rra>/', dl.host_id % $maxdirs, '/', dl.host_id, '/', dtd.local_data_id, '.rrd')";
} elseif ($pattern == 'hash_device_dq') {
	$pattern1  = "CONCAT('<path_rra>/', host_id % $maxdirs, '/', host_id, '/', snmp_query_id, '/', local_data_id, '.rrd') AS new_data_source_path,";
	$pattern2  = "REPLACE(CONCAT('<path_rra>/', host_id % $maxdirs, '/', host_id, '/', snmp_query_id, '/', local_data_id, '.rrd'), '<path_rra>', '$base_rra_path') AS new_rrd_path";
	$sql_where = "WHERE dtd.data_source_path != CONCAT('<path_rra>/', dl.host_id % $maxdirs, '/', dl.host_id, '/', IF(dl.snmp_query_id > 0, CONCAT(dl.snmp_query_id, '/'), ''), dtd.local_data_id, '.rrd')";
}

$sql_where  = '';
$sql_params = array();

if ($host_id > 0) {
	$sql_where    = ' AND h.id = ?';
	$sql_params[] = $host_id;
}

if ($host_template_id > 0) {
	$sql_where    = ' AND h.host_template_id = ?';
	$sql_params[] = $host_template_id;
}

/* fetch all DS having wrong path */
$data_sources = db_fetch_assoc_prepared("SELECT dtd.local_data_id, dl.host_id % $maxdirs AS hash_id,
	dl.host_id, dtd.data_source_path, dl.snmp_query_id, h.description,
	$pattern1
	REPLACE(data_source_path, '<path_rra>', '$base_rra_path') AS rrd_path,
	$pattern2
	FROM data_template_data AS dtd
	INNER JOIN data_local AS dl
	ON dl.id = dtd.local_data_id
	INNER JOIN host AS h
	ON h.id = dl.host_id
	$sql_where",
	$sql_params);

/* setup some counters */
$total_count = cacti_sizeof($data_sources);
$done_count = 0;
$warn_count = 0;
$skip_count = 0;
$started    = false;

printf('NOTE: Found:%s Data Sources.  Beginning Process' . PHP_EOL, number_format($total_count));

/* scan all data sources */
foreach ($data_sources as $info) {
	if (($done_count + $warn_count + $skip_count) % 100 == 0 && $started) {
		printf("NOTE: Completed: %d of %d RRDfiles" . PHP_EOL, $done_count + $warn_count + $skip_count, $total_count);
	}

	$started = true;

	$new_base_path = dirname($info['new_rrd_path']);
	$new_rrd_path  = $info['new_rrd_path'];
	$old_rrd_path  = $info['rrd_path'];
	$local_data_id = $info['local_data_id'];

    /* acquire lock in order to prevent race conditions */
    while (!db_fetch_cell("SELECT GET_LOCK('boost.single_ds.$local_data_id', 1)")) {
        usleep(50000);
    }

	/* create one subfolder for every host */
	if (!is_dir($new_base_path)) {
		/* see if we can create the directory for the new file */
		if (mkdir($new_base_path, 0775, true)) {
			struct_debug("NOTE: New Directory '$new_base_path' Created for RRD Files");

			if ($config['cacti_server_os'] != 'win32') {
				if (sp_recursive_chown($new_base_path, $owner_id) && sp_recursive_chgrp($new_base_path, $group_id)) {
					struct_debug("NOTE: New Directory '$new_base_path' Permissions Set");
				} else {
					print "FATAL: Could not Set Permissions for Directory '$new_base_path'" . PHP_EOL;

					exit -5;
				}
			}
		} else {
			print "FATAL: Could NOT Make New Directory '$new_base_path'" . PHP_EOL;

			exit -1;
		}
	}

	/**
	 * check for the old file and if not exists, try to find it
	 * else update the database and set an error
	 */
	if (!file_exists($old_rrd_path)) {
		/* check for file in other path */
		$data_source_name = db_fetch_cell('SELECT data_source_name
			FROM data_template_rrd
			WHERE local_data_id = ?
			LIMIT 1',
			array($local_data_id));

		$data_source_path1 = $base_rra_path . '/' . strtolower(clean_up_file_name($info['description'])) . '_' . $local_data_id . '.rrd';

		if ($pattern == '' || $pattern == 'device') {
			$data_source_path2 = $base_rra_path . '/' . $info['host_id'] . '/' . $info['snmp_query_id'] . '/' . $local_data_id . '.rrd';
		} elseif ($pattern == 'device_dq') {
			$data_source_path2 = $base_rra_path . '/' . $info['host_id'] . '/' . $local_data_id . '.rrd';
		} elseif ($pattern == 'hash_device') {
			$data_source_path2 = $base_rra_path . '/' . $info['hash_id'] . '/' . $info['host_id'] . '/' . $local_data_id . '.rrd';
		} elseif ($pattern == 'hash_device_dq') {
			$data_source_path2 = $base_rra_path . '/' . $info['hash_id'] . '/' . $info['host_id'] . '/' . $info['snmp_query_id'] . '/' . $local_data_id . '.rrd';
		}

		if (file_exists($data_source_path1)) {
			$old_rrd_path = $data_source_path1;
		} elseif (file_exists($data_source_path2)) {
			$old_rrd_path = $data_source_path2;
		} else {
			$warn_count++;

			print "WARNING: Legacy RRA Path '$old_rrd_path' Does not exist, Skipping" . PHP_EOL;
		}

		/* alter database */
		update_database($info);
	}

	/**
	 * check that the old file exists, and if so, move it to
	 * it's new location if different than the old
	 */
	if (file_exists($old_rrd_path)) {
		if ($old_rrd_path != $new_rrd_path) {
			if (rename($old_rrd_path, $new_rrd_path)) {
				$done_count++;

				struct_debug("Move Completed for: '" . $old_rrd_path . "' > '" . $new_rrd_path . "'");

				if ($config['cacti_server_os'] != 'win32') {
					if (sp_recursive_chown($new_rrd_path, $owner_id) && sp_recursive_chgrp($new_rrd_path, $group_id)) {
						struct_debug("Permissions set for '$new_rrd_path'");
					} else {
						print "FATAL: Could not Set Permissions for File '$new_rrd_path'" . PHP_EOL;
						exit -6;
					}
				}

				/* alter database */
				update_database($info);
			} else {
				print "FATAL: Could not Move RRD File '$old_rrd_path' to '$new_rrd_path'" . PHP_EOL;
				exit -3;
			}
		} else {
			$skip_count++;
		}
	}

    db_fetch_cell("SELECT RELEASE_LOCK('boost.single_ds.$local_data_id')");
}

$end = microtime(true);

$stats = sprintf('Time:%0.2f Renamed:%s Skipped:%s Warnings:%s', $end - $start, $done_count, $skip_count, $warn_count);

cacti_log("RRDSTRUCT STATS: $stats", false, 'SYSTEM');

print "NOTE: RRD Restructure Complete: $stats" . PHP_EOL;

/**
 * struct_debug - Simple debug function for restructuring
 *
 * @param  (string) - The output string
 *
 * @return (void)
 */
function struct_debug($string) {
	global $debug;

	if ($debug) {
		print date('H:i:s') . 'DEBUG: ' . trim($string) . PHP_EOL;
	}
}

/**
 * update database - update database pointers to point to the new
 * database location
 *
 * @param  (array) - $info - an array of local_data_id, new_rrd_path, and the new_data_source_path
 *
 * @return (void)
 */
function update_database($info) {
	/* update table poller_item */
	db_execute_prepared('UPDATE poller_item
		SET rrd_path = ?
		WHERE local_data_id = ?',
		array($info['new_rrd_path'], $info['local_data_id']));

	/* update table data_template_data */
	db_execute_prepared('UPDATE data_template_data
		SET data_source_path = ?
		WHERE local_data_id = ?',
		array($info['new_data_source_path'], $info['local_data_id']));

	struct_debug("Database Changes Complete for File '" . $info['new_rrd_path'] . "'");
}

/**
 * sp_recursive_chown - Recursively chown on a path
 *
 * @param  (string)     $path
 * @param  (string|int) $user
 *
 * @return (void)
 */
function sp_recursive_chown($path, $user) {
	$directory = rtrim($path, '/');

	if ($items = glob($path . '/*')) {
		foreach ($items as $item) {
			if (is_dir($item)) {
				return sp_recursive_chown($item, $user);
			} else {
				return chown($item, $user);
			}
		}
	}

	return chown($path, $user);
}

/**
 * sp_recursive_chgrp - Recursively chgrp on a path
 *
 * @param  (string)     $path
 * @param  (string|int) $group
 *
 * @return (void)
 */
function sp_recursive_chgrp($path, $group) {
	$directory = rtrim($path, '/');

	if ($items = glob($path . '/*')) {
		foreach ($items as $item) {
			if (is_dir($item)) {
				return sp_recursive_chgrp($item, $group);
			} else {
				return chgrp($item, $group);
			}
		}
	}

	return chgrp($path, $group);
}

/**
 * display_version - displays version information
 */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Structured Paths Creation Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/**
 * display_help - displays help information
 */
function display_help() {
	display_version();

	print PHP_EOL . 'usage: structure_rra_paths.php [--host-id=N] [--host-template-id=N] [--proceed]' . PHP_DEOL;

	print 'A simple interactive command line utility that converts a Cacti system from using' . PHP_EOL;
	print 'legacy RRA paths to using structured RRA paths with the following' . PHP_EOL;
	print 'four naming patterns:' . PHP_DEOL;

	print 'device                 - <path_rra>/host_id/local_data_id.rrd' . PHP_EOL;
	print 'device/data_query      - <path_rra>/host_id/data_query_id/local_data_id.rrd' . PHP_EOL;
	print 'hash/device            - <path_rra>/hash_id/host_id/data_query_id/local_data_id.rrd' . PHP_EOL;
	print 'hash/device/data_query - <path_rra>/hash_id/host_id/data_query_id/local_data_id.rrd' . PHP_DEOL;

	print 'The pattern that you choose will depend on how many Devices and Graphs per Device' . PHP_EOL;
	print 'you have.  It\'s possible that if your site has over 100k Devices, you may want' . PHP_EOL;
	print 'to use one of last two options.' . PHP_DEOL;

	print 'Optional:' . PHP_EOL;
	print ' --host-id=N           Specify if you wish to switch on a single Device.' . PHP_EOL;
	print ' --host-template-id=N  Specify if you wish to change for a class of Devices.' . PHP_DEOL;

	print 'This utility is designed for very large Cacti systems or file systems that have' . PHP_EOL;
	print 'problems with very large directories.  It will run interactively, but it first' . PHP_EOL;
	print 'requires you to be using Cacti\'s performance boosting feature called Boost.' . PHP_DEOL;

	print 'On Linux/UNIX, the root user is required to apply file and directory ownership.' . PHP_EOL;
	print 'The when leveraging boost, the utility will work with or without the Cacti poller' . PHP_EOL;
	print 'running.  The utility will use the set_lock() and release_lock() MySQL/MariaDB' . PHP_EOL;
	print 'for interlocking, and therefore the utility is safe to run while the Cacti' . PHP_EOL;
	print 'poller is running.'. PHP_DEOL;

	print 'It is recommended that you not interrupt this script as files may not appear' . PHP_EOL;
	print 'in the locations that the utility expects them to be which may cause issues.' . PHP_DEOL;

	print 'For Each File, it will:' . PHP_DEOL;
	print '  1) Create the Structured Path, if Necessary' . PHP_EOL;
	print '  2) Move the File to the Structured Path Using the New Name' . PHP_EOL;
	print '  3) Alter the two Database Tables Required'. PHP_DEOL;
}

