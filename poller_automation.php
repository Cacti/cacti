#!/usr/bin/env php
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

if (function_exists('pcntl_async_signals')) {
	pcntl_async_signals(true);
} else {
	declare(ticks = 100);
}

ini_set('output_buffering', 'Off');

require(__DIR__ . '/include/cli_check.php');
require_once($config['base_path'] . '/lib/api_device.php');
require_once($config['base_path'] . '/lib/api_data_source.php');
require_once($config['base_path'] . '/lib/api_graph.php');
require_once($config['base_path'] . '/lib/api_tree.php');
require_once($config['base_path'] . '/lib/data_query.php');
require_once($config['base_path'] . '/lib/html_form_template.php');
require_once($config['base_path'] . '/lib/ping.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/snmp.php');
require_once($config['base_path'] . '/lib/sort.php');
require_once($config['base_path'] . '/lib/template.php');
require_once($config['base_path'] . '/lib/utility.php');

if ($config['poller_id'] > 1) {
	if ($config['connection'] == 'online') {
		db_force_remote_cnn();
	} else {
		cacti_log('WARNING: Main Cacti database offline or in recovery.  Can not run automation', false, 'AUTOM8');
		admin_email(__('Cacti System Warning'), __('WARNING: Main Cacti database offline or in recovery'));
		exit(1);
	}
}

/** sig_handler - provides a generic means to catch exceptions to the Cacti log.
 * @arg $signo  - (int) the signal that was thrown by the interface.
 * @return      - null */
function sig_handler($signo) {
	global $network_id, $thread, $master, $poller_id;

    switch ($signo) {
        case SIGTERM:
        case SIGINT:
			if ($thread > 0) {
				clearTask($network_id, getmypid());
				exit(0);
			} elseif($thread == 0 && !$master) {
				$pids = array_rekey(db_fetch_assoc_prepared("SELECT pid
					FROM automation_processes
					WHERE network_id = ?
					AND task!='tmaster'",
					array($network_id)), 'pid', 'pid');

				if (cacti_sizeof($pids)) {
					foreach($pids as $pid) {
						posix_kill($pid, SIGTERM);
					}
				}

				clearTask($network_id, getmypid());

				sleep(5);

				db_execute_prepared('DELETE
					FROM automation_ips
					WHERE network_id = ?',
					array($network_id));
			} else {
				$pids = array_rekey(db_fetch_assoc_prepared("SELECT pid
					FROM automation_processes
					WHERE poller_id = ?
					AND task='tmaster'",
					array($poller_id)), 'pid', 'pid');

				if (cacti_sizeof($pids)) {
					foreach($pids as $pid) {
						posix_kill($pid, SIGTERM);
					}
				}

				clearTask($network_id, getmypid());
			}

            exit(0);

            break;
        default:
            /* ignore all other signals */
    }
}

/* take time and log performance data */
$start = microtime(true);

// Unix Timestamp for Database
$startTime = time();

/* let PHP run just as long as it has to */
ini_set('max_execution_time', '0');

$dir = dirname(__FILE__);
chdir($dir);

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug      = false;
$force      = false;
$network_id = 0;
$poller_id  = $config['poller_id'];
$thread     = 0;
$master     = false;

global $debug, $poller_id, $network_id, $thread, $master;

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-d':
			case '--debug':
				$debug = true;
				break;
			case '-M':
			case '--master':
				$master = true;
				break;
			case '--poller':
				$poller_id = $value;
				break;
			case '-f':
			case '--force':
				$force = true;
				break;
			case '--network':
				$network_id = $value;
				break;
			case '--thread':
				$thread = $value;
				break;
			case '-v':
			case '--version':
				display_version();
				exit(0);
			case '-h':
			case '--help':
				display_help();
				exit(0);
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit(1);
		}
	}
}

/* install signal handlers for UNIX only */
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, 'sig_handler');
    pcntl_signal(SIGINT, 'sig_handler');
}

// Let's ensure that we were called correctly
if (!$master && !$network_id) {
	print "FATAL: You must specify -M to Start the Master Control Process, or the Network ID using --network\n";
	exit(1);
}

// Simple check for a disabled network
if (!$master && $thread == 0) {
	$status = db_fetch_cell_prepared('SELECT enabled
		FROM automation_networks
		WHERE id = ?
		AND poller_id = ?',
		array($network_id, $poller_id));

	if ($status != 'on' && !$force) {
		cacti_log(automation_get_pid() . " WARNING: The Network ID: $network_id is disabled.  You must use the 'force' option to force it's execution.", true, 'AUTOM8');
		exit(1);
	}
}

if ($master) {
	if (!db_table_exists('automation_networks')) {
		exit(0);
	}

	$networks = db_fetch_assoc_prepared('SELECT *
		FROM automation_networks
		WHERE poller_id = ?',
		array($poller_id));

	$launched = 0;
	if (cacti_sizeof($networks)) {
		foreach($networks as $network) {
			if ($network['snmp_id'] == 0) {
				cacti_log("ERROR: Automation can not run for Network '" . $network['name'] . "' since the SNMP ID is not set.", false, 'AUTOM8');
				continue;
			}

			if (api_automation_is_time_to_start($network['id']) || $force) {
				automation_debug("Launching Network Master for '" . $network['name'] . "'\n");
				exec_background(read_config_option('path_php_binary'), '-q ' . read_config_option('path_webroot') . '/poller_automation.php --poller=' . $poller_id . ' --network=' . $network['id'] . ($force ? ' --force':'') . ($debug ? ' --debug':''));
				$launched++;
			} else {
				automation_debug("Not time to Run Discovery for '" . $network['name'] . "'\n");
			}
		}
	}

	exit(0);
}

// Check for Network Master
if (!$master && $thread == 0) {
	automation_debug("Thread master about to launch collector threads\n");

	// Remove any stale entries
	$pids = array_rekey(
		db_fetch_assoc_prepared('SELECT pid
			FROM automation_processes
			WHERE network_id = ?',
			array($network_id)),
		'pid', 'pid'
	);

	automation_debug("Killing any prior running threads\n");
	if (cacti_sizeof($pids)) {
		foreach($pids as $pid) {
			if (isProcessRunning($pid)) {
				killProcess($pid);
				cacti_log("WARNING: Automation Process $pid is still running for Network ID: $network_id", true, 'AUTOM8');
			} else {
				cacti_log("WARNING: Process $pid claims to be running but not found for Network ID: $network_id", true, 'AUTOM8');
			}
		}
	}

	automation_debug("Removing any orphan entries\n");

	db_execute_prepared('DELETE FROM automation_ips
		WHERE network_id = ?',
		array($network_id));

	db_execute_prepared('DELETE FROM automation_processes
		WHERE network_id = ?',
		array($network_id));

	registerTask($network_id, getmypid(), $poller_id, 'tmaster');

	cacti_log(automation_get_pid() . " Network Discover is now running for Subnet Range '$network_id'", true, 'AUTOM8');

	$preexisting_devices = getNetworkDevices($network_id);

	automation_primeIPAddressTable($network_id);

	$threads = db_fetch_cell_prepared('SELECT threads
		FROM automation_networks
		WHERE id = ?',
		array($network_id));

	if ($threads <= 0) {
		$threads = 1;
	}

	automation_debug("Automation will use $threads Threads\n");

	db_execute_prepared('UPDATE automation_networks
		SET last_started = ?
		WHERE id = ?',
		array(date('Y-m-d H:i:s', $startTime), $network_id));

	$curthread = 1;
	while($curthread <= $threads) {
		automation_debug("Launching Thread $curthread\n");
		exec_background(read_config_option('path_php_binary'), '-q ' . read_config_option('path_webroot') . '/poller_automation.php --poller=' . $poller_id . " --thread=$curthread --network=$network_id" . ($force ? ' --force':'') . ($debug ? ' --debug':''));
		$curthread++;
	}

	sleep(5);
	automation_debug("Checking for Running Threads\n");

	$failcount = 0;
	while (true) {
		$command = db_fetch_cell_prepared('SELECT command
			FROM automation_processes
			WHERE network_id = ?
			AND task="tmaster"',
			array($network_id));

		if ($command == 'cancel') {
			killProcess(getmypid());
		}

		$running = db_fetch_cell_prepared('SELECT count(*)
			FROM automation_processes
			WHERE network_id = ?
			AND task!="tmaster"
			AND status="running"',
			array($network_id));

		automation_debug("Found $running Threads\n");

		// Are there no more running tasks? Wait up to 15 seconds to
		// allow processes to start before checking for failures
		if (($running == 0 && $failcount > 3) || $command == 'cancel') {
			db_execute_prepared('DELETE FROM automation_ips
				WHERE network_id = ?',
				array($network_id));

			$totals = db_fetch_row_prepared('SELECT SUM(up_hosts) AS up, SUM(snmp_hosts) AS snmp
				FROM automation_processes
				WHERE network_id = ?',
				array($network_id));

			/* take time and log performance data */
			$end = microtime(true);

			db_execute_prepared('UPDATE automation_networks
				SET up_hosts = ?, snmp_hosts = ?,
					last_started = ?, last_runtime = ?
				WHERE id = ?',
				array($totals['up'], $totals['snmp'], date('Y-m-d H:i:s', $startTime), ($end - $start), $network_id));

			clearAllTasks($network_id);
			reportNetworkStatus($network_id, $preexisting_devices);

			exit(0);
		} else {
			$failcount++;
		}

		sleep(5);
	}
} else {
	registerTask($network_id, getmypid(), $poller_id);
	discoverDevices($network_id, $thread);
	endTask($network_id, getmypid());
}

exit(0);

function discoverDevices($network_id, $thread) {
	$network = db_fetch_row_prepared('SELECT *
		FROM automation_networks
		WHERE id = ?',
		array($network_id));

	$temp = db_fetch_assoc('SELECT automation_templates.*, host_template.name
		FROM automation_templates
		LEFT JOIN host_template
		ON (automation_templates.host_template=host_template.id)');

	$dns = trim($network['dns_servers']);

	/* Let's do some stats! */
	$stats = array();
	$stats['scanned'] = 0;
	$stats['ping']    = 0;
	$stats['snmp']    = 0;
	$stats['added']   = 0;
	$count_graph      = 0;
	$count            = 0;

	while(true) {
		// Check for cancel
		$command = db_fetch_cell_prepared('SELECT command
			FROM automation_processes
			WHERE network_id = ?
			AND task = "tmaster"',
			array($network_id));

		if ($command == 'cancel' || empty($command)) {
			removeMyProcess(getmypid(), $network_id);
			killProcess(getmypid());
			exit(0);
		}

		// set and ip to be scanned
		db_execute_prepared('UPDATE automation_ips
			SET pid = ?, thread = ?
			WHERE network_id = ?
			AND status = 0
			AND pid = 0
			LIMIT 1',
			array(getmypid(), $thread, $network_id));

		$device = db_fetch_row_prepared('SELECT *
			FROM automation_ips
			WHERE pid = ?
			AND thread = ?
			AND status=0',
			array(getmypid(), $thread));

		if (cacti_sizeof($device) && isset($device['ip_address'])) {
			$count++;

			cacti_log(automation_get_pid() . ' NOTE: Found device IP address \'' . $device['ip_address'] .'\' to check', false, 'AUTOM8', POLLER_VERBOSITY_MEDIUM);

			if (!filter_var($device['ip_address'], FILTER_VALIDATE_IP)) {
				cacti_log(automation_get_pid() . ' WARNING: IP address \'' . $device['ip_address'] .'\' is not a valid IP address.', false, 'AUTOM8');

				markIPDone($device['ip_address'], $network_id);

				continue;
			}

			if ($dns != '') {
				$dnsname = automation_get_dns_from_ip($device['ip_address'], $dns, 300);

				if ($dnsname != $device['ip_address'] && $dnsname != 'timed_out') {
					automation_debug("Device: " . $device['ip_address'] . ", Checking DNS: Found '" . $dnsname . "'");

					db_execute_prepared('UPDATE automation_ips
						SET hostname = ?
						WHERE ip_address = ?',
						array($dnsname, $device['ip_address']));

					$device['hostname']      = $dnsname;
					$device['dnsname']       = $dnsname;
					$device['dnsname_short'] = preg_split('/[\.]+/', strtolower($dnsname), -1, PREG_SPLIT_NO_EMPTY);
				} elseif ($network['enable_netbios'] == 'on') {
					automation_debug("Device: " . $device['ip_address'] . ", Checking DNS: Not found, Checking NetBIOS:");

					$netbios = ping_netbios_name($device['ip_address']);

					if ($netbios === false) {
						automation_debug(" Not found");
						$device['hostname']      = $device['ip_address'];
						$device['dnsname']       = '';
						$device['dnsname_short'] = '';
					} else {
						automation_debug(" Found: '" . $netbios . "'");

						db_execute_prepared('UPDATE automation_ips
							SET hostname = ?
							WHERE ip_address = ?',
							array($device['hostname'], $device['ip_address']));

						$device['dnsname']       = $netbios;
						$device['dnsname_short'] = $netbios;
					}
				} else {
					automation_debug("Device: " . $device['ip_address'] . ", Checking DNS: Not found");

					$device['hostname']      = $device['ip_address'];
					$device['dnsname']       = '';
					$device['dnsname_short'] = '';
				}
			} else {
				$dnsname = @gethostbyaddr($device['ip_address']);
				$device['hostname'] = $dnsname;

				if ($dnsname != $device['ip_address']) {
					automation_debug("Device: " . $device['ip_address'] . ", Checking DNS: Found '" . $dnsname . "'");

					db_execute_prepared('UPDATE automation_ips
						SET hostname = ?
						WHERE ip_address = ?',
						array($dnsname, $device['ip_address']));

					$device['dnsname']       = $dnsname;
					$device['dnsname_short'] = preg_split('/[\.]+/', strtolower($dnsname), -1, PREG_SPLIT_NO_EMPTY);
				} elseif ($network['enable_netbios'] == 'on') {
					automation_debug("Device: " . $device['ip_address'] . ", Checking DNS: Not found, Checking NetBIOS:");

					$netbios = ping_netbios_name($device['ip_address']);
					if ($netbios === false) {
						automation_debug(" Not found");
						$device['hostname']      = $device['ip_address'];
						$device['dnsname']       = '';
						$device['dnsname_short'] = '';
					} else {
						automation_debug(" Found: '" . $netbios . "'");

						db_execute_prepared('UPDATE automation_ips
							SET hostname = ?
							WHERE ip_address = ?',
							array($device['hostname'], $device['ip_address']));

						$device['dnsname']       = $netbios;
						$device['dnsname_short'] = $netbios;
					}
				} else {
					automation_debug("Device: " . $device['ip_address'] . ", Checking DNS: Not found");

					$device['hostname']      = $device['ip_address'];
					$device['dnsname']       = '';
					$device['dnsname_short'] = '';
				}
			}

			$exists = db_fetch_row_prepared('SELECT id, snmp_version, status, deleted
				FROM host
				WHERE hostname IN (?,?)',
				array($device['ip_address'], $device['hostname']));

			if (!cacti_sizeof($exists)) {
				automation_debug(", Status: Not in Cacti");

				if (substr($device['ip_address'], -3) < 255) {
					automation_debug(', Ping: ');

					// Set status to running
					markIPRunning($device['ip_address'], $network_id);

					$stats['scanned']++;

					$device['snmp_status']          = 0;
					$device['ping_status']          = 0;
					$device['snmp_id']              = $network['snmp_id'];
					$device['poller_id']            = $network['poller_id'];
					$device['site_id']              = $network['site_id'];
					$device['snmp_version']         = '2';
					$device['snmp_port']            = '';
					$device['snmp_community']       = '';
					$device['snmp_username']        = '';
					$device['snmp_password']        = '';
					$device['snmp_auth_protocol']   = '';
					$device['snmp_auth_passphrase'] = '';
					$device['snmp_auth_protocol']   = '';
					$device['snmp_context']         = '';
					$device['snmp_port']            = '';
					$device['snmp_timeout']         = '';
					$device['snmp_sysDescr']        = '';
					$device['snmp_sysObjectID']     = '';
					$device['snmp_sysUptime']       = 0;
					$device['snmp_sysName']         = '';
					$device['snmp_sysName_short']   = '';
					$device['snmp_sysLocation']     = '';
					$device['snmp_sysContact']      = '';
					$device['os']                   = '';
					$device['snmp_priv_passphrase'] = '';
					$device['snmp_priv_protocol']   = '';
					$device['max_oids']             = '10';
					$device['bulk_walk_size']       = '-1';

					/* create new ping socket for host pinging */
					$ping = new Net_Ping;
					$ping->host['hostname'] = $device['ip_address'];
					$ping->retries = $network['ping_retries'];
					$ping->port    = $network['ping_port'];;

					/* perform the appropriate ping check of the host */
					$bypass_ping = false;
					$result      = false;
					if ($network['ping_method'] == PING_SNMP) {
						$bypass_ping = true;
					}

					if ($bypass_ping == false) {
						$result = $ping->ping(AVAIL_PING, $network['ping_method'], $network['ping_timeout'], 1);

						if (!$result) {
							automation_debug(" No response");
							updateDownDevice($network_id, $device['ip_address']);
						} else {
							automation_debug(" Responded");
							$stats['ping']++;
							addUpDevice($network_id, getmypid());
						}
					}


					if (($result || $bypass_ping) && automation_valid_snmp_device($device)) {
						$snmp_sysName       = trim($device['snmp_sysName']);
						$snmp_sysName_short = '';
						if (!is_ipaddress($snmp_sysName)) {
							$parts = explode('.', $snmp_sysName);
							foreach($parts as $part) {
								if (is_numeric($part)) {
									$snmp_sysName_short = $snmp_sysName;
									break;
								}
							}

							if ($snmp_sysName_short == '') {
								$snmp_sysName_short = $parts[0];
							}
						} else {
							$snmp_sysName_short = $snmp_sysName;
						}

						$exists = db_fetch_row_prepared('SELECT id, status, snmp_version, deleted
							FROM host
							WHERE hostname IN (?,?)',
							array($snmp_sysName_short, $snmp_sysName));

						if (cacti_sizeof($exists)) {
							if ($exists['deleted'] != 'on') {
								if ($exists['status'] == 3 || $exists['status'] == 2) {
									addUpDevice($network_id, getmypid());

									if ($exists['snmp_version'] > 0) {
										addSNMPDevice($network_id, getmypid());
									}

									// Rerun data queries if specified
									rerunDataQueries($exists['id'], $network);
								}

								automation_debug(' Device is in Cacti!');
							} else {
								automation_debug(' Device is in Cacti but marked as deleted!');
							}

							markIPDone($device['ip_address'], $network_id);
						} else {
							$host_id = 0;

							if ($snmp_sysName != '') {
								$hostname = gethostbyaddr($device['ip_address']);
								if ($hostname != $device['ip_address']) {
									if (strpos($hostname, '.')) {
										$hostname = substr($hostname, 0, strpos($hostname, '.') - 1);
									}
								}

								$isCactiSysName = db_fetch_cell_prepared('SELECT COUNT(*)
									FROM host
									WHERE snmp_sysName = ?
									AND (hostname = ? OR hostname LIKE "' . $hostname . '%")',
									array($snmp_sysName, $device['ip_address']));

								if ($isCactiSysName) {
									automation_debug(", Skipping sysName '" . $snmp_sysName . "' already in Cacti!\n");
									markIPDone($device['ip_address'], $network_id);
									continue;
								}

								if ($network['same_sysname'] == '') {
									$isDuplicateSysNameDiscovery = db_fetch_cell_prepared('SELECT COUNT(*)
										FROM automation_devices
										WHERE network_id = ?
										AND sysName != ""
										AND ip != ?
										AND sysName = ?',
										array($network_id, $device['ip_address'], $snmp_sysName));

									$isDuplicateSysNameCacti = db_fetch_cell_prepared('SELECT COUNT(*)
										FROM host
										WHERE snmp_sysName = ?
										AND hostname != ?',
										array($snmp_sysName, $device['ip_address']));

									if ($isDuplicateSysNameDiscovery || $isDuplicateSysNameCacti) {
										automation_debug(", Skipping sysName '" . $snmp_sysName . "' already Discovered!\n");
										markIPDone($device['ip_address'], $network_id);
										continue;
									}
								}

								$stats['snmp']++;
								addSNMPDevice($network_id, getmypid());

								automation_debug(" Responded");

								$fos = automation_find_os($device['snmp_sysDescr'], $device['snmp_sysObjectID'], $device['snmp_sysName']);

								if ($fos != false && $network['add_to_cacti'] == 'on') {
									automation_debug(', Template: ' . $fos['name'] . "\n");
									$device['os']                   = $fos['name'];
									$device['host_template']        = $fos['host_template'];
									$device['availability_method']  = $fos['availability_method'];

									$host_id = automation_add_device($device);

									if (!empty($host_id)) {
										if (isset($device['snmp_sysDescr']) && $device['snmp_sysDescr'] != '') {
											db_execute_prepared('UPDATE host
												SET snmp_sysDescr = ?
												WHERE id = ?',
												array($device['snmp_sysDescr'], $host_id));
										}

										if (isset($device['snmp_sysObjectID']) && $device['snmp_sysObjectID'] != '') {
											db_execute_prepared('UPDATE host
												SET snmp_sysObjectID = ?
												WHERE id = ?',
												array($device['snmp_sysObjectID'], $host_id));
										}

										if (isset($device['snmp_sysUptime']) && $device['snmp_sysUptime'] != '') {
											db_execute_prepared('UPDATE host
												SET snmp_sysUptimeInstance = ?
												WHERE id = ?',
												array($device['snmp_sysUptime'], $host_id));
										}

										if (isset($device['snmp_sysContact']) && $device['snmp_sysContact'] != '') {
											db_execute_prepared('UPDATE host
												SET snmp_sysContact = ?
												WHERE id = ?',
												array($device['snmp_sysContact'], $host_id));
										}

										if (isset($device['snmp_sysName']) && $device['snmp_sysName'] != '') {
											db_execute_prepared('UPDATE host
												SET snmp_sysName = ?
												WHERE id = ?',
												array($device['snmp_sysName'], $host_id));
										}

										if (isset($device['snmp_sysLocation']) && $device['snmp_sysLocation'] != '') {
											db_execute_prepared('UPDATE host
												SET snmp_sysLocation = ?
												WHERE id = ?',
												array($device['snmp_sysLocation'], $host_id));
										}

										automation_update_device($host_id);
									}

									$stats['added']++;
								} elseif ($fos == false) {
									automation_debug(", Template: Not found, Not adding to Cacti\n");
								} else {
									automation_debug(", Template: " . $fos['name']);
									$device['os'] = $fos['name'];
									automation_debug(", Skipped: Add to Cacti disabled\n");
								}
							}

							// if the devices template is not discovered, add to found table
							if ($host_id == 0) {
								db_execute('REPLACE INTO automation_devices
									(network_id, hostname, ip, snmp_community, snmp_version, snmp_port, snmp_username, snmp_password, snmp_auth_protocol, snmp_priv_passphrase, snmp_priv_protocol, snmp_context, sysName, sysLocation, sysContact, sysDescr, sysUptime, os, snmp, up, time) VALUES ('
									. $network_id                              . ', '
									. db_qstr($device['dnsname'])              . ', '
									. db_qstr($device['ip_address'])           . ', '
									. db_qstr($device['snmp_community'])       . ', '
									. db_qstr($device['snmp_version'])         . ', '
									. db_qstr($device['snmp_port'])            . ', '
									. db_qstr($device['snmp_username'])        . ', '
									. db_qstr($device['snmp_password'])        . ', '
									. db_qstr($device['snmp_auth_protocol'])   . ', '
									. db_qstr($device['snmp_priv_passphrase']) . ', '
									. db_qstr($device['snmp_priv_protocol'])   . ', '
									. db_qstr($device['snmp_context'])         . ', '
									. db_qstr($device['snmp_sysName'])         . ', '
									. db_qstr($device['snmp_sysLocation'])     . ', '
									. db_qstr($device['snmp_sysContact'])      . ', '
									. db_qstr($device['snmp_sysDescr'])        . ', '
									. db_qstr($device['snmp_sysUptime'])       . ', '
									. db_qstr($device['os'])                   . ', '
									. '1, 1,' . time() . ')');
							}

							markIPDone($device['ip_address'], $network_id);
						}
					} elseif ($result) {
						db_execute('REPLACE INTO automation_devices
							(network_id, hostname, ip, snmp_community, snmp_version, snmp_port, snmp_username, snmp_password, snmp_auth_protocol, snmp_priv_passphrase, snmp_priv_protocol, snmp_context, sysName, sysLocation, sysContact, sysDescr, sysUptime, os, snmp, up, time) VALUES ('
							. $network_id                              . ', '
							. db_qstr($device['dnsname'])              . ', '
							. db_qstr($device['ip_address'])           . ', '
							. db_qstr($device['snmp_community'])       . ', '
							. db_qstr($device['snmp_version'])         . ', '
							. db_qstr($device['snmp_port'])            . ', '
							. db_qstr($device['snmp_username'])        . ', '
							. db_qstr($device['snmp_password'])        . ', '
							. db_qstr($device['snmp_auth_protocol'])   . ', '
							. db_qstr($device['snmp_priv_passphrase']) . ', '
							. db_qstr($device['snmp_priv_protocol'])   . ', '
							. db_qstr($device['snmp_context'])         . ', '
							. db_qstr($device['snmp_sysName'])         . ', '
							. db_qstr($device['snmp_sysLocation'])     . ', '
							. db_qstr($device['snmp_sysContact'])      . ', '
							. db_qstr($device['snmp_sysDescr'])        . ', '
							. db_qstr($device['snmp_sysUptime'])       . ', '
							. '"", 0, 1,' . time() . ')');

						automation_debug(", Alive no SNMP!");

						markIPDone($device['ip_address'], $network_id);
					} else {
						markIPDone($device['ip_address'], $network_id);
					}

					automation_debug("\n");
				} else {
					automation_debug(", Status: Ignoring Address (PHP Bug does not allow us to ping .255 as it thinks its a broadcast IP)!\n");
					markIPDone($device['ip_address'], $network_id);
				}
			} else {
				if ($exists['deleted'] != 'on') {
					if ($exists['status'] == 3 || $exists['status'] == 2) {
						addUpDevice($network_id, getmypid());

						if ($exists['snmp_version'] > 0) {
							addSNMPDevice($network_id, getmypid());
						}

						// Rerun data queries if specified
						rerunDataQueries($exists['id'], $network);
					}

					automation_debug(", Status: Already in Cacti\n");
				} else {
					automation_debug(", Status: Already in Cacti but marked as deleted\n");
				}

				markIPDone($device['ip_address'], $network_id);
			}
		} else {
			// no more ips to scan
			break;
		}
	}

	cacti_log(automation_get_pid() . ' Network ' . $network['name'] . " Thread $thread Finished, " . $stats['scanned'] . ' IPs Scanned, ' . $stats['ping'] . ' IPs Responded to Ping, ' . $stats['snmp'] . ' Responded to SNMP, ' . $stats['added'] . ' Device Added, ' . $count_graph .  ' Graphs Added to Cacti', true, 'AUTOM8');

	return true;
}

/*  display_version - displays version information */
function display_version() {
	$version = CACTI_VERSION_TEXT_CLI;
    print "Cacti Network Discovery Scanner, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print "\nusage: poller_automation.php -M [--poller=ID] | --network=network_id [-T=thread_id]\n";
	print "    [--debug] [--force]\n\n";
	print "Cacti's automation poller.  This poller has two operating modes, master and worker.\n";
	print "The master process tracks and launches all workers based upon Cacti's automation\n";
	print "settings.  If you only want to force a network to be collected, you only need to\n";
	print "specify the Network ID and the force options.\n\n";
	print "Master Process:\n";
	print "    -M | --master - Master poller for all Automation\n";
	print "    --poller=ID   - Master Poller ID, Defaults to 0 or WebServer\n\n";
	print "Network Masters and Workers:\n";
	print "    --network=n   - Network ID to discover\n";
	print "    --thread=n    - Thread ID, Defaults to 0 or Network Master\n\n";
	print "General Options:\n";
	print "    --force       - Force the execution of a discovery process\n";
	print "    --debug       - Display verbose output during execution\n\n";
}

function isProcessRunning($pid) {
    return posix_kill($pid, 0);
}

function killProcess($pid) {
	return posix_kill($pid, SIGTERM);
}

function removeMyProcess($pid, $network_id) {
	db_execute_prepared('DELETE FROM automation_processes
		WHERE pid = ?
		AND network_id = ?',
		array($pid, $network_id));

	db_execute_prepared('DELETE FROM automation_ips
		WHERE pid = ?
		AND network_id = ?',
		array($pid, $network_id));
}

function rerunDataQueries($host_id, &$network) {
	if ($network['rerun_data_queries'] == 'on') {
		$snmp_queries = db_fetch_assoc_prepared('SELECT snmp_query_id
			FROM host_snmp_query
			WHERE host_id = ?',
			array($host_id));

		if (cacti_sizeof($snmp_queries)) {
			foreach($snmp_queries as $query) {
				run_data_query($host_id, $query['snmp_query_id']);
			}
		}
	}
}

function registerTask($network_id, $pid, $poller_id, $task = 'collector') {
	db_execute_prepared("REPLACE INTO automation_processes
		(pid, poller_id, network_id, task, status, heartbeat, command)
		VALUES (?, ?, ?, ?, 'running', NOW(), 'start')",
		array($pid, $poller_id, $network_id, $task));
}

function endTask($network_id, $pid) {
	db_execute_prepared("UPDATE automation_processes
		SET status='done', heartbeat=NOW()
		WHERE pid = ?
		AND network_id = ?",
		array($pid, $network_id));
}

function addUpDevice($network_id, $pid) {
	db_execute_prepared('UPDATE automation_processes
		SET up_hosts=up_hosts+1, heartbeat=NOW()
		WHERE pid = ?
		AND network_id = ?',
		array($pid, $network_id));
}

function addSNMPDevice($network_id, $pid) {
	db_execute_prepared('UPDATE automation_processes
		SET snmp_hosts=snmp_hosts+1, heartbeat=NOW()
		WHERE pid = ?
		AND network_id = ?',
		array($pid, $network_id));
}

function reportNetworkStatus($network_id, $old_devices) {
	$details = db_fetch_row_prepared('SELECT notification_enabled, notification_email,
		notification_fromname, notification_fromemail
		FROM automation_networks
		WHERE id = ?',
		array($network_id));

	if (cacti_sizeof($details)) {
		if ($details['notification_enabled'] == 'on') {
			if ($details['notification_fromname'] == '') {
				$fromname = read_config_option('automation_fromname');
				if ($fromname == '') {
					$fromname = read_config_option('settings_from_name');

					if ($fromname == '') {
						$fromname = __('Cacti Primary Admin');
					}
				}
			} else {
				$fromname = $details['notification_fromname'];
			}

			if ($details['notification_fromemail'] == '') {
				$fromemail = read_config_option('automation_fromemail');
				if ($fromemail == '') {
					$fromemail = read_config_option('settings_from_email');

					if ($fromemail == '') {
						$fromemail = 'root@cacti.net';
					}
				}
			} else {
				$fromemail = $details['notification_fromemail'];
			}

			$from = $fromname . ' <' . $fromemail . '>';

			if ($details['notification_email'] != '') {
				$email = $details['notification_email'];
			} else {
				$email = read_config_option('automation_email');

				if ($email == '') {
					$admin_user = read_config_option('admin_user');

					if ($admin_user == '') {
						cacti_log('WARNING: Unable to send Automation Notification Email.  No Primary Admin User Account specified.', false, 'POLLER');

						return false;
					}

					$details = db_fetch_cell_prepared('SELECT email_address AS notification_email, full_name
						FROM user_auth
						WHERE id = ?',
						array($admin_user));

					if (!cacti_sizeof($details)) {
						cacti_log('WARNING: Unable to send Automation Notification Email.  The Primary Admin User Account does not exist.', false, 'POLLER');
						return false;
					}

					if ($details['notification_email'] == '') {
						cacti_log('WARNING: Unable to send Automation Notification Email.  The Primary Admin User Account does not have an Email Address.', false, 'POLLER');
						return false;
					}

					$email = ($details['full_name'] != '' ? $details['full_name']:__('Cacti Primary Admin')) . ' <' . $details['notification_email'] . '>';
				}
			}

			$new_devices = getNetworkDevices($network_id);

			$ids = array();
			populateDeviceIndex($ids, 0, $old_devices);
			populateDeviceIndex($ids, 1, $new_devices);

			$table_head_style = 'style="border-bottom: 1px solid black"';
			$table_head = '<tr>' .
				"<td $table_head_style><i>Hostname</i></td>" .
				"<td $table_head_style><i>IP Address</i></td>" .
				"<td $table_head_style><i>SNMP Name</i></td>" .
				"<td $table_head_style><i>Has SNMP?</i></td>" .
				"<td $table_head_style><i>Responding?</i></td>" .
				'</tr>';

			$table_exist = '';
			$table_new = '';
			$count_exist = 0;
			$count_new = 0;

			$font_up = '<font color="green">up</font>';
			$font_down = '<font color="red">down</font>';

			foreach ($new_devices as $device) {
				$id = $device['ip'];
				$html_line = '<tr><td>' . $device['hostname'] .
					'</td><td>' . $device['ip'] .
					'</td><td>' . (empty($device['sysName']) ? '<i><u>None</u></i>' : $device['sysName']) .
					'</td><td>' . ($device['snmp'] ? $font_up : $font_down) .
					'</td><td>' . ($device['up'] ? $font_up : $font_down) .
					'</td></tr>';

				if ($ids[$id]['old'] != '') {
					$table_exist .= $html_line;
					$count_exist++;
				} else {
					$table_new .= $html_line;
					$count_new++;
				}
			}

			if (strlen($table_exist) > 0) {
				$table_exist .= '<tr><td colspan="5"</td>&nbsp;</td></tr>';
			}

			if (strlen($table_new) > 0) {
				$table_new .= '<tr><td colspan="5"</td>&nbsp;</td></tr>';
			}

			$v = CACTI_VERSION;
			$headers['User-Agent'] = 'Cacti-Automation-v' . $v;

			$status = ($count_new + $count_exist) . ' devices discovered';
			if ($count_new > 0) {
				$status .= ', ' . $count_new . ' new!';
			}

			$network = db_fetch_row_prepared('SELECT id, name, subnet_range, last_started, last_runtime
				FROM automation_networks
				WHERE id = ?',
				array($network_id));

			$subject = 'Discovery of ' . $network['name'] . ' (' . $network['subnet_range'] . ') - ' . $status;
			$output = '<h1>Discovery of ' . $network['name'] . '</h1><hr><br>' .
				'<h2>Summary</h2><table>' .
				'<tr><td>Network:</td><td>' . $network['subnet_range'] . '</td></tr>'.
				'<tr><td>Started:</td><td>' . $network['last_started'] . '</td></tr>' .
				'<tr><td>Duration:</td><td>' . intval($network['last_runtime']) . '</td></tr>' .
				'<tr><td>Existing:</td><td>' . $count_exist . ' devices</td></tr>' .
				'<tr><td>New:</td><td>' . $count_new . ' devices</td></tr>' .
				'</table><br><br>';

			if ($count_new > 0 || $count_exist > 0) {
				$output .= '<table cellspacing="5" cellpadding="5">';
				if ($count_new > 0) {
					$output .= '<tr><td colspan="5"><h3>New Devices</h3></td></tr>' . $table_head . $table_new;
				}

				if ($count_exist > 0) {
					$output .= '<tr><td colspan="5"><h3>Existing Devices</h3></td></tr>' . $table_head . $table_exist;
				}
				$output .= '</table>';
			}

			$error = mailer(
				$from,
				$email,
				'',
				'',
				'',
				$subject,
				$output,
				__('Cacti Automation Report requires an html based Email client'),
				'',
				$headers
			);

			if (strlen($error)) {
				cacti_log("WARNING: Automation had problems sending to '$email' for $status.  The error was '$error'", false, 'AUTOM8');
			} else {
				cacti_log("NOTICE: Email Notification Sent to '$email' for $status.", false, 'AUTOM8');
			}
		}
	}
}

function populateDeviceIndex(&$ids, $is_new, $devices) {
	$field = ($is_new ? 'new' : 'old');

	foreach ($devices as $index => $device) {
		$id = $device['ip'];

		if (!isset($ids[$id])) {
			$ids[$id] = array('old' => '', 'new' => '');
		}

		$ids[$id][$field] = $id;
	}
}

function clearTask($network_id, $pid) {
	db_execute_prepared('DELETE
		FROM automation_processes
		WHERE pid = ?
		AND network_id = ?',
		array($pid, $network_id));

	db_execute_prepared('DELETE
		FROM automation_ips
		WHERE network_id = ?',
		array($network_id));
}

function clearAllTasks($network_id) {
	db_execute_prepared('DELETE FROM automation_processes
		WHERE network_id = ?',
		array($network_id));
}

function markIPRunning($ip_address, $network_id) {
	db_execute_prepared('UPDATE automation_ips
		SET status=1
		WHERE ip_address = ?
		AND network_id = ?',
		array($ip_address, $network_id));
}

function markIPDone($ip_address, $network_id) {
	db_execute_prepared('UPDATE automation_ips
		SET status=2
		WHERE ip_address = ?
		AND network_id = ?',
		array($ip_address, $network_id));
}

function getNetworkDevices($network_id) {
	return db_fetch_assoc_prepared('SELECT id, hostname, ip, sysName, snmp, up, time
		FROM automation_devices
		WHERE network_id = ?
		ORDER BY hostname',
		array($network_id));
}

function updateDownDevice($network_id, $ip) {
	$exists = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM automation_devices
		WHERE ip = ?
		AND network_id = ?',
		array($ip, $network_id));

	if ($exists) {
		db_execute_prepared("UPDATE automation_devices
			SET up='0'
			WHERE ip = ?
			AND network_id = ?",
			array($ip, $network_id));
	}
}
