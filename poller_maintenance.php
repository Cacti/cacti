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

require(__DIR__ . '/include/cli_check.php');
require_once($config['library_path'] . '/api_data_source.php');
require_once($config['library_path'] . '/api_device.php');
require_once($config['library_path'] . '/api_graph.php');
include_once($config['library_path'] . '/poller.php');
require_once($config['library_path'] . '/rrd.php');
require_once($config['library_path'] . '/utility.php');

/* let PHP run just as long as it has to */
ini_set('max_execution_time', '0');

error_reporting(E_ALL);
$dir = dirname(__FILE__);
chdir($dir);

global $config, $database_default, $archived, $purged, $disable_log_rotation, $poller_start;

/* record the start time */
$poller_start = microtime(true);

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug    = false;
$force    = false;
$archived = 0;
$purged   = 0;
$start    = microtime(true);

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--version' :
			case '-V' :
			case '-v' :
				display_version();
				exit(0);
			case '--help' :
			case '-H' :
			case '-h' :
				display_help();
				exit(0);
			case '--force' :
				$force = true;
				break;
			case '--debug' :
				$debug = true;
				break;
			default :
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit(1);
		}
	}
}

maint_debug('Checking for Purge Actions');

/* silently end if the registered process is still running, or process table missing */
if (!register_process_start('maintenance', 'master', $config['poller_id'], read_config_option('maintenance_timeout'))) {
	exit(0);
}

if ($config['poller_id'] == 1) {
	rrdfile_purge($force);

	authcache_purge();

	secpass_check_expired();

	reindex_devices();
}

// Check the realtime cache and poller
realtime_purge_cache();

// Remove deleted devices
api_device_purge_deleted_devices();

// Rotate Cacti Logs
logrotate_check($force);

// Remove deleted devices
remove_aged_row_cache();

if ($config['poller_id'] > 1) {
	api_plugin_hook('poller_remote_maint');
}

$end = microtime(true);

cacti_log(sprintf('MAINT STATS: Time:%0.2f', $end - $start), false, 'SYSTEM');

unregister_process('maintenance', 'master', $config['poller_id']);

exit(0);

function reindex_devices() {
	global $config;

	$schedule = read_config_option('automatic_reindex');

	// 0 - Disabled
	// 1 - Daily at Midnight
	// 2 - Weekly on Sunday
	// 3 - Monthly on Sunday

	$command_string = cacti_escapeshellcmd(read_config_option('path_php_binary'));
	$extra_args     = $config['base_path'] . '/cli/poller_reindex_hosts.php --id=all --qid=all';

	if ($schedule == 0) {
		return;
	}

	$last_run = read_config_option('periodic_reindex_lastrun');
	$now      = time();

	if (empty($last_run)) {
		set_config_option('periodic_reindex_lastrun', time());
		return;
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
}

function remove_aged_row_cache() {
	$classes = array_rekey(
		db_fetch_assoc('SELECT REPLACE(name, "time_last_change_", "") AS name, value
			FROM settings
			WHERE name LIKE "time_last_change%"'),
		'name', 'value'
	);

	if (cacti_sizeof($classes)) {
		foreach($classes as $name => $ts) {
			db_execute_prepared('DELETE FROM user_auth_row_cache
				WHERE class = ? AND UNIX_TIMESTAMP(time) < ?',
				array($name, $ts));
		}
	}
}

function logrotate_check($force) {
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
		$date_next->modify('+'.$frequency.'day');

		cacti_log('Cacti Log Rotation - TIMECHECK Ran: ' . $date_orig->format('Y-m-d H:i:s')
			. ', Now: ' . $date_now->format('Y-m-d H:i:s')
			. ', Next: ' . $date_next->format('Y-m-d H:i:s'), true, 'MAINT', POLLER_VERBOSITY_HIGH);

		if ($date_next < $date_now || $force) {
			logrotate_rotatenow();
		}
	}
}

function authcache_purge() {
	/* removing security tokens older than 90 days */
	if (read_config_option('auth_cache_enabled') == 'on') {
		db_execute_prepared('DELETE FROM user_auth_cache
			WHERE last_update < ?',
			array(date('Y-m-d H:i:s', time()-(86400*90))));
	} else {
		db_execute('TRUNCATE TABLE user_auth_cache');
	}
}

function rrdfile_purge($force) {
	global $archived, $purged, $poller_start;

	/* are my tables already present? */
	$purge = db_fetch_cell('SELECT count(*)
		FROM data_source_purge_action');

	/* if the table that holds the actions is present, work on it */
	if ($purge) {
		maint_debug("Purging Required - Files Found $purge");

		/* take the purge in steps */
		while (true) {
			maint_debug('Grabbing 1000 RRDfiles to Remove');

			$file_array = db_fetch_assoc('SELECT id, name, local_data_id, action
				FROM data_source_purge_action
				ORDER BY name
				LIMIT 1000');

			if (cacti_sizeof($file_array) == 0) {
				break;
			}

			if (cacti_sizeof($file_array) || $force) {
				/* there's something to do for us now */
				remove_files($file_array);

				if ($force) {
					cleanup_ds_and_graphs();
				}
			}
		}

		/* record the start time */
		$poller_end = microtime(true);
		set_config_option('rrdcleaner_last_run_time', date('Y-m-d G:i:s', time()));
		$string = sprintf('RRDMAINT STATS: Time:%4.4f Purged:%s Archived:%s', ($poller_end - $poller_start), $purged, $archived);
		cacti_log($string, true, 'SYSTEM');
	}
}

/** realtime_purge_cache() - This function will purge files in the realtime directory
 *  that are older than 2 hours without changes */
function realtime_purge_cache() {
	/* remove all Realtime files over than 2 hours */
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

	db_execute("DELETE FROM poller_output_realtime WHERE time < FROM_UNIXTIME(UNIX_TIMESTAMP()-300)");
}

/*
 * logrotate_rotatenow
 * Rotates the cacti log
 */
function logrotate_rotatenow() {
	global $config;

	$poller_start = microtime(true);

	$logs = array();
	$log = read_config_option('path_cactilog');
	if (empty($log)) {
		$log = $config['base_path'] . '/log/cacti.log';
	}
	$logs['Cacti'] = $log;

	$log = read_config_option('path_stderrlog');
	if (!empty($log)) {
		$logs['Cacti StdErr'] = $log;
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

	/* record the start time */
	$poller_end = microtime(true);
	$string = sprintf('LOGMAINT STATS: Time:%4.4f, Rotated:%d, Removed:%d, Days Retained:%d', ($poller_end - $poller_start), $rotated, $cleaned, $days);
	cacti_log($string, true, 'SYSTEM');
}

/* logrotate_file_rotate()
 * rotates the specified log file, appending date given
 */
function logrotate_file_rotate($name, $log, $date) {
	if (empty($log)) {
		return 0;
	}

	clearstatcache();
	if (!file_exists($log)) {
		cacti_log('Cacti Log Rotation - Skipped missing ' . $name . ' Log : ' . $log, true, 'MAINT');
		return 0;
	}

	if (is_writable(dirname($log) . '/') && is_writable($log)) {
		$perms = octdec(substr(decoct( fileperms($log) ), 2));
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
			cacti_log('Cacti Log Rotation - ERROR: Permissions issue.  Please check your ' . $name  . ' Log directory : ' . basename($log), true, 'MAINT');
		}
	} else {
		cacti_log('Cacti Log Rotation - ERROR: Permissions issue.  Please check your ' . $name . ' Log as directory or file are not writable : ' . $log, true, 'MAINT');
	}
	return 0;
}

/*
 * logrotate_file_clean
 * Cleans up any old log files that should be removed
 */
function logrotate_file_clean($name, $log, $date, $rotation) {
	global $config;

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
		$date_log->modify('-'.$rotation.'day');
		$e = $date_log->format('Ymd');

		cacti_log('Cacti Log Rotation - Purging all ' . $name . ' logs before '. $e, true, 'MAINT');

		foreach ($dir as $d) {
			$fileparts = explode('-', $d);
			$matches   = false;

			if (strpos($d, $baselogname) !== false) {
				if ($fileparts > 1) {
					foreach($fileparts as $p) {
						// Is it in the form YYYYMMDD?
						if (is_numeric($p) && strlen($p) == 8) {
							$matches = true;
							if ($p < $e) {
								if (is_writable($baselogdir . $d)) {
									@unlink($baselogdir . $d);
									cacti_log('Cacti Log Rotation - Purging ' . $name  . ' Log : ' . $d, true, 'MAINT');
								} else {
									cacti_log('Cacti Log Rotation - ERROR: Can not purge ' . $name  . ' Log : ' . $d, true, 'MAINT');
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
}

/*
 * secpass_check_expired
 * Checks user accounts to determine if the accounts and/or their passwords should be expired
 */
function secpass_check_expired () {
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
			array($t));

		$t = $t - (intval($e) * 86400);

		db_execute_prepared("UPDATE user_auth
			SET enabled = ''
			WHERE realm = 0
			AND enabled = 'on'
			AND lastlogin < ?
			AND id > 1",
			array($t));
	}
	$e = read_config_option('secpass_expirepass');
	if ($e > 0 && is_numeric($e)) {
		$t = time();
		db_execute_prepared("UPDATE user_auth
			SET lastchange = ?
			WHERE lastchange = -1
			AND realm = 0
			AND enabled = 'on'",
			array($t));

		$t = $t - (intval($e) * 86400);

		db_execute_prepared("UPDATE user_auth
			SET must_change_password = 'on'
			WHERE realm = 0
			AND enabled = 'on'
			AND lastchange < ?",
			array($t));
	}
}

/*
 * remove_files
 * remove all unwanted files; the list is given by table data_source_purge_action
 */
function remove_files($file_array) {
	global $config, $debug, $archived, $purged;

	maint_debug('RRDClean is now running on ' . cacti_sizeof($file_array) . ' items');

	/* determine the location of the RRA files */
	if (isset ($config['rra_path'])) {
		$rra_path = $config['rra_path'];
	} else {
		$rra_path = $config['base_path'] . '/rra';
	}

	if ( read_config_option('storage_location')) {
		$rrdtool_pipe = rrd_init();
		rrdtool_execute('setcnn timeout off', false, RRDTOOL_OUTPUT_NULL, $rrdtool_pipe, $logopt = 'POLLER');
	}else {
		/* let's prepare the archive directory */
		$rrd_archive = read_config_option('rrd_archive', true);
		if ($rrd_archive == '') {
			$rrd_archive = $rra_path . '/archive';
		}
		rrdclean_create_path($rrd_archive);
	}

	/* now scan the files */
	foreach ($file_array as $file) {
		$source_file = $rra_path . '/' . $file['name'];

		if( read_config_option('storage_location') == 0) {
			switch ($file['action']) {
				case '1' :
					if (unlink($source_file)) {
						maint_debug('Deleted: ' . $file['name']);
					} else {
						cacti_log($file['name'] . " ERROR: RRDfile Maintenance unable to delete from $rra_path!", true, 'MAINT');
					}
					$purged++;
					break;
				case '3' :
					$target_file = $rrd_archive . '/' . $file['name'];
					$target_dir = dirname($target_file);
					if (!is_dir($target_dir)) {
						rrdclean_create_path($target_dir);
					}

					if (rename($source_file, $target_file)) {
						maint_debug('Moved: ' . $file['name'] . ' to: ' . $rrd_archive);
					} else {
						cacti_log($file['name'] . " ERROR: RRDfile Maintenance unable to move to $rrd_archive!", true, 'MAINT');
					}
					$archived++;
					break;
			}
		}else {
			switch($file['action']) {
				case '1':
					if ( rrdtool_execute('unlink ' . $source_file, false, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, $logopt = 'MAINT')) {
						maint_debug('Deleted: ' . $file['name']);
					}else {
						cacti_log($file['name'] . 'ERROR: RRDfile Maintenance unable to delete from RRDproxy!', true, 'MAINT');
					}
					$purged++;
					break;
				case '3':
					if ( rrdtool_execute('archive ' . $source_file, false, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, $logopt = 'MAINT')) {
						maint_debug('Moved: ' . $file['name'] . ' to: RRDproxy Archive');
					}else {
						cacti_log($file['name'] . 'ERROR: RRDfile Maintenance unable to move to RRDproxy Archive!', true, 'MAINT');
					}
					$archived++;
					break;
			}
		}

		/* drop from data_source_purge_action table */
		db_execute_prepared('DELETE FROM `data_source_purge_action`
			WHERE name = ?',
			array($file['name']));

		maint_debug('Delete from data_source_purge_action: ' . $file['name']);

		//fetch all local_graph_id's according to this data source
		$lgis = db_fetch_assoc_prepared('SELECT DISTINCT gl.id
			FROM graph_local AS gl
			INNER JOIN graph_templates_item AS gti
			ON gl.id = gti.local_graph_id
			INNER JOIN data_template_rrd AS dtr
			ON dtr.id=gti.task_item_id
			INNER JOIN data_local AS dl
			ON dtr.local_data_id=dl.id
			WHERE (local_data_id=?)',
			array($file['local_data_id']));

		if (cacti_sizeof($lgis)) {
			/* anything found? */
			maint_debug('Processing ' . cacti_sizeof($lgis) . ' Graphs for data source id: ' . $file['local_data_id']);

			/* get them all */
			foreach ($lgis as $item) {
				$remove_lgis[] = $item['id'];
				maint_debug('remove local_graph_id=' . $item['id']);
			}

			/* and remove them in a single run */
			if (!empty ($remove_lgis)) {
				api_graph_remove_multi($remove_lgis);
			}
		}

		/* remove related data source if any */
		if ($file['local_data_id'] > 0) {
			maint_debug('Removing Data Source: ' . $file['local_data_id']);
			api_data_source_remove($file['local_data_id']);
		}
	}

	if(read_config_option('storage_location')) {
		rrd_close($rrdtool_pipe);
	}

	maint_debug('RRDClean has finished a purge pass of ' . cacti_sizeof($file_array) . ' items');
}

function rrdclean_create_path($path) {
	global $config;

	if (!is_dir($path)) {
		if (mkdir($path, 0775)) {
			if ($config['cacti_server_os'] != 'win32') {
				$owner_id      = fileowner($config['rra_path']);
				$group_id      = filegroup($config['rra_path']);

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

/*
 * cleanup_ds_and_graphs - courtesy John Rembo
 */
function cleanup_ds_and_graphs() {
	global $config;

	$remove_ldis = array ();
	$remove_lgis = array ();

	maint_debug('RRDClean now cleans up all data sources and graphs');

	//fetch all local_data_id's which have appropriate data-sources
	$rrds = db_fetch_assoc("SELECT local_data_id, name_cache, data_source_path
		FROM data_template_data
		WHERE name_cache > ''");

	//filter those whose rrd files doesn't exist
	foreach ($rrds as $item) {
		$ldi = $item['local_data_id'];
		$name = $item['name_cache'];
		$ds_pth = $item['data_source_path'];
		$real_pth = str_replace('<path_rra>', $config['rra_path'], $ds_pth);
		if (!file_exists($real_pth)) {
			if (!in_array($ldi, $remove_ldis)) {
				$remove_ldis[] = $ldi;
				maint_debug("RRD file is missing for data source name: $name (local_data_id=$ldi)");
			}
		}
	}

	if (empty ($remove_ldis)) {
		maint_debug('No missing rrd files found');
		return 0;
	}

	maint_debug('Processing Graphs');
	//fetch all local_graph_id's according to filtered rrds
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

	if (!empty ($remove_lgis)) {
		maint_debug('removing graphs');
		api_graph_remove_multi($remove_lgis);
	}

	maint_debug('removing data sources');
	api_data_source_remove_multi($remove_ldis);

	maint_debug('removed graphs:' . cacti_count($remove_lgis) . ' removed data-sources:' . cacti_count($remove_ldis));
}

function maint_debug($message) {
	global $debug;

	if ($debug) {
		print trim($message) . "\n";
	}
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	print "Cacti Maintenance Poller, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*
 * display_help
 * displays the usage of the function
 */
function display_help() {
	display_version();

	print "\nusage: poller_maintenance.php [--force] [--debug]\n\n";
	print "Cacti's maintenance poller.  This poller is responsible for executing periodic\n";
	print "maintenance activities for Cacti including log rotation, deactivating accounts, etc.\n\n";
	print "Optional:\n";
	print "    --force   - Force immediate execution, e.g. for testing.\n";
	print "    --debug   - Display verbose output during execution.\n\n";
}
