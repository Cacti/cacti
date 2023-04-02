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

global $config;

if (!isset($called_by_script_server)) {
	include_once(__DIR__ . '/../include/cli_check.php');
	include_once(__DIR__ . '/../lib/snmp.php');

	array_shift($_SERVER['argv']);

	print call_user_func_array('ss_net_snmp_disk_bytes', $_SERVER['argv']);
} else {
	include_once(__DIR__ . '/../lib/snmp.php');
}

function ss_net_snmp_disk_bytes($host_id_or_hostname = '') {
	global $environ, $poller_id, $config;

	if (empty($host_id_or_hostname) || $host_id_or_hostname === null) {
		return 'reads:0 writes:0';
	}

	if (!is_numeric($host_id_or_hostname)) {
		$host_id = db_fetch_cell_prepared('SELECT id
			FROM host
			WHERE hostname = ?',
			array($host_id_or_hostname));
	} else {
		$host_id = $host_id_or_hostname;
	}

	$tmpdir = sys_get_temp_dir();

	if (!db_table_exists('host_value_cache')) {
		if ($environ != 'realtime') {
			$tmpdir  = $tmpdir . '/cacti/net-snmp-devio';
			$tmpfile = $host_id . '_bytes';
		} else {
			$tmpdir  = $tmpdir . '/cacti-rt/net-snmp-devio';
			$tmpfile = $host_id . '_' . $poller_id . '_bytes_rt';
		}

		if (!is_dir($tmpdir)) {
			mkdir($tmpdir, 0777, true);
		}
	} else {
		if ($environ != 'realtime') {
			$dimension = $host_id . '_bytes';
			$ttl = -1;
		} else {
			$dimension = $host_id . '_' . $poller_id . '_bytes_rt';
			$ttl = 300;
		}
	}

	$found    = false;
	$previous = array();

	if (!db_table_exists('host_value_cache')) {
		if (is_file("$tmpdir/$tmpfile")) {
			$previous = json_decode(file_get_contents("$tmpdir/$tmpfile"), true);
			$found    = true;
		}
	} else {
		$previous = json_decode(
			db_fetch_cell_prepared('SELECT value
				FROM host_value_cache
				WHERE host_id = ?
				AND dimension = ?',
				array($host_id, $dimension)), true
		);

		if (!empty($previous)) {
			$found = true;
		} else {
			$found = false;
		}
	}

	$indexes = array();

	$host = db_fetch_row_prepared('SELECT *
		FROM host
		WHERE id = ?',
		array($host_id));

	if (!cacti_sizeof($host)) {
		return 'reads:0 writes:0';
	}

	$uptime  = cacti_snmp_get($host['hostname'],
		$host['snmp_community'],
		'.1.3.6.1.2.1.1.3.0',
		$host['snmp_version'],
		$host['snmp_username'],
		$host['snmp_password'],
		$host['snmp_auth_protocol'],
		$host['snmp_priv_passphrase'],
		$host['snmp_priv_protocol'],
		$host['snmp_context'],
		$host['snmp_port'],
		$host['snmp_timeout'],
		$host['ping_retries'],
		SNMP_POLLER,
		$host['snmp_engine_id']);

	$current['uptime'] = $uptime;

	$names  = cacti_snmp_walk($host['hostname'],
		$host['snmp_community'],
		'.1.3.6.1.4.1.2021.13.15.1.1.2',
		$host['snmp_version'],
		$host['snmp_username'],
		$host['snmp_password'],
		$host['snmp_auth_protocol'],
		$host['snmp_priv_passphrase'],
		$host['snmp_priv_protocol'],
		$host['snmp_context'],
		$host['snmp_port'],
		$host['snmp_timeout'],
		$host['ping_retries'],
		SNMP_POLLER,
		$host['snmp_engine_id']);

	foreach ($names as $measure) {
		if (substr($measure['value'],0,2) == 'sd' || substr($measure['value'],0,4) == 'nvme' || substr($measure['value'],0,2) == 'vm') {
			if (is_numeric(substr(strrev($measure['value']),0,1))) {
				continue;
			}

			$parts                                     = explode('.', $measure['oid']);
			$indexes[$parts[cacti_sizeof($parts) - 1]] = $parts[cacti_sizeof($parts) - 1];
		}
	}

	$bytesread = $byteswritten = 0;

	if (cacti_sizeof($indexes)) {
		$bytes = cacti_snmp_walk($host['hostname'],
			$host['snmp_community'],
			'.1.3.6.1.4.1.2021.13.15.1.1.12',
			$host['snmp_version'],
			$host['snmp_username'],
			$host['snmp_password'],
			$host['snmp_auth_protocol'],
			$host['snmp_priv_passphrase'],
			$host['snmp_priv_protocol'],
			$host['snmp_context'],
			$host['snmp_port'],
			$host['snmp_timeout'],
			$host['ping_retries'],
			$host['max_oids'],
			SNMP_POLLER,
			$host['snmp_engine_id']);

		foreach ($bytes as $measure) {
			$parts = explode('.', $measure['oid']);
			$index = $parts[cacti_sizeof($parts) - 1];

			if (array_key_exists($index, $indexes)) {
				if (!isset($previous['uptime'])) {
					$bytesread = 'U';
				} elseif ($current['uptime'] < $previous['uptime']) {
					$bytesread = 'U';
				} elseif (!isset($previous["br$index"])) {
					$bytesread = 'U';
				} elseif ($previous["br$index"] > $measure['value']) {
					if ($bytesread != 'U') {
						$bytesread += $measure['value'] + 18446744073709551615 - $previous["br$index"] - $previous["br$index"];
					} else {
						$bytesread = $measure['value'] + 18446744073709551615 - $previous["br$index"] - $previous["br$index"];
					}
				} else {
					if ($bytesread != 'U') {
						$bytesread += $measure['value'] - $previous["br$index"];
					} else {
						$bytesread = $measure['value'] - $previous["br$index"];
					}
				}

				$current["br$index"] = $measure['value'];
			}
		}

		$bytes = cacti_snmp_walk($host['hostname'],
			$host['snmp_community'],
			'.1.3.6.1.4.1.2021.13.15.1.1.13',
			$host['snmp_version'],
			$host['snmp_username'],
			$host['snmp_password'],
			$host['snmp_auth_protocol'],
			$host['snmp_priv_passphrase'],
			$host['snmp_priv_protocol'],
			$host['snmp_context'],
			$host['snmp_port'],
			$host['snmp_timeout'],
			$host['ping_retries'],
			$host['max_oids'],
			SNMP_POLLER,
			$host['snmp_engine_id']);

		foreach ($bytes as $measure) {
			$parts = explode('.', $measure['oid']);
			$index = $parts[cacti_sizeof($parts) - 1];

			if (array_key_exists($index, $indexes)) {
				if (!isset($previous['uptime'])) {
					$byteswritten = 'U';
				} elseif ($current['uptime'] < $previous['uptime']) {
					$byteswritten = 'U';
				} elseif (!isset($previous["bw$index"])) {
					$byteswritten = 'U';
				} elseif ($previous["bw$index"] > $measure['value']) {
					if ($byteswritten != 'U') {
						$byteswritten += $measure['value'] + 18446744073709551615 - $previous["bw$index"] - $previous["bw$index"];
					} else {
						$byteswritten = $measure['value'] + 18446744073709551615 - $previous["bw$index"] - $previous["bw$index"];
					}
				} else {
					if ($byteswritten != 'U') {
						$byteswritten += $measure['value'] - $previous["bw$index"];
					} else {
						$byteswritten = $measure['value'] - $previous["bw$index"];
					}
				}

				$current["bw$index"] = $measure['value'];
			}
		}

		if (!db_table_exists('host_value_cache')) {
			$data = "'" . json_encode($current) . "'";
			shell_exec("echo $data > $tmpdir/$tmpfile");
		} else {
			$data = json_encode($current);

			db_execute_prepared('REPLACE INTO host_value_cache (host_id, dimension, value, time_to_live)
				VALUES (?, ?, ?, ?)',
				array($host_id, $dimension, $data, $ttl));
		}
	}

	if ($found) {
		return "bytesread:$bytesread byteswritten:$byteswritten";
	} else {
		return 'bytesread:0 byteswritten:0';
	}
}
