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
	include_once(dirname(__FILE__) . '/../include/cli_check.php');
	include_once(dirname(__FILE__) . '/../lib/snmp.php');

	array_shift($_SERVER['argv']);

	print call_user_func_array('ss_net_snmp_disk_io', $_SERVER['argv']);
} else {
	include_once(dirname(__FILE__) . '/../lib/snmp.php');
}

if (!function_exists('ss_counter_step')) {
	/**
	 * Apply a Counter32/Counter64 wrap-corrected delta to a running total. Counter32 (2^32) fits in
	 * PHP_INT_MAX on 64-bit but saturates on 32-bit; Counter64 (2^64) overflows PHP_INT on every
	 * supported runtime. ext-gmp is a Cacti dependency, so prefer arbitrary-precision arithmetic and
	 * fall back to float (with documented precision loss) only when gmp is unavailable. Returns a
	 * numeric string. If $running is 'U' or non-numeric, the first computed delta replaces it -- 'U'
	 * does not persist. Used as part of Cacti's scripts functionality.
	 *
	 * @param mixed $running The running.
	 * @param string $current The current.
	 * @param string $previous The previous.
	 * @param string $modulus The modulus.
	 *
	 * @return string The resulting string.
	 */
	function ss_counter_step($running, string $current, string $previous, string $modulus) : string {
		/* counters arrive from the device and the state file; anything other
		 * than an unsigned integer would make gmp_init() throw */
		if (!ctype_digit($current) || !ctype_digit($previous)) {
			return 'U';
		}

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

if (!function_exists('ss_net_snmp_state_dir')) {
	/**
	 * Return a state directory private to the poller user, creating it when it
	 * does not exist. Used as part of Cacti's scripts functionality.
	 *
	 * The directory lives under the shared system temporary directory, so the
	 * directory and its parent must be real directories owned by this user and
	 * closed to other users before state is read from or written to them.
	 *
	 * @param string $dir The state directory.
	 *
	 * @return string|false The directory, or false when it cannot be trusted.
	 */
	function ss_net_snmp_state_dir($dir) {
		foreach (array(dirname($dir), $dir) as $path) {
			if (!is_link($path) && !file_exists($path)) {
				@mkdir($path, 0700);
			}

			clearstatcache(true, $path);

			if (is_link($path) || !is_dir($path)) {
				cacti_log("WARNING: State directory '$path' is not a directory", false, 'POLLER');

				return false;
			}

			if (function_exists('posix_geteuid') && fileowner($path) !== posix_geteuid()) {
				cacti_log("WARNING: State directory '$path' is owned by another user", false, 'POLLER');

				return false;
			}

			if ((fileperms($path) & 0077) && !@chmod($path, 0700)) {
				cacti_log("WARNING: State directory '$path' is open to other users", false, 'POLLER');

				return false;
			}
		}

		return $dir;
	}
}

if (!function_exists('ss_net_snmp_state_read')) {
	/**
	 * Load the previous poll's counters. Used as part of Cacti's scripts functionality.
	 *
	 * @param string $file The state file.
	 *
	 * @return array|false The decoded state, or false when there is no usable state file.
	 */
	function ss_net_snmp_state_read($file) {
		if (is_link($file) || !is_file($file)) {
			return false;
		}

		if (function_exists('posix_geteuid') && fileowner($file) !== posix_geteuid()) {
			return false;
		}

		$state = json_decode((string) file_get_contents($file), true);

		return (is_array($state) ? $state : array());
	}
}

if (!function_exists('ss_net_snmp_state_write')) {
	/**
	 * Replace the state file in one step so a reader never sees a partial
	 * write. Used as part of Cacti's scripts functionality.
	 *
	 * @param string $dir The state directory.
	 * @param string $file The state file.
	 * @param string $data The encoded state.
	 *
	 * @return bool True when the state file was replaced.
	 */
	function ss_net_snmp_state_write($dir, $file, $data) {
		$temp = tempnam($dir, 'state');

		/* tempnam() falls back to the system directory when $dir is unusable */
		if ($temp === false || realpath(dirname($temp)) !== realpath($dir)) {
			if ($temp !== false) {
				@unlink($temp);
			}

			return false;
		}

		if (file_put_contents($temp, $data) === false || !rename($temp, $file)) {
			@unlink($temp);

			return false;
		}

		return true;
	}
}

/**
 * Handles the ss net SNMP disk io. Used as part of Cacti's scripts functionality.
 *
 * @param mixed $host_id_or_hostname The host ID or hostname.
 *
 * @return string The resulting string.
 */
function ss_net_snmp_disk_io($host_id_or_hostname = '') {
	global $environ, $poller_id, $config;

	if (empty($host_id_or_hostname) || $host_id_or_hostname === NULL) {
		return 'reads:0 writes:0';
	} elseif (!is_numeric($host_id_or_hostname)) {
		$host_id = db_fetch_cell_prepared('SELECT id
			FROM host
			WHERE hostname = ?',
			array($host_id_or_hostname));
	} else {
		$host_id = $host_id_or_hostname;
	}

	$host_id = (int) $host_id;

	if ($host_id <= 0) {
		return 'reads:0 writes:0';
	}

	$tmpdir = sys_get_temp_dir();

	if ($environ != 'realtime') {
		$tmpdir = $tmpdir . '/cacti/net-snmp-devio';
		$tmpfile = $host_id . '_io';
	} else {
		$tmpdir = $tmpdir . '/cacti-rt/net-snmp-devio';
		$tmpfile = $host_id . '_' . (int) $poller_id . '_io_rt';
	}

	$tmpdir = ss_net_snmp_state_dir($tmpdir);

	$previous = array();
	$found    = false;

	if ($tmpdir !== false) {
		$previous = ss_net_snmp_state_read("$tmpdir/$tmpfile");
		$found    = ($previous !== false);

		if (!$found) {
			$previous = array();
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

	foreach($names as $measure) {
		if (substr($measure['value'],0,2) == 'sd' || substr($measure['value'],0,4) == 'nvme' || substr($measure['value'],0,2) == 'vm') {
			if (is_numeric(substr(strrev($measure['value']),0,1))) {
				continue;
			}

			$parts = explode('.', $measure['oid']);
			$indexes[$parts[cacti_sizeof($parts)-1]] = $parts[cacti_sizeof($parts)-1];
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
			$host['ping_retries'],
			$host['max_oids'],
			SNMP_POLLER,
			$host['snmp_engine_id']);

		foreach($iops as $measure) {
			$parts = explode('.', $measure['oid']);
			$index = $parts[cacti_sizeof($parts)-1];

			if (array_key_exists($index, $indexes)) {
				if (!isset($previous['uptime'])) {
					$reads = 'U';
				} elseif ($current['uptime'] < $previous['uptime']) {
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
			$host['ping_retries'],
			$host['max_oids'],
			SNMP_POLLER,
			$host['snmp_engine_id']);

		foreach($iops as $measure) {
			$parts = explode('.', $measure['oid']);
			$index = $parts[cacti_sizeof($parts)-1];

			if (array_key_exists($index, $indexes)) {
				if (!isset($previous['uptime'])) {
					$writes = 'U';
				} elseif ($current['uptime'] < $previous['uptime']) {
					$writes = 'U';
				} elseif (!isset($previous["dw$index"])) {
					$writes = 'U';
				} else {
					$writes = ss_counter_step($writes, (string) $measure['value'], (string) $previous["dw$index"], '4294967296');
				}

				$current["dw$index"] = $measure['value'];
			}
		}

		if ($tmpdir !== false) {
			ss_net_snmp_state_write($tmpdir, "$tmpdir/$tmpfile", json_encode($current));
		}
	}

	if ($found) {
		return "reads:$reads writes:$writes";
	} else {
		return 'reads:0 writes:0';
	}
}

