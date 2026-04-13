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

require(__DIR__ . '/include/cli_check.php');
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/api_device.php');
require_once(CACTI_PATH_LIBRARY . '/api_graph.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/rrd.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');
require_once(CACTI_PATH_LIBRARY . '/ping.php');
require_once(CACTI_PATH_LIBRARY . '/snmp.php');

// let PHP run just as long as it has to
ini_set('max_execution_time', '0');

error_reporting(E_ALL);
$dir = __DIR__;
chdir($dir);

global $database_default, $archived, $purged, $disable_log_rotation, $poller_start;

// record the start time
$poller_start = microtime(true);

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

$debug    = false;
$force    = false;
$archived = 0;
$purged   = 0;
$start    = microtime(true);

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (str_contains($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
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
			case '--force':
				$force = true;

				break;
			case '--debug':
				$debug = true;

				break;
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();

				exit(1);
		}
	}
}

maint_debug('Checking for Purge Actions');

// silently end if the registered process is still running, or process table missing
if (!$force) {
	$timeout = intval(read_config_option('maintenance_timeout'));

	if (!register_process_start('maintenance', 'master', POLLER_ID, $timeout)) {
		cacti_log('INFO: Another maintenance session is already running', false, 'MAINTENANCE', POLLER_VERBOSITY_LOW);

		exit(0);
	}
}

if (POLLER_ID == 1) {
	rrdfile_purge($force);

	authcache_purge();

	secpass_check_expired();

	reindex_devices();

	remove_aged_password_hashes();

	unlock_cacti();
}

// Check the realtime cache and poller
realtime_purge_cache();

// Remove deleted devices
api_device_purge_deleted_devices();

// Rotate Cacti Logs
logrotate_check($force);

// Detect number of CPU cores and optimal settings
cpu_cores_check();

// Remove deleted devices
remove_aged_row_cache();

// Update Object Totals Caches
if (POLLER_ID == 1) {
	update_graphs_data_source_templates_totals($force);
}

// Remove expired host value cache
purge_host_value_cache();

if (POLLER_ID > 1) {
	api_plugin_hook('poller_remote_maint');
}

phpversion_check($force);

$stats = device_recovery_sweep();

$end = microtime(true);

if ($stats['devices'] > 0) {
	cacti_log(sprintf('MAINT RECOVERY STATS: Time:%0.2f Checks:%s Recovered:%s', $stats['sweeptime'], $stats['devices'], $stats['recovered']), false, 'SYSTEM');
}

cacti_log(sprintf('MAINT TOTAL STATS: Time:%0.2f', $end - $start), false, 'SYSTEM');

if (!$force) {
	unregister_process('maintenance', 'master', POLLER_ID);
}

exit(0);

function unlock_cacti() : void {
	$lockout = read_config_option('cacti_lockout_status', true);

	if ($lockout != '') {
		$lockout = json_decode($lockout, true);

		if (time() - (30 * 60) > $lockout['time']) {
			set_config_option('cacti_lockout_status', '');

			cacti_log('WARNING: Cacti Maintenance Mode cleared by main Cacti Data Collector automatically!', true, 'SYSTEM');
		}
	}
}

function purge_host_value_cache() : void {
	if (db_table_exists('host_value_cache')) {
		db_execute('DELETE FROM host_value_cache
			WHERE time_to_live > 0
			AND UNIX_TIMESTAMP() - UNIX_TIMESTAMP(last_updated) > time_to_live');
	}
}

function device_recovery_sweep() : array {
	$start = microtime(true);

	maint_debug('Attempting to Recover Downed Devices using SNMP Options');

	$devices = db_fetch_assoc_prepared('SELECT *
		FROM host
		WHERE status = ?
		AND deleted = ""
		AND disabled = ""
		AND snmp_options > 0
		AND status_options_date < DATE_SUB(NOW(), INTERVAL ? SECOND)
		AND poller_id = ?',
		[HOST_DOWN, read_config_option('snmp_options_retry_interval'), POLLER_ID]);

	$snmp_columns = [
		'snmp_version',
		'snmp_community',
		'snmp_timeout',
		'snmp_retries',
		'snmp_username',
		'snmp_password',
		'snmp_auth_protocol',
		'snmp_priv_protocol',
		'snmp_priv_passphrase',
		'snmp_context',
		'snmp_engine_id'
	];

	$recovered    = 0;
	$down_devices = cacti_sizeof($devices);

	if (cacti_sizeof($devices)) {
		maint_debug(sprintf('Found %s Devices to Recover', cacti_sizeof($devices)));

		$options = db_fetch_assoc('SELECT * FROM automation_snmp_items ORDER BY sequence');
		$names   = array_rekey(db_fetch_assoc('SELECT * FROM automation_snmp'), 'id', 'name');

		if (cacti_sizeof($options)) {
			foreach ($devices as $d) {
				$device_up = false;

				$start = microtime(true);

				foreach ($options as $o) {
					$ping = new Net_Ping;

					$thost = $d;

					foreach ($snmp_columns as $c) {
						$thost[$c] = $o[$c];
					}

					switch($thost['availability_method']) {
						case AVAIL_NONE:
						case AVAIL_PING:
							$thost['availability_method'] = AVAIL_SNMP;

							break;
					}

					$ping->host = $thost;
					$ping->port = $thost['ping_port'];

					if ($ping->ping($thost['availability_method'], $thost['ping_method'], $thost['ping_timeout'], $thost['ping_retries'])) {
						cacti_log(sprintf('RECOVERY STATS: Time:%0.2f Device[%s] STATUS: Device \'%s\' brought UP with Options Set [%s]', microtime(true) - $start, $thost['id'], $thost['hostname'], $names[$o['snmp_id']]), true, 'SYSTEM');

						$sql        = 'UPDATE host SET ';
						$sql_params = [];

						foreach ($snmp_columns as $i => $c) {
							$sql .= ($i > 0 ? ', ' : '') . "$c = ?";
							$sql_params[] = $thost[$c];
						}

						$sql .= ', status = 3, status_rec_date = NOW() WHERE id = ?';
						$sql_params[] = $thost['id'];

						db_execute_prepared($sql, $sql_params);

						$device_up = true;
						$recovered++;

						break;
					}
				}

				if (!$device_up && isset($thost['id'])) {
					cacti_log(sprintf('RECOVERY STATS: Time:%0.2f Device[%s] STATUS: Device \'%s\' remains Down. No matching Options Sets.', microtime(true) - $start, $thost['id'], $thost['hostname']), true, 'SYSTEM');
					db_execute_prepared('UPDATE host SET status_options_date = NOW() WHERE id = ?', [$thost['id']]);
				}
			}
		}
	} else {
		maint_debug(sprintf('Found 0 Devices to Recover'));
	}

	$time = microtime(true) - $start;

	return ['devices' => $down_devices, 'recovered' => $recovered, 'sweeptime' => $time];
}

function update_graphs_data_source_templates_totals(bool $force) : bool {
	// Don't run this script too often
	$last_run   = read_config_option('maintenance_totals_update');
	$last_graph = read_config_option('time_last_change_graph');
	$cur_time   = time();

	$gr_update = ($last_run < $last_graph ? true : false);
	$tm_update = ($cur_time - $last_run > 3600 ? true : false);

	if (!empty($last_run) && !$gr_update && !$tm_update && !$force) {
		return false;
	}

	set_config_option('maintenance_totals_update', time());

	if (db_column_exists('host_template', 'devices')) {
		object_cache_update_device_totals();
		object_cache_update_data_source_totals();
		object_cache_update_graph_totals();
		object_cache_update_aggregate_totals();
	}

	return true;
}

function reindex_devices() : bool {
	$schedule = read_config_option('automatic_reindex');

	// 0 - Disabled
	// 1 - Daily at Midnight
	// 2 - Weekly on Sunday
	// 3 - Monthly on Sunday

	$command_string = cacti_escapeshellcmd(read_config_option('path_php_binary'));
	$extra_args     = CACTI_PATH_CLI . '/poller_reindex_hosts.php --id=all --qid=all';

	if ($schedule == 0) {
		return false;
	}

	$last_run = intval(read_config_option('periodic_reindex_lastrun'));
	$now      = time();

	if (empty($last_run)) {
		set_config_option('periodic_reindex_lastrun', time());

		return false;
	} else {
		if ($schedule == 1) {
			if (date('z', $now) != date('z', $last_run)) {
				set_config_option('periodic_reindex_lastrun', $now);
				exec_background($command_string, $extra_args);
			}
		} elseif ($schedule == 2) {
			if (date('z', $now) != date('z', $last_run) && date('w', $now) == 0) {
				exec_background($command_string, $extra_args);
			}
		} elseif ($schedule == 3) {
			if (date('z', $now) != date('z', $last_run)) {
				if (date('w', $now) == 0 && date('n', $now) != date('n', $last_run)) {
					exec_background($command_string, $extra_args);
				}
			}
		}
	}

	return true;
}

function remove_aged_row_cache() : void {
	$classes = array_rekey(
		db_fetch_assoc('SELECT REPLACE(name, "time_last_change_", "") AS name, value
			FROM settings
			WHERE name LIKE "time_last_change%"'),
		'name', 'value'
	);

	if (cacti_sizeof($classes)) {
		foreach ($classes as $name => $ts) {
			db_execute_prepared('DELETE FROM user_auth_row_cache
				WHERE class = ? AND UNIX_TIMESTAMP(time) < ?',
				[$name, $ts]);
		}
	}
}

function remove_aged_password_hashes() : void {
	db_execute('DELETE FROM user_auth_reset_hashes
		WHERE expiry < NOW()');
}

function logrotate_check(bool $force) : void {
	global $disable_log_rotation;

	// Check whether the cacti log.  Rotations takes place around midnight
	if (isset($disable_log_rotation) && $disable_log_rotation == true) {
		// Skip log rotation as it's handled by logrotate.d
	} elseif (read_config_option('logrotate_enabled') == 'on') {
		$frequency  = read_config_option('logrotate_frequency');

		if (empty($frequency)) {
			$frequency = 1;
		}

		$last = read_config_option('logrotate_lastrun');
		$now  = time();

		if (empty($last)) {
			$last = time();
			set_config_option('logrotate_lastrun', $last);
		}

		$date_now = new DateTime();
		$date_now->setTimestamp($now);

		// Take the last date/time, set the time to 59 seconds past midnight
		// then remove one minute to make it the previous evening
		$date_orig = new DateTime();
		$date_orig->setTimestamp($last);
		$date_last = new DateTime();
		$date_last->setTimestamp($last)->setTime(0,0,59)->modify('-1 minute');

		// Make sure we clone the last date, or we end up modifying the same object!
		$date_next = clone $date_last;
		$date_next->modify('+' . $frequency . 'day');

		cacti_log('Cacti Log Rotation - TIMECHECK Ran: ' . $date_orig->format('Y-m-d H:i:s')
			. ', Now: ' . $date_now->format('Y-m-d H:i:s')
			. ', Next: ' . $date_next->format('Y-m-d H:i:s'), true, 'MAINT', POLLER_VERBOSITY_HIGH);

		if ($date_next < $date_now || $force) {
			logrotate_rotatenow();
		}
	}
}

function authcache_purge() : void {
	// removing security tokens older than 90 days
	if (read_config_option('auth_cache_enabled') == 'on') {
		db_execute_prepared('DELETE FROM user_auth_cache
			WHERE last_update < ?',
			[date('Y-m-d H:i:s', time() - (86400 * 90))]);
	} else {
		db_execute('TRUNCATE TABLE user_auth_cache');
	}
}

function rrdfile_purge(bool $force) : void {
	global $archived, $purged, $poller_start;

	// are my tables already present?
	$purge = db_fetch_cell('SELECT COUNT(*)
		FROM data_source_purge_action');

	// if the table that holds the actions is present, work on it
	if ($purge) {
		maint_debug("Purging Required - Files Found $purge");

		// take the purge in steps
		while (true) {
			maint_debug('Grabbing 1000 RRDfiles to Remove');

			$file_array = db_fetch_assoc('SELECT DISTINCT id, name, local_data_id, action
				FROM data_source_purge_action
				ORDER BY name
				LIMIT 1000');

			if (cacti_sizeof($file_array) == 0) {
				break;
			}

			if (cacti_sizeof($file_array) > 0 || $force) {
				// there's something to do for us now
				remove_files($file_array);

				if ($force) {
					cleanup_ds_and_graphs();
				}
			} else {
				maint_debug('No RRDfiles found for archiving or removal');
			}
		}

		// record the start time
		set_config_option('rrdcleaner_last_run_time', time());

		$poller_end = microtime(true);
		set_config_option('rrdcleaner_last_run_time', time());
		$string = sprintf('RRDMAINT STATS: Time:%4.4f Purged:%s Archived:%s', ($poller_end - $poller_start), $purged, $archived);
		cacti_log($string, true, 'SYSTEM');
	} else {
		maint_debug('No RRDfiles scheduled for arching or removal');
	}
}

/**
 * realtime_purge_cache() - This function will purge files in the realtime directory
 * that are older than 2 hours without changes
 *
 * @return void
 */
function realtime_purge_cache() : void {
	// remove all Realtime files over than 2 hours
	if (read_config_option('realtime_cache_path') != '') {
		$cache_path = read_config_option('realtime_cache_path');

		if (is_dir($cache_path) && is_writeable($cache_path)) {
			foreach (new DirectoryIterator($cache_path) as $fileInfo) {
				if ($fileInfo->isDot()) {
					continue;
				}

				// only remove .png and .rrd files
				if ((substr($fileInfo->getFilename(), -4, 4) == '.png') || (substr($fileInfo->getFilename(), -4, 4) == '.rrd')) {
					if ((time() - $fileInfo->getMTime()) >= 7200) {
						unlink($fileInfo->getRealPath());
					}
				}
			}
		}
	}

	db_execute('DELETE FROM poller_output_realtime WHERE time < FROM_UNIXTIME(UNIX_TIMESTAMP()-300)');
}

/**
 * logrotate_rotatenow - Rotates the cacti log
 *
 * @return void
 */
function logrotate_rotatenow() : void {
	$poller_start = microtime(true);

	$logs = [];
	$log  = read_config_option('path_cactilog');

	if (empty($log)) {
		$log = CACTI_PATH_LOG . '/cacti.log';
	}
	$logs['Cacti'] = $log;

	$log = read_config_option('path_stderrlog');

	if (!empty($log)) {
		$logs['Cacti StdErr'] = $log;
	}

	$log = read_config_option('path_boost_log');

	if (!empty($log)) {
		$logs['Cacti Boost'] = $log;
	}

	$run_time = time();
	set_config_option('logrotate_lastrun', $run_time);

	$date     = new DateTime();
	$date->setTimestamp($run_time)->modify('-1day');

	$rotated = 0;
	$cleaned = 0;

	$days = read_config_option('logrotate_retain');

	if ($days == '' || $days < 0) {
		$days = 7;
	}

	if ($days > 365) {
		$days = 365;
	}

	foreach ($logs as $name => $log) {
		$rotated += logrotate_file_rotate($name, $log, $date);
		$cleaned += logrotate_file_clean($name, $log, $date, $days);
	}

	$cleaned += logrotate_file_clean($name, $log, $date, $days);

	// record the start time
	$poller_end = microtime(true);
	$string     = sprintf('LOGMAINT STATS: Time:%4.4f, Rotated:%d, Removed:%d, Days Retained:%d', ($poller_end - $poller_start), $rotated, $cleaned, $days);

	cacti_log($string, true, 'SYSTEM');
}

/**
 * logrotate_file_rotate() - rotates the specified log file, appending date given
 *
 * @param string $name
 * @param string $log
 * @param object $date
 *
 * @return int
 */
function logrotate_file_rotate(string $name, string $log, object $date) : int {
	if (empty($log)) {
		return 0;
	}

	clearstatcache();

	if (!file_exists($log)) {
		cacti_log('Cacti Log Rotation - Skipped missing ' . $name . ' Log : ' . $log, true, 'MAINT');

		return 0;
	}

	if (is_writable(dirname($log) . '/') && is_writable($log)) {
		$perms = octdec(substr(decoct(fileperms($log)), 2));
		$owner = fileowner($log);
		$group = filegroup($log);

		if ($owner !== false) {
			$ext = $date->format('Ymd');

			if (file_exists($log . '-' . $ext)) {
				$ext_inc = 1;

				while (file_exists($log . '-' . $ext . '-' . $ext_inc) && $ext_inc < 99) {
					$ext_inc++;
				}

				$ext = $ext . '-' . $ext_inc;
			}

			if (rename($log, $log . '-' . $ext)) {
				touch($log);
				chown($log, $owner);
				chgrp($log, $group);
				chmod($log, $perms);

				cacti_log('Cacti Log Rotation - Created ' . $name . ' Log : ' . basename($log) . '-' . $ext, true, 'MAINT');

				return 1;
			} else {
				cacti_log('Cacti Log Rotation - ERROR: Could not rename ' . $name . ' Log "' . basename($log) . '" to "' . basename($log) . '-' . $ext . '"', true, 'MAINT');
			}
		} else {
			cacti_log('Cacti Log Rotation - ERROR: Permissions issue.  Please check your ' . $name . ' Log directory : ' . basename($log), true, 'MAINT');
		}
	} else {
		cacti_log('Cacti Log Rotation - ERROR: Permissions issue.  Please check your ' . $name . ' Log as directory or file are not writable : ' . $log, true, 'MAINT');
	}

	return 0;
}

/**
 * logrotate_file_clean - Cleans up any old log files that should be removed
 * @param string $name
 * @param string $log
 * @param object $date
 * @param int    $rotation
 *
 * @return bool
 */
function logrotate_file_clean(string $name, string $log, object $date, int $rotation) : bool {
	if (empty($log)) {
		return false;
	}

	if ($rotation <= 0) {
		return false;
	}

	$baselogdir  = dirname($log) . '/';
	$baselogname = basename($log);

	clearstatcache();
	$dir = scandir($baselogdir);

	if (cacti_sizeof($dir)) {
		$date_log = clone $date;
		$date_log->modify('-' . $rotation . 'day');
		$e = $date_log->format('Ymd');

		cacti_log('Cacti Log Rotation - Purging all ' . $name . ' logs before ' . $e, true, 'MAINT');

		foreach ($dir as $d) {
			$fileparts = explode('-', $d);
			$matches   = false;

			if (str_contains($d, $baselogname)) {
				if (cacti_sizeof($fileparts) > 1) {
					foreach ($fileparts as $p) {
						// Is it in the form YYYYMMDD?
						if (is_numeric($p) && strlen($p) == 8) {
							$matches = true;

							if ($p < $e) {
								if (is_writable($baselogdir . $d)) {
									@unlink($baselogdir . $d);
									cacti_log('Cacti Log Rotation - Purging ' . $name . ' Log : ' . $d, true, 'MAINT');
								} else {
									cacti_log('Cacti Log Rotation - ERROR: Can not purge ' . $name . ' Log : ' . $d, true, 'MAINT');
								}
							} else {
								cacti_log('Cacti Log Rotation - NOTE: Not expired, keeping ' . $name . ' Log : ' . $d, true, 'MAINT', POLLER_VERBOSITY_HIGH);
							}
						}
					}
				}
			}

			if ($matches) {
				cacti_log('Cacti Log Rotation - NOTE: File not in expected naming format, ignoring ' . $name . ' Log : ' . $d, true, 'MAINT', POLLER_VERBOSITY_DEBUG);
			}
		}
	}

	clearstatcache();

	return true;
}

/**
 * secpass_check_expired - Checks user accounts to determine if the accounts and/or their passwords should be expired
 */
function secpass_check_expired() : void {
	maint_debug('Checking for Account / Password expiration');

	// Expire Old Accounts
	$e = read_config_option('secpass_expireaccount');

	if ($e > 0 && is_numeric($e)) {
		$t = time();
		db_execute_prepared("UPDATE user_auth
			SET lastlogin = ?
			WHERE lastlogin = -1
			AND realm = 0
			AND enabled = 'on'",
			[$t]);

		$t = $t - (intval($e) * 86400);

		db_execute_prepared("UPDATE user_auth
			SET enabled = ''
			WHERE realm = 0
			AND enabled = 'on'
			AND lastlogin < ?
			AND id > 1",
			[$t]);
	}

	$e = read_config_option('secpass_expirepass');

	if ($e > 0 && is_numeric($e)) {
		$t = time();
		db_execute_prepared("UPDATE user_auth
			SET lastchange = ?
			WHERE lastchange = -1
			AND realm = 0
			AND enabled = 'on'",
			[$t]);

		$t = $t - (intval($e) * 86400);

		db_execute_prepared("UPDATE user_auth
			SET must_change_password = 'on'
			WHERE realm = 0
			AND enabled = 'on'
			AND lastchange < ?",
			[$t]);
	}
}

// remove_files - remove all unwanted files; the list is given by table data_source_purge_action
function remove_files(array $file_array) : void {
	global $debug, $archived, $purged;

	maint_debug('RRDClean is now running on ' . cacti_sizeof($file_array) . ' items');

	$rra_path     = CACTI_PATH_RRA;
	$rrdtool_pipe = null;
	$rrd_archive  = '';

	if (read_config_option('storage_location')) {
		$rrdtool_pipe = rrd_init();

		rrdtool_execute('setcnn timeout off', false, RRDTOOL_OUTPUT_NULL, $rrdtool_pipe, $logopt = 'POLLER');
	} else {
		// let's prepare the archive directory
		$rrd_archive = read_config_option('rrd_archive', true);

		if ($rrd_archive == '') {
			$rrd_archive = $rra_path . '/archive';
		}

		$rrd_archive = rtrim($rrd_archive, '/');
		rrdclean_create_path($rrd_archive);
	}

	// now scan the files
	foreach ($file_array as $file) {
		// the variables shouldn't be here, but I'm keeping them just in case
		$real_file = str_replace('<path_rra>', $rra_path, $file['name']);
		$real_file = str_replace('<path_cacti>', CACTI_PATH_BASE, $real_file);

		if ($real_file == $file['name']) {
			$real_file = $rra_path . '/' . $file['name'];
		}

		$base_file = str_replace('<path_rra>', '', $file['name']);
		$base_file = str_replace('<path_cacti>', '', $base_file);

		if (read_config_option('storage_location') == 0) {
			switch ($file['action']) {
				case '1':
					if (file_exists($real_file) && cacti_strtolower(pathinfo($real_file, PATHINFO_EXTENSION)) === 'rrd') {
						if (unlink($real_file)) {
							maint_debug('Deleted: ' . $real_file);
							$purged++;
						} else {
							cacti_log("WARNING: RRDfile Maintenance is unable to remove $real_file from $rra_path!", true, 'MAINT');
						}
					}

					break;
				case '3':
					$target_file = $rrd_archive . '/' . $base_file;
					$target_dir  = dirname($target_file);

					if (!is_dir($target_dir)) {
						rrdclean_create_path($target_dir);
					}

					if (file_exists($real_file)) {
						if (rename($real_file, $target_file)) {
							maint_debug("Moved: $real_file to: $target_file");
							$archived++;
						} else {
							cacti_log("WARNING: RRDfile Maintenance is unable to move $real_file to $target_file!", true, 'MAINT');
						}
					}

					break;
			}
		} else {
			switch($file['action']) {
				case '1':
					if (rrdtool_execute('unlink ' . $file['name'], false, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, $logopt = 'MAINT')) {
						maint_debug('Deleted: ' . $file['name']);
					} else {
						cacti_log("WARNING RRDfile Maintenance is unable to remove {$file['name']} from the RRDproxy!", true, 'MAINT');
					}

					$purged++;

					break;
				case '3':
					if (rrdtool_execute('archive ' . $file['name'], false, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, $logopt = 'MAINT')) {
						maint_debug("Moved: {file['name']} to: RRDproxy Archive");
					} else {
						cacti_log("WARNING RRDfile Maintenance is unable to move {$file['name']} to the RRDproxy Archive!", true, 'MAINT');
					}

					$archived++;

					break;
			}
		}

		// drop from data_source_purge_action table
		db_execute_prepared('DELETE FROM `data_source_purge_action`
			WHERE name = ?',
			[$file['name']]);

		maint_debug('Delete from data_source_purge_action: ' . $file['name']);

		// fetch all local_graph_id's according to this data source
		$lgis = db_fetch_assoc_prepared('SELECT DISTINCT gl.id
			FROM graph_local AS gl
			INNER JOIN graph_templates_item AS gti
			ON gl.id = gti.local_graph_id
			INNER JOIN data_template_rrd AS dtr
			ON dtr.id=gti.task_item_id
			INNER JOIN data_local AS dl
			ON dtr.local_data_id=dl.id
			WHERE (local_data_id=?)',
			[$file['local_data_id']]);

		if (cacti_sizeof($lgis)) {
			// anything found?
			maint_debug('Processing ' . cacti_sizeof($lgis) . ' Graphs for data source id: ' . $file['local_data_id']);

			// get them all
			foreach ($lgis as $item) {
				$remove_lgis[] = $item['id'];
				maint_debug('remove local_graph_id=' . $item['id']);
			}

			// and remove them in a single run
			if (!empty($remove_lgis)) {
				api_graph_remove_multi($remove_lgis);
			}
		}

		// remove related data source if any
		if ($file['local_data_id'] > 0) {
			maint_debug('Removing Data Source: ' . $file['local_data_id']);
			api_data_source_remove($file['local_data_id']);
		}
	}

	if (read_config_option('storage_location')) {
		rrd_close($rrdtool_pipe);
	}

	maint_debug('RRDClean has finished a purge pass of ' . cacti_sizeof($file_array) . ' items');
}

function rrdclean_create_path(string $path) : bool {
	if (!is_dir($path)) {
		if (mkdir($path, 0775, true)) {
			if (CACTI_SERVER_OS != 'win32') {
				$owner_id = fileowner(CACTI_PATH_RRA);
				$group_id = filegroup(CACTI_PATH_RRA);

				// NOTE: chown/chgrp fails for non-root users, checking their
				// result is therefore irrelevant
				@chown($path, $owner_id);
				@chgrp($path, $group_id);
			}
		} else {
			cacti_log("ERROR: RRDfile Maintenance unable to create directory '" . $path . "'", false, 'MAINT');
		}
	}

	// if path existed, we can return true
	return is_dir($path) && is_writable($path);
}

// cleanup_ds_and_graphs - courtesy John Rembo
function cleanup_ds_and_graphs() : mixed {
	$remove_ldis = [];
	$remove_lgis = [];

	maint_debug('RRDClean now cleans up all data sources and graphs');

	// fetch all local_data_id's which have appropriate data-sources
	$rrds = db_fetch_assoc("SELECT local_data_id, name_cache, data_source_path
		FROM data_template_data
		WHERE name_cache > ''");

	// filter those whose rrd files doesn't exist
	foreach ($rrds as $item) {
		$ldi      = $item['local_data_id'];
		$name     = $item['name_cache'];
		$ds_pth   = $item['data_source_path'];
		$real_pth = str_replace('<path_rra>', CACTI_PATH_RRA, $ds_pth);

		if (!file_exists($real_pth)) {
			if (!in_array($ldi, $remove_ldis, true)) {
				$remove_ldis[] = $ldi;
				maint_debug("RRD file is missing for data source name: $name (local_data_id=$ldi)");
			}
		}
	}

	if (empty($remove_ldis)) {
		maint_debug('No missing rrd files found');

		return false;
	}

	maint_debug('Processing Graphs');
	// fetch all local_graph_id's according to filtered rrds
	$lgis = db_fetch_assoc('SELECT DISTINCT gl.id
		FROM graph_local AS gl
		INNER JOIN graph_templates_item AS gti
		ON gl.id=gti.local_graph_id
		INNER JOIN data_template_rrd AS dtr
		ON dtr.id=gti.task_item_id
		INNER JOIN data_local AS dl
		ON dtr.local_data_id=dl.id
		WHERE (' . array_to_sql_or($remove_ldis, 'local_data_id') . ')');

	foreach ($lgis as $item) {
		$remove_lgis[] = $item['id'];
		maint_debug('RRD file missing for local_graph_id=' . $item['id']);
	}

	if (!empty($remove_lgis)) {
		maint_debug('removing graphs');
		api_graph_remove_multi($remove_lgis);
	}

	maint_debug('removing data sources');
	api_data_source_remove_multi($remove_ldis);

	maint_debug('removed graphs:' . cacti_count($remove_lgis) . ' removed data-sources:' . cacti_count($remove_ldis));

	return cacti_count($remove_lgis);
}

function maint_debug(string $message) : void {
	global $debug;

	if ($debug) {
		print 'DEBUG: ' . trim($message) . "\n";
	}
}

/**
 * display_version - displays version information
 */
function display_version() : void {
	$version = get_cacti_cli_version();
	print "Cacti Maintenance Poller, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/**
 * display_help - displays the usage of the function
 */
function display_help() : void {
	display_version();

	print "\nusage: poller_maintenance.php [--force] [--debug]\n\n";
	print "Cacti's maintenance poller.  This poller is responsible for executing periodic\n";
	print "maintenance activities for Cacti including log rotation, deactivating accounts, etc.\n\n";
	print "Optional:\n";
	print "    --force   - Force immediate execution, e.g. for testing.\n";
	print "    --debug   - Display verbose output during execution.\n\n";
}

function phpversion_check(bool $force = false) : void {
	$now  = time();
	$last = db_fetch_cell('select value from settings where name = "phpver_last"');

	if (empty($last)) {
		$last = $now - 86500;
	}

	$date_now = new DateTime();
	$date_now->setTimestamp($now);

	// Take the last date/time, set the time to 59 seconds past midnight
	// then remove one minute to make it the previous evening
	$date_orig = new DateTime();
	$date_orig->setTimestamp($last);
	$date_last = new DateTime();
	$date_last->setTimestamp($last)->setTime(0,0,59)->modify('-1 minute');

	// Make sure we clone the last date, or we end up modifying the same object!
	$date_next = clone $date_last;
	$date_next->modify('+1day');

	$phpbad_ver = version_compare(PHP_VERSION,'7.4','<');

	if ($phpbad_ver && ($date_next < $date_now || $force)) {
		cacti_log('WARNING: PHP Version "' . PHP_VERSION . '"will not be supported by the develop branch in the future.  If you cannot upgrade to PHP 7.1 or higher, please switch branches', false, 'CACTI');
		db_execute_prepared('REPLACE INTO settings (name, value) VALUES ("phpver_last", ?)', [$now]);
	}
}

function cpu_cores_check() : void {
	$now         = time();
	$cores       = detect_cpu_cores();
	$name_last   = 'cpu_cores_last_' . POLLER_ID;
	$name_count  = 'cpu_cores_count_' . POLLER_ID;
	$poller_type = read_config_option('poller_type');

	$poller_settings = db_fetch_row_prepared('SELECT name, processes, threads
		FROM poller
		WHERE id = ?',
		[POLLER_ID]);

	$last_notify = db_fetch_cell_prepared('select value from settings where name = ?', [$name_last]);
	$last_count  = db_fetch_cell_prepared('select value from settings where name = ?', [$name_count]);

	if (!$last_count) {
		db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)', [$name_last, $now]);
		db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)', [$name_count, $cores]);
	} elseif ($cores != $last_count && $last_notify < ($now - 86400)) {
		cacti_log('WARNING: CPU cores changed. Maybe you should adjust the Poller settings (Processes and threads)', true, 'POLLER');
		admin_email(__('Cacti System Warning'), __('WARNING: CPU cores changed for poller %d with name %s. Maybe you should adjust the Poller settings (Processes and threads)', POLLER_ID, $poller_settings['name']));
		db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)', [$name_last, $now]);
		db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)', [$name_count, $cores]);
	}

	if ($poller_type == 1 && $poller_settings['processes'] < 2 && $cores > $poller_settings['processes'] && $last_notify < ($now - 86400)) {
		cacti_log('WARNING: Default setting number of processes. It looks like this cmd poller uses default settings. To achieve optimal performance, change poller settings (Processes)', true, 'POLLER');
		admin_email(__('Cacti System Warning'), __('WARNING: Default number of processes on poller %d with name %s. It looks like this cmd poller uses default settings. To achieve optimal performance, change poller settings (Processes)', POLLER_ID, $poller_settings['name']));
		db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)', [$name_last, $now]);
	} elseif ($poller_type == 2 && $poller_settings['threads'] <= 2 && $cores > $poller_settings['processes'] && $last_notify < ($now - 86400)) {
		cacti_log('WARNING: Default setting number of processes/threads. It looks like this spine poller uses default settings. To achieve optimal performance, change poller settings (Processes and threads)', true, 'POLLER');
		admin_email(__('Cacti System Warning'), __('WARNING: Default number of processes/threads on poller %d with name %s. It looks like this spine poller uses default settings. To achieve optimal performance, change poller settings (Processes and threads)', POLLER_ID, $poller_settings['name']));
		db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)', [$name_last, $now]);
	}

	if ($poller_type == 1 && ($cores * 2) < $poller_settings['processes'] && $last_notify < ($now - 86400)) {
		cacti_log('WARNING: Number of CMD poller processes is too high. To achieve optimal performance, change poller settings (Processes)', true, 'POLLER');
		admin_email(__('Cacti System Warning'), __('WARNING: Number of CMD poller processes on poller %d with name %s is too high. To achieve optimal performance, change poller settings (Processes)', POLLER_ID, $poller_settings['name']));
		db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)', [$name_last, $now]);
	}
}
