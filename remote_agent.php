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

require(__DIR__ . '/include/global.php');
require_once(CACTI_PATH_LIBRARY . '/api_device.php');
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/data_query.php');
require_once(CACTI_PATH_LIBRARY . '/api_graph.php');
require_once(CACTI_PATH_LIBRARY . '/api_tree.php');
require_once(CACTI_PATH_LIBRARY . '/data_query.php');
require_once(CACTI_PATH_LIBRARY . '/html_form_template.php');
require_once(CACTI_PATH_LIBRARY . '/ping.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/rrd.php');
require_once(CACTI_PATH_LIBRARY . '/snmp.php');
require_once(CACTI_PATH_LIBRARY . '/sort.php');
require_once(CACTI_PATH_LIBRARY . '/template.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

global $remote_db_cnn_id;

$debug = false;

$poller_db_cnn_id = false;

if (POLLER_ID > 1) {
	if (CACTI_CONNECTION == 'online') {
		if (gnrv('action') == 'runquery') {
			db_force_remote_cnn();
		}

		$poller_db_cnn_id = $remote_db_cnn_id;
	}
}

if (!remote_client_authorized()) {
	print 'FATAL: You are not authorized to use this service';

	exit;
}

set_default_action();

switch (grv('action')) {
	case 'polldata':
		// Only let realtime polling run for a short time
		ini_set('max_execution_time', read_config_option('script_timeout'));

		debug('Start: Poling Data for Realtime');
		poll_for_data();
		debug('End: Poling Data for Realtime');

		break;
	case 'runquery':
		debug('Start: Running Data Query');
		run_remote_data_query();
		debug('End: Running Data Query');

		break;
	case 'ping':
		debug('Start: Pinging Device');
		ping_device();
		debug('End: Pinging Device');

		break;
	case 'snmpget':
		debug('Start: Performing SNMP Get Request');
		get_snmp_data();
		debug('End: Performing SNMP Get Request');

		break;
	case 'snmpwalk':
		debug('Start: Performing SNMP Walk Request');
		get_snmp_data_walk();
		debug('End: Performing SNMP Walk Request');

		break;
	case 'graph_json':
		debug('Start: Performing Graph Request');
		get_graph_data();
		debug('End: Performing Graph Request');

		break;
	case 'discover':
		debug('Start:Performing Network Discovery Request');
		run_remote_discovery();
		debug('End:Performing Network Discovery Request');

		break;
	default:
		if (!api_plugin_hook_function('remote_agent', grv('action'))) {
			debug('WARNING: Unknown Agent Request');
			print 'Unknown Agent Request';
		}
}

exit;

function debug(string $message) : void {
	global $debug;

	if ($debug) {
		cacti_log('REMOTE DEBUG: ' . trim($message), false, 'WEBSVCS');
	}
}

function remote_client_authorized() : bool {
	global $poller_db_cnn_id, $remote_agent_whitelist;

	// don't allow to run from the command line
	$client_addr = get_client_addr();

	if ($client_addr === false) {
		return false;
	}

	if (!filter_var($client_addr, FILTER_VALIDATE_IP)) {
		cacti_log('ERROR: Invalid remote agent client IP Address.  Exiting');

		return false;
	}

	// Whitelist check runs before the poller-count guard so single-poller
	// installs that rely on the whitelist are not incorrectly rejected.
	if (is_array($remote_agent_whitelist) && in_array($client_addr, $remote_agent_whitelist, true)) {
		return true;
	}

	$pollers = db_fetch_assoc_prepared('SELECT hostname
		FROM poller
		WHERE disabled = ?',
		[''],
		true,
		$poller_db_cnn_id
	);

	if (cacti_sizeof($pollers) <= 1) {
		cacti_log("Unauthorized remote agent access attempt from $client_addr", false, 'SECURITY');

		return false;
	}

	$allowed_hostnames = [];
	$poller_hostnames  = [];
	$direct_match      = false;

	foreach ($pollers as $poller) {
		$poller_host = trim($poller['hostname']);

		if ($poller_host === '') {
			continue;
		}

		if ($poller_host === $client_addr) {
			$direct_match = true;

			continue;
		}

		if (!filter_var($poller_host, FILTER_VALIDATE_IP)) {
			$normalized_host     = cacti_strtolower(rtrim($poller_host, '.'));
			$allowed_hostnames[] = $normalized_host;
			$poller_hostnames[]  = $poller_host;
		}
	}

	if (!cacti_sizeof($allowed_hostnames)) {
		cacti_log("Unauthorized remote agent access attempt from $client_addr", false, 'SECURITY');

		return false;
	}

	if ($direct_match) {
		return true;
	}

	foreach ($poller_hostnames as $poller_host) {
		$poller_forward_records = @dns_get_record($poller_host, DNS_A | DNS_AAAA);

		if (is_array($poller_forward_records)) {
			foreach ($poller_forward_records as $record) {
				$ip = isset($record['ip']) ? $record['ip'] : (isset($record['ipv6']) ? $record['ipv6'] : '');

				if ($ip === $client_addr) {
					return true;
				}
			}
		}
	}

	$client_name = gethostbyaddr($client_addr);

	if ($client_name === false || $client_name == $client_addr) {
		cacti_log('NOTE: Unable to resolve hostname from address ' . $client_addr, false, 'WEBUI', POLLER_VERBOSITY_MEDIUM);
		cacti_log("Unauthorized remote agent access attempt from $client_addr", false, 'SECURITY');

		return false;
	}

	$normalized_client_name = cacti_strtolower(rtrim($client_name, '.'));

	if (!in_array($normalized_client_name, $allowed_hostnames, true)) {
		cacti_log("Unauthorized remote agent access attempt from $client_name ($client_addr)", false, 'SECURITY');

		return false;
	}

	$forward_records = @dns_get_record($client_name, DNS_A | DNS_AAAA);
	$forward_match   = false;

	if (is_array($forward_records)) {
		foreach ($forward_records as $record) {
			$ip = isset($record['ip']) ? $record['ip'] : (isset($record['ipv6']) ? $record['ipv6'] : '');

			if ($ip === $client_addr) {
				$forward_match = true;

				break;
			}
		}
	}

	if (!$forward_match) {
		$safe_name = preg_replace('/[^a-zA-Z0-9.\-:]/', '', $client_name);
		cacti_log('WARNING: PTR record for ' . $client_addr . ' resolves to ' . $safe_name . ' but forward lookup does not match. Rejecting.', false, 'SECURITY');

		return false;
	}

	return true;
}

function get_graph_data() : bool {
	gfrv('graph_start');
	gfrv('graph_end');
	gfrv('graph_height');
	gfrv('graph_width');
	gfrv('local_graph_id');
	gfrv('rra_id');
	gfrv('graph_theme', FILTER_CALLBACK, ['options' => 'sanitize_search_string']);
	gfrv('graph_nolegend', FILTER_CALLBACK, ['options' => 'sanitize_search_string']);
	gfrv('effective_user');

	$local_graph_id   = gfrv('local_graph_id');
	$rra_id           = gfrv('rra_id');

	$graph_data_array = [];

	// override: graph start time (unix time)
	if (!ierv('graph_start') && grv('graph_start') < FILTER_VALIDATE_MAX_DATE_AS_INT) {
		$graph_data_array['graph_start'] = grv('graph_start');
	}

	// override: graph end time (unix time)
	if (!ierv('graph_end') && grv('graph_end') < FILTER_VALIDATE_MAX_DATE_AS_INT) {
		$graph_data_array['graph_end'] = grv('graph_end');
	}

	// override: graph height (in pixels)
	if (!ierv('graph_height') && grv('graph_height') < 3000) {
		$graph_data_array['graph_height'] = grv('graph_height');
	}

	// override: graph width (in pixels)
	if (!ierv('graph_width') && grv('graph_width') < 3000) {
		$graph_data_array['graph_width'] = grv('graph_width');
	}

	// override: skip drawing the legend?
	if (!ierv('graph_nolegend')) {
		$graph_data_array['graph_nolegend'] = grv('graph_nolegend');
	}

	// print RRDtool graph source?
	if (!ierv('show_source')) {
		$graph_data_array['print_source'] = grv('show_source');
	}

	// disable cache check
	if (isrv('disable_cache')) {
		$graph_data_array['disable_cache'] = true;
	}

	// set the theme
	if (isrv('graph_theme')) {
		$graph_data_array['graph_theme'] = cacti_validate_theme(grv('graph_theme'));
	}

	// set the effective user
	if (isrv('effective_user')) {
		$user = grv('effective_user');
	} else {
		$user = 0;
	}

	$graph_data_array['graphv'] = true;

	$xport_options = [];

	print @rrdtool_function_graph($local_graph_id, $rra_id, $graph_data_array, null, $xport_options, $user);

	return true;
}

function get_snmp_data() : void {
	$host_id = gfrv('host_id');
	$oid     = gnrv('oid');

	if (!is_string($oid) || !preg_match('/^[0-9.]+$/', $oid)) {
		print 'U';

		return;
	}

	$output  = '';

	if (!empty($host_id)) {
		$host    = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', [$host_id]);
		$session = cacti_snmp_session($host['hostname'], $host['snmp_community'], $host['snmp_version'],
			$host['snmp_username'], $host['snmp_password'], $host['snmp_auth_protocol'], $host['snmp_priv_passphrase'],
			$host['snmp_priv_protocol'], $host['snmp_context'], $host['snmp_engine_id'], $host['snmp_port'],
			$host['snmp_timeout'], $host['ping_retries'], $host['max_oids']);

		if ($session === false) {
			$output = 'U';
		} else {
			$output = cacti_snmp_session_get($session, $oid);
			$session->close();
		}
	}

	print $output;
}

function get_snmp_data_walk() : void {
	$host_id = gfrv('host_id');
	$oid     = gnrv('oid');

	if (!is_string($oid) || !preg_match('/^[0-9.]+$/', $oid)) {
		print 'U';

		return;
	}

	$output  = '';

	if (!empty($host_id)) {
		$host    = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', [$host_id]);
		$session = cacti_snmp_session($host['hostname'], $host['snmp_community'], $host['snmp_version'],
			$host['snmp_username'], $host['snmp_password'], $host['snmp_auth_protocol'], $host['snmp_priv_passphrase'],
			$host['snmp_priv_protocol'], $host['snmp_context'], $host['snmp_engine_id'], $host['snmp_port'],
			$host['snmp_timeout'], $host['ping_retries'], $host['max_oids']);

		if ($session === false) {
			$output = 'U';
		} else {
			$output = cacti_snmp_session_walk($session, $oid);
			$session->close();
		}
	}

	if (cacti_sizeof($output)) {
		print json_encode($output);
	} else {
		print 'U';
	}
}

function ping_device() : void {
	$host_id = gfrv('host_id');
	api_device_ping_device($host_id, true);
}

function poll_for_data() : mixed {
	$local_data_ids = gnrv('local_data_ids');
	$host_id        = gfrv('host_id');
	$poller_id      = gnrv('poller_id');
	$return         = [];

	// ensure we have a valid poller_id
	if (!preg_match('/^[a-z0-9]+$/i', $poller_id)) {
		return [];
	}

	$i = 0;

	if (cacti_sizeof($local_data_ids)) {
		foreach ($local_data_ids as $local_data_id) {
			input_validate_input_number($local_data_id, 'local_data_id');

			$items = db_fetch_assoc_prepared('SELECT *
				FROM poller_item
				WHERE host_id = ?
				AND local_data_id = ?',
				[$host_id, $local_data_id]);

			$script_server_calls = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM poller_item
				WHERE host_id = ?
				AND local_data_id = ?
				AND action = 2',
				[$host_id, $local_data_id]);

			if (cacti_sizeof($items)) {
				foreach ($items as $item) {
					switch ($item['action']) {
						case POLLER_ACTION_SNMP: // snmp
							if (($item['snmp_version'] == 0) || (($item['snmp_community'] == '') && ($item['snmp_version'] != 3))) {
								$output = 'U';
							} else {
								$host    = db_fetch_row_prepared('SELECT ping_retries, max_oids FROM host WHERE hostname = ?', [$item['hostname']]);
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

							$return[$i]['value']         = $output;
							$return[$i]['rrd_name']      = $item['rrd_name'];
							$return[$i]['local_data_id'] = $local_data_id;

							break;
						case POLLER_ACTION_SCRIPT: // script (popen)
							$output = trim(exec_poll($item['arg1']));

							if (prepare_validate_result($output) === false) {
								if (strlen($output) > 20) {
									$strout = 20;
								} else {
									$strout = strlen($output);
								}

								$output = 'U';
							}

							$return[$i]['value']         = $output;
							$return[$i]['rrd_name']      = $item['rrd_name'];
							$return[$i]['local_data_id'] = $local_data_id;

							break;
						case POLLER_ACTION_SCRIPT_PHP: // script (php script server)
							$cactides = [
								0 => ['pipe', 'r'], // stdin is a pipe that the child will read from
								1 => ['pipe', 'w'], // stdout is a pipe that the child will write to
								2 => ['pipe', 'w']  // stderr is a pipe to write to
							];

							$cactiphp = false;
							$pipes    = false;

							if (function_exists('proc_open')) {
								$cactiphp = proc_open(read_config_option('path_php_binary') . ' -q ' . CACTI_PATH_BASE . '/script_server.php realtime ' . cacti_escapeshellarg($poller_id), $cactides, $pipes);

								// proc_open returns false if the child could not be spawned; fall back to
								// the non-proc path rather than reading from non-existent pipes
								if (!is_resource($cactiphp)) {
									cacti_log('WARNING: Unable to start PHP Script Server, falling back to direct execution', false, 'POLLER', POLLER_VERBOSITY_LOW);

									$using_proc_function = false;
									$pipes               = false;
								} else {
									$output = fgets($pipes[1], 1024);

									$using_proc_function = true;
								}
							} else {
								$using_proc_function = false;
							}

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

							$return[$i]['value']         = $output;
							$return[$i]['rrd_name']      = $item['rrd_name'];
							$return[$i]['local_data_id'] = $local_data_id;

							if (($using_proc_function == true) && ($script_server_calls > 0)) {
								// close php server process
								fwrite($pipes[0], "quit\r\n");
								fclose($pipes[0]);
								fclose($pipes[1]);
								fclose($pipes[2]);

								$return_value = proc_close($cactiphp);
							}

							break;
					}

					$i++;
				}
			}
		}
	}

	print json_encode($return);

	return false;
}

function run_remote_data_query() : void {
	$host_id       = gfrv('host_id');
	$data_query_id = gfrv('data_query_id');

	if ($host_id > 0 && $data_query_id > 0) {
		run_data_query($host_id, $data_query_id);
	}
}

function run_remote_discovery() : void {
	$poller_id = cacti_escapeshellarg((string) POLLER_ID);
	$network   = cacti_escapeshellarg(gfrv('network'));
	$php       = cacti_escapeshellcmd(read_config_option('path_php_binary'));
	$path      = cacti_escapeshellarg(read_config_option('path_webroot') . '/poller_automation.php');

	$options   = ' --poller=' . $poller_id . ' --network=' . $network . ' --force';

	if (isrv('debug')) {
		$options .= ' --debug';
	}

	exec_background($php, '-q ' . $path . $options);

	sleep(2);
}
