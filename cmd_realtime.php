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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br>This script is only meant to run at the command line.');
}

$start = date('Y-m-d H:i:s'); // for runtime measurement

ini_set('max_execution_time', '0');

/* we are not talking to the browser */
$no_http_headers = true;

include('./include/global.php');
include_once($config['base_path'] . '/lib/snmp.php');
include_once($config['base_path'] . '/lib/poller.php');
include_once($config['base_path'] . '/lib/rrd.php');
include_once($config['base_path'] . '/lib/ping.php');

/* correct for a windows PHP bug. fixed in 5.2.0 */
if (count($_SERVER['argv']) < 4) {
	echo "No graph_id, interval, pollerid specified.\n\n";
	echo "Usage: cmd_rt.php POLLER_ID GRAPH_ID INTERVAL\n\n";
	exit(-1);
}

$poller_id = $_SERVER['argv'][1];
$graph_id  = (int)$_SERVER['argv'][2];
$interval  = (int)$_SERVER['argv'][3];

if ($graph_id <= 0) {
	echo "Invalid graph_id specified.\n\n";
	exit(-1);
}

if ($interval <= 0) {
	echo "Invalid interval specified.\n\n";
	exit(-1);
}

/* record the start time */
$start = microtime(true);

/* initialize the polling items */
$polling_items = array();

/* get poller_item for graph_id */
$local_data_ids = db_fetch_assoc_prepared('SELECT DISTINCT data_template_rrd.local_data_id
	FROM graph_templates_item
	LEFT JOIN data_template_rrd 
	ON graph_templates_item.task_item_id=data_template_rrd.id
	WHERE graph_templates_item.local_graph_id = ?
	AND data_template_rrd.local_data_id IS NOT NULL', array($graph_id));

if (!count($local_data_ids)) {
	echo "No local_graph_id found\n\n";
	exit(-1);
}

$ids = array();
foreach ($local_data_ids as $row) $ids[] = $row['local_data_id'];

/* check arguments */
$polling_items       = db_fetch_assoc('SELECT *
	FROM poller_item
	WHERE local_data_id IN (' . implode(',', $ids).')
	ORDER BY host_id');

$script_server_calls = db_fetch_cell('SELECT count(*)
	FROM poller_item
	WHERE (action=2)');

$print_data_to_stdout = true;

/* get the number of polling items from the database */
$hosts = db_fetch_assoc("SELECT * FROM host WHERE disabled = '' ORDER BY id");

/* rework the hosts array to be searchable */
$hosts = array_rekey($hosts, 'id', $host_struc);

$host_count = sizeof($hosts);
$script_server_calls = db_fetch_cell('SELECT count(*) FROM poller_item WHERE action=2');

if (sizeof($polling_items)) {
	/* startup Cacti php polling server and include the include file for script processing */
	if ($script_server_calls > 0) {
		$cactides = array(
			0 => array('pipe', 'r'), // stdin is a pipe that the child will read from
			1 => array('pipe', 'w'), // stdout is a pipe that the child will write to
			2 => array('pipe', 'w')  // stderr is a pipe to write to
			);

		if (function_exists('proc_open')) {
			$cactiphp = proc_open(read_config_option('path_php_binary') . ' -q ' . $config['base_path'] . '/script_server.php realtime ' . $poller_id, $cactides, $pipes);
			$output = fgets($pipes[1], 1024);
			$using_proc_function = true;
		}else {
			$using_proc_function = false;
		}
	} else {
		$using_proc_function = false;
	}

	/* all polled items need the same insert time */
	$host_update_time = date('Y-m-d H:i:s');

	foreach ($polling_items as $item) {
		$data_source   = $item['local_data_id'];
		$host_id       = $item['host_id'];
		$col_poller_id = $item['poller_id'];

		if ($col_poller_id > 1) {
			$hostname = db_fetch_cell_prepared('SELECT hostname FROM poller WHERE id = ?', array($col_poller_id));
			$output = file_get_contents(get_url_type() . '://' . $hostname . $config['url_path'] . '/remote_agent.php?action=polldata&host_id=' . $host_id . '&local_data_id=' . $data_source . '&poller_id=' . $poller_id);
		} else {
			switch ($item['action']) {
			case POLLER_ACTION_SNMP: /* snmp */
				if (($item['snmp_version'] == 0) || (($item['snmp_community'] == '') && ($item['snmp_version'] != 3))) {
					$output = 'U';
				}else {
					$host = db_fetch_row_prepared('SELECT ping_retries, max_oids FROM host WHERE hostname = ?', array($item['hostname']));
					$session = cacti_snmp_session($item['hostname'], $item['snmp_community'], $item['snmp_version'],
						$item['snmp_username'], $item['snmp_password'], $item['snmp_auth_protocol'], $item['snmp_priv_passphrase'],
						$item['snmp_priv_protocol'], $item['snmp_context'], $item['snmp_engine_id'], $item['snmp_port'],
						$item['snmp_timeout'], $host['ping_retries'], $host['max_oids']);

					if ($session === false) {
						$output = 'U';
					} else {
						$output = cacti_snmp_session_get($session, $item['arg1']);
						$session->close();
					}

					if (prepare_validate_result($output) === false) {
						if (strlen($output) > 20) {
							$strout = 20;
						} else {
							$strout = strlen($output);
						}

						$output = 'U';
					}
				}

				break;
			case POLLER_ACTION_SCRIPT: /* script (popen) */
				$output = trim(exec_poll($item['arg1']));

				if (prepare_validate_result($output) === false) {
					if (strlen($output) > 20) {
						$strout = 20;
					} else {
						$strout = strlen($output);
					}

					$output = 'U';
				}

				break;
			case POLLER_ACTION_SCRIPT_PHP: /* script (php script server) */
				if ($using_proc_function == true) {
					$output = trim(str_replace("\n", '', exec_poll_php($item['arg1'], $using_proc_function, $pipes, $cactiphp)));

					if (prepare_validate_result($output) === false) {
						if (strlen($output) > 20) {
							$strout = 20;
						} else {
							$strout = strlen($output);
						}

						$output = 'U';
					}
				} else {
					$output = 'U';
				}

				break;
			}
		}

		if (isset($output)) {
			db_execute_prepared('INSERT INTO poller_output_realtime 
				(local_data_id, rrd_name, time, poller_id, output) 
				VALUES 
				(?, ?, ?, ?, ?)', 
				array($item['local_data_id'], $item['rrd_name'], $host_update_time, $poller_id, $output));
		}
	}

	if (($using_proc_function == true) && ($script_server_calls > 0)) {
		/* close php server process */
		fwrite($pipes[0], "quit\r\n");
		fclose($pipes[0]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$return_value = proc_close($cactiphp);
	}
}
