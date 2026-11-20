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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

// display no errors
error_reporting(0);

if (!isset($called_by_script_server)) {
	include(__DIR__ . '/../include/cli_check.php');
	array_shift($_SERVER['argv']);
	print call_user_func_array('ss_mikrotik_wireless_reg', $_SERVER['argv']);
}

/**
 * Data query backend for resource/script_server/mikrotik_wireless_reg.xml.
 *
 * Registrations are indexed by the client MAC address, whether collected
 * over SNMP or, for RouterOS 7 WifiWave2 devices, the RouterOS API.
 *
 * @param int|string $host_id The Cacti host id
 * @param string     $cmd     One of index, num_indexes, query or get
 * @param string     $object  The query field for query and get
 * @param string     $index   The registration index for get
 *
 * @return string The value for get and num_indexes, otherwise empty
 */
function ss_mikrotik_wireless_reg($host_id, $cmd = 'index', $object = '', $index = '') {
	// the table only exists when the mikrotik plugin is installed
	if (!db_table_exists('plugin_mikrotik_wireless_registrations', false)) {
		if ($cmd == 'num_indexes') {
			return '0';
		}

		if ($cmd == 'get') {
			return 'U';
		}

		return '';
	}

	$host_id = (int) $host_id;

	if ($cmd == 'index') {
		foreach (ss_mikrotik_wireless_reg_getnames($host_id) as $mac) {
			print $mac . PHP_EOL;
		}
	} elseif ($cmd == 'num_indexes') {
		// counted with the same filters as the index list
		return (string) db_fetch_cell_prepared('SELECT COUNT(*)
			FROM plugin_mikrotik_wireless_registrations
			WHERE host_id = ?',
			[$host_id]);
	} elseif ($cmd == 'query') {
		if ($object == 'macAddress') {
			foreach (ss_mikrotik_wireless_reg_getnames($host_id) as $mac) {
				print $mac . '!' . $mac . PHP_EOL;
			}
		}
	} elseif ($cmd == 'get') {
		return ss_mikrotik_wireless_reg_getvalue($host_id, $index, $object);
	}

	return '';
}

/**
 * Return a value of a wireless registration.
 *
 * @param int|string $host_id The Cacti host id
 * @param string     $index   The client MAC address
 * @param string     $object  The data query output field, e.g. TxBytes
 *
 * @return string The value, U when there is no data
 */
function ss_mikrotik_wireless_reg_getvalue($host_id, $index, $object) {
	$columns = [
		'SignalToNoise' => 'SignalToNoise',
		'Uptime'        => 'Uptime',
		'TxRate'        => 'TxRate',
		'RxRate'        => 'RxRate',
		'TxBytes'       => 'TxBytes',
		'RxBytes'       => 'RxBytes',
		'TxPackets'     => 'TxPackets',
		'RxPackets'     => 'RxPackets',
		'Strength'      => 'Strength',
		'TxStrengthCh0' => 'TxStrengthCh0',
		'RxStrengthCh0' => 'RxStrengthCh0',
		'TxStrengthCh1' => 'TxStrengthCh1',
		'RxStrengthCh1' => 'RxStrengthChl', // the plugin column name has a historical typo
		'TxStrengthCh2' => 'TxStrengthCh2',
		'RxStrengthCh2' => 'RxStrengthCh2',
		'TxStrength'    => 'TxStrength',
	];

	if (!isset($columns[$object])) {
		return 'U';
	}

	$value = db_fetch_cell_prepared('SELECT `' . $columns[$object] . '`
		FROM plugin_mikrotik_wireless_registrations
		WHERE host_id = ?
		AND `index` = ?
		AND present = 1',
		[(int) $host_id, $index]);

	if ($object == 'Uptime') {
		return ss_mikrotik_wireless_reg_uptime($value);
	}

	// a disconnected or unknown registration: unknown, not zero
	return is_numeric($value) ? (string) $value : 'U';
}

/**
 * Convert a stored registration uptime to seconds.  The SNMP collector stores
 * mtxrWlRtabUptime TimeTicks (hundredths of a second), the RouterOS API
 * collector 'days:hours:minutes:seconds'.
 *
 * @param mixed $value The stored uptime
 *
 * @return string The uptime in seconds, U when it is not recognized
 */
function ss_mikrotik_wireless_reg_uptime($value) {
	if (is_numeric($value)) {
		return (string) floor($value / 100);
	}

	if (!is_string($value) || !preg_match('/^(?:(\d+):)?(\d+):(\d+):(\d+)(?:\.\d+)?$/', trim($value), $matches)) {
		return 'U';
	}

	return (string) ((int) $matches[1] * 86400 + (int) $matches[2] * 3600 + (int) $matches[3] * 60 + (int) $matches[4]);
}

/**
 * Return the client MAC addresses registered on a device.
 *
 * @param int|string $host_id The Cacti host id
 *
 * @return array<int, string>
 */
function ss_mikrotik_wireless_reg_getnames($host_id) {
	$return_arr = [];

	$arr = db_fetch_assoc_prepared('SELECT `index` AS macAddress
		FROM plugin_mikrotik_wireless_registrations
		WHERE host_id = ?
		ORDER BY macAddress',
		[(int) $host_id]);

	if (cacti_sizeof($arr)) {
		foreach ($arr as $a) {
			$return_arr[] = $a['macAddress'];
		}
	}

	return $return_arr;
}
