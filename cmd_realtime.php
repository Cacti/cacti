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

require_once(__DIR__ . '/include/cli_check.php');
require_once($config['base_path'] . '/lib/snmp.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/rrd.php');
require_once($config['base_path'] . '/lib/ping.php');

ini_set('max_execution_time', '0');

$start = date('Y-m-d H:i:s'); // for runtime measurement

/* correct for a windows PHP bug. fixed in 5.2.0 */
if (cacti_count($_SERVER['argv']) < 4) {
	echo "No graph_id, interval, pollerid specified.\n\n";
	echo "Usage: cmd_realtime.php POLLER_ID GRAPH_ID INTERVAL\n\n";
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
$local_data_ids = db_fetch_assoc_prepared('SELECT DISTINCT dtr.local_data_id, dl.host_id
	FROM graph_templates_item AS gti
	INNER JOIN data_template_rrd AS dtr
	ON gti.task_item_id=dtr.id
	INNER JOIN data_local AS dl
	ON dl.id=dtr.local_data_id
	WHERE gti.local_graph_id = ?
	AND dtr.local_data_id > 0',
	array($graph_id));

if (!cacti_count($local_data_ids)) {
	echo "No local_graph_id found\n\n";
	exit(-1);
}

$ids      = array();
$hosts    = array();
$idbyhost = array();

foreach ($local_data_ids as $row) {
	if ($row['local_data_id'] > 0 && $row['host_id'] != '') {
		$ids[$row['local_data_id']]  = $row['local_data_id'];
		$hosts[$row['host_id']]      = $row['host_id'];

		$idbyhost[$row['host_id']][] = $row['local_data_id'];
	}
}

$print_data_to_stdout = true;

if (cacti_sizeof($idbyhost)) {
	$polling_items = db_fetch_assoc('SELECT *
		FROM poller_item
		WHERE local_data_id IN (' . implode(',', $ids) . ')
		AND host_id IN (' . implode(',', $hosts) . ')
		ORDER BY host_id');

	$script_server_calls = db_fetch_cell('SELECT count(*)
		FROM poller_item
		WHERE action=2
		AND host_id IN (' . implode(',', $hosts) . ')
		AND local_data_id IN (' . implode(',', $ids) . ')');

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
		} else {
			$using_proc_function = false;
		}
	} else {
		$using_proc_function = false;
	}

	/* all polled items need the same insert time */
	$host_update_time = date('Y-m-d H:i:s');

	foreach ($idbyhost as $host_id => $local_data_ids) {
		$col_poller_id = db_fetch_cell_prepared('SELECT poller_id
			FROM host
			WHERE id = ?', array($host_id));

		$local_data_ids = array(
			'local_data_ids' => $local_data_ids
		);

		if ($col_poller_id > 1) {
			$hostname = db_fetch_cell_prepared('SELECT hostname
				FROM poller
				WHERE id = ?',
				array($col_poller_id));

			$port = read_config_option('remote_agent_port');
			if ($port != '') {
				$port = ':' . $port;
			}

			$url = get_url_type() . '://' . $hostname . $port .
				$config['url_path'] . '/remote_agent.php' .
				'?action=polldata' .
				'&host_id=' . $host_id .
				'&' . http_build_query($local_data_ids) .
				'&poller_id=' . $poller_id;

			$fgc_contextoption = get_default_contextoption();
			$fgc_context       = stream_context_create($fgc_contextoption);
			$output            = json_decode(@file_get_contents($url, FALSE, $fgc_context), true);

			if (cacti_sizeof($output)) {
				$sql = '';
				foreach($output as $item) {
					$sql .= ($sql != '' ? ', ':'')      . '(' .
						db_qstr($item['local_data_id']) . ', ' .
						db_qstr($item['rrd_name'])      . ', ' .
						db_qstr($host_update_time)      . ', ' .
						db_qstr($poller_id)             . ', ' .
						db_qstr($item['value'])         . ')';
				}

				db_execute("INSERT INTO poller_output_realtime
					(local_data_id, rrd_name, time, poller_id, output)
					VALUES $sql");
			}
		} else {
			$poller_items = db_fetch_assoc_prepared('SELECT *
				FROM poller_item
				WHERE host_id = ?
				AND local_data_id IN(' . implode(',', $local_data_ids['local_data_ids']) . ')',
				array($host_id));

			if (cacti_sizeof($poller_items)) {
				foreach($poller_items as $item) {
					switch ($item['action']) {
					case POLLER_ACTION_SNMP: /* snmp */
						if (($item['snmp_version'] == 0) || (($item['snmp_community'] == '') && ($item['snmp_version'] != 3))) {
							$output = 'U';
						} else {
							$host = db_fetch_row_prepared('SELECT ping_retries, max_oids
								FROM host
								WHERE id = ?',
								array($host_id));

							if (!cacti_sizeof($host)) {
								$host['ping_retries'] = 1;
								$host['max_oids'] = 1;
							}

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

					if (isset($output)) {
						db_execute_prepared('INSERT INTO poller_output_realtime
							(local_data_id, rrd_name, time, poller_id, output)
							VALUES
							(?, ?, ?, ?, ?)',
							array($item['local_data_id'], $item['rrd_name'], $host_update_time, $poller_id, $output));
					}
				}
			}
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

