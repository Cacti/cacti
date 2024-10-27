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

error_reporting(0);

if (!isset($called_by_script_server)) {
	include_once(dirname(__FILE__) . '/../include/cli_check.php');
	include_once(dirname(__FILE__) . '/../lib/snmp.php');

	array_shift($_SERVER['argv']);

	print call_user_func_array('ss_host_disk', $_SERVER['argv']);
} else {
	include_once(dirname(__FILE__) . '/../lib/snmp.php');
}

function ss_host_disk($hostname = '', $host_id = 0, $snmp_auth = '', $cmd = 'index', $arg1 = '', $arg2 = '') {
	$snmp = explode(':', $snmp_auth);
	$snmp_version   = $snmp[0];
	$snmp_port      = $snmp[1];
	$snmp_timeout   = $snmp[2];
	$ping_retries   = $snmp[3];
	$max_oids       = $snmp[4];

	$snmp_auth_username   = '';
	$snmp_auth_password   = '';
	$snmp_auth_protocol   = '';
	$snmp_priv_passphrase = '';
	$snmp_priv_protocol   = '';
	$snmp_context         = '';
	$snmp_community       = '';

	if ($snmp_version == 3) {
		$snmp_auth_username   = $snmp[6];
		$snmp_auth_password   = $snmp[7];
		$snmp_auth_protocol   = $snmp[8];
		$snmp_priv_passphrase = $snmp[9];
		$snmp_priv_protocol   = $snmp[10];
		$snmp_context         = $snmp[11];
	} else {
		$snmp_community = $snmp[5];
	}

	$oids = array(
		'total'       => '.1.3.6.1.2.1.25.2.3.1.5',
		'totalin'     => '.1.3.6.1.2.1.25.2.3.1.5',
		'used'        => '.1.3.6.1.2.1.25.2.3.1.6',
		'failures'    => '.1.3.6.1.2.1.25.2.3.1.7',
		'index'       => '.1.3.6.1.2.1.25.2.3.1.1',
		'description' => '.1.3.6.1.2.1.25.2.3.1.3',
		'sau'         => '.1.3.6.1.2.1.25.2.3.1.4'
	);

	if ($cmd == 'index') {
		$return_arr = ss_host_disk_reindex(
			cacti_snmp_walk($hostname, $snmp_community, $oids['index'], $snmp_version, $snmp_auth_username,
				$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
				$snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER)
			);

		if (cacti_sizeof($return_arr)) {
			foreach ($return_arr as $item) {
				print $item . "\n";
			}
		}
	} elseif ($cmd == 'num_indexes') {
		$return_arr = ss_host_disk_reindex(
			cacti_snmp_walk($hostname, $snmp_community, $oids['index'], $snmp_version, $snmp_auth_username,
				$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
				$snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER)
			);

		return cacti_sizeof($return_arr);
	} elseif ($cmd == 'query') {
		$arg = $arg1;

		$arr_index = ss_host_disk_reindex(
			cacti_snmp_walk($hostname, $snmp_community, $oids['index'], $snmp_version, $snmp_auth_username,
				$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
				$snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER)
			);

		if (cacti_sizeof($arr_index)) {
			$arr = ss_host_disk_reindex(
				cacti_snmp_walk($hostname, $snmp_community, $oids[$arg], $snmp_version, $snmp_auth_username,
					$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
					$snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER)
				);

			if (cacti_sizeof($arr)) {
				for ($i=0;($i<cacti_sizeof($arr_index));$i++) {
					if (isset($arr[$i])) {
						print $arr_index[$i] . '!' . $arr[$i] . "\n";
					} else {
						print $arr_index[$i] . '!' . "Unknown\n";
					}
				}
			} else {
				return '';
			}
		} else {
			return '';
		}
	} elseif ($cmd == 'get') {
		$arg   = $arg1;
		$index = $arg2;

		$value = api_plugin_hook_function('hmib_get_disk', array('host_id' => $host_id, 'arg' => $arg, 'index' => $index));

		if (is_array($value)) {
			if (($arg == 'total') || ($arg == 'used')) {
				$sau = preg_replace('/[^0-9]/i', '', db_fetch_cell_prepared("SELECT field_value
					FROM host_snmp_cache
					WHERE host_id = ?
					AND field_name = 'hrStorageAllocationUnits'
					AND snmp_index = ?",
					array($host_id, $index)));

				$snmp_data = cacti_snmp_get($hostname, $snmp_community, $oids[$arg] . ".$index", $snmp_version,
					$snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase,
					$snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, SNMP_POLLER);

				if ($snmp_data != '' && $snmp_data < 0) {
					return (abs($snmp_data) + 2147483647) * $sau;
				} elseif (is_numeric($snmp_data) && is_numeric($sau)) {
					return $snmp_data * $sau;
				} elseif (is_numeric($snmp_data) && !$sau) {
					return $snmp_data;
				} else {
					return 'U';
				}
			} else {
				return cacti_snmp_get($hostname, $snmp_community, $oids[$arg] . ".$index", $snmp_version,
					$snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase,
					$snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, SNMP_POLLER);
			}
		} else {
			return $value;
		}
	}
}

function ss_host_disk_reindex($arr) {
	$return_arr = array();

	if (cacti_sizeof($arr)) {
		for ($i=0;($i<cacti_sizeof($arr));$i++) {
			if (!isset($arr[$i]['value'])) {
				return array();
			}

			$return_arr[$i] = $arr[$i]['value'];
		}
	}

	return $return_arr;
}
