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

function snmpagent_enabled() : bool {
	return read_config_option('enable_snmp_agent') == 'on';
}

function snmpagent_cacti_stats_update(array $data) : bool {
	$mc = new MibCache();

	if (!snmpagent_enabled()) {
		return false;
	}

	// refresh total stats
	$mc->object('cactiStatsTotalsDevices')->set(snmpagent_read('cactiStatsTotalsDevices'));
	$mc->object('cactiStatsTotalsDataSources')->set(snmpagent_read('cactiStatsTotalsDataSources'));
	$mc->object('cactiStatsTotalsGraphs')->set(snmpagent_read('cactiStatsTotalsGraphs'));

	// local polling stats  - does not support distributed environments so far.
	$mc->object('cactiStatsLocalPollerRuntime')->set($data[0]);

	$index  = 1;
	$values = [
		'cactiStatsPollerRunTime'             => $data[0],
		'cactiStatsPollerConcurrentProcesses' => $data[2],
		'cactiStatsPollerThreads'             => $data[3],
		'cactiStatsPollerHosts'               => $data[4],
		'cactiStatsPollerHostsPerProcess'     => $data[5],
		'cactiStatsPollerItems'               => $data[6],
		'cactiStatsPollerRrrdsProcessed'      => $data[7],
		'cactiStatsPollerUtilization'         => round($data[0] / read_config_option('poller_interval', true) * 100, 10)
	];

	try {
		$mc->table('cactiStatsPollerTable')->row($index)->update($values);
	} catch (Exception $e) {
		cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
	}

	$mc->object('cactiStatsLastUpdate')->set(time());

	return true;
}

function snmpagent_global_settings_update() : void {
	$mc = new MibCache();

	$mc->object('cactiApplVersion')->set(snmpagent_read('cactiApplVersion'));
	$mc->object('cactiApplSnmpVersion')->set(snmpagent_read('cactiApplSnmpVersion'));
	$mc->object('cactiApplRrdtoolVersion')->set(get_rrdtool_version());
	$mc->object('cactiApplPollerEnabled')->set((read_config_option('poller_enabled', true) == 'on') ? 1 : 2);
	$mc->object('cactiApplPollerType')->set(read_config_option('poller_type', true));
	$mc->object('cactiApplPollerInterval')->set(read_config_option('poller_interval', true));
	$mc->object('cactiApplPollerMaxProcesses')->set(read_config_option('concurrent_processes', true));
	$mc->object('cactiApplPollerLoadBalance')->set((read_config_option('process_leveling', true) == 'on') ? 1 : 2);
	$mc->object('cactiApplSpineMaxThreads')->set(read_config_option('max_threads', true));
	$mc->object('cactiApplSpineScriptServers')->set(read_config_option('php_servers', true));
	$mc->object('cactiApplSpineScriptTimeout')->set(read_config_option('script_timeout', true));
	$mc->object('cactiApplSpineMaxOids')->set(read_config_option('max_get_size', true));
	$mc->object('cactiApplLastUpdate')->set(time());

	// update boost settings
	$mc->mib('CACTI-BOOST-MIB');
	$mc->object('boostApplRrdUpdateEnabled')->set((read_config_option('boost_rrd_update_enable', true) == 'on') ? 1 : 2);
	$mc->object('boostApplRrdUpdateInterval')->set(read_config_option('boost_rrd_update_interval', true));
	$mc->object('boostApplRrdUpdateMaxRecords')->set(read_config_option('boost_rrd_update_max_records', true));
	$mc->object('boostApplRrdUpdateMaxRecordsPerSelect')->set(read_config_option('boost_rrd_update_max_records_per_select', true));
	$mc->object('boostApplRrdUpdateMaxStringLength')->set(read_config_option('boost_rrd_update_string_length', true));
	$mc->object('boostApplRrdUpdatePollerMemLimit')->set(read_config_option('boost_poller_mem_limit', true));
	$mc->object('boostApplRrdUpdateMaxRunTime')->set(read_config_option('boost_rrd_update_max_runtime', true));
	$mc->object('boostApplRrdUpdateRedirect')->set((read_config_option('boost_redirect', true) == 'on') ? 1 : 2);
	$mc->object('boostApplImageCacheEnabled')->set((read_config_option('boost_png_cache_enable', true) == 'on') ? 1 : 2);
	$mc->object('boostApplLoggingEnabled')->set((read_config_option('boost_debug_enabled', true) == 'on') ? 1 : 2);
	$mc->object('boostApplLastUpdate')->set(time());
}

function snmpagent_api_device_new(array $device) : bool {
	if (!snmpagent_enabled()) {
		return false;
	}

	$mc = new MibCache();
	// add device to cactiApplDeviceTable and cactiStatsDeviceTable
	$device_data = db_fetch_row_prepared('SELECT * FROM `host` WHERE id = ?', [$device['id']]);

	$appl_values = [
		'cactiApplDeviceIndex'        => $device_data['id'],
		'cactiApplDeviceDescription'  => $device_data['description'],
		'cactiApplDeviceHostname'     => $device_data['hostname'],
		'cactiApplDeviceStatus'       => $device_data['status'],
		'cactiApplDeviceEventCount'   => $device_data['status_event_count'],
		'cactiApplDeviceFailDate'     => $device_data['status_fail_date'],
		'cactiApplDeviceRecoveryDate' => $device_data['status_rec_date'],
		'cactiApplDeviceLastError'    => $device_data['status_last_error'],
	];

	$stats_values = [
		'cactiStatsDeviceIndex'        => $device_data['id'],
		'cactiStatsDeviceHostname'     => $device_data['hostname'],
		'cactiStatsDeviceMinTime'      => $device_data['min_time'],
		'cactiStatsDeviceMaxTime'      => $device_data['max_time'],
		'cactiStatsDeviceCurTime'      => $device_data['cur_time'],
		'cactiStatsDeviceAvgTime'      => $device_data['avg_time'],
		'cactiStatsDeviceTotalPolls'   => $device_data['total_polls'],
		'cactiStatsDeviceFailedPolls'  => $device_data['failed_polls'],
		'cactiStatsDeviceAvailability' => $device_data['availability']
	];

	try {
		$mc->table('cactiApplDeviceTable')->row($device['id'])->replace($appl_values);
		$mc->object('cactiApplLastUpdate')->set(time());
	} catch (Exception $e) {
		cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
	}

	try {
		$mc->table('cactiStatsDeviceTable')->row($device['id'])->replace($stats_values);
		$mc->object('cactiStatsTotalsDevices')->set(snmpagent_read('cactiStatsTotalsDevices'));
		$mc->object('cactiStatsLastUpdate')->set(time());
	} catch (Exception $e) {
		cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
	}

	return true;
}

function snmpagent_data_source_action_bottom(array $data) : bool {
	if (!snmpagent_enabled()) {
		return false;
	}

	$mc     = new MibCache();
	$action = $data[0];

	if ($action == '1') {
		// delete data sources
		$mc->object('cactiStatsTotalsDataSources')->set(snmpagent_read('cactiStatsTotalsDataSources'));
		$mc->object('cactiStatsTotalsGraphs')->set(snmpagent_read('cactiStatsTotalsGraphs'));
		$mc->object('cactiStatsLastUpdate')->set(time());
	} elseif ($action == '4') {
		// duplicate data sources
		$mc->object('cactiStatsTotalsDataSources')->set(snmpagent_read('cactiStatsTotalsDataSources'));
		$mc->object('cactiStatsLastUpdate')->set(time());
	}

	return true;
}

function snmpagent_graphs_action_bottom(array $data) : bool {
	if (!snmpagent_enabled()) {
		return false;
	}

	$mc     = new MibCache();
	$action = $data[0];

	if ($action == '1') {
		// delete graphs
		$mc->object('cactiStatsTotalsDataSources')->set(snmpagent_read('cactiStatsTotalsDataSources'));
		$mc->object('cactiStatsTotalsGraphs')->set(snmpagent_read('cactiStatsTotalsGraphs'));
		$mc->object('cactiStatsLastUpdate')->set(time());
	} elseif ($action == '3') {
		// duplicate graphs
		$mc->object('cactiStatsTotalsGraphs')->set(snmpagent_read('cactiStatsTotalsGraphs'));
		$mc->object('cactiStatsLastUpdate')->set(time());
	}

	return true;
}

function snmpagent_device_action_bottom(array $data) : bool {
	if (!snmpagent_enabled()) {
		return false;
	}

	$mc             = new MibCache();
	$action         = $data[0];
	$selected_items = $data[1];

	if ($selected_items != false) {
		switch($action) {
			case '1':
				// delete devices
				foreach ($selected_items as $device_id) {
					try {
						$mc->table('cactiApplDeviceTable')->row($device_id)->delete();
					} catch (Exception $e) {
						cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
					}

					try {
						$mc->table('cactiStatsDeviceTable')->row($device_id)->delete();
					} catch (Exception $e) {
						cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
					}
				}

				// update total statistics
				$mc->object('cactiStatsTotalsDevices')->set(snmpagent_read('cactiStatsTotalsDevices'));
				$mc->object('cactiStatsTotalsDataSources')->set(snmpagent_read('cactiStatsTotalsDataSources'));
				$mc->object('cactiStatsTotalsGraphs')->set(snmpagent_read('cactiStatsTotalsGraphs'));
				$mc->object('cactiStatsLastUpdate')->set(time());

				break;
			case '2':
				// enable devices
				foreach ($selected_items as $device_id) {
					$device_status = db_fetch_cell_prepared('SELECT status FROM host WHERE id = ?', [$device_id]);

					try {
						$mc->table('cactiApplDeviceTable')->row($device_id)->update(['cactiApplDeviceStatus' => $device_status]);
					} catch (Exception $e) {
						cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
					}
				}
				$mc->object('cactiApplLastUpdate')->set(time());

				break;
			case '3':
				// disable devices
				foreach ($selected_items as $device_id) {
					$device_status = db_fetch_cell_prepared('SELECT status FROM host WHERE id = ?', [$device_id]);

					try {
						$mc->table('cactiApplDeviceTable')->row($device_id)->update(['cactiApplDeviceStatus' => 4]);
					} catch (Exception $e) {
						cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
					}
				}
				$mc->object('cactiApplLastUpdate')->set(time());

				break;
			case '5':
				// clear device statistics
				$values = [
					'cactiStatsDeviceMinTime'      => '9.99999',
					'cactiStatsDeviceMaxTime'      => '0',
					'cactiStatsdeviceCurTime'      => '0',
					'cactiStatsDeviceAvgTime'      => '0',
					'cactiStatsDeviceTotalPolls'   => '0',
					'cactiStatsDeviceFailedPolls'  => '0',
					'cactiStatsDeviceAvailability' => '100'
				];

				foreach ($selected_items as $device_id) {
					try {
						$mc->table('cactiStatsDeviceTable')->row($device_id)->update($values);
					} catch (Exception $e) {
						cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
					}
				}
				$mc->object('cactiStatsLastUpdate')->set(time());

				break;
			default:
				// nothing to do
		} // switch
	}

	return true;
}

function snmpagent_poller_exiting(int $poller_index = 1) : bool {
	if (!snmpagent_enabled()) {
		return false;
	}

	$mc = new MibCache();

	try {
		$poller = $mc->table('cactiApplPollerTable')->row($poller_index)->select();

		if ($poller == false) {
			throw new Exception('Unable to find a poller');
		}

		$varbinds = [
			'cactiApplPollerIndex'     => $poller_index,
			'cactiApplPollerHostname'  => $poller['cactiApplPollerHostname'],
			'cactiApplPollerIpAddress' => $poller['cactiApplPollerIpAddress']
		];

		snmpagent_notification('cactiNotifyPollerRuntimeExceeding', 'CACTI-MIB', $varbinds, SNMPAGENT_EVENT_SEVERITY_HIGH);
	} catch (Exception $e) {
		cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
	}

	return true;
}

function snmpagent_poller_bottom() : bool {
	if (!db_table_exists('snmpagent_cache')) {
		return true;
	}

	if (!snmpagent_enabled()) {
		snmpagent_cache_uninstall();

		return false;
	}

	if (!snmpagent_cache_initialized()) {
		snmpagent_cache_rebuilt();
	}

	$device_in_maintenance = false;

	$mc = new MibCache();

	// START: update total device stats table
	// deprecated
	$devicestatus_indices = [0 => 0, 1 => 1, 2 => 2, 3 => 3, 4 => 4];
	$current_states       = db_fetch_assoc('SELECT status, COUNT(*) as cnt FROM `host` GROUP BY status');

	if ($current_states && cacti_sizeof($current_states) > 0) {
		foreach ($current_states as $current_state) {
			$index  = $devicestatus_indices[$current_state['status']];
			$values = [
				'cactiStatsTotalsDeviceStatusIndex'   => $current_state['status'],
				'cactiStatsTotalsDeviceStatusCounter' => $current_state['cnt']
			];

			try {
				$mc->table('cactiStatsTotalsDeviceStatusTable')->row($index)->replace($values);
			} catch (Exception $e) {
				cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
			}
			unset($devicestatus_indices[$current_state['status']]);
		}
	}

	if (cacti_sizeof($devicestatus_indices) > 0) {
		foreach ($devicestatus_indices as $status => $index) {
			$values = [
				'cactiStatsTotalsDeviceStatusIndex'   => $status,
				'cactiStatsTotalsDeviceStatusCounter' => 0
			];

			try {
				$mc->table('cactiStatsTotalsDeviceStatusTable')->row($index)->replace($values);
			} catch (Exception $e) {
				cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
			}
		}
	}

	//
	$mc->object('cactiStatsTotalsDeviceStatusUnknown')->set(snmpagent_read('cactiStatsTotalsDeviceStatusUnknown'));
	$mc->object('cactiStatsTotalsDeviceStatusDown')->set(snmpagent_read('cactiStatsTotalsDeviceStatusDown'));
	$mc->object('cactiStatsTotalsDeviceStatusRecovering')->set(snmpagent_read('cactiStatsTotalsDeviceStatusRecovering'));
	$mc->object('cactiStatsTotalsDeviceStatusUp')->set(snmpagent_read('cactiStatsTotalsDeviceStatusUp'));
	$mc->object('cactiStatsTotalsDeviceStatusDisabled')->set(snmpagent_read('cactiStatsTotalsDeviceStatusDisabled'));
	// END: update total device stats table

	// update state and statistics of all devices
	$mc_dstatus = [];

	try {
		$mc_devices = $mc->table('cactiApplDeviceTable')->select(['cactiApplDeviceIndex', 'cactiApplDeviceStatus']);

		if ($mc_devices && cacti_sizeof($mc_devices)) {
			foreach ($mc_devices as $mc_device) {
				if (isset($mc_device['cactiApplDeviceStatus']) && isset($mc_device['cactiApplDeviceIndex'])) {
					$mc_dstatus[$mc_device['cactiApplDeviceIndex']] = $mc_device['cactiApplDeviceStatus'];
				}
			}
		}
	} catch (Exception $e) {
		cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
	}
	$mc_dfailed      = [];

	try {
		$mc_device_stats = $mc->table('cactiStatsDeviceTable')->select(['cactiStatsDeviceIndex', 'cactiStatsDeviceFailedPolls']);

		if ($mc_device_stats && cacti_sizeof($mc_device_stats) > 0) {
			foreach ($mc_device_stats as $mc_device_stat) {
				if (isset($mc_device_stat['cactiStatsDeviceFailedPolls']) && isset($mc_device_stat['cactiStatsDeviceIndex'])) {
					$mc_dfailed[$mc_device_stat['cactiStatsDeviceIndex']] = $mc_device_stat['cactiStatsDeviceFailedPolls'];
				}
			}
		}
	} catch (Exception $e) {
		cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
	}

	$devices = db_fetch_assoc('SELECT id, description, hostname, status,
		disabled, status_event_count, status_fail_date, status_rec_date,
		status_last_error, min_time, max_time, cur_time, avg_time,
		total_polls, failed_polls, availability, snmp_engine_id
		FROM host
		ORDER BY id ASC');

	if (cacti_sizeof($devices)) {
		foreach ($devices as $device) {
			$device_in_maintenance = api_plugin_hook_function('is_device_in_maintenance', $device['id']);

			if (!$device_in_maintenance) {
				$varbinds = [
					'cactiApplDeviceIndex'       => $device['id'],
					'cactiApplDeviceDescription' => $device['description'],
					'cactiApplDeviceHostname'    => $device['hostname'],
					'cactiApplDeviceLastError'   => $device['status_last_error']
				];

				$overwrite['snmp_engine_id'] = $device['snmp_engine_id'];

				if (isset($mc_dfailed[$device['id']]) && $device['failed_polls'] > $mc_dfailed[$device['id']]) {
					snmpagent_notification('cactiNotifyDeviceFailedPoll', 'CACTI-MIB', $varbinds, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
				}

				if (isset($mc_dstatus[$device['id']]) && $mc_dstatus[$device['id']] == HOST_UP && $device['status'] == HOST_DOWN) {
					snmpagent_notification('cactiNotifyDeviceDown', 'CACTI-MIB', $varbinds, SNMPAGENT_EVENT_SEVERITY_HIGH, $overwrite);
				} elseif (isset($mc_dstatus[$device['id']]) && $mc_dstatus[$device['id']] == HOST_DOWN && $device['status'] == HOST_RECOVERING) {
					snmpagent_notification('cactiNotifyDeviceRecovering', 'CACTI-MIB', $varbinds, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
				}
			}

			$values = [
				'cactiApplDeviceStatus'       => ($device['disabled'] == 'on') ? 4 : $device['status'],
				'cactiApplDeviceEventCount'   => $device['status_event_count'],
				'cactiApplDeviceFailDate'     => $device['status_fail_date'],
				'cactiApplDeviceRecoveryDate' => $device['status_rec_date'],
				'cactiApplDeviceLastError'    => $device['status_last_error']
			];

			try {
				$mc->table('cactiApplDeviceTable')->row($device['id'])->update($values);
			} catch (Exception $e) {
				cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
			}

			$values = [
				'cactiStatsDeviceMinTime'      => $device['min_time'],
				'cactiStatsDeviceMaxTime'      => $device['max_time'],
				'cactiStatsDeviceCurTime'      => $device['cur_time'],
				'cactiStatsDeviceAvgTime'      => $device['avg_time'],
				'cactiStatsDeviceTotalPolls'   => $device['total_polls'],
				'cactiStatsDeviceFailedPolls'  => $device['failed_polls'],
				'cactiStatsDeviceAvailability' => $device['availability']
			];

			try {
				$mc->table('cactiStatsDeviceTable')->row($device['id'])->update($values);
			} catch (Exception $e) {
				cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
			}
		}

		return true;
	}

	// get a list of all plugins available on that system
	$pluginslist = snmpagent_get_pluginslist();

	// truncate plugin mib table
	try {
		$mc->table('cactiApplPluginTable')->truncate();
	} catch (Exception $e) {
		cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
	}

	// refill plugin mib table
	if ($pluginslist && cacti_sizeof($pluginslist) > 0) {
		$i = 1;

		foreach ($pluginslist as $plugin) {
			$values = [
				'cactiApplPluginIndex'   => $i,
				'cactiApplPluginType'    => 2,
				'cactiApplPluginName'    => $plugin['directory'],
				'cactiApplPluginStatus'  => $plugin['status'],
				'cactiApplPluginVersion' => $plugin['version']
			];

			try {
				$mc->table('cactiApplPluginTable')->row($i)->insert($values);
			} catch (Exception $e) {
				cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
			}
			$i++;
		}
	}
	$mc->object('cactiApplLastUpdate')->set(time());

	$recache_stats = db_fetch_cell("SELECT value
		FROM settings
		WHERE name = 'stats_recache'");

	if ($recache_stats) {
		[$time, $hosts]     = explode(' ', $recache_stats);
		$time               = str_replace('RecacheTime:', '', $time);
		$hosts              = str_replace('HostsRecached:', '', $hosts);

		$mc->object('cactiStatsRecacheTime')->set($time);
		$mc->object('cactiStatsRecachedHosts')->set($hosts);
	}
	$mc->object('cactiStatsLastUpdate')->set(time());

	// clean up the notification log
	$snmp_notification_managers = db_fetch_assoc('SELECT id, max_log_size FROM snmpagent_managers');

	if ($snmp_notification_managers && cacti_sizeof($snmp_notification_managers) > 0) {
		foreach ($snmp_notification_managers as $snmp_notification_manager) {
			db_execute_prepared('DELETE FROM snmpagent_notifications_log
				WHERE manager_id = ?
				AND `time` <= ?',
				[$snmp_notification_manager['id'], time() - 86400 * $snmp_notification_manager['max_log_size']]);
		}
	}

	return true;
}

function snmpagent_get_pluginslist() : array {
	global $plugins, $plugins_integrated;
	/* update the list of known plugins only once per polling cycle. In all other cases we would
	   have to create too many new hooks to update that MIB table just in time.
	   We have to do the same like function plugins_load_temp_table(), which will not be available
	   during the execution of that function. */

	$pluginslist        = [];
	$registered_plugins = db_fetch_assoc('SELECT * FROM plugin_config ORDER BY name');

	foreach ($registered_plugins as $t) {
		$pluginslist[$t['directory']] = $t;
	}

	$path = CACTI_PATH_PLUGINS . '/';
	$dh   = opendir($path);

	if ($dh !== false) {
		while (($file = readdir($dh)) !== false) {
			if ((is_dir("$path$file")) && !in_array($file, $plugins_integrated, true) &&
				(file_exists("$path$file/setup.php")) && (!array_key_exists($file, $pluginslist))) {
				if (file_exists("$path$file/INFO")) {
					$cinfo              = plugin_load_info_file("$path$file/INFO");
					$pluginslist[$file] = $cinfo;
				}
			}
		}

		closedir($dh);
	}

	return $pluginslist;
}

/**
 * snmpagent_cache_install()
 * Generates a SNMP caching tables reflecting all objects of the Cacti MIB
 *
 * @return bool
 */
function snmpagent_cache_install() : bool {
	if (!snmpagent_enabled()) {
		return false;
	}

	// drop everything
	snmpagent_cache_uninstall();

	$mc = new MibCache();
	$mc->install(CACTI_PATH_MIBS . '/CACTI-MIB');
	$mc->install(CACTI_PATH_MIBS . '/CACTI-SNMPAGENT-MIB');
	$mc->install(CACTI_PATH_MIBS . '/CACTI-BOOST-MIB');
	snmpagent_cache_init();

	// call install routine of plugins supporting the SNMPAgent
	api_plugin_hook('snmpagent_cache_install');

	return true;
}

function snmpagent_cache_uninstall() : void {
	// drop everything if not empty

	$tables = [
		'snmpagent_cache',
		'snmpagent_mibs',
		'snmpagent_cache_notifications',
		'snmpagent_cache_textual_conventions'
	];

	foreach ($tables as $table) {
		$rows = db_fetch_cell("SELECT COUNT(*) FROM $table");

		if ($rows > 0) {
			db_execute("TRUNCATE $table");
		}
	}
}

function snmpagent_cache_initialized() : mixed {
	return db_fetch_cell('SELECT COUNT(*) FROM `snmpagent_cache`') > 0;
}

function snmpagent_cache_rebuilt() : void {
	snmpagent_cache_install();
}

function snmpagent_cache_init() : void {
	/* fill up the cache with a minimum of data and ignore all values that
	 *  will be updated automatically at the bottom of the next poller run
	 */
	$mc = new MibCache();

	// update global settings
	snmpagent_global_settings_update();

	// add pollers of a distributed system (future)
	$pollers = db_fetch_assoc('SELECT id FROM poller ORDER BY id ASC');

	if ($pollers && cacti_sizeof($pollers) > 0) {
		foreach ($pollers as $poller) {
			$poller_data = db_fetch_row_prepared('SELECT * FROM poller WHERE id = ?', [$poller['id']]);
		}
	} else {
		// this is NOT a distributed system, but it should have at least one local poller.
		$poller_lastrun = read_config_option('poller_lastrun', true);
		$values         = [
			'cactiApplPollerIndex'      => 1,
			'cactiApplPollerHostname'   => 'localhost',
			'cactiApplPollerIpAddress'  => '127.0.0.1',
			'cactiApplPollerLastUpdate' => $poller_lastrun
		];

		try {
			$mc->table('cactiApplPollerTable')->row(1)->insert($values);
		} catch (Exception $e) {
			cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
		}

		$values = [
			'cactiStatsPollerIndex'    => 1,
			'cactiStatsPollerHostname' => 'localhost',
			'cactiStatsPollerMethod'   => read_config_option('poller_type', true)
		];

		try {
			$mc->table('cactiStatsPollerTable')->row(1)->insert($values);
		} catch (Exception $e) {
			cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
		}
	}

	// add all devices as devicetable entries to the snmp cache
	$devices = db_fetch_assoc('SELECT id, description, hostname, disabled, status_event_count, status_fail_date,
		status_rec_date, status_last_error, min_time, max_time, cur_time,
		avg_time, total_polls, failed_polls, availability
		FROM host
		ORDER BY id ASC');

	if (cacti_sizeof($devices)) {
		foreach ($devices as $device) {
			$device = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', [$device['id']]);

			// add device to cactiApplDeviceTable
			if (cacti_sizeof($device)) {
				$values = [
					'cactiApplDeviceIndex'        => $device['id'],
					'cactiApplDeviceDescription'  => $device['description'],
					'cactiApplDeviceHostname'     => $device['hostname'],
					'cactiApplDeviceStatus'       => ($device['disabled'] == 'on') ? 4 : $device['status'],
					'cactiApplDeviceEventCount'   => $device['status_event_count'],
					'cactiApplDeviceFailDate'     => $device['status_fail_date'],
					'cactiApplDeviceRecoveryDate' => $device['status_rec_date'],
					'cactiApplDeviceLastError'    => $device['status_last_error'],
				];

				try {
					$mc->table('cactiApplDeviceTable')->row($device['id'])->insert($values);
				} catch (Exception $e) {
					cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
				}

				// add device to cactiStatsDeviceTable
				$values = [
					'cactiStatsDeviceIndex'        => $device['id'],
					'cactiStatsDeviceHostname'     => $device['hostname'],
					'cactiStatsDeviceMinTime'      => $device['min_time'],
					'cactiStatsDeviceMaxTime'      => $device['max_time'],
					'cactiStatsDeviceCurTime'      => $device['cur_time'],
					'cactiStatsDeviceAvgTime'      => $device['avg_time'],
					'cactiStatsDeviceTotalPolls'   => $device['total_polls'],
					'cactiStatsDeviceFailedPolls'  => $device['failed_polls'],
					'cactiStatsDeviceAvailability' => $device['availability']
				];

				try {
					$mc->table('cactiStatsDeviceTable')->row($device['id'])->insert($values);
				} catch (Exception $e) {
					cacti_log('WARNING: SNMPAgent: ' . $e->getMessage(), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
				}
			}
		}
	}
}

function snmpagent_read(string $object) : mixed {
	switch($object) {
		case 'cactiApplVersion':
			$value = db_fetch_cell('SELECT `cacti` FROM `version`');

			break;
		case 'cactiApplSnmpVersion':
			$snmp_version = read_config_option('snmp_version', true);
			$value        = $snmp_version;

			if (function_exists('snmpget')) {
				$value = 3;
			}

			break;
		case 'cactiStatsTotalsDevices':
			$value = db_fetch_cell('SELECT COUNT(*) FROM host');

			break;
		case 'cactiStatsTotalsDataSources':
			$value = db_fetch_cell('SELECT COUNT(*) FROM data_local');

			break;
		case 'cactiStatsTotalsGraphs':
			$value = db_fetch_cell('SELECT COUNT(*) FROM graph_local');

			break;
		case 'cactiStatsTotalsDeviceStatusUnknown':
			$value = db_fetch_cell('SELECT COUNT(*) FROM host WHERE status = 0');

			break;
		case 'cactiStatsTotalsDeviceStatusDown':
			$value = db_fetch_cell('SELECT COUNT(*) FROM host WHERE status = 1');

			break;
		case 'cactiStatsTotalsDeviceStatusRecovering':
			$value = db_fetch_cell('SELECT COUNT(*) FROM host WHERE status = 2');

			break;
		case 'cactiStatsTotalsDeviceStatusUp':
			$value = db_fetch_cell('SELECT COUNT(*) FROM host WHERE status = 3');

			break;
		case 'cactiStatsTotalsDeviceStatusDisabled':
			$value = db_fetch_cell('SELECT COUNT(*) FROM host WHERE status = 4');

			break;
		default:
			$value = false;
	}

	return $value;
}

function snmpagent_notification(string $notification, string $mib, array $varbinds, int $severity = SNMPAGENT_EVENT_SEVERITY_MEDIUM, mixed $overwrite = false) : bool {
	global $config, $snmpagent_event_severity;

	if (isset($config['snmpagent']['notifications']['ignore'][$notification])) {
		return false;
	}

	$path_snmptrap = read_config_option('path_snmptrap');

	if (!in_array($severity, [SNMPAGENT_EVENT_SEVERITY_LOW, SNMPAGENT_EVENT_SEVERITY_MEDIUM, SNMPAGENT_EVENT_SEVERITY_HIGH, SNMPAGENT_EVENT_SEVERITY_CRITICAL], true)) {
		cacti_log('ERROR: Unknown event severity: "' . $severity . '" for ' . $notification . ' (' . $mib . ')', false, 'SNMPAGENT', POLLER_VERBOSITY_NONE);

		return false;
	}

	$enterprise_oid = db_fetch_cell_prepared('SELECT oid
		FROM snmpagent_cache
		WHERE `name` = ?
		AND `mib` = ?',
		[$notification, $mib]);

	if (!$enterprise_oid) {
		// system does not know this event
		cacti_log('ERROR: Unknown event: ' . $notification . ' (' . $mib . ')', false, 'SNMPAGENT', POLLER_VERBOSITY_NONE);

		return false;
	} else {
		$branches             = explode('.', $enterprise_oid);
		$specific_trap_number = array_pop($branches);
	}

	// generate a list of SNMP notification receivers listening for this notification
	$notification_managers = db_fetch_assoc_prepared('SELECT snmpagent_managers.*
		FROM snmpagent_managers_notifications
		INNER JOIN snmpagent_managers
		ON (snmpagent_managers.id = snmpagent_managers_notifications.manager_id)
		WHERE snmpagent_managers.disabled = 0
		AND snmpagent_managers_notifications.notification = ?
		AND snmpagent_managers_notifications.mib = ?',
		[$notification, $mib]);

	if (cacti_sizeof($notification_managers) == 0) {
		// No receivers found for the message, record it to the cacti.log
		cacti_log('WARNING: No notification receivers configured for event: ' . $notification . ' (' . $mib . '), severity: ' . $snmpagent_event_severity[$severity], false, 'SNMPAGENT', POLLER_VERBOSITY_NONE);

		if (!in_array($severity, [SNMPAGENT_EVENT_SEVERITY_HIGH, SNMPAGENT_EVENT_SEVERITY_CRITICAL], true)) {
			// Prevent log spam of messages lower than a high severity
			$config['snmpagent']['notifications']['ignore'][$notification] = 1;
		}

		return false;
	}

	$registered_var_binds = [];

	// get a list of registered var binds
	$reg_var_binds = db_fetch_assoc_prepared('SELECT
		scn.attribute, sc.oid, sc.type, sctc.type as tcType
		FROM snmpagent_cache_notifications AS scn
		LEFT JOIN snmpagent_cache AS sc
		ON sc.mib = scn.mib AND sc.name = scn.attribute
		LEFT JOIN snmpagent_cache_textual_conventions AS sctc
		ON sc.mib = sctc.mib AND sc.type = sctc.name
		WHERE scn.name = ?
		AND scn.mib = ?
		ORDER BY scn.sequence_id',
		[$notification, $mib]);

	if (cacti_sizeof($reg_var_binds)) {
		foreach ($reg_var_binds as $reg_var_bind) {
			$registered_var_binds[$reg_var_bind['attribute']] = [
				'oid'  => $reg_var_bind['oid'],
				'type' => $reg_var_bind['tcType'] ?: $reg_var_bind['type']
			];
		}
	}

	$difference = array_diff(array_keys($registered_var_binds), array_keys($varbinds));

	if (cacti_sizeof($difference) == 0) {
		/* order the managers by message type to send out all notifications immediately. Informs
		   will take more processing time.
		*/
		$notification_managers = db_fetch_assoc_prepared('SELECT sm.*
			FROM snmpagent_managers_notifications AS smn
			INNER JOIN snmpagent_managers AS sm
			ON sm.id = smn.manager_id
			WHERE smn.notification = ?
			AND smn.mib = ?
			ORDER BY sm.snmp_message_type', [$notification, $mib]);

		if (cacti_sizeof($notification_managers)) {
			include_once(CACTI_PATH_LIBRARY . '/poller.php');

			/*
			TYPE: one of i, u, t, a, o, s, x, d, b
				i: INTEGER, u: unsigned INTEGER, t: TIMETICKS, a: IPADDRESS
				o: OBJID, s: STRING, x: HEX STRING, d: DECIMAL STRING, b: BITS
				U: unsigned int64, I: signed int64, F: float, D: double
			*/
			$smi2netsnmp_datatypes = [
				'integer'           => 'i',
				'integer32'         => 'i',
				'unsigned32'        => 'u',
				'gauge'             => 'i',
				'gauge32'           => 'i',
				'counter'           => 'i',
				'counter32'         => 'i',
				'counter64'         => 'I',
				'timeticks'         => 't',
				'octet string'      => 's',
				'opaque'            => 's',
				'object identifier' => 'o',
				'ipaddress'         => 'a',
				'networkaddress'    => 'IpAddress',
				'bits'              => 'b',
				'displaystring'     => 's',
				'physaddress'       => 's',
				'macaddress'        => 's',
				'truthvalue'        => 'i',
				'testandincr'       => 'i',
				'autonomoustype'    => 'o',
				'variablepointer'   => 'o',
				'rowpointer'        => 'o',
				'rowstatus'         => 'i',
				'timestamp'         => 't',
				'timeinterval'      => 'i',
				'dateandtime'       => 's',
				'storagetype'       => 'i',
				'tdomain'           => 'o',
				'taddress'          => 's'
			];

			$log_notification_varbinds  = '';

			// Each token is escaped individually below; building the varbind triples
			// as discrete array elements keeps values with spaces or shell metacharacters
			// from splitting or altering the command line.
			$snmp_notification_varbinds = [];

			$args = [];

			foreach ($notification_managers as $notification_manager) {
				if (!cacti_sizeof($snmp_notification_varbinds)) {
					foreach ($registered_var_binds as $name => $attributes) {
						$snmp_notification_varbinds[] = $attributes['oid'];
						$snmp_notification_varbinds[] = $smi2netsnmp_datatypes[cacti_strtolower($attributes['type'])];
						$snmp_notification_varbinds[] = str_replace('"', "'", $varbinds[$name]);
						$log_notification_varbinds .= $name . ':"' . str_replace('"', "'", $varbinds[$name]) . '" ';
					}
				}

				if ($notification_manager['snmp_version'] == 1) {
					$args = array_merge([
						'-v', '1',
						'-c', $notification_manager['snmp_community'],
						$notification_manager['hostname'] . ':' . $notification_manager['snmp_port'],
						$enterprise_oid,
						'', '6', $specific_trap_number, ''
					], $snmp_notification_varbinds);
				} elseif ($notification_manager['snmp_version'] == 2) {
					$args = ['-v', '2c', '-c', $notification_manager['snmp_community']];

					if ($notification_manager['snmp_message_type'] == 2) {
						$args[] = '-Ci';
					}

					$args = array_merge($args, [
						$notification_manager['hostname'] . ':' . $notification_manager['snmp_port'],
						'', $enterprise_oid
					], $snmp_notification_varbinds);
				} elseif ($notification_manager['snmp_version'] == 3) {
					if ($overwrite && isset($overwrite['snmp_engine_id']) && $overwrite['snmp_engine_id']) {
						$notification_manager['snmp_engine_id'] = $overwrite['snmp_engine_id'];
					}

					$args = ['-v', '3', '-e', $notification_manager['snmp_engine_id']];

					if ($notification_manager['snmp_message_type'] == 2) {
						$args[] = '-Ci';
					}

					$args[] = '-u';
					$args[] = $notification_manager['snmp_username'];

					if ($notification_manager['snmp_password'] && $notification_manager['snmp_priv_passphrase']) {
						$snmp_security_level = 'authPriv';
					} elseif ($notification_manager['snmp_password']) {
						$snmp_security_level = 'authNoPriv';
					} else {
						$snmp_security_level = 'noAuthNoPriv';
					}

					$args[] = '-l';
					$args[] = $snmp_security_level;

					if ($snmp_security_level != 'noAuthNoPriv') {
						$args[] = '-a';
						$args[] = $notification_manager['snmp_auth_protocol'];
						$args[] = '-A';
						$args[] = $notification_manager['snmp_password'];
					}

					if ($snmp_security_level == 'authPriv') {
						$args[] = '-x';
						$args[] = $notification_manager['snmp_priv_protocol'];
						$args[] = '-X';
						$args[] = $notification_manager['snmp_priv_passphrase'];
					}

					$args = array_merge($args, [
						$notification_manager['hostname'] . ':' . $notification_manager['snmp_port'],
						'', $enterprise_oid
					], $snmp_notification_varbinds);
				}

				// execute net-snmp to generate this notification in the background.
				// snmptrap relies on fixed positional arguments (agent address and
				// uptime placeholders) that are passed empty here; escape each token
				// directly so empty positions survive as quoted '' rather than being
				// swallowed when exec_background joins the arguments with spaces.
				$escaped_args = implode(' ', array_map(static function ($arg) {
					return $arg === '' ? "''" : cacti_escapeshellarg($arg);
				}, $args));

				exec_background(cacti_escapeshellcmd($path_snmptrap), $escaped_args);

				// insert a new entry into the notification log for that SNMP receiver
				$save                 = [];
				$save['id']           = 0;
				$save['time']         = time();
				$save['severity']     = $severity;
				$save['manager_id']   = $notification_manager['id'];
				$save['notification'] = $notification;
				$save['mib']          = $mib;
				$save['varbinds']     = substr($log_notification_varbinds, 0, 5000);

				sql_save($save, 'snmpagent_notifications_log');

				// log the net-snmp command for Cacti admins if they wish for
				cacti_log("NOTE: $path_snmptrap " . str_replace([$notification_manager['snmp_password'], $notification_manager['snmp_priv_passphrase']], '********', implode(' ', $args)), false, 'SNMPAGENT', POLLER_VERBOSITY_MEDIUM);
			}
		}

		return true;
	} else {
		// mismatching number of var binds
		cacti_log('ERROR: Incomplete number of varbinds given for event: ' . $notification . ' (' . $mib . ')', false, 'SNMPAGENT', POLLER_VERBOSITY_NONE);

		return false;
	}
}
