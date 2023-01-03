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

function api_poller_cache_item_add($host_id, $host_field_override, $local_data_id, $rrd_step, $poller_action_id, $data_source_item_name, $num_rrd_items, $arg1 = '', $arg2 = '', $arg3 = '') {
	static $hosts = array();

	if (!isset($hosts[$host_id])) {
		$host = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . '
			id, poller_id, hostname, snmp_community, snmp_version,
			snmp_username, snmp_password, snmp_auth_protocol, snmp_priv_passphrase,
			snmp_priv_protocol, snmp_context, snmp_engine_id, snmp_port,
			snmp_timeout, disabled
			FROM host
			WHERE id = ?',
			array($host_id));

		if (cacti_sizeof($host)) {
			$hosts[$host_id] = $host;
		}
	} else {
		$host = $hosts[$host_id];
	}

	if (cacti_sizeof($host) || (isset($host_id))) {
		if (isset($host['disabled']) && $host['disabled'] == 'on') {
			return;
		}

		if (!isset($host['id'])) {
			// host id 0 can not have snmp
			if ($poller_action_id == POLLER_ACTION_SNMP) {
				return;
			}

			$host['id'] = 0;
			$host['poller_id']      = 1;
			$host['snmp_community'] = '';
			$host['snmp_timeout'] = '';
			$host['snmp_username'] = '';
			$host['snmp_password'] = '';
			$host['snmp_auth_protocol'] = '';
			$host['snmp_priv_passphrase'] = '';
			$host['snmp_priv_protocol'] = '';
			$host['snmp_context'] = '';
			$host['snmp_engine_id'] = '';
			$host['snmp_version'] = '';
			$host['snmp_port'] = '';
			$host['hostname'] = 'None';

			$hosts[0] = $host;
		}

		/* the $host_field_override array can be used to override certain host fields in the poller cache */
		if (cacti_sizeof($host_field_override)) {
			$host = array_merge($host, $host_field_override);
		}

		// don't add to poller cache for wrong snmp information
		if ($poller_action_id == POLLER_ACTION_SNMP) {
			if (($host['snmp_version'] < 1) || ($host['snmp_version'] > 3) ||
				($host['snmp_community'] == '' && $host['snmp_version'] != 3)) {
				return;
			}
		}

		$rrd_next_step = api_poller_get_rrd_next_step($host_id, $rrd_step, $local_data_id);

		return "($local_data_id, " . $host['poller_id'] . ', ' .
			$host['id'] . ", $poller_action_id," .
			db_qstr($host['hostname'])           . ', ' . db_qstr($host['snmp_community'])       . ', ' .
			db_qstr($host['snmp_version'])       . ', ' . db_qstr($host['snmp_timeout'])         . ', ' .
			db_qstr($host['snmp_username'])      . ', ' . db_qstr($host['snmp_password'])        . ', ' .
			db_qstr($host['snmp_auth_protocol']) . ', ' . db_qstr($host['snmp_priv_passphrase']) . ', ' .
			db_qstr($host['snmp_priv_protocol']) . ', ' . db_qstr($host['snmp_context'])         . ', ' .
			db_qstr($host['snmp_engine_id'])     . ', ' . db_qstr($host['snmp_port'])            . ', ' .
			db_qstr($data_source_item_name)      . ', ' . db_qstr(clean_up_path(get_data_source_path($local_data_id, true))) . ', ' .
			db_qstr($num_rrd_items)              . ', ' . db_qstr($rrd_step)                     . ', ' .
			db_qstr($rrd_next_step)              . ', ' . db_qstr($arg1)                         . ', ' .
			db_qstr($arg2)                       . ', ' . db_qstr($arg3) . ", '1')";
	}
}

function api_poller_get_rrd_next_step($host_id, $rrd_step, $local_data_id) {
	global $config;

	static $rrd_step_counter = 0;
	static $last_host = -1;
	static $last_data_id = -1;
	static $warning_issued = false;

	$poller_interval = read_config_option('poller_interval');
	$rrd_next_step   = 0;

	if (empty($poller_interval)) {
		$poller_interval = 300;
	}

	if ($rrd_step < $poller_interval && !$warning_issued) {
		$message = sprintf('WARNING: The Poller Interval is %s and you have a Data Source with a sampling interval of %s.  Change your Poller Interval to %s seconds, and repopulate your poller cache.', $poller_interval, $rrd_step, $rrd_step);

		admin_email('Cacti Poller Interval Warning', $message);
		cacti_log($message, false, 'POLLER');
		$warning_issued = true;
	}

	$process_leveling = read_config_option('process_leveling');

	if ($last_host != $host_id || $process_leveling != 'on') {
		$rrd_step_counter = 0;
	} elseif ($rrd_step != $poller_interval) {
		if ($last_data_id != $local_data_id) {
			$rrd_step_counter++;
		}

		$modulus = ceil($rrd_step / $poller_interval);
		$rrd_next_step = $poller_interval * ($rrd_step_counter % $modulus);
	}

	$last_host    = $host_id;
	$last_data_id = $local_data_id;

	return $rrd_next_step;
}

