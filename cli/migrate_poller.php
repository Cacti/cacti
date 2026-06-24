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
require_once(CACTI_PATH_LIBRARY . '/api_automation_tools.php');
require_once(CACTI_PATH_LIBRARY . '/api_device.php');
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/api_graph.php');
require_once(CACTI_PATH_LIBRARY . '/api_tree.php');
require_once(CACTI_PATH_LIBRARY . '/data_query.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/snmp.php');
require_once(CACTI_PATH_LIBRARY . '/template.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

if (! cacti_sizeof($parms)) {
	display_help();

	exit(0);
}

// setup defaults
$source_poller = 0;
$dest_poller   = 0;
$migrate_all   = false;
$host_ids      = '';
$quietMode     = false;

// Migration output files
$migration_log_file = CACTI_PATH_LOG . '/migration_log_' . date('Y-m-d_H-i-s') . '.txt';

foreach ($parms as $parameter) {
	if (str_contains($parameter, '=')) {
		[$arg, $value] = explode('=', $parameter, 2);
	} else {
		$arg   = $parameter;
		$value = '';
	}

	switch ($arg) {
		case '-d':
		case '--debug':
			$debug = true;

			break;
		case '--source-poller':
			$source_poller = intval($value);

			break;
		case '--dest-poller':
			$dest_poller = intval($value);

			break;
		case '--host-ids':
			$host_ids = trim($value);

			break;
		case '--all':
			$migrate_all = true;

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

		case '--quiet':
			$quietMode = true;

			break;
		default:
			print "ERROR: Invalid Argument: ($arg)" . PHP_EOL . PHP_EOL;
			display_help();
			exit(1);
	}
}

// Validate required arguments
if ($source_poller <= 0 || $dest_poller <= 0) {
	print 'ERROR: Both --source-poller and --dest-poller are required and must be positive integers.' . PHP_EOL . PHP_EOL;
	display_help();
	exit(1);
}

// Validate that source and destination pollers are different
if ($source_poller == $dest_poller) {
	print 'ERROR: Source and destination pollers cannot be the same' . PHP_EOL;
	exit(1);
}

// Validate migration mode (exactly one of --all or --host-ids)
if ($migrate_all && $host_ids != '') {
	print 'ERROR: Cannot specify both --all and --host-ids options' . PHP_EOL;
	display_help();
	exit(1);
}

if (!$migrate_all && $host_ids == '') {
	print 'ERROR: Must specify exactly one migration option: --all or --host-ids' . PHP_EOL;
	display_help();
	exit(1);
}

// Validate that both pollers exist
if (!$quietMode) {
	print 'Validating pollers...' . PHP_EOL;
}

if (!check_if_poller_exists($source_poller)) {
	print 'ERROR: Source poller validation failed' . PHP_EOL;
	exit(1);
}

if (!check_if_poller_exists($dest_poller)) {
	print 'ERROR: Destination poller validation failed' . PHP_EOL;
	exit(1);
}

if (!$quietMode) {
	print 'All validations passed. Starting migration process...' . PHP_EOL . PHP_EOL;
}

// Execute migration based on selected mode
if ($migrate_all) {
	migrate_all_devices($source_poller, $dest_poller, $quietMode);
} elseif ($host_ids != '') {
	migrate_from_host_ids($host_ids, $source_poller, $dest_poller, $quietMode);
}

if (!$quietMode) {
	print PHP_EOL;
	print 'Script execution completed.' . PHP_EOL;
}

exit(0);

/**
 * Check if a poller exists in the database
 *
 * @param int $poller_id The poller ID to check
 *
 * @return bool True if poller exists, false otherwise
 */
function check_if_poller_exists(int $poller_id) : bool {
	$poller = db_fetch_row_prepared('SELECT id, name FROM poller WHERE id = ?', [$poller_id]);

	if (empty($poller)) {
		print "ERROR: Poller ID $poller_id does not exist" . PHP_EOL;
		print 'Valid poller IDs are:' . PHP_EOL;
		$pollers = get_poller_list();

		foreach ($pollers as $p) {
			print '  Poller ID: ' . $p['id'] . ' - Name: ' . $p['name'] . PHP_EOL;
		}

		return false;
	}

	return true;
}

/**
 * Get list of all pollers from database
 *
 * @return mixed List of pollers with id and name
 */
function get_poller_list() : mixed {
	return db_fetch_assoc('SELECT id, name FROM poller ORDER BY id');
}

/**
 * Get devices for a specific poller
 *
 * @param int $poller_id The poller ID
 *
 * @return array List of devices on the poller
 */
function get_devices_by_poller(int $poller_id) : mixed {
	return db_fetch_assoc_prepared('SELECT id, hostname, description, poller_id FROM host WHERE poller_id = ?', [$poller_id]);
}

/**
 * Log migration activity to file
 *
 * @param string $message The message to log
 *
 * @return void
 */
function log_migration(string $message) : void {
	cacti_log($message, false, 'MIGRATE');
}

/**
 * Write device migration record to log for rollback purposes
 *
 * @param array $device      Device information
 * @param int   $dest_poller Destination poller ID
 *
 * @return void
 */
function write_migration_log(array $device, int $dest_poller) : void {
	global $migration_log_file;

	$message = "Migrated device ID: {$device['id']}, Hostname: {$device['hostname']}, Description: {$device['description']}, From Poller: {$device['poller_id']} to Poller: $dest_poller";

	// Append to log file
	$result = file_put_contents($migration_log_file, date('Y-m-d H:i:s') . " - $message" . PHP_EOL, FILE_APPEND);

	if ($result === false) {
		cacti_log("ERROR: Failed to write to migration log file: $migration_log_file", false, 'MIGRATE');
	}
}

/**
 * Migrate a single device to new poller
 *
 * @param array $device      Device information
 * @param int   $dest_poller Destination poller ID
 *
 * @return bool Success status
 */
function migrate_device(array $device, int $dest_poller) : bool {
	// Update device poller_id
	$host_id = api_device_save(
		$device['id'],
		$device['host_template_id'],
		$device['description'],
		$device['hostname'],
		$device['snmp_community'],
		$device['snmp_version'],
		$device['snmp_username'],
		$device['snmp_password'],
		$device['snmp_port'],
		$device['snmp_timeout'],
		$device['disabled'],
		$device['availability_method'],
		$device['ping_method'],
		$device['ping_port'],
		$device['ping_timeout'],
		$device['ping_retries'],
		$device['notes'],
		$device['snmp_auth_protocol'],
		$device['snmp_priv_passphrase'],
		$device['snmp_priv_protocol'],
		$device['snmp_context'],
		$device['snmp_engine_id'],
		$device['max_oids'],
		$device['device_threads'],
		$dest_poller, // This is the key change - new poller ID
		$device['site_id'],
		$device['external_id'],
		$device['location'],
		$device['bulk_walk_size'],
		$device['snmp_options'],
		$device['snmp_retries']
	);

	if (is_error_message() || $host_id != $device['id']) {
		$reason = is_error_message() ? 'API save failure' : 'ID mismatch after save';
		cacti_log("Device migration failed for '{$device['description']}' (ID: {$device['id']}): $reason. Host ID returned: $host_id, Expected: {$device['id']}");

		return false;
	}

	// Log the migration
	log_migration("Moving device {$device['description']} (ID: {$device['id']}) from Poller {$device['poller_id']} to Poller $dest_poller");

	// Write to rollback log
	write_migration_log($device, $dest_poller);

	return true;
}

/**
 * Migrate all devices from source poller to destination poller
 *
 * @param int  $source_poller Source poller ID
 * @param int  $dest_poller   Destination poller ID
 * @param bool $quietMode     Whether to suppress output
 *
 * @return bool
 */
function migrate_all_devices(int $source_poller, int $dest_poller, bool $quietMode) : bool {
	$devices = get_devices_by_poller($source_poller);

	if (empty($devices)) {
		print "No devices found on poller $source_poller" . PHP_EOL;

		return false;
	}

	if (!$quietMode) {
		print "Migration Mode: All devices from poller $source_poller" . PHP_EOL;
		print 'Found ' . count($devices) . " device(s) on poller $source_poller" . PHP_EOL;
		print "This will move ALL devices from Poller $source_poller to Poller $dest_poller" . PHP_EOL . PHP_EOL;

		// Display devices to be migrated
		print 'Devices to be migrated:' . PHP_EOL;

		foreach ($devices as $device) {
			print '  ID: ' . $device['id'] . ' - ' . $device['hostname'] . ' (' . $device['description'] . ')' . PHP_EOL;
		}

		print PHP_EOL;
		print 'Are you sure you want to continue? (y/N): ';
		$confirmation = trim(fgets(STDIN));

		if (cacti_strtolower($confirmation) !== 'y') {
			print 'Migration cancelled by user' . PHP_EOL;

			return false;
		}

		print PHP_EOL;
		print 'Starting migration of ' . count($devices) . ' device(s)...' . PHP_EOL;
	}

	$success_count = 0;
	$error_count   = 0;

	foreach ($devices as $device) {
		// Get full device record for api_device_save
		$full_device = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', [$device['id']]);

		if (!empty($full_device)) {
			if (migrate_device($full_device, $dest_poller)) {
				$success_count++;

				if (!$quietMode) {
					print "Moved device: {$device['description']} (ID: {$device['id']}) to Poller $dest_poller" . PHP_EOL;
				}
			} else {
				$error_count++;
				print 'ERROR: Failed to migrate device ' . $device['id'] . ' (' . $device['description'] . ')' . PHP_EOL;
			}
		} else {
			$error_count++;
			print 'ERROR: Could not find device ' . $device['id'] . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
		print 'Migration completed!' . PHP_EOL;
		print "Successfully migrated: $success_count device(s)" . PHP_EOL;
		print "Failed migrations: $error_count device(s)" . PHP_EOL;

		if ($success_count > 0) {
			global $migration_log_file;
			print "Migration log written to: $migration_log_file" . PHP_EOL;
			print "Rollback data recorded in: $migration_log_file" . PHP_EOL;
		}
	}

	return true;
}

/**
 * Migrate devices from comma-separated host IDs
 *
 * @param string $host_ids_string Comma-separated list of host IDs
 * @param int    $source_poller   Source poller ID (for logging)
 * @param int    $dest_poller     Destination poller ID
 * @param bool   $quietMode       Whether to suppress output
 *
 * @return bool - False if there are problems
 */
function migrate_from_host_ids(string $host_ids_string, int $source_poller, int $dest_poller, bool $quietMode) : bool {
	// Parse comma-separated host IDs
	$host_id_array = array_map('trim', explode(',', $host_ids_string));
	$host_id_array = array_filter($host_id_array, function ($id) {
		return is_numeric($id) && $id > 0;
	});

	if (empty($host_id_array)) {
		print 'ERROR: No valid host IDs provided. Host IDs must be numeric and greater than 0.' . PHP_EOL;

		return false;
	}

	if (!$quietMode) {
		print 'Migration Mode: Specific host IDs' . PHP_EOL;
		print 'Host IDs to migrate: ' . implode(', ', $host_id_array) . PHP_EOL;
		print 'This will move ' . count($host_id_array) . " device(s) from any poller to Poller $dest_poller" . PHP_EOL . PHP_EOL;

		// Display devices to be migrated
		print 'Devices to be migrated:' . PHP_EOL;

		foreach ($host_id_array as $host_id) {
			$device = db_fetch_row_prepared('SELECT id, hostname, description, poller_id FROM host WHERE id = ?', [$host_id]);

			if (!empty($device)) {
				print "  ID: {$device['id']} - {$device['hostname']} ({$device['description']}) [Current Poller: {$device['poller_id']}]" . PHP_EOL;
			} else {
				print "  ID: $host_id - ERROR: Device not found" . PHP_EOL;
			}
		}

		print PHP_EOL;
		print 'Are you sure you want to continue? (y/N): ';
		$confirmation = trim(fgets(STDIN));

		if (cacti_strtolower($confirmation) !== 'y') {
			print 'Migration cancelled by user' . PHP_EOL;

			return false;
		}

		print PHP_EOL;
		print 'Starting migration of ' . count($host_id_array) . ' device(s)...' . PHP_EOL;
	}

	$success_count = 0;
	$error_count   = 0;

	foreach ($host_id_array as $host_id) {
		// Get full device record for api_device_save
		$device = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', [$host_id]);

		if (!empty($device)) {
			if (migrate_device($device, $dest_poller)) {
				$success_count++;

				if (!$quietMode) {
					print "Moved device: {$device['description']} (ID: {$device['id']}) from Poller {$device['poller_id']} to Poller $dest_poller" . PHP_EOL;
				}
			} else {
				$error_count++;
				print 'ERROR: Failed to migrate device ' . $device['id'] . ' (' . $device['description'] . ')' . PHP_EOL;
			}
		} else {
			$error_count++;
			print "ERROR: Could not find device with ID $host_id" . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
		print 'Migration completed!' . PHP_EOL;
		print "Successfully migrated: $success_count device(s)" . PHP_EOL;
		print "Failed migrations: $error_count device(s)" . PHP_EOL;

		if ($success_count > 0) {
			global $migration_log_file;
			print "Migration log written to: $migration_log_file" . PHP_EOL;
			print "Rollback data recorded in: $migration_log_file" . PHP_EOL;
		}
	}

	return true;
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	$version = get_cacti_cli_version();
	print "Cacti Add Permissions Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays the usage of the function
 *
 * @return void
 */
function display_help() : void {
	display_version();

	print PHP_EOL;
	print 'Purpose: This script is used to move devices off a given poller to another poller' . PHP_EOL . PHP_EOL;

	print 'Usage:' . PHP_EOL;
	print 'php ' . basename(__FILE__) . ' --source-poller=ID --dest-poller=ID [--all | --host-ids=ID1,ID2,...] [--quiet] [--help]' . PHP_EOL . PHP_EOL;

	print 'Required Arguments:' . PHP_EOL;
	print '    --source-poller=ID     Source Poller ID to move devices from' . PHP_EOL;
	print '    --dest-poller=ID       Destination Poller ID to move devices to' . PHP_EOL . PHP_EOL;

	print 'Migration Mode (choose exactly one):' . PHP_EOL;
	print '    --all                  Migrate all devices off the source poller' . PHP_EOL;
	print '    --host-ids=ID1,ID2,... Migrate specific devices by host ID (single ID or comma-separated list)' . PHP_EOL . PHP_EOL;

	print 'Optional Arguments:' . PHP_EOL;
	print '    --quiet                Suppress verbose output (batch mode)' . PHP_EOL;
	print '    --help, -h             Display this help message' . PHP_EOL;
	print '    --version, -v          Display version information' . PHP_EOL . PHP_EOL;

	print 'Output Files:' . PHP_EOL;
	print '    migration_log.txt      Timestamped log of all migration activities,' . PHP_EOL;
	print '                           including rollback data with original poller assignments' . PHP_EOL . PHP_EOL;

	print 'Examples:' . PHP_EOL;
	print '    # Migrate all devices from poller 1 to poller 2:' . PHP_EOL;
	print '    php ' . basename(__FILE__) . ' --source-poller=1 --dest-poller=2 --all' . PHP_EOL . PHP_EOL;
	print '    # Migrate a single device by host ID:' . PHP_EOL;
	print '    php ' . basename(__FILE__) . ' --source-poller=1 --dest-poller=2 --host-ids=123' . PHP_EOL;
	print '    # Migrate multiple devices by host IDs:' . PHP_EOL;
	print '    php ' . basename(__FILE__) . ' --source-poller=1 --dest-poller=2 --host-ids=123,124,125' . PHP_EOL . PHP_EOL;

	print 'Note: You must specify exactly one migration option: --all or --host-ids' . PHP_EOL . PHP_EOL;
}
