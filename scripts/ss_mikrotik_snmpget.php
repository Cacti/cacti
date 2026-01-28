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
	print call_user_func_array('ss_mikrotik_snmpget', $_SERVER['argv']);
}

function ss_mikrotik_snmpget($host_id = '', $oid = '') {
	global $config;

	include_once($config['base_path'] . '/lib/snmp.php');

	if ($host_id > 0) {
		$host = db_fetch_row_prepared('SELECT hostname, snmp_community, snmp_version, snmp_username, snmp_password,
			snmp_auth_protocol, snmp_priv_passphrase, snmp_priv_protocol, snmp_context,
			snmp_port, snmp_timeout, max_oids, snmp_engine_id
			FROM host
			WHERE id = ?',
			[$host_id]);

		if (cacti_sizeof($host)) {
			$get = cacti_snmp_get($host['hostname'], $host['snmp_community'], $oid, $host['snmp_version'],
				$host['snmp_username'], $host['snmp_password'],
				$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
				$host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'],
				read_config_option('snmp_retries'), SNMP_WEBUI, $host['snmp_engine_id']);

			if (!empty($get)) {
				return $get;
			}
		}
	}

	return '0';
}
