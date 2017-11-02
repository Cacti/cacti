<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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

function sig_handler($signo) {
	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: Cacti Poller process terminated by user', true, 'POLLER');

			/* record the process as having completed */
			record_cmdphp_done();
			db_close();

			exit;
			break;
		default:
			/* ignore all other signals */
	}
}

/* function to assist in logging */
function debug_level($host_id, $level) {
	static $debug_levels = array();

	if (!isset($debug_levels[$host_id])) {
		if (is_device_debug_enabled($host_id)) {
			$debug_levels[$host_id] = POLLER_VERBOSITY_NONE;
			return POLLER_VERBOSITY_NONE;
		} else {
			$debug_levels[$host_id] = $level;
			return $level;
		}
	} else {
		return $debug_levels[$host_id];
	}
}

include(dirname(__FILE__) . '/include/global.php');
include_once($config['base_path'] . '/lib/snmp.php');
include_once($config['base_path'] . '/lib/poller.php');
include_once($config['base_path'] . '/lib/rrd.php');
include_once($config['base_path'] . '/lib/ping.php');
include_once($config['library_path'] . '/variables.php');

/* let the poller server know about cmd.php being finished */
function record_cmdphp_done($pid = '') {
	global $poller_id;

	if ($pid == '') $pid = getmypid();

	db_execute_prepared('UPDATE poller_time SET end_time=NOW() WHERE poller_id = ? AND pid = ?', array($poller_id, $pid));
}

/* let cacti processes know that a poller has started */
function record_cmdphp_started() {
	global $poller_id;

	db_execute_prepared("INSERT INTO poller_time
		(poller_id, pid, start_time, end_time)
		VALUES (?, ?, NOW(), '0000-00-00 00:00:00')", array($poller_id, getmypid()));
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
		}
	}
}

function display_version() {
	$version = get_cacti_version();
	echo "Cacti Legacy Host Data Collector, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	echo "\nusage: cmd.php --first=ID --last=ID [--poller=ID] [--mibs] [--debug]\n\n";
	echo "Cacti's legacy data collector.  This data collector is called by poller.php\n";
	echo "every poller interval to gather information from devices.  It is recommended\n";
	echo "that every system deploy spine instead of cmd.php in production due to the built\n";
	echo "in scalability limits of cmd.php.\n\n";
	echo "Required\n";
	echo "    --first  - First host id in the range to collect from.\n";
	echo "    --last   - Last host id in the range to collect from.\n\n";
	echo "Optional:\n";
	echo "    --poller - The poller to run as.  Defaults to the system poller.\n";
	echo "    --mode   - The poller mode, either online, offline, or recovery.\n";
	echo "    --mibs   - Refresh all system mibs from hosts supporting snmp.\n";
	echo "    --debug  - Display verbose output during execution.\n\n";
}

function debug($message) {
	global $debug;

	if ($debug) {
		print('DEBUG: ' . $message . "\n");
	}
}

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br>This script is only meant to run at the command line.');
}

global $poller_id, $sessions, $downhosts;

ini_set('max_execution_time', '0');
ini_set('memory_limit', '512M');

$no_http_headers = true;

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$first     = NULL;
$last      = NULL;
$allhost   = true;
$debug     = false;
$mibs      = false;
$mode      = 'online';
$poller_id = $config['poller_id'];

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
			display_version();
			exit;
		case '--help':
		case '-H':
		case '-h':
			display_help();
			exit;
		case '--poller':
		case '-p':
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
			$mibs    = true;
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
			echo "ERROR: Invalid Argument: ($arg)\n\n";
			display_help();
			exit(1);
		}
	}
}

if ($first == NULL || $last == NULL ) {
	cacti_log('FATAL: You must either a host range, or no range at all using --first=N --last=N syntax!', true, 'POLLER');
	exit(-1);
} elseif (!is_numeric($first) || $first <0) {
	cacti_log('FATAL: The first host in the host range is invalid!', true, 'POLLER');
	exit(-1);
} elseif (!is_numeric($last) || $last <0) {
	cacti_log('FATAL: The last host in the host range is invalid!', true, 'POLLER');
	exit(-1);
} elseif ($last < $first) {
	cacti_log('FATAL: The first host must always be less or equal to the last host!=', true, 'POLLER');
	exit(-1);
}

/* verify the poller_id */
if (!is_numeric($poller_id) || $poller_id < 1) {
	cacti_log('FATAL: The poller needs to be a positive numeric value', true, 'POLLER');
	exit(-1);
} else {
	$exists = db_fetch_cell_prepared('SELECT COUNT(*) FROM host WHERE poller_id = ?', array($poller_id));

	if (empty($exists)) {
		cacti_log('FATAL: No devices matching this poller exist on the system', true, 'POLLER');
		record_cmdphp_done();
		db_close();
		exit(-1);
	}
}

/* notify cacti processes that a poller is running */
record_cmdphp_started();

/* install signal handlers for UNIX only */
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

/* record the start time */
$start = microtime(true);

/* initialize the polling items */
$polling_items = array();

/* determine how often the poller runs from settings */
$polling_interval = read_config_option('poller_interval');

/* check arguments */
if ($allhost) {
	if (isset($polling_interval)) {
		$polling_items = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' *
			FROM poller_item
			WHERE poller_id = ?
			AND rrd_next_step<=0
			ORDER BY host_id',
			array($poller_id));

		$script_server_calls = db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . ' count(*)
			FROM poller_item
			WHERE poller_id = ?
			AND action IN (?, ?)
			AND rrd_next_step<=0',
			array($poller_id, POLLER_ACTION_SCRIPT_PHP, POLLER_ACTION_SCRIPT_PHP_COUNT));
	} else {
		$polling_items = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' *
			FROM poller_item
			WHERE poller_id = ?
			ORDER by host_id',
			array($poller_id));

		$script_server_calls = db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . ' count(*)
			FROM poller_item
			WHERE poller_id = ?
			AND action IN (?, ?)',
			array($poller_id, POLLER_ACTION_SCRIPT_PHP, POLLER_ACTION_SCRIPT_PHP_COUNT));
	}

	$print_data_to_stdout = true;

	/* get the number of polling items from the database */
	$hosts = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' *
		FROM host
		WHERE poller_id = ?
		AND disabled=""
		ORDER BY id',
		array($poller_id));

	/* rework the hosts array to be searchable */
	$hosts = array_rekey($hosts, 'id', $host_struc);

	$host_count = sizeof($hosts);

	/* setup next polling interval */
	if (isset($polling_interval)) {
		db_execute_prepared('UPDATE poller_item
			SET rrd_next_step = rrd_next_step - ?
			WHERE poller_id = ?',
			array($polling_interval, $poller_id));

		db_execute_prepared('UPDATE poller_item
			SET rrd_next_step = rrd_step - ?
			WHERE poller_id = ?
			AND rrd_next_step < 0',
			array($polling_interval, $poller_id));
	}
} else {
	$print_data_to_stdout = false;
	if ($first <= $last) {
		/* address potential exploits */
		input_validate_input_number($first);
		input_validate_input_number($last);

		$hosts = db_fetch_assoc_prepared("SELECT " . SQL_NO_CACHE . " *
			FROM host
			WHERE poller_id = ?
			AND disabled = ''
			AND id >= ?
			AND id <= ?
			ORDER by id",
			array($poller_id, $first, $last));

		$hosts      = array_rekey($hosts, 'id', $host_struc);
		$host_count = sizeof($hosts);

		if (isset($polling_interval)) {
			$polling_items = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' *
				FROM poller_item
				WHERE poller_id = ?
				AND host_id >= ?
				AND host_id <= ?
				AND rrd_next_step <= 0
				ORDER by host_id',
				array($poller_id, $first, $last));

			$script_server_calls = db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . ' count(*)
				FROM poller_item
				WHERE poller_id = ?
				AND action IN(?, ?)
				AND host_id >= ?
				AND host_id <= ?
				AND rrd_next_step <= 0',
				array($poller_id, POLLER_ACTION_SCRIPT_PHP, POLLER_ACTION_SCRIPT_PHP_COUNT, $first, $last));

			/* setup next polling interval */
			db_execute_prepared('UPDATE poller_item
				SET rrd_next_step = rrd_next_step - ?
				WHERE poller_id = ?
				AND host_id >= ?
				AND host_id <= ?',
				array($polling_interval, $poller_id, $first, $last));

			db_execute_prepared('UPDATE poller_item
				SET rrd_next_step = rrd_step - ?
				WHERE poller_id = ?
				AND rrd_next_step < 0
				AND host_id >= ?
				AND host_id <= ?',
				array($polling_interval, $poller_id, $first, $last));
		} else {
			$polling_items = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' *
				FROM poller_item
				WHERE poller_id = ?
				AND host_id >= ? and host_id <= ?
				ORDER by host_id',
				array($poller_id, $first, $last));

			$script_server_calls = db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . ' count(*)
				FROM poller_item
				WHERE poller_id = ?
				AND action IN (?, ?)
				AND host_id >= ?
				AND host_id <= ?',
				array($poller_id, POLLER_ACTION_SCRIPT_PHP, POLLER_ACTION_SCRIPT_PHP_COUNT, $first, $last));
		}
	} else {
		print "ERROR: Invalid Arguments.  The first argument must be less than or equal to the first.\n";
		print "USAGE: CMD.PHP [[first_host] [second_host]]\n";

		cacti_log('ERROR: Invalid Arguments.  This rist argument must be less than or equal to the first.', false, 'POLLER');

		/* record the process as having completed */
		record_cmdphp_done();
		db_close();
		exit('-1');
	}
}

if ((sizeof($polling_items) > 0) && (read_config_option('poller_enabled') == 'on')) {
	$failure_type = '';
	$host_down    = false;
	$new_host     = true;
	$last_host    = '';
	$current_host = '';
	$output_array = array();
	$output_count = 0;
	$error_ds     = array();

	/* create new ping socket for host pinging */
	$ping = new Net_Ping;

	/* startup Cacti php polling server and include the include file for script processing */
	if ($script_server_calls > 0) {
		$cactides = array(
			0 => array('pipe', 'r'), // stdin is a pipe that the child will read from
			1 => array('pipe', 'w'), // stdout is a pipe that the child will write to
			2 => array('pipe', 'w')  // stderr is a pipe to write to
		);

		$cactiphp = proc_open(read_config_option('path_php_binary') . ' -q ' . $config['base_path'] . '/script_server.php cmd', $cactides, $pipes);
		$output = fgets($pipes[1], 1024);

		if (substr_count($output, 'Started') != 0) {
			cacti_log('PHP Script Server Started Properly', $print_data_to_stdout, 'POLLER', POLLER_VERBOSITY_HIGH);
		}

		$using_proc_function = true;
	} else {
		$using_proc_function = false;
	}

        /* to avoid DST ambiguity, always use GMT for inserting TIMESTAMP data */
        db_execute('SET SESSION time_zone = "+0:00"');

	foreach ($polling_items as $item) {
		$data_source  = $item['local_data_id'];
		$current_host = $item['host_id'];

		if ($current_host != $last_host) {
			$new_host = true;

			/* assume the host is up */
			$host_down = false;

			/* assume we don't have to spike prevent */
			$set_spike_kill = false;

			$host_update_time = gmdate('Y-m-d H:i:s'); // for poller update time

			if ($last_host != '') {
				if (sizeof($error_ds)) {
					cacti_log('WARNING: Invalid Response(s), Errors[' . sizeof($error_ds) . '] Device[' . $last_host . '] Thread[1] DS[' . implode(', ', $error_ds) . ']', false, 'POLLER');
				}

				$error_ds = array();
			}
		}

		$host_id = $item['host_id'];

		if (($new_host) && (!empty($host_id))) {
			$ping->host = $item;
			$ping->port = $hosts[$host_id]['ping_port'];

			/* perform the appropriate ping check of the host */
			if ($ping->ping($hosts[$host_id]['availability_method'], $hosts[$host_id]['ping_method'],
				$hosts[$host_id]['ping_timeout'], $hosts[$host_id]['ping_retries'])) {
				$host_down = false;
				update_host_status(HOST_UP, $host_id, $hosts, $ping, $hosts[$host_id]['availability_method'], $print_data_to_stdout);

				cacti_log("Device[$host_id] STATUS: Device '" . $item['hostname'] . "' is UP.", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_DEBUG));

				if ($mibs && $hosts[$host_id]['availability_method'] != 0 && $hosts[$host_id]['availability_method'] != 3) {
					update_system_mibs($host_id);
				}
			} else {
				$host_down = true;
				update_host_status(HOST_DOWN, $host_id, $hosts, $ping, $hosts[$host_id]['availability_method'], $print_data_to_stdout);

				cacti_log("Device[$host_id] STATUS: Device '" . $item['hostname'] . "' is Down.", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_DEBUG));
			}

			if (!$host_down) {
				/* do the reindex check for this host */
				$reindex = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' pr.data_query_id, pr.action,
					pr.op, pr.assert_value, pr.arg1
					FROM poller_reindex AS pr
					WHERE pr.host_id=?',
					array($item['host_id']));

				if (sizeof($reindex) && !$host_down) {
					cacti_log("Device[$host_id] RECACHE: Processing " . sizeof($reindex) . " items in the auto reindex cache for '" . $item['hostname'] . "'.", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_DEBUG));

					foreach ($reindex as $index_item) {
						$assert_fail = false;

						/* do the check */
						switch ($index_item['action']) {

						case POLLER_ACTION_SNMP: /* snmp */
							open_snmp_session($host_id, $item);

							if (isset($sessions[$host_id . '_' . $item['snmp_version'] . '_' . $item['snmp_port']])) {
								$output = cacti_snmp_session_get($sessions[$host_id . '_' . $item['snmp_version'] . '_' . $item['snmp_port']], $index_item['arg1']);
							} else {
								$output = 'U';
							}

							cacti_log("Device[$host_id] RECACHE DQ[" . $index_item['data_query_id'] . '] OID: ' . $index_item['arg1'] . ', output: ' . $output, $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

							break;
						case POLLER_ACTION_SCRIPT: /* script (popen) */
							$output = trim(exec_poll($index_item['arg1']));

							if (!is_numeric($output)) {
								if (prepare_validate_result($output) == false) {
									if (strlen($output) > 20) {
										$strout = 20;
									} else {
										$strout = strlen($output);
									}

									cacti_log("Device[$host_id] RECACHE DQ[" . $index_item['data_query_id'] . '] Warning: Result from Script not valid. Partial Result: ' . substr($output, 0, $strout), $print_data_to_stdout, 'POLLER');
								}
							}

							cacti_log("Device[$host_id] RECACHE DQ[" . $index_item['data_query_id'] . '] Script: ' . $index_item['arg1'] . ", output: $output", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

							break;
						case POLLER_ACTION_SCRIPT_PHP: /* script (php script server) */
							$output = trim(str_replace("\n", '', exec_poll_php($index_item['arg1'], $using_proc_function, $pipes, $cactiphp)));

							if (!is_numeric($output)) {
								if (prepare_validate_result($output) == false) {
									if (strlen($output) > 20) {
										$strout = 20;
									} else {
										$strout = strlen($output);
									}

									cacti_log("Device[$host_id] RECACHE DQ[" . $index_item['data_query_id'] . '] WARNING: Result from Script Server not valid. Partial Result: ' . substr($output, 0, $strout), $print_data_to_stdout, 'POLLER');
								}
							}

							cacti_log("Device[$host_id] RECACHE DQ[" . $index_item['data_query_id'] . '] Script Server: ' . $index_item['arg1'] . ", output: $output", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

							break;
						case POLLER_ACTION_SNMP_COUNT: /* snmp; count items */
							open_snmp_session($host_id, $item);

							if (isset($sessions[$host_id . '_' . $item['snmp_version'] . '_' . $item['snmp_port']])) {
								$output = sizeof(cacti_snmp_session_walk($sessions[$host_id . '_' . $item['snmp_version'] . '_' . $item['snmp_port']], $index_item['arg1']));
							} else {
								$output = 'U';
							}

							cacti_log("Device[$host_id] RECACHE DQ[" . $index_item['data_query_id'] . '] OID Count: ' . $index_item['arg1'] . ', output: ' . $output, $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

							break;
						case POLLER_ACTION_SCRIPT_COUNT: /* script (popen); count items */
							/* count items found */
							$script_index_array = exec_into_array($index_item['arg1']);
							$output = sizeof($script_index_array);

							cacti_log("Device[$host_id] RECACHE DQ[" . $index_item['data_query_id'] . '] Script Count: ' . $index_item['arg1'] . ", output: $output", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

							break;
						case POLLER_ACTION_SCRIPT_PHP_COUNT: /* script (php script server); count items */
							$output = exec_into_array($index_item['arg1']);
							$output = sizeof($output);

							cacti_log("Device[$host_id] RECACHE DQ[" . $index_item['data_query_id'] . '] Script Server Count: ' . $index_item['arg1'] . ", output: $output", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

							break;
						default: /* invalid reindex option */
							cacti_log("Device[$host_id] RECACHE DQ[" . $index_item['data_query_id'] . '] ERROR: Invalid reindex option: ' . $index_item['action'], $print_data_to_stdout, 'POLLER');
						}

						/* assert the result with the expected value in the db; recache if the assert fails */
						/* TODO: remove magic ":" from poller_command["command"]; this may interfere with scripts */
						if (($index_item['op'] == '=') && ($index_item['assert_value'] != trim($output))) {
							cacti_log("ASSERT: '" . $index_item['assert_value'] . '=' . trim($output) .
								"' failed. Recaching host '" . $item['hostname'] .
								"', data query #" . $index_item['data_query_id'], $print_data_to_stdout, 'POLLER');

							db_execute_prepared('REPLACE INTO poller_command
								(poller_id, time, action, command)
								VALUES (?, NOW(), ?, ?)',
								array($poller_id, POLLER_COMMAND_REINDEX, $item['host_id'] . ':' . $index_item['data_query_id']));

							$assert_fail = true;
						}else if (($index_item['op'] == '>') && ($index_item['assert_value'] < trim($output))) {
							cacti_log("ASSERT: '" . $index_item['assert_value'] . '>' . trim($output) .
								"' failed. Recaching host '" . $item['hostname'] .
								"', data query #" . $index_item['data_query_id'], $print_data_to_stdout, 'POLLER');

							db_execute_prepared('REPLACE INTO poller_command
								(poller_id, time, action, command)
								VALUES (?, NOW(), ?, ?)',
								array($poller_id, POLLER_COMMAND_REINDEX, $item['host_id'] . ':' . $index_item['data_query_id']));

							$assert_fail = true;
						}else if (($index_item['op'] == '<') && ($index_item['assert_value'] > trim($output))) {
							cacti_log("ASSERT: '" . $index_item['assert_value'] . '<' . trim($output) .
								"' failed. Recaching host '" . $item['hostname'] .
								"', data query #" . $index_item['data_query_id'], $print_data_to_stdout, 'POLLER');

							db_execute_prepared('REPLACE INTO poller_command
								(poller_id, time, action, command)
								VALUES (?, NOW(), ?, ?)',
								array($poller_id, POLLER_COMMAND_REINDEX, $item['host_id'] . ':' . $index_item['data_query_id']));

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

							/* spike kill logic */
							if (($assert_fail) &&
								(($index_item['op'] == '<') || ($index_item['arg1'] == '.1.3.6.1.2.1.1.3.0'))) {
								/* don't spike kill unless we are certain */
								if (!empty($output)) {
									$set_spike_kill = true;

									cacti_log("Device[$host_id] NOTICE: Spike Kill in Effect for '" . $item['hostname'] . "'.", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_DEBUG));
								}
							}
						}
					}
				}
			}

			$new_host = false;
			$last_host = $current_host;
		}

		if (!$host_down) {
			switch ($item['action']) {
			case POLLER_ACTION_SNMP: /* snmp */
				if (($item['snmp_version'] == 0) || (($item['snmp_community'] == '') && ($item['snmp_version'] != 3))) {
					cacti_log("Device[$host_id] DS[$data_source] ERROR: Invalid SNMP Data Source.  Please either delete it from the database, or correct it.", $print_data_to_stdout, 'POLLER');
					$output = 'U';
				}else {
					open_snmp_session($host_id, $item);

					if (isset($sessions[$host_id . '_' . $item['snmp_version'] . '_' . $item['snmp_port']])) {
						$output = cacti_snmp_session_get($sessions[$host_id . '_' . $item['snmp_version'] . '_' . $item['snmp_port']], $item['arg1']);
					} else {
						$output = 'U';
					}

					if (!is_numeric($output)) {
						if (prepare_validate_result($output) == false) {
							if (strlen($output) > 20) {
								$strout = 20;
							} else {
								$strout = strlen($output);
							}

							$error_ds[$data_source] = $data_source;

							cacti_log("Device[$host_id] DS[$data_source] WARNING: Result from SNMP not valid.  Partial Result: " . substr($output, 0, $strout), $print_data_to_stdout, 'POLLER', POLLER_VERBOSITY_MEDIUM);
							$output = 'U';
						}
					}
				}

				cacti_log("Device[$host_id] DS[$data_source] SNMP: v" . $item['snmp_version'] . ': ' . $item['hostname'] . ', dsname: ' . $item['rrd_name'] . ', oid: ' . $item['arg1'] . ", output: $output", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

				break;
			case POLLER_ACTION_SCRIPT: /* script (popen) */
				$output = trim(exec_poll($item['arg1']));

				if (!is_numeric($output)) {
					if (prepare_validate_result($output) == false) {
						if (strlen($output) > 20) {
							$strout = 20;
						} else {
							$strout = strlen($output);
						}

						$error_ds[$data_source] = $data_source;

						cacti_log("Device[$host_id] DS[$data_source] WARNING: Result from CMD not valid.  Partial Result: " . substr($output, 0, $strout), $print_data_to_stdout, 'POLLER', POLLER_VERBOSITY_MEDIUM);
					}
				}

				cacti_log("Device[$host_id] DS[$data_source] CMD: " . $item['arg1'] . ", output: $output", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

				break;
			case POLLER_ACTION_SCRIPT_PHP: /* script (php script server) */
				$output = trim(str_replace("\n", '', exec_poll_php($item['arg1'], $using_proc_function, $pipes, $cactiphp)));

				if (!is_numeric($output)) {
					if (prepare_validate_result($output) == false) {
						if (strlen($output) > 20) {
							$strout = 20;
						} else {
							$strout = strlen($output);
						}

						$error_ds[$data_source] = $data_source;

						cacti_log("Device[$host_id] DS[$data_source] WARNING: Result from SERVER not valid.  Partial Result: " . substr($output, 0, $strout), $print_data_to_stdout, 'POLLER', POLLER_VERBOSITY_MEDIUM);
					}
				}

				cacti_log("Device[$host_id] DS[$data_source] SERVER: " . $item['arg1'] . ", output: $output", $print_data_to_stdout, 'POLLER', debug_level($host_id, POLLER_VERBOSITY_MEDIUM));

				break;
			default: /* invalid polling option */
				$error_ds[$data_source] = $data_source;

				cacti_log("Device[$host_id] DS[$data_source] ERROR: Invalid polling option: " . $item['action'], $stdout, 'POLLER');
			} /* End Switch */

			if (isset($output)) {
				/* insert a U in place of the actual value if the snmp agent restarts */
				if (($set_spike_kill) && (!substr_count($output, ':'))) {
					$output_array[] = sprintf('(%d, %s, %s, "U")', $item['local_data_id'], db_qstr($item['rrd_name']), db_qstr($host_update_time));
				/* otherwise, just insert the value received from the poller */
				} else {
					$output_array[] = sprintf('(%d, %s, %s, %s)', $item['local_data_id'], db_qstr($item['rrd_name']), db_qstr($host_update_time), db_qstr($output));
				}

				if ($output_count > 1000) {
					db_execute('INSERT IGNORE INTO poller_output
						(local_data_id, rrd_name, time, output)
						VALUES ' . implode(', ', $output_array));

					if (read_config_option('boost_redirect') == 'on') {
						db_execute('INSERT IGNORE INTO poller_output_boost
							(local_data_id, rrd_name, time, output)
							VALUES ' . implode(', ', $output_array));
					}

					$output_array = array();
					$output_count = 0;
				} else {
					$output_count++;
				}
			}
		} /* Next Cache Item */

		/* check for an over running poller */
		$now = microtime(true);
		if ($now - $start > $polling_interval) {
			cacti_log('WARNING: cmd.php poller over ran its polling intervale and therefore ending');
			break;
		}
	} /* End foreach */

	if (sizeof($error_ds)) {
		cacti_log('WARNING: Invalid Response(s), Errors[' . sizeof($error_ds) . '] Device[' . $last_host . '] Thread[1] DS[' . implode(', ', $error_ds) . ']', false, 'POLLER');
	}

	if ($output_count > 0) {
		db_execute('INSERT IGNORE INTO poller_output
			(local_data_id, rrd_name, time, output)
			VALUES ' . implode(', ', $output_array));

		if (read_config_option('boost_redirect') == 'on') {
			db_execute('INSERT IGNORE INTO poller_output_boost
				(local_data_id, rrd_name, time, output)
				VALUES ' . implode(', ', $output_array));
		}
	}

	if ($using_proc_function && $script_server_calls > 0) {
		// close php server process
		fwrite($pipes[0], "quit\r\n");
		fclose($pipes[0]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$return_value = proc_close($cactiphp);
	}

	/* take time and log performance data */
	$end = microtime(true);

	cacti_log(sprintf('Time: %01.4f s, ' .
		'Poller: %i, ' .
		'Theads: N/A, ' .
		'Devices: %s',
		round($end-$start,4),
		$poller_id,
		$host_count), $print_data_to_stdout, 'POLLER', POLLER_VERBOSITY_MEDIUM);
} else {
	cacti_log('NOTE: There are no items in your poller for this polling cycle!', true, 'POLLER', POLLER_VERBOSITY_MEDIUM);
}

/* record the process as having completed */
record_cmdphp_done();

/* close the database connection */
db_close();

