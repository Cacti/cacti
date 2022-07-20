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

error_reporting(0);

if (!isset($called_by_script_server)) {
	include_once(dirname(__FILE__) . '/../include/cli_check.php');

	$checks = array(
		'ss_poller',
		'ss_poller_items',
		'ss_recache',
		'ss_boost',
		'ss_boost_mem',
		'ss_boost_table',
		'ss_boost_records',
		'ss_boost_avg_size',
		'ss_boost_timing',
		'ss_export',
		'ss_thold_time',
		'ss_thold_checks',
		'ss_thold_hstats',
		'ss_syslog_time',
		'ss_syslog_stats',
		'ss_monitor_time',
		'ss_monitor_stats',
		'ss_report_stats',
		'ss_spike_time',
		'ss_spike_stats',
		'ss_webseer_counts',
		'ss_webseer_stats',
	);

	foreach ($checks as $check) {
		if (function_exists($check)) {
			print $check . ': ' . call_user_func($check) . PHP_EOL;
		}
	}
}

function ss_thold_time() {
	$_stats = explode(' ', db_fetch_cell('SELECT value FROM settings WHERE name="stats_thold"'));

	$stats = '';
	foreach ($_stats as $_stat) {
		if (preg_match('/^Time/', $_stat)) $stats .= str_replace('Time:', '', $_stat);
	}

	return empty($stats) ? '0' : trim($stats);
}

function ss_thold_checks() {
	$_stats = explode(' ', db_fetch_cell('SELECT value FROM settings WHERE name="stats_thold"'));

	$stats = '';
	foreach ($_stats as $_stat) {
		if (preg_match('/^Tholds/', $_stat)) $stats .= str_replace('Tholds:', '', $_stat);
	}

	return empty($stats) ? '0' : trim($stats);
}

function ss_thold_hstats() {
	$_stats = explode(' ', db_fetch_cell('SELECT value FROM settings WHERE name="stats_thold"'));

	$stats = '';
	foreach ($_stats as $_stat) {
		if (preg_match('/^TotalDevices/', $_stat)) {
			$stats .= $_stat . ' ';
		} elseif (preg_match('/^DownDevices/', $_stat)) {
			$stats .= $_stat;
		}
	}

	return empty($stats) ? 'TotalDevices:0 DownDevices:0' : trim($stats);
}

function ss_monitor_time() {
	$_stats = explode(' ', db_fetch_cell('SELECT value FROM settings WHERE name="stats_monitor"'));

	$stats = '';
	foreach ($_stats as $_stat) {
		if (preg_match('/^Time/', $_stat)) $stats .= str_replace('Time:', '', $_stat);
	}

	return empty($stats) ? '0' : trim($stats);
}

function ss_monitor_stats() {
	$_stats = explode(' ', db_fetch_cell('SELECT value FROM settings WHERE name="stats_monitor"'));

	$stats = '';
	foreach ($_stats as $_stat) {
		if (preg_match('/^Reboots/', $_stat)) {
			$stats .= $_stat . ' ';
		} elseif (preg_match('/^DownDevices/', $_stat)) {
			$stats .= $_stat . ' ';
		} elseif (preg_match('/^Notifications/', $_stat)) {
			$stats .= $_stat . ' ';
		} elseif (preg_match('/^Purges/', $_stat)) {
			$stats .= $_stat . ' ';
		}
	}

	return empty($stats) ? 'Reboots:0 DownDevices:0 Notifications:0 Purges:0' : trim($stats);
}

function ss_syslog_time() {
	$_stats = explode(' ', db_fetch_cell('SELECT value FROM settings WHERE name="syslog_stats"'));

	$stats = '';
	foreach ($_stats as $_stat) {
		if (preg_match('/^time/', $_stat)) $stats .= str_replace('time:', '', $_stat);
	}

	return empty($stats) ? '0' : trim($stats);
}

function ss_syslog_stats() {
	$_stats = explode(' ', db_fetch_cell('SELECT value FROM settings WHERE name="syslog_stats"'));

	$stats = '';
	foreach ($_stats as $_stat) {
		if (preg_match('/^deletes/', $_stat)) {
			$stats .= $_stat . ' ';
		} elseif (preg_match('/^incoming/', $_stat)) {
			$stats .= $_stat . ' ';
		} elseif (preg_match('/^removes/', $_stat)) {
			$stats .= $_stat . ' ';
		} elseif (preg_match('/^xfers/', $_stat)) {
			$stats .= $_stat . ' ';
		} elseif (preg_match('/^alerts/', $_stat)) {
			$stats .= $_stat . ' ';
		} elseif (preg_match('/^alarms/', $_stat)) {
			$stats .= $_stat . ' ';
		} elseif (preg_match('/^reports/', $_stat)) {
			$stats .= $_stat . ' ';
		}
	}

	return empty($stats) ? 'deletes:0 incoming:0 removes:0 xfers:0 alerts:0 alarms:0 reports:0' : trim($stats);
}

function ss_poller() {
	$stats = db_fetch_cell('SELECT value
		FROM settings
		WHERE name="stats_poller"');

	return empty($stats) ? 'Time:0 Method:0 Processes:0 Threads:0 Hosts:0 HostsPerProcess:0 DataSources:0 RRDsProcessed:0' : trim($stats);
}

function ss_webseer_counts() {
	$stats = array();
	if (db_table_exists('plugin_webseer_urls')) {
		$stats = db_fetch_row('SELECT SUM(triggered) AS triggered,
			SUM(CASE WHEN triggered=0 THEN 1 ELSE 0 END) AS successful,
			SUM(CASE WHEN enabled="" THEN 1 ELSE 0 END) AS disabled
			FROM plugin_webseer_urls');
	}


	return !cacti_sizeof($stats) ? 'triggered:0 successful:0 disabled:0' : 'triggered:' . $stats['triggered'] . ' successful:' . $stats['successful'] . ' disabled:' . $stats['disabled'];
}

function ss_webseer_stats() {
	$_stats = explode(' ', db_fetch_cell('SELECT value FROM settings WHERE name="stats_webseer"'));

	$stats = '';
	foreach ($_stats as $_stat) {
		if (preg_match('/^Time/', $_stat)) {
			$stats .= $_stat . ' ';
		} elseif (preg_match('/^Checks/', $_stat)) {
			$stats .= $_stat . ' ';
		} elseif (preg_match('/^Servers/', $_stat)) {
			$stats .= $_stat . ' ';
		}
	}

	return empty($stats) ? 'Time:0 Checks:0 Servers:0' : trim($stats);
}

function ss_poller_items() {
	$poller_cache = db_fetch_assoc('SELECT action, COUNT(*) AS count
		FROM poller_item
		GROUP BY action');

	$entries = array(0, 0, 0);

	if (cacti_sizeof($poller_cache)) {
		foreach ($poller_cache as $item) {
			$entries[$item['action']] = $item['count'];
		}
	}

	return trim(
		'snmp:'          . $entries[0] . ' ' .
		'script:'        . $entries[1] . ' ' .
		'script_server:' . $entries[2]
	);
}

function ss_recache() {
	$stats = db_fetch_cell('SELECT value
		FROM settings
		WHERE name LIKE "stats_recache%"
		LIMIT 1');

	return empty($stats) ? 'RecacheTime:0 DevicesRecached:0' : trim($stats);
}

function ss_boost() {
	$stats = db_fetch_cell('SELECT value
		FROM settings
		WHERE name = "stats_boost"');

	return empty($stats) ? 'Time:0 RRDUpdates:0' : trim($stats);
}

function ss_boost_mem() {
	$stats = db_fetch_cell('SELECT value
		FROM settings
		WHERE name="boost_peak_memory"');

	return empty($stats) ? '0' : trim($stats);
}

function ss_boost_table() {
	$stats = db_fetch_cell('SELECT DATA_LENGTH+INDEX_LENGTH AS tbl_len
		FROM INFORMATION_SCHEMA.TABLES
		WHERE TABLE_NAME = "poller_output_boost"
		AND TABLE_SCHEMA = SCHEMA()');

	return empty($stats) ? '0' : trim($stats);
}

function ss_boost_records() {
	$stats = db_fetch_cell('SELECT TABLE_ROWS
		FROM INFORMATION_SCHEMA.TABLES
		WHERE TABLE_NAME = "poller_output_boost"
		AND TABLE_SCHEMA = SCHEMA()');

	return empty($stats) ? '0' : trim($stats);
}

function ss_boost_avg_size() {
	$stats = db_fetch_cell('SELECT AVG_ROW_LENGTH
		FROM INFORMATION_SCHEMA.TABLES
		WHERE TABLE_NAME = "poller_output_boost"
		AND TABLE_SCHEMA = SCHEMA()');

	return empty($stats) ? '0' : trim($stats);
}

function ss_boost_timing() {
	$_stats = explode(' ', db_fetch_cell('SELECT value FROM settings WHERE name="stats_detail_boost"'));

	$stats = '';
	foreach ($_stats as $_stat) {
		if (preg_match('/^get_records:/', $_stat)) {
			$stats .= str_replace('get_records', 'rrd_get_records', $_stat) . ' ';
		} elseif (preg_match('/^results_cycle:/', $_stat)) {
			$stats .= str_replace('results_cycle', 'rrd_results_cycle', $_stat) . ' ';
		} elseif (preg_match('/^rrd_filename_and_template:/', $_stat)) {
			$stats .= str_replace('rrd_filename_and_template', 'rrd_template', $_stat) . ' ';
		} elseif (preg_match('/^rrd_lastupdate:/', $_stat)) {
			$stats .= $_stat . ' ';
		} elseif (preg_match('/^rrdupdate:/', $_stat)) {
			$stats .= str_replace('rrdupdate', 'rrd_update', $_stat) . ' ';
		} elseif (preg_match('/^delete:/', $_stat)) {
			$stats .= str_replace('delete', 'rrd_delete', $_stat) . ' ';
		}
	}

	return empty($stats) ? 'get_records:0 results_cycle:0 rrd_filename_and_template:0 rrd_lastupdate:0 rrdupdate:0 delete:0' : trim($stats);
}

function ss_export() {
	$_stats = explode(' ', db_fetch_cell('SELECT value FROM settings WHERE name="stats_export"'));

	$stats = '';
	foreach ($_stats as $_stat) {
		if (preg_match('/^ExportDuration/', $_stat)) {
			$stats .= $_stat . ' ';
		} elseif (preg_match('/^TotalGraphsExported/', $_stat)) {
			$stats .= $_stat . ' ';
		}
	}

	return empty($stats) ? 'ExportDuration:0 TotalGraphsExported:0' : trim($stats);
}

