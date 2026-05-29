#!/usr/bin/env php
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

global $config;

if (!isset($called_by_script_server)) {
	include_once(__DIR__ . '/../include/cli_check.php');
	include_once(__DIR__ . '/../lib/snmp.php');

	array_shift($_SERVER['argv']);

	print call_user_func_array('ss_net_snmp_disk_io', $_SERVER['argv']);
} else {
	include_once(__DIR__ . '/../lib/snmp.php');
}

if (!function_exists('ss_counter_step')) {
	/**
	 * Apply a Counter32/Counter64 wrap-corrected delta to a running total.
	 *
	 * Counter32 (2^32) fits in PHP_INT_MAX on 64-bit but saturates on 32-bit;
	 * Counter64 (2^64) overflows PHP_INT on every supported runtime. ext-gmp
	 * is a Cacti dependency, so prefer arbitrary-precision arithmetic and
	 * fall back to float (with documented precision loss) only when gmp is
	 * unavailable.
	 *
	 * Returns a numeric string ('U' is sticky once set).
	 * @param mixed $running
	 */
	function ss_counter_step($running, string $current, string $previous, string $modulus) : string {
		if (!function_exists('gmp_init')) {
			$cur   = (float) $current;
			$prev  = (float) $previous;
			$delta = ($cur >= $prev) ? ($cur - $prev) : ($cur + (float) $modulus - $prev);

			if ($running === 'U' || !is_numeric($running)) {
				return (string) $delta;
			}

			return (string) ((float) $running + $delta);
		}

		$cur  = gmp_init($current);
		$prev = gmp_init($previous);

		if (gmp_cmp($cur, $prev) >= 0) {
			$delta = gmp_sub($cur, $prev);
		} else {
			$delta = gmp_sub(gmp_add($cur, gmp_init($modulus)), $prev);
		}

		if ($running === 'U' || !is_numeric((string) $running)) {
			return gmp_strval($delta);
		}

		return gmp_strval(gmp_add(gmp_init((string) $running), $delta));
	}
}

function ss_net_snmp_disk_io(mixed $host_id_or_hostname = '') : string {
	global $environ, $poller_id, $config;

	if (empty($host_id_or_hostname) || $host_id_or_hostname === null) {
		return 'reads:0 writes:0';
	}

	if (!is_numeric($host_id_or_hostname)) {
		$host_id = db_fetch_cell_prepared('SELECT id
			FROM host
			WHERE hostname = ?',
			[$host_id_or_hostname]);
	} else {
		$host_id = $host_id_or_hostname;
	}

	$tmpdir = sys_get_temp_dir();

	if (!db_table_exists('host_value_cache')) {
		if ($environ != 'realtime') {
			$tmpdir .= '/cacti/net-snmp-devio';
			$tmpfile = $host_id . '_io';
		} else {
			$tmpdir .= '/cacti-rt/net-snmp-devio';
			$tmpfile = $host_id . '_' . $poller_id . '_io_rt';
		}

		if (!is_dir($tmpdir)) {
			mkdir($tmpdir, 0755, true);
		}
	} else {
		$tmpdir = null;

		if ($environ != 'realtime') {
			$dimension = $host_id . '_io';
			$ttl       = -1;
		} else {
			$dimension = $host_id . '_' . $poller_id . '_io_rt';
			$ttl       = 300;
		}
	}

	$previous = [];
	$found    = false;

	if (!db_table_exists('host_value_cache')) {
		if (file_exists("$tmpdir/$tmpfile")) {
			$previous = json_decode(file_get_contents("$tmpdir/$tmpfile"), true);
			$found    = true;
		}
	} else {
		$previous = json_decode(
			db_fetch_cell_prepared('SELECT value
				FROM host_value_cache
				WHERE host_id = ?
				AND dimension = ?
				LIMIT 1',
				[$host_id, $dimension]), true
		);

		// remove the old entry or entries
		db_execute_prepared('DELETE FROM host_value_cache
			WHERE host_id = ?
			AND dimension = ?
			AND time_to_live = ?',
			[$host_id, $dimension, $ttl]);

		if (!empty($previous)) {
			$found = true;
		} else {
			$found = false;
		}
	}

	$indexes = [];
	$host    = db_fetch_row_prepared('SELECT *
		FROM host
		WHERE id = ?',
		[$host_id]);

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
		$host['snmp_retries'],
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
		$host['snmp_retries'],
		SNMP_POLLER,
		$host['snmp_engine_id']);

	foreach ($names as $measure) {
		if (preg_match('/^(?:sd|nvme|xvd|vd|vm|hd|md|dm-)/', $measure['value'])) {
			if (preg_match('/(?:p\d+|\d+)$/', $measure['value']) && !preg_match('/^nvme\d+n\d+$/', $measure['value'])) {
				continue;
			}

			$parts = explode('.', $measure['oid']);

			$indexes[$parts[cacti_sizeof($parts) - 1]] = $parts[cacti_sizeof($parts) - 1];
		}
	}

	$reads = $writes = 0;

	if (cacti_sizeof($indexes)) {
		$iops = cacti_snmp_walk($host['hostname'],
			$host['snmp_community'],
			'.1.3.6.1.4.1.2021.13.15.1.1.5',
			$host['snmp_version'],
			$host['snmp_username'],
			$host['snmp_password'],
			$host['snmp_auth_protocol'],
			$host['snmp_priv_passphrase'],
			$host['snmp_priv_protocol'],
			$host['snmp_context'],
			$host['snmp_port'],
			$host['snmp_timeout'],
			$host['snmp_retries'],
			$host['max_oids'],
			SNMP_POLLER,
			$host['snmp_engine_id']);

		foreach ($iops as $measure) {
			$parts = explode('.', $measure['oid']);
			$index = $parts[cacti_sizeof($parts) - 1];

			if (array_key_exists($index, $indexes)) {
				if (!isset($previous['uptime'])) {
					$reads = 'U';
				} elseif (intval($current['uptime']) < intval($previous['uptime'])) {
					$reads = 'U';
				} elseif (!isset($previous["dr$index"])) {
					$reads = 'U';
				} else {
					$reads = ss_counter_step($reads, (string) $measure['value'], (string) $previous["dr$index"], '4294967296');
				}

				$current["dr$index"] = $measure['value'];
			}
		}

		$iops = cacti_snmp_walk($host['hostname'],
			$host['snmp_community'],
			'.1.3.6.1.4.1.2021.13.15.1.1.6',
			$host['snmp_version'],
			$host['snmp_username'],
			$host['snmp_password'],
			$host['snmp_auth_protocol'],
			$host['snmp_priv_passphrase'],
			$host['snmp_priv_protocol'],
			$host['snmp_context'],
			$host['snmp_port'],
			$host['snmp_timeout'],
			$host['snmp_retries'],
			$host['max_oids'],
			SNMP_POLLER,
			$host['snmp_engine_id']);

		foreach ($iops as $measure) {
			$parts = explode('.', $measure['oid']);
			$index = $parts[cacti_sizeof($parts) - 1];

			if (array_key_exists($index, $indexes)) {
				if (!isset($previous['uptime'])) {
					$writes = 'U';
				} elseif (intval($current['uptime']) < intval($previous['uptime'])) {
					$writes = 'U';
				} elseif (!isset($previous["dw$index"])) {
					$writes = 'U';
				} else {
					$writes = ss_counter_step($writes, (string) $measure['value'], (string) $previous["dw$index"], '4294967296');
				}

				$current["dw$index"] = $measure['value'];
			}
		}

		if (!db_table_exists('host_value_cache')) {
			$data = json_encode($current);
			file_put_contents("$tmpdir/$tmpfile", $data);
		} else {
			$data = json_encode($current);

			db_execute_prepared('REPLACE INTO host_value_cache (host_id, dimension, value, time_to_live)
				VALUES (?, ?, ?, ?)',
				[$host_id, $dimension, $data, $ttl]);
		}
	}

	if ($found) {
		return "reads:$reads writes:$writes";
	} else {
		return 'reads:0 writes:0';
	}
}
