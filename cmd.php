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

require_once(__DIR__ . '/include/cli_check.php');
if (function_exists('pcntl_async_signals')) {
	pcntl_async_signals(true);
} else {
	declare(ticks = 100);
}

ini_set('max_execution_time', '0');
ini_set('memory_limit', '-1');

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

$first      = NULL;
$last       = NULL;
$allhost    = true;
$debug      = false;
$mibs       = false;
$mode       = 'online';
$sessions   = array();
$downhosts  = array();
$host_count = 0;
$tot_errors = 0;
$poller_id  = $config['poller_id'];
$pmessage   = false;
$help       = false;
$version    = false;

if (sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
		case '--version':
		case '-V':
		case '-v':
			$version = true;

			break;
		case '--help':
		case '-H':
		case '-h':
			$help = true;

			break;
		case '--poller':
		case '-p':
			$pmessage = true;
			$poller_id = $value;

			break;
		case '--first':
		case '-f':
			$first   = $value;
			$allhost = false;

			break;
		case '--last':
		case '-l':
			$last    = $value;
			$allhost = false;

			break;
		case '--mibs':
		case '-m':
			$mibs = true;

			break;
		case '--mode':
		case '-N':
			$mode = $value;

			break;
		case '--debug':
		case '-d':
			$debug = true;

			break;
		default:
			print "ERROR: Invalid Argument: ($arg)" . PHP_EOL . PHP_EOL;
			display_help();
			exit(1);
		}
	}
}

require_once($config['base_path'] . '/lib/snmp.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/rrd.php');
require_once($config['base_path'] . '/lib/ping.php');

if ($version) {
	display_version();
	exit;
} elseif ($help) {
	display_help();
	exit;
}

global $poller_db_cnn_id, $remote_db_cnn_id, $cactiphp, $using_proc_function;
global $poller_id, $sessions, $downhosts, $print_data_to_stdout;

$maxwidth   = get_max_column_width();

if ($pmessage) {
	cacti_log('Forcing poller to ' . $value, true, 'POLLER', POLLER_VERBOSITY_HIGH);
}

if ($config['poller_id'] > 1 && $config['connection'] == 'online') {
	$poller_db_cnn_id = $remote_db_cnn_id;
} else {
	$poller_db_cnn_id = false;
}

if ($first == NULL && $last == NULL) {
	// This is valid
} elseif (!is_numeric($first) || $first < 0) {
	cacti_log('FATAL: The first host in the host range is invalid!', true, 'POLLER');
	exit(-1);
} elseif (!is_numeric($last) || $last < 0) {
	cacti_log('FATAL: The last host in the host range is invalid!', true, 'POLLER');
	exit(-1);
} elseif ($last < $first) {
	cacti_log('FATAL: The first host must always be less or equal to the last host!', true, 'POLLER');
	exit(-1);
}

// verify the poller_id
if (!is_numeric($poller_id) || $poller_id < 1) {
	cacti_log('FATAL: The poller needs to be a positive numeric value', true, 'POLLER');
	exit(-1);
}

// notify cacti processes that a poller is running
record_cmdphp_started();

$exists = db_fetch_cell_prepared('SELECT COUNT(*)
	FROM host
	WHERE poller_id = ?',
	array($poller_id));

if ($exists == 0 && $poller_id > 1) {
	record_cmdphp_done();
	db_close();
	exit(-1);
}

// install signal handlers for UNIX only
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

// record the start time
$start = microtime(true);
$poller_interval = read_config_option('poller_interval');
$cron_interval   = read_config_option('cron_interval');
$active_profiles = read_config_option('active_profiles');

// check arguments
if ($allhost) {
	$sql_where1 = '';
	$sql_where2 = '';
	$sql_where3 = '';

	$params1    = array($poller_id);
	$params2    = array($poller_id, POLLER_ACTION_SCRIPT_PHP, POLLER_ACTION_SCRIPT_PHP_COUNT);
	$params3    = array($poller_interval, $poller_interval, 0, $poller_interval, $poller_id);
} else {
	$sql_where0 = 'WHERE poller_id > ?';
	$sql_where1 = ' AND ((h.id >= ? AND h.id <= ?) OR h.id IS NULL)';
	$sql_where2 = ' AND pi.host_id >= ? AND pi.host_id <= ?';
	$sql_where3 = ' AND pi.host_id >= ? AND pi.host_id <= ?';

	$params1    = array($poller_id, $first, $last);
	$params2    = array($poller_id, POLLER_ACTION_SCRIPT_PHP, POLLER_ACTION_SCRIPT_PHP_COUNT, $first, $last);

	if ($cron_interval == $poller_interval) {
		$params3    = array($poller_interval, $poller_interval, 0, $poller_interval, $poller_id, $first, $last);
	} else {
		$params3    = array($poller_interval, $poller_interval, $poller_interval, $poller_interval, $poller_id, $first, $last);
	}
}

if ($debug) {
	$print_data_to_stdout = true;
	$medium = POLLER_VERBOSITY_LOW;
	$hmedium = POLLER_VERBOSITY_LOW;
} elseif ($allhost) {
	$print_data_to_stdout = true;
	$medium  = POLLER_VERBOSITY_LOW;
	$hmedium = POLLER_VERBOSITY_MEDIUM;
} else {
	$print_data_to_stdout = false;
	$medium  = POLLER_VERBOSITY_MEDIUM;
	$hmedium = POLLER_VERBOSITY_MEDIUM;
}

// address potential exploits
input_validate_input_number($first);
input_validate_input_number($last);

if ($active_profiles != 1) {
	$poller_items = db_fetch_assoc_prepared("SELECT " . SQL_NO_CACHE . " *
		FROM poller_item AS pi
		LEFT JOIN host AS h
		ON h.id = pi.host_id
		WHERE pi.poller_id = ?
		AND (h.disabled = '' OR h.disabled IS NULL)
		$sql_where1
		AND pi.rrd_next_step <= 0
		ORDER by pi.host_id",
		$params1);

	$script_server_calls = db_fetch_cell_prepared("SELECT " . SQL_NO_CACHE . " COUNT(*)
		FROM poller_item AS pi
		LEFT JOIN host AS h
		ON h.id = pi.host_id
		WHERE pi.poller_id = ?
		AND (h.disabled = '' OR h.disabled IS NULL)
		AND pi.action IN(?, ?)
		$sql_where2
		AND pi.rrd_next_step <= 0",
		$params2);

	// setup next polling interval
	db_execute_prepared("UPDATE poller_item AS pi
		SET rrd_next_step = IF(rrd_step = ?, 0, IF(rrd_next_step - ? < 0, rrd_step - ?, rrd_next_step - ?))
		WHERE poller_id = ?
		$sql_where3",
		$params3);
} else {
	$poller_items = db_fetch_assoc_prepared("SELECT " . SQL_NO_CACHE . " *
		FROM poller_item AS pi
		LEFT JOIN host AS h
		ON h.id = pi.host_id
		WHERE pi.poller_id = ?
		AND (h.disabled = '' OR h.disabled IS NULL)
		$sql_where1
		ORDER by pi.host_id",
		$params1);

	$script_server_calls = db_fetch_cell_prepared("SELECT " . SQL_NO_CACHE . " COUNT(*)
		FROM poller_item AS pi
		LEFT JOIN host AS h
		ON h.id = pi.host_id
		WHERE pi.poller_id = ?
		AND (h.disabled = '' OR h.disabled IS NULL)
		AND pi.action IN(?, ?)
		$sql_where2",
		$params2);
}

if (cacti_sizeof($poller_items) && read_config_option('poller_enabled') == 'on') {
	$host_down    = false;
	$new_host     = true;
	$last_host    = '';
	$output_array = array();
	$output_count = 0;
	$error_ds     = array();
	$width_dses   = array();

	/* startup Cacti php polling server and include the
	 * include file for script processing
	 */
	if ($script_server_calls > 0) {
		$cactides = array(
			0 => array('pipe', 'r'), // stdin is a pipe that the child will read from
			1 => array('pipe', 'w'), // stdout is a pipe that the child will write to
			2 => array('pipe', 'w')  // stderr is a pipe to write to
		);

		$php_path = read_config_option('path_php_binary');

		$command = $php_path     . ' -q ' .
			$config['base_path'] . '/script_server.php ' .
			' --environ=cmd '    .
			' --poller='         . $poller_id .
			' --mode='           . $config['connection'];

		$cactiphp = proc_open($command, $cactides, $pipes);
		$output = fgets($pipes[1], 1024);

		if (substr_count($output, 'Started') != 0) {
			cacti_log('PHP Script Server Started Properly', $print_data_to_stdout, 'POLLER', POLLER_VERBOSITY_HIGH);
		}

		$using_proc_function = true;
	} else {
		$using_proc_function = false;
		$cactiphp = false;
	}

	foreach ($poller_items as $item) {
		$ds      = $item['local_data_id'];
		$host_id = $item['host_id'];

		// check for host change
		if ($host_id != $last_host) {
			$new_host       = true;
			$host_down      = false;
			$set_spike_kill = false;

			if ($last_host != '') {
				$host_end = microtime(true);

				if ($output_count > 0) {
					cacti_log("Device[$last_host] Writing $output_count items to Poller Output Table", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

					db_execute('INSERT IGNORE INTO poller_output
						(local_data_id, rrd_name, time, output)
						VALUES ' . implode(', ', $output_array), true, $poller_db_cnn_id);

					if (read_config_option('boost_redirect') == 'on' && read_config_option('boost_rrd_update_enable') == 'on') {
						db_execute('INSERT IGNORE INTO poller_output_boost
							(local_data_id, rrd_name, time, output)
							VALUES ' . implode(', ', $output_array), true, $poller_db_cnn_id);
					}

					$output_array = array();
					$output_count = 0;
				}

				db_execute_prepared('UPDATE host
					SET polling_time = ?
					WHERE id = ?
					AND deleted = ""',
					array(($host_end - $host_start), $last_host));

				$errors = cacti_sizeof($error_ds);

				cacti_log(sprintf('Device[%d] Time[%3.2f] Items[%d] Errors[%d]', $last_host, $host_end - $host_start, $itemcnt, $errors), $print_data_to_stdout, 'POLLER', $hmedium);

				if ($errors > 0) {
					if (read_config_option('spine_log_level') == 1) {
						cacti_log('WARNING: Invalid Response(s), Errors[' . $errors . '] Device[' . $last_host . '] Thread[1] DS[' . implode(', ', $error_ds) . ']', false, 'POLLER');
					}

					$tot_errors += $errors;
				}
			}

			$error_ds    = array();
			$itemcnt     = 0;
			$host_start  = microtime(true);

			$host_count++;
		}

		// ping host, find out if there are re-index checks required
		if ($new_host && !empty($host_id)) {
			$host_down = ping_and_reindex_check($item, $mibs);
		}

		$new_host  = false;
		$last_host = $host_id;

		if (!$host_down) {
			$output = collect_device_data($item, $error_ds);
			$itemcnt++;

			if (read_config_option('poller_debug') == 'on' && strlen($output) > $maxwidth) {
				$width_dses[] = $ds;
				$width_errors++;
			}

			if ($set_spike_kill && !substr_count($output, ':')) {
				// insert a U in place of the actual value if the snmp agent restarts
				$output_array[] = sprintf('(%d, %s, CURRENT_TIMESTAMP(), "U")', $item['local_data_id'], db_qstr($item['rrd_name']));
			} else {
				// otherwise, just insert the value received from the poller
				$output_array[] = sprintf('(%d, %s, CURRENT_TIMESTAMP(), %s)', $item['local_data_id'], db_qstr($item['rrd_name']), db_qstr($output));
			}

			if ($output_count > 2000) {
				cacti_log("Device[$host_id] Writing $output_count items to Poller Output Table", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

				db_execute('INSERT IGNORE INTO poller_output
					(local_data_id, rrd_name, time, output)
					VALUES ' . implode(', ', $output_array), true, $poller_db_cnn_id);

				if (read_config_option('boost_redirect') == 'on' && read_config_option('boost_rrd_update_enable') == 'on') {
					db_execute('INSERT IGNORE INTO poller_output_boost
						(local_data_id, rrd_name, time, output)
						VALUES ' . implode(', ', $output_array), true, $poller_db_cnn_id);
				}

				$output_array = array();
				$output_count = 0;
			} else {
				$output_count++;
			}
		}

		// check for an over running poller
		$now = microtime(true);
		if ($now - $start > $poller_interval) {
			cacti_log('WARNING: cmd.php poller has run over its polling interval and therefore is ending');
			break;
		}
	}

	// Record the last hosts polling time
	$host_end = microtime(true);

	// Flush the items to the output table
	if ($output_count > 0) {
		cacti_log("Device[$host_id] Writing $output_count items to Poller Output Table", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

		db_execute('INSERT IGNORE INTO poller_output
			(local_data_id, rrd_name, time, output)
			VALUES ' . implode(', ', $output_array), true, $poller_db_cnn_id);

		if (read_config_option('boost_redirect') == 'on' && read_config_option('boost_rrd_update_enable') == 'on') {
			db_execute('INSERT IGNORE INTO poller_output_boost
				(local_data_id, rrd_name, time, output)
				VALUES ' . implode(', ', $output_array), true, $poller_db_cnn_id);
		}
	}

	db_execute_prepared('UPDATE host
		SET polling_time = ?
		WHERE id = ?
		AND deleted = ""',
		array(($host_end - $host_start), $host_id));

	$errors = cacti_sizeof($error_ds);

	cacti_log(sprintf('Device[%d] Time[%3.2f] Items[%d] Errors[%d]', $last_host, $host_end - $host_start, $itemcnt, $errors), $print_data_to_stdout, 'POLLER', $hmedium);

	if ($errors > 0) {
		if (read_config_option('spine_log_level') == 1) {
			cacti_log('WARNING: Invalid Response(s), Errors[' . $errors . '] Device[' . $host_id . '] Thread[1] DS[' . implode(', ', $error_ds) . ']', false, 'POLLER');
		}

		$tot_errors += $errors;
	}

	if (cacti_sizeof($width_dses)) {
		cacti_log('WARNING: Long Responses Errors[' . cacti_sizeof($width_dses) . '] DS[' . implode(', ', $width_dses) . ']', false, 'POLLER');
	}

	if ($using_proc_function && $script_server_calls > 0) {
		// close php server process
		@fwrite($pipes[0], "quit\r\n");
		fclose($pipes[0]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$return_value = proc_close($cactiphp);
	}

	// take time and log performance data
	$end = microtime(true);

	cacti_log(sprintf(
		'Time: %01.4f s, ' .
		'Poller: %s, '     .
		'Threads: N/A, '   .
		'Devices: %d, '    .
		'Items: %d, '      .
		'Errors: %d',
		round($end-$start,4),
		$poller_id,
		$host_count,
		cacti_sizeof($poller_items),
		$tot_errors), $print_data_to_stdout, 'POLLER', $medium);
} else {
	cacti_log('NOTE: There are no items in your poller for this polling cycle!', true, 'POLLER', $medium);
}

// record the process as having completed
record_cmdphp_done();

// close the database connection
db_close();

exit(0);

// function to assist in logging
function debug_level($host_id, $level) {
	global $debug;

	static $debug_enabled = array();

	if (!isset($debug_enabled[$host_id])) {
		if ($debug) {
			$debug_enabled[$host_id] = true;
		} else {
			$debug_enabled[$host_id] = is_device_debug_enabled($host_id);
		}
	}

	$level = $debug_enabled[$host_id] ? POLLER_VERBOSITY_NONE : $level;

	return $level;
}

// let the poller server know about cmd.php being finished
function record_cmdphp_done($pid = '') {
	global $poller_id, $poller_db_cnn_id;

	if ($pid == '') $pid = getmypid();

	db_execute_prepared('UPDATE poller_time
		SET end_time=NOW()
		WHERE poller_id = ?
		AND pid = ?',
		array($poller_id, $pid), true, $poller_db_cnn_id);
}

// let cacti processes know that a poller has started
function record_cmdphp_started() {
	global $poller_id, $poller_db_cnn_id;

	db_execute_prepared("INSERT INTO poller_time
		(poller_id, pid, start_time, end_time)
		VALUES (?, ?, NOW(), '0000-00-00 00:00:00')",
		array($poller_id, getmypid()), true, $poller_db_cnn_id);
}

function open_snmp_session($host_id, &$item) {
	global $sessions, $downhosts;

	if (!isset($item['max_oids'])) {
		$item['max_oids'] = read_config_option('max_get_size');
	}

	if (!isset($sessions[$host_id . '_' . $item['snmp_version'] . '_' . $item['snmp_port']]) && !isset($downhosts[$host_id . '_' . $item['snmp_version'] . '_' . $item['snmp_port']])) {
		$sessions[$host_id . '_' . $item['snmp_version'] . '_' . $item['snmp_port']] = cacti_snmp_session($item['hostname'], $item['snmp_community'], $item['snmp_version'],
			$item['snmp_username'], $item['snmp_password'], $item['snmp_auth_protocol'], $item['snmp_priv_passphrase'],
			$item['snmp_priv_protocol'], $item['snmp_context'], $item['snmp_engine_id'], $item['snmp_port'],
			$item['snmp_timeout'], read_config_option('snmp_retries'), $item['max_oids']);

		if ($sessions[$host_id . '_' . $item['snmp_version'] . '_' . $item['snmp_port']] === false) {
			unset($sessions[$host_id . '_' . $item['snmp_version'] . '_' . $item['snmp_port']]);
			$downhosts[$host_id . '_' . $item['snmp_version'] . '_' . $item['snmp_port']] = true;

			return false;
		}
	}

	return $sessions[$host_id . '_' . $item['snmp_version'] . '_' . $item['snmp_port']];
}

function snmp_mark_host_down($host_id, &$item) {
	global $sessions, $downhosts;

	unset($sessions[$host_id . '_' . $item['snmp_version'] . '_' . $item['snmp_port']]);
	$downhosts[$host_id . '_' . $item['snmp_version'] . '_' . $item['snmp_port']] = true;
}

function update_system_mibs($host_id) {
	$system_mibs = array(
		'snmp_sysDescr'             => '.1.3.6.1.2.1.1.1.0',
		'snmp_sysObjectID'          => '.1.3.6.1.2.1.1.2.0',
		'snmp_sysUpTimeInstanceAlt' => '.1.3.6.1.6.3.10.2.1.3.0',
		'snmp_sysUpTimeInstance'    => '.1.3.6.1.2.1.1.3.0',
		'snmp_sysContact'           => '.1.3.6.1.2.1.1.4.0',
		'snmp_sysName'              => '.1.3.6.1.2.1.1.5.0',
		'snmp_sysLocation'          => '.1.3.6.1.2.1.1.6.0'
	);

	$h = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($host_id));

	if (cacti_sizeof($h)) {
		$session = open_snmp_session($host_id, $h);
		$uptimeAltFound = false;
		$uptime = false;

		if ($session !== false) {
			foreach($system_mibs as $name => $oid) {
				$value = cacti_snmp_session_get($session, $oid);

				if ($name == 'snmp_sysUpTimeInstanceAlt' && $value > 0) {
					$uptime = $value * 100;
					$uptimeAltFound = true;
				} elseif ($name == 'snmp_sysUpTimeInstance' && !$uptimeAltFound) {
					$uptime = $value;
				} elseif ($name != 'snmp_sysUpTimeInstanceAlt' && !empty($value)) {
					db_execute_prepared("UPDATE host SET $name = ?
						WHERE deleted = ''
						AND id = ?",
						array($value, $host_id));
				}
			}

			if ($uptime !== false) {
				db_execute_prepared("UPDATE host SET snmp_sysUpTimeInstance = ?
					WHERE deleted = ''
					AND id = ?",
					array($uptime, $host_id));
			}
		} else {
			cacti_log("WARNING: Unable to open session for System Mib collection for Device[$host_id]", false, 'POLLER');
		}
	}
}

function collect_device_data(&$item, &$error_ds) {
	global $print_data_to_stdout, $using_proc_function, $sessions, $pipes, $cactiphp;

	$thread_start = microtime(true);
	$ds           = $item['local_data_id'];
	$host_id      = $item['host_id'];
	$output       = 'U';

	switch ($item['action']) {
		case POLLER_ACTION_SNMP:
			if (($item['snmp_version'] == 0) || (($item['snmp_community'] == '') && ($item['snmp_version'] != 3))) {
				cacti_log("Device[$host_id] DS[$ds] ERROR: Invalid SNMP Data Source.  Please either delete it from the database, or correct it.", $print_data_to_stdout, 'POLLER');
				$output = 'U';
			} else {
				$session = open_snmp_session($host_id, $item);

				if ($session !== false) {
					$output = cacti_snmp_session_get($session, $item['arg1'], true);

					if (!is_numeric($output)) {
						if (prepare_validate_result($output) == false) {
							$error_ds[$ds] = $ds;

							if (read_config_option('spine_log_level') == 2) {
								cacti_log("WARNING: Invalid Response, Device[$host_id] DS[$ds] OID:". $item['arg1'] . ", output: $output", $print_data_to_stdout, 'POLLER');
							}

							$output = 'U';
						}
					}
				} else {
					snmp_mark_host_down($host_id, $item);

					$output = 'U';

					$error_ds[$ds] = $ds;

					if (read_config_option('spine_log_level') == 2) {
						cacti_log("WARNING: Invalid Response, Device[$host_id] DS[$ds] OID:" . $item['arg1']. ", output: U", $print_data_to_stdout, 'POLLER');
					}
				}

			}

			$total_time = (microtime(true) - $thread_start) * 1000;

			cacti_log("Device[$host_id] DS[$ds] TT[" . round($total_time, 2) . "] SNMP: v" . $item['snmp_version'] . ': ' . $item['hostname'] . ', dsname: ' . $item['rrd_name'] . ', oid: ' . $item['arg1'] . ", output: $output", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

			break;
		case POLLER_ACTION_SCRIPT:
			$output = trim(exec_poll($item['arg1']));

			if ($output == 'U') {
				$error_ds[$ds] = $ds;

				if (read_config_option('spine_log_level') == 2) {
					cacti_log("WARNING: Invalid Response, Device[$host_id] DS[$ds] SCRIPT: " . $item['arg1'] . ", output: U", $print_data_to_stdout, 'POLLER');
				}
			} elseif (!is_numeric($output)) {
				if (prepare_validate_result($output) == false) {
					$error_ds[$ds] = $ds;

					if (read_config_option('spine_log_level') == 2) {
						cacti_log("WARNING: Invalid Response, Device[$host_id] DS[$ds] SCRIPT: " . $item['arg1'] . ", output: $output", $print_data_to_stdout, 'POLLER');
					}
				}
			}

			$total_time = (microtime(true) - $thread_start) * 1000;

			cacti_log("Device[$host_id] DS[$ds] TT[" . round($total_time, 2) . "] SCRIPT: " . $item['arg1'] . ", output: $output", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

			break;
		case POLLER_ACTION_SCRIPT_PHP:
			$output = trim(str_replace("\n", '', exec_poll_php($item['arg1'], $using_proc_function, $pipes, $cactiphp)));

			if ($output == 'U') {
				$error_ds[$ds] = $ds;

				if (read_config_option('spine_log_level') == 2) {
					cacti_log("WARNING: Invalid Response, Device[$host_id] DS[$ds] SERVER: " . $item['arg1'] . ", output: U", $print_data_to_stdout, 'POLLER');
				}
			} elseif (!is_numeric($output)) {
				if (prepare_validate_result($output) == false) {
					$error_ds[$ds] = $ds;

					if (read_config_option('spine_log_level') == 2) {
						cacti_log("WARNING: Invalid Response, Device[$host_id] DS[$ds] SERVER: " . $item['arg1'] . ", output: $output", $print_data_to_stdout, 'POLLER');
					}
				}
			}

			$total_time = (microtime(true) - $thread_start) * 1000;

			cacti_log("Device[$host_id] DS[$ds] TT[" . round($total_time, 2) . "] SERVER: " . $item['arg1'] . ", output: $output", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

			break;
		default:
			$error_ds[$ds] = $ds;

			cacti_log("Device[$host_id] DS[$ds] ERROR: Invalid polling option: " . $item['action'], $stdout, 'POLLER');
	}

	return $output;
}

function ping_and_reindex_check(&$item, $mibs) {
	global $poller_id, $print_data_to_stdout, $sessions, $set_spike_kill, $poller_db_cnn_id, $pipes, $cactiphp, $using_proc_function;

	$ping    = new Net_Ping;
	$host_id = $item['host_id'];

	$host = db_fetch_row_prepared('SELECT hostname, ping_port, ping_method, ping_retries, ping_timeout, availability_method
		FROM host
		WHERE id = ?',
		array($host_id));

	$ping->host = $item;
	$ping->port = $host['ping_port'];

	// perform the appropriate ping check of the host
	if ($ping->ping($host['availability_method'], $host['ping_method'], $host['ping_timeout'], $host['ping_retries'])) {
		$host_down = false;

		update_host_status(HOST_UP, $host_id, $ping, $host['availability_method'], $print_data_to_stdout);

		cacti_log("Device[$host_id] STATUS: Device '" . $item['hostname'] . "' is UP.", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_DEBUG));

		if ($mibs && $host['availability_method'] != 0 && $host['availability_method'] != 3) {
			update_system_mibs($host_id);
		}
	} else {
		$host_down = true;
		update_host_status(HOST_DOWN, $host_id, $ping, $host['availability_method'], $print_data_to_stdout);

		cacti_log("Device[$host_id] STATUS: Device '" . $item['hostname'] . "' is Down.", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_DEBUG));
	}

	if (!$host_down) {
		// do the reindex check for this host
		$reindex = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' pr.data_query_id, pr.action,
			pr.op, pr.assert_value, pr.arg1
			FROM poller_reindex AS pr
			WHERE pr.host_id=?',
			array($item['host_id']));

		if (cacti_sizeof($reindex)) {
			cacti_log("Device[$host_id] RECACHE: Processing " . cacti_sizeof($reindex) . " items in the auto reindex cache for '" . $item['hostname'] . "'.", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_DEBUG));

			foreach ($reindex as $index_item) {
				$assert_fail = false;

				switch ($index_item['action']) {
					case POLLER_ACTION_SNMP:
						$session = open_snmp_session($host_id, $item);

						if ($session !== false) {
							if (trim($index_item['arg1']) == '.1.3.6.1.2.1.1.3.0') {
								$output = cacti_snmp_session_get($session, '.1.3.6.1.6.3.10.2.1.3.0');

								if ($output > 0) {
									$output *= 100;
								} else {
									$output = cacti_snmp_session_get($session, $index_item['arg1']);
								}
							} else {
								$output = cacti_snmp_session_get($session, $index_item['arg1']);
							}
						} else {
							$output = 'U';
						}

						cacti_log("Device[$host_id] DQ[" . $index_item['data_query_id'] . '] RECACHE OID: ' . $index_item['arg1'] . ', (assert:' . $index_item['assert_value'] . ' ' . $index_item['op'] . ' output:' . $output . ')', $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

						break;
					case POLLER_ACTION_SCRIPT:
						$output = trim(exec_poll($index_item['arg1']));

						if (!is_numeric($output)) {
							if (prepare_validate_result($output) == false) {
								if (strlen($output) > 20) {
									$strout = 20;
								} else {
									$strout = strlen($output);
								}

								cacti_log("Device[$host_id] DQ[" . $index_item['data_query_id'] . '] RECACHE Warning: Result from Script not valid. Partial Result: ' . substr($output, 0, $strout), $print_data_to_stdout, 'POLLER');
							}
						}

						cacti_log("Device[$host_id] DQ[" . $index_item['data_query_id'] . '] RECACHE SCRIPT: ' . $index_item['arg1'] . ", output: $output", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

						break;
					case POLLER_ACTION_SCRIPT_PHP:
						$output = trim(str_replace("\n", '', exec_poll_php($index_item['arg1'], $using_proc_function, $pipes, $cactiphp)));

						if (!is_numeric($output)) {
							if (prepare_validate_result($output) == false) {
								if (strlen($output) > 20) {
									$strout = 20;
								} else {
									$strout = strlen($output);
								}

								cacti_log("Device[$host_id] DQ[" . $index_item['data_query_id'] . '] RECACHE WARNING: Result from Script Server not valid. Partial Result: ' . substr($output, 0, $strout), $print_data_to_stdout, 'POLLER');
							}
						}

						cacti_log("Device[$host_id] DQ[" . $index_item['data_query_id'] . '] RECACHE SERVER: ' . $index_item['arg1'] . ", output: $output", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

						break;
					case POLLER_ACTION_SNMP_COUNT:
						$session = open_snmp_session($host_id, $item);

						if ($session !== false) {
							$output = cacti_sizeof(cacti_snmp_session_walk($session, $index_item['arg1']));
						} else {
							$output = 'U';
						}

						cacti_log("Device[$host_id] DQ[" . $index_item['data_query_id'] . '] RECACHE OID COUNT: ' . $index_item['arg1'] . ', output: ' . $output, $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

						break;
					case POLLER_ACTION_SCRIPT_COUNT:
						// count items found
						$script_index_array = exec_into_array($index_item['arg1']);
						$output = cacti_sizeof($script_index_array);

						cacti_log("Device[$host_id] DQ[" . $index_item['data_query_id'] . '] RECACHE CMD COUNT: ' . $index_item['arg1'] . ", output: $output", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

						break;
					case POLLER_ACTION_SCRIPT_PHP_COUNT:
						$output = exec_into_array($index_item['arg1']);
						$output = cacti_sizeof($output);

						cacti_log("Device[$host_id] DQ[" . $index_item['data_query_id'] . '] RECACHE SERVER COUNT: ' . $index_item['arg1'] . ", output: $output", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

						break;
					default:
						cacti_log("Device[$host_id] DQ[" . $index_item['data_query_id'] . '] RECACHE ERROR: Invalid reindex option: ' . $index_item['action'], $print_data_to_stdout, 'POLLER');
				}

				/* assert the result with the expected value in the
				 * db; recache if the assert fails
				 */
				/* TODO: remove magic ":" from poller_command["command"]; this may interfere with scripts */
				if (($index_item['op'] == '=') && ($index_item['assert_value'] != trim($output))) {
					cacti_log("Device[$host_id] HT[1] DQ[" . $index_item['data_query_id'] . "] RECACHE ASSERT FAILED '" . $index_item['assert_value'] . '=' . trim($output), $print_data_to_stdout, 'POLLER');

					db_execute_prepared('REPLACE INTO poller_command
						(poller_id, time, action, command)
						VALUES (?, NOW(), ?, ?)',
						array($poller_id, POLLER_COMMAND_REINDEX, $item['host_id'] . ':' . $index_item['data_query_id']), true, $poller_db_cnn_id);

					$assert_fail = true;
				} elseif (($index_item['op'] == '>') && ($index_item['assert_value'] < trim($output))) {
					cacti_log("Device[$host_id] HT[1] DQ[" . $index_item['data_query_id'] . "] RECACHE ASSERT FAILED '" . $index_item['assert_value'] . '>' . trim($output), $print_data_to_stdout, 'POLLER');

					db_execute_prepared('REPLACE INTO poller_command
						(poller_id, time, action, command)
						VALUES (?, NOW(), ?, ?)',
						array($poller_id, POLLER_COMMAND_REINDEX, $item['host_id'] . ':' . $index_item['data_query_id']), true, $poller_db_cnn_id);

					$assert_fail = true;
				} elseif (($index_item['op'] == '<') && ($index_item['assert_value'] > trim($output))) {
					cacti_log("Device[$host_id] DQ[" . $index_item['data_query_id'] . "] RECACHE ASSERT FAILED '" . $index_item['assert_value'] . '<' . trim($output), $print_data_to_stdout, 'POLLER');

					db_execute_prepared('REPLACE INTO poller_command
						(poller_id, time, action, command)
						VALUES (?, NOW(), ?, ?)',
						array($poller_id, POLLER_COMMAND_REINDEX, $item['host_id'] . ':' . $index_item['data_query_id']), true, $poller_db_cnn_id);

					$assert_fail = true;
				}

				/* update 'poller_reindex' with the correct information if:
				 * 1) the assert fails
				 * 2) the OP code is > or < meaning the current value could have changed without causing
				 *     the assert to fail */
				if (($assert_fail == true) || ($index_item['op'] == '>') || ($index_item['op'] == '<')) {
					db_execute_prepared('UPDATE poller_reindex
						SET assert_value = ?
						WHERE host_id = ?
						AND data_query_id = ?
						AND arg1 = ?',
						array($output, $host_id, $index_item['data_query_id'], $index_item['arg1']));

					// spike kill logic
					if (($assert_fail) &&
						(($index_item['op'] == '<') || ($index_item['arg1'] == '.1.3.6.1.2.1.1.3.0' || $index_item['arg1'] == '.1.3.6.1.6.3.10.2.1.3.0'))) {
						// don't spike kill unless we are certain
						if (!empty($output)) {
							$set_spike_kill = true;

							cacti_log("Device[$host_id] NOTICE: Spike Kill in Effect for '" . $item['hostname'] . "'.", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_DEBUG));
						}
					}
				}
			}
		}
	}

	return $host_down;
}

function get_max_column_width() {
	$pcol_data = db_fetch_row("SHOW COLUMNS FROM poller_output WHERE Field='output'");
	$bcol_data = db_fetch_row("SHOW COLUMNS FROM poller_output_boost WHERE Field='output'");

	if (isset($pcol_data['Type'])) {
		$pcol = $pcol_data['Type'];
		$data = explode('(', $pcol);
		$pmax  = trim($data[1], ')');
	}

	if (cacti_sizeof($bcol_data)) {
		$bcol = $bcol_data['Type'];
		$data = explode('(', $bcol);
		$bmax  = trim($data[1], ')');
	}

	return min($pmax, $bmax);
}

function sig_handler($signo) {
	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: Cacti Poller process terminated by user', true, 'POLLER');

			// record the process as having completed
			record_cmdphp_done();
			db_close();

			exit;
			break;
		default:
			// ignore all other signals
	}
}

function display_version() {
	$version = get_cacti_version();
	print "Cacti Legacy Host Data Collector, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays the usage of the function
 *
 * @return (void)
 */
function display_help () {
	display_version();

	print PHP_EOL;
	print 'usage: cmd.php --first=ID --last=ID [--poller=ID] [--mibs] [--debug]' . PHP_EOL . PHP_EOL;

	print 'Cacti\'s legacy data collector.  This data collector is called by poller.php' . PHP_EOL;
	print 'every poller interval to gather information from devices.  It is recommended' . PHP_EOL;
	print 'that every system deploy spine instead of cmd.php in production due to the built' . PHP_EOL;
	print 'in scalability limits of cmd.php.' . PHP_EOL . PHP_EOL;

	print 'Required' . PHP_EOL;
	print '    --first  - First host id in the range to collect from.' . PHP_EOL;
	print '    --last   - Last host id in the range to collect from.' . PHP_EOL . PHP_EOL;

	print 'Optional:' . PHP_EOL;
	print '    --poller - The poller to run as.  Defaults to the system poller.' . PHP_EOL;
	print '    --mode   - The poller mode, either online, offline, or recovery.' . PHP_EOL;
	print '    --mibs   - Refresh all system mibs from hosts supporting snmp.' . PHP_EOL;
	print '    --debug  - Display verbose output during execution.' . PHP_EOL . PHP_EOL;
}

