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

/* tick use required as of PHP 4.3.0 to accomodate signal handling */
declare(ticks = 1);

/* we are not talking to the browser */
$no_http_headers = true;

function sig_handler($signo) {
	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: Cacti Master Poller process terminated by user', true, 'POLLER', POLLER_VERBOSITY_LOW);

			$running_processes = db_fetch_assoc('SELECT ' . SQL_NO_CACHE . ' * FROM poller_time WHERE end_time=\'0000-00-00 00:00:00\'');

			if (sizeof($running_processes)) {
				foreach($running_processes as $process) {
					if (function_exists('posix_kill')) {
						cacti_log("WARNING: Termination poller process with pid '" . $process['pid'] . "'", true, 'POLLER', POLLER_VERBOSITY_LOW);
						posix_kill($process['pid'], SIGTERM);
					}
				}
			}

			db_execute('TRUNCATE TABLE poller_time');

			exit;
			break;
		default:
			/* ignore all other signals */
	}
}

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* start initialization section */
include(dirname(__FILE__) . '/include/global.php');
include_once($config['base_path'] . '/lib/poller.php');
include_once($config['base_path'] . '/lib/data_query.php');
include_once($config['base_path'] . '/lib/rrd.php');
include_once($config['base_path'] . '/lib/dsstats.php');
include_once($config['base_path'] . '/lib/boost.php');
include_once($config['base_path'] . '/lib/reports.php');

/* initialize some variables */
$force     = false;
$debug     = false;
$mibs      = false;

/* set the poller_id */
$poller_id = $config['poller_id'];
$hostname  = php_uname('n');

/* requires for remote poller stage out */
chdir(dirname(__FILE__));

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-p':
			case '--poller':
				$poller_id = $value;

				break;
			case '-d':
			case '--debug':
				$debug = true;

				break;
			case '--force':
				$force = true;

				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit;
			case '-H':
			case '-h':
			case '--help':
				display_help();
				exit;
			default:
				echo "ERROR: Invalid Argument: ($arg)\n\n";
				display_help();
				exit(1);
		}
	}
}

// Update the pollers hostname if it is blank, otherwise allow the user to edit it
if (db_fetch_cell_prepared('SELECT hostname FROM poller where id = ?', array($poller_id), 'hostname') == '') {
	db_execute_prepared('UPDATE poller SET hostname = ? WHERE id = ?', array($hostname, $poller_id));
}

// Check to see if the poller is disabled
poller_enabled_check($poller_id);

// install signal handlers for UNIX only
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

api_plugin_hook('poller_top');

// Prime the poller_resource_cache for multiple pollers
update_resource_cache($poller_id);

// record the start time
$poller_start    = microtime(true);
$overhead_time   = 0;

// get number of polling items from the database
$poller_interval = read_config_option('poller_interval');

// retreive the last time the poller ran
$poller_lastrun  = read_config_option('poller_lastrun_' . $poller_id);

// collect the system mibs every 4 hours
if ($poller_lastrun % 14440 < time() % 14440 || empty($poller_lastrun)) {
	$mibs = true;
}

// get the current cron interval from the database
$cron_interval = read_config_option('cron_interval');

if ($cron_interval != 60) {
	$cron_interval = 300;
}

// see if the user wishes to use process leveling
$process_leveling = read_config_option('process_leveling');

// retreive the number of concurrent process settings
$concurrent_processes = read_config_option('concurrent_processes');

// correct for possible poller output not empty occurances
$ds_needing_fixes = db_fetch_assoc_prepared('SELECT local_data_id,
	MIN(rrd_next_step) AS next_step,
	COUNT(DISTINCT rrd_next_step) AS instances
	FROM poller_item
	WHERE poller_id = ?
	AND rrd_num > 1
	GROUP BY local_data_id
	HAVING instances > 1',
	array($poller_id));

if (sizeof($ds_needing_fixes)) {
	foreach($ds_needing_fixes as $ds) {
		db_execute_prepared('UPDATE poller_item
			SET rrd_next_step = ?
			WHERE local_data_id = ?',
			array($ds['next_step'], $ds['local_data_id']));
	}
}

// assume a scheduled task of either 60 or 300 seconds
if (!empty($poller_interval)) {
	$poller_runs = intval($cron_interval / $poller_interval);
	$sql_where   = 'WHERE rrd_next_step<=0 AND poller_id = ' . $poller_id;

	define('MAX_POLLER_RUNTIME', $poller_runs * $poller_interval - 2);
} else {
	$sql_where       = 'WHERE poller_id = ' . $poller_id;
	$poller_runs     = 1;
	$poller_interval = 300;
	define('MAX_POLLER_RUNTIME', 298);
}

$num_polling_items = db_fetch_cell('SELECT ' . SQL_NO_CACHE . ' COUNT(*) FROM poller_item ' . $sql_where);
if (isset($concurrent_processes) && $concurrent_processes > 1) {
	$items_perhost     = array_rekey(db_fetch_assoc("SELECT " . SQL_NO_CACHE . " host_id, COUNT(*) AS data_sources
		FROM poller_item
		$sql_where
		GROUP BY host_id
		ORDER BY host_id"), 'host_id', 'data_sources');
}

if (isset($items_perhost) && sizeof($items_perhost)) {
	$items_per_process   = floor($num_polling_items / $concurrent_processes);

	if ($items_per_process == 0) {
		$process_leveling = 'off';
	}
} else {
	$process_leveling = 'off';
}

/* some text formatting for platform specific vocabulary */
if ($config['cacti_server_os'] == 'unix') {
	$task_type = 'Cron';
} else {
	$task_type = 'Scheduled Task';
}

if ($debug) {
	$level = POLLER_VERBOSITY_NONE;
} else {
	$level = POLLER_VERBOSITY_MEDIUM;
}

$poller_seconds_sincerun = 'never';
if (isset($poller_lastrun)) {
	$poller_seconds_sincerun = $poller_start - $poller_lastrun;
}

cacti_log("NOTE: Poller Int: '$poller_interval', $task_type Int: '$cron_interval', Time Since Last: '" . round($poller_seconds_sincerun,2) . "', Max Runtime '" . MAX_POLLER_RUNTIME. "', Poller Runs: '$poller_runs'", true, 'POLLER', $level);

/* our cron can run at either 1 or 5 minute intervals */
if ($poller_interval <= 60) {
	$min_period = '60';
} else {
	$min_period = '300';
}

/* get to see if we are polling faster than reported by the settings, if so, exit */
if ((isset($poller_lastrun) && isset($poller_interval) && $poller_lastrun > 0) && (!$force)) {
	/* give the user some flexibility to run a little moe often */
	if ((($poller_start - $poller_lastrun)*1.3) < MAX_POLLER_RUNTIME) {
		cacti_log("NOTE: $task_type is configured to run too often!  The Poller Interval is '$poller_interval' seconds, with a minimum $task_type period of '$min_period' seconds, but only " . number_format_i18n($poller_start - $poller_lastrun, 1) . ' seconds have passed since the poller last ran.', true, 'POLLER', $level);
		exit;
	}
}

/* check to see whether we have the poller interval set lower than the poller is actually ran, if so, issue a warning */
if ((($poller_start - $poller_lastrun - 5) > MAX_POLLER_RUNTIME) && ($poller_lastrun > 0)) {
	cacti_log("WARNING: $task_type is out of sync with the Poller Interval!  The Poller Interval is '$poller_interval' seconds, with a maximum of a '$min_period' second $task_type, but " . number_format_i18n($poller_start - $poller_lastrun, 1) . ' seconds have passed since the last poll!', true, 'POLLER');
}

db_execute_prepared('REPLACE INTO settings
	(name, value)
	VALUES (?, ?)',
	array('poller_lastrun_' . $poller_id, (int)$poller_start));

/* let PHP only run 1 second longer than the max runtime, plus the poller needs lot's of memory */
ini_set('max_execution_time', MAX_POLLER_RUNTIME + 1);
ini_set('memory_limit', '512M');

$poller_runs_completed = 0;
$poller_items_total    = 0;

while ($poller_runs_completed < $poller_runs) {
	/* record the start time for this loop */
	$loop_start = microtime(true);

	if ($poller_id == '1') {
		$polling_hosts = array_merge(
			array(0 => array('id' => '0')),
			db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' id
				FROM host
				WHERE poller_id = ?
				AND disabled=""
				ORDER BY id', array($poller_id)));
	} else {
		$polling_hosts = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' id
			FROM host
			WHERE poller_id = ?
			AND disabled=""
			ORDER BY id', array($poller_id));
	}

	$script = $server = $snmp = 0;
	$totals = db_fetch_assoc_prepared('SELECT action, count(*) AS totals FROM poller_item WHERE poller_id = ? GROUP BY action', array($poller_id));
	if (sizeof($totals)) {
		foreach($totals as $value) {
			switch($value['action']) {
			case '0': // SNMP
				$snmp = $value['totals'];
				break;
			case '1': // Script
				$script = $value['totals'];
				break;
			case '2': // Server
				$server = $value['totals'];
				break;
			}
		}
	}

	/* update statistics */
	db_execute_prepared('INSERT INTO poller (id, snmp, script, server, last_status, status)
		VALUES (?, ?, ?, ?, NOW(), 1)
		ON DUPLICATE KEY UPDATE snmp=VALUES(snmp), script=VALUES(script),
		server=VALUES(server), last_status=VALUES(last_status), status=VALUES(status)',
		array($poller_id, $snmp, $script, $server));

	/* calculate overhead time */
	if ($overhead_time == 0) {
		$overhead_time = $loop_start - $poller_start;
	}

	/* initialize counters for script file handling */
	$host_count = 1;

	/* initialize file creation flags */
	$change_proc = false;

	/* initialize file and host count pointers */
	$started_processes = 0;
	$first_host        = 0;
	$last_host         = 0;

	/* update web paths for the poller */
	db_execute("REPLACE INTO settings (name, value) VALUES ('path_webroot','" . addslashes(($config['cacti_server_os'] == 'win32') ? strtr(strtolower(substr(dirname(__FILE__), 0, 1)) . substr(dirname(__FILE__), 1),"\\", '/') : dirname(__FILE__)) . "')");

	/* obtain some defaults from the database */
	$poller_type = read_config_option('poller_type');
	$max_threads = read_config_option('max_threads');

	/* initialize poller_time and poller_output tables, check poller_output for issues */
	$running_processes = db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . ' COUNT(*)
		FROM poller_time
		WHERE poller_id = ?
		AND end_time="0000-00-00 00:00:00"', array($poller_id));

	if ($running_processes) {
		cacti_log("WARNING: There are '$running_processes' detected as overrunning a polling process, please investigate", true, 'POLLER');
	}

	db_execute_prepared('DELETE FROM poller_time WHERE poller_id = ?', array($poller_id));

	// Only report issues for the main poller or from bad local data ids, other pollers may insert somewhat asynchornously
	$issues_limit = 20;

	if ($poller_id == 1) {
		$issues = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' local_data_id, rrd_name
			FROM poller_output AS po
			LEFT JOIN data_local AS dl
			ON po.local_data_id=dl.id
			LEFT JOIN host AS h
			ON dl.host_id=h.id
			WHERE h.poller_id = ? OR h.id IS NULL
			LIMIT ' . $issues_limit, array($poller_id));
	} elseif ($config['connection'] == 'online') {
		$issues = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' local_data_id, rrd_name
			FROM poller_output AS po
			LEFT JOIN data_local AS dl
			ON po.local_data_id=dl.id
			LEFT JOIN host AS h
			ON dl.host_id=h.id
			WHERE (h.poller_id = ? OR h.id IS NULL)
			AND time < FROM_UNIXTIME(UNIX_TIMESTAMP()-600)
			LIMIT ' . $issues_limit, array($poller_id));
	} else{
		$issues = array();
	}

	if (sizeof($issues)) {
		$count  = db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . ' COUNT(*)
			FROM poller_output AS po
			LEFT JOIN data_local AS dl
			ON po.local_data_id=dl.id
			LEFT JOIN host AS h
			ON dl.host_id=h.id
			WHERE h.poller_id = ? OR h.id IS NULL', array($poller_id));


		if (sizeof($issues)) {
			$issue_list =  'DS[';
			$i = 0;
			foreach($issues as $issue) {
				$issue_list .= ($i > 0 ? ', ' : '') . $issue['local_data_id'];
				$i++;
			}
			$issue_list .= ']';
		}

		if ($count > $issues_limit) {
			$issue_list .= ", Additional Issues Remain.  Only showing first $issues_limit";
		}

		cacti_log("WARNING: Poller Output Table not Empty.  Issues: $count, $issue_list", true, 'POLLER');

		db_execute_prepared('DELETE po
			FROM poller_output AS po
			LEFT JOIN data_local AS dl
			ON po.local_data_id=dl.id
			LEFT JOIN host AS h
			ON dl.host_id=h.id
			WHERE h.poller_id = ? OR h.id IS NULL', array($poller_id));
	}

	/* mainline */
	if (read_config_option('poller_enabled') == 'on') {
		/* determine the number of hosts to process per file */
		$hosts_per_process = ceil(($poller_id == '1' ? sizeof($polling_hosts)-1 : sizeof($polling_hosts)) / $concurrent_processes );

		$items_launched    = 0;

		/* exit poller if spine is selected and file does not exist */
		if (($poller_type == '2') && (!file_exists(read_config_option('path_spine')))) {
			cacti_log('ERROR: The path: ' . read_config_option('path_spine') . ' is invalid.  Can not continue', true, 'POLLER');
			exit;
		}

		/* Determine Command Name */
		if ($poller_type == '2') {
			$command_string = read_config_option('path_spine');
			if (read_config_option('path_spine_config') != '' && file_exists(read_config_option('path_spine_config'))) {
				$extra_args     = ' -C ' . read_config_option('path_spine_config');
			} else {
				$extra_args     = '';
			}

			$method         = 'spine';
			$total_procs    = $concurrent_processes * $max_threads;
			chdir(dirname(read_config_option('path_spine')));
		}else if ($config['cacti_server_os'] == 'unix') {
			$command_string = read_config_option('path_php_binary');
			$extra_args     = '-q "' . $config['base_path'] . '/cmd.php"';
			$method         = 'cmd.php';
			$total_procs    = $concurrent_processes;
		} else {
			$command_string = read_config_option('path_php_binary');
			$extra_args     = '-q "' . strtolower($config['base_path'] . '/cmd.php"');
			$method         = 'cmd.php';
			$total_procs    = $concurrent_processes;
		}

		$extra_args = api_plugin_hook_function('poller_command_args', $extra_args);

		if ($poller_id > 1) {
			$extra_args .= ' --mode=' . $config['connection'];
		}

		/* Populate each execution file with appropriate information */
		foreach ($polling_hosts as $item) {
			if ($host_count == 1) {
				$first_host = $item['id'];
			}

			if ($process_leveling != 'on') {
				if ($host_count == $hosts_per_process) {
					$last_host    = $item['id'];
					$change_proc  = true;
				}
			} else {
				if (isset($items_perhost[$item['id']])) {
					$items_launched += $items_perhost[$item['id']];
				}

				if (($items_launched >= $items_per_process) ||
					(sizeof($items_perhost) == $concurrent_processes)) {
					$last_host      = $item['id'];
					/* if this is the dummy entry for externally updated data sources
					 * that are not related to any host (host id = 0), do NOT change_proc */
					$change_proc    = ($item['id'] == 0 ? false : true);
					$items_launched = 0;
				}
			}

			$host_count ++;

			if ($change_proc) {
				exec_background($command_string, "$extra_args --poller=$poller_id --first=$first_host --last=$last_host" . ($mibs ? ' --mibs':''));
				usleep(100000);

				$host_count   = 1;
				$change_proc  = false;
				$first_host   = 0;
				$last_host    = 0;

				$started_processes++;
			} /* end change_process */
		} /* end for each */

		/* launch the last process */
		if ($host_count > 1) {
			$last_host = $item['id'];

			exec_background($command_string, "$extra_args --poller=$poller_id --first=$first_host --last=$last_host" . ($mibs ? ' --mibs':''));
			usleep(100000);

			$started_processes++;
		}

		if ($poller_id == 1) {
			/* insert the current date/time for graphs */
			db_execute("REPLACE INTO settings (name, value) VALUES ('date', NOW())");

			/* open a pipe to rrdtool for writing */
			$rrdtool_pipe = rrd_init();
		}

		if ($poller_type == '1') {
			$max_threads = 'N/A';
		}

		$rrds_processed = 0;
		$poller_finishing_dispatched = false;
		while (1) {
			$finished_processes = db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . " count(*)
				FROM poller_time
				WHERE poller_id = ?
				AND end_time >'0000-00-00 00:00:00'", array($poller_id));

			if ($finished_processes >= $started_processes) {
				/* all scheduled pollers are finished */
				if ($poller_finishing_dispatched === false) {
					api_plugin_hook('poller_finishing');
					$poller_finishing_dispatched = true;
				}

				if ($poller_id == 1) {
					$rrds_processed = $rrds_processed + process_poller_output($rrdtool_pipe, true);
				}

				log_cacti_stats($loop_start, $method, $concurrent_processes, $max_threads,
					($poller_id == '1' ? sizeof($polling_hosts) - 1 : sizeof($polling_hosts)), $hosts_per_process, $num_polling_items, $rrds_processed);

				break;
			}else {
				if (read_config_option('log_verbosity') >= POLLER_VERBOSITY_MEDIUM || $debug) {
					print 'Waiting on ' . ($started_processes - $finished_processes) . ' of ' . $started_processes . " pollers.\n";
				}

				$mtb = microtime(true);

				if ($poller_id == 1) {
					$rrds_processed = $rrds_processed + process_poller_output($rrdtool_pipe);
				}

				/* end the process if the runtime exceeds MAX_POLLER_RUNTIME */
				if (($poller_start + MAX_POLLER_RUNTIME) < time()) {
					cacti_log('Maximum runtime of ' . MAX_POLLER_RUNTIME . ' seconds exceeded. Exiting.', true, 'POLLER');

					/* generate a snmp notification */
					snmpagent_poller_exiting();

					api_plugin_hook_function('poller_exiting');
					log_cacti_stats($loop_start, $method, $concurrent_processes, $max_threads,
						($poller_id == '1' ? sizeof($polling_hosts) - 1 : sizeof($polling_hosts)), $hosts_per_process, $num_polling_items, $rrds_processed);

					break;
				} elseif (microtime(true) - $mtb < 1) {
					sleep(1);
				}
			}
		}

		if ($poller_id == 1) {
			rrd_close($rrdtool_pipe);
		}

		/* process poller commands */
		if (db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . ' COUNT(*) FROM poller_command WHERE poller_id = ?', array($poller_id)) > 0) {
			$command_string = read_config_option('path_php_binary');
			$extra_args = '-q "' . $config['base_path'] . "/poller_commands.php\" --poller=$poller_id";
			exec_background($command_string, $extra_args);
		} else {
			/* no re-index or Rechache present on this run
			 * in case, we have more PCOMMANDS than recaching, this has to be moved to poller_commands.php
			 * but then we'll have to call it each time to make sure, stats are updated */
			db_execute("REPLACE INTO settings (name,value) VALUES ('stats_recache_$poller_id','RecacheTime:0.0 DevicesRecached:0')");
		}

		if ($method == 'spine') {
			$webroot = read_config_option('path_webroot');

			if (is_dir($webroot)) {
				chdir($webroot);
			}
		}
	} else {
		cacti_log('NOTE: There are no items in your poller for this polling cycle!', true, 'POLLER', $level);
	}

	$poller_runs_completed++;

	/* record the start time for this loop */
	$loop_end  = microtime(true);
	$loop_time = $loop_end - $loop_start;

	if ($loop_time < $poller_interval) {
		/* sleep the appripriate amount of time */
		if ($poller_runs_completed < $poller_runs) {
			$plugin_start = microtime(true);

			/* all plugins moved to core */
			if ($poller_id == 1) {
				snmpagent_poller_bottom();
				dsstats_poller_bottom();
				boost_poller_bottom();
				api_plugin_hook('poller_bottom');
			}

			/* record the start time for this loop */
			$loop_end      = microtime(true);
			$cur_loop_time = $loop_end - $loop_start;

			if ($poller_runs_completed == 1) {
				$sleep_time = $poller_interval - $cur_loop_time - $overhead_time;
			} else {
				$sleep_time = $poller_interval - $cur_loop_time;
			}

			/* log some nice debug information */
			if ($debug) {
				echo 'Loop  Time is: ' . round($loop_time, 2) . "\n";
				echo 'Sleep Time is: ' . round($sleep_time, 2) . "\n";
				echo 'Total Time is: ' . round($loop_end - $poller_start, 2) . "\n";
 			}

			$plugin_end = microtime(true);
			if (($sleep_time - ($plugin_end - $plugin_start)) > 0) {
				usleep(($sleep_time - ($plugin_end - $plugin_start)) * 1000000);
			}

			api_plugin_hook('poller_top');
		}
	} else {
		cacti_log('WARNING: Cacti Polling Cycle Exceeded Poller Interval by ' . $loop_end-$loop_start-$poller_interval . ' seconds', true, 'POLLER', $level);
	}

	// Flush the boost table if in recovery mode
	poller_recovery_flush_boost($poller_id);
}

function poller_enabled_check($poller_id) {
	$disabled = db_fetch_cell_prepared('SELECT disabled FROM poller WHERE id = ?', array($poller_id));

	if ($disabled == 'on') {
		db_execute_prepared('UPDATE poller SET last_status=NOW() WHERE id = ?', array($poller_id));
		cacti_log('WARNING: Poller ' . $poller_id . ' is Disabled, graphing or other activities are running', true, 'SYSTEM');
		exit(1);
	}
}

function log_cacti_stats($loop_start, $method, $concurrent_processes, $max_threads, $num_hosts,
	$hosts_per_process, $num_polling_items, $rrds_processed) {
	global $poller_id;

	/* take time and log performance data */
	$loop_end = microtime(true);

	$perf_data = array(
		round($loop_end-$loop_start,4),
		$method,
		$concurrent_processes,
		$max_threads,
		$num_hosts,
		$hosts_per_process,
		$num_polling_items,
		$rrds_processed
	);

	$cacti_stats = vsprintf('Time:%01.4f Method:%s Processes:%s Threads:%s Hosts:%s HostsPerProcess:%s DataSources:%s RRDsProcessed:%s', $perf_data);
	cacti_log('STATS: ' . $cacti_stats , true, 'SYSTEM');

	/* insert poller stats into the settings table */
	db_execute_prepared('REPLACE INTO settings (name, value) VALUES ("stats_poller",?)', array($cacti_stats));
	db_execute_prepared('INSERT INTO poller (id, total_time, last_update, last_status, status)
		VALUES (?, ?, NOW(), NOW(), 2)
		ON DUPLICATE KEY UPDATE total_time=VALUES(total_time), last_update=VALUES(last_update),
		last_status=VALUES(last_status), status=VALUES(status)',
		array($poller_id, round($loop_end-$loop_start,4)));

	/* update snmpcache */
	snmpagent_cacti_stats_update($perf_data);

	api_plugin_hook_function('cacti_stats_update', $perf_data);
}

/**
 * function for bulk spikekill that only runs on the main cacti server
 */
function spikekill_poller_bottom () {
    global $config;
    include_once($config['base_path'] . '/lib/poller.php');

    $command_string = read_config_option('path_php_binary');
    $extra_args = '-q ' . $config['base_path'] . '/poller_spikekill.php';
    exec_background($command_string, $extra_args);
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	echo "Cacti Main Poller, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	echo "\nusage: poller.php [--poller=ID] [--force] [--debug]\n\n";
	echo "Cacti's main poller.  This poller is the launcher of cmd.php, spine, and all other\n";
	echo "background processes.  It is the heart of Cacti's data collection engine.\n\n";
	echo "Optional:\n";
	echo "    --poller=ID    Run as the poller indicated and not the default poller.\n";
	echo "    --force        Override poller overrun detection and force a poller run.\n";
	echo "    --debug|-d     Output debug information.  Similar to cacti's DEBUG logging level.\n\n";
}

/* start post data processing */
if ($poller_id == 1) {
	snmpagent_poller_bottom();
	boost_poller_bottom();
	dsstats_poller_bottom();
	reports_poller_bottom();
	spikekill_poller_bottom();
	automation_poller_bottom();
	poller_maintenance();
	api_plugin_hook('poller_bottom');
} else {
	automation_poller_bottom();
	poller_maintenance();
}


