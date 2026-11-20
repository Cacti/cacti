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

error_reporting(0);

if (!isset($called_by_script_server)) {
	include_once(__DIR__ . '/../include/cli_check.php');

	array_shift($_SERVER['argv']);

	print call_user_func_array('ss_upsd', $_SERVER['argv']);
}

function ss_upsd_check_null($value) {
	if ($value === null) {
		return 'U';
	} else {
		return $value;
	}
}

function ss_upsd($function, $host_id) {
	$output = array();

	/* the tables only exist when the apcupsd plugin is installed; no row cache, it would go stale in the script server */
	if (db_table_exists('apcupsd_ups', false) && db_table_exists('apcupsd_ups_stats', false)) {
		$ups_id = db_fetch_cell_prepared('SELECT id
			FROM apcupsd_ups
			WHERE host_id = ?',
			array($host_id));

		if ($ups_id > 0) {
			$output = db_fetch_row_prepared('SELECT *
				FROM apcupsd_ups_stats
				WHERE ups_id = ?',
				array($ups_id));
		}
	}

	switch ($function) {
		case 'upsd_voltages':
			if (cacti_sizeof($output)) {
				return 'high_transfer:' . ss_upsd_check_null($output['ups_hitrans'])       .
					' line_volts:'      . ss_upsd_check_null($output['ups_line_voltage'])     .
					' low_transfer:'    . ss_upsd_check_null($output['ups_lowtrans'])         .
					' max_line_volts:'  . ss_upsd_check_null($output['ups_max_line_voltage']) .
					' min_line_volts:'  . ss_upsd_check_null($output['ups_min_line_voltage']) .
					' output_volts:'    . ss_upsd_check_null($output['ups_output_voltage']);
			} else {
				return 'high_transfer:U line_volts:U low_transfer:U max_line_volts:U min_line_volts:U output_volts:U';
			}

			break;
		case 'upsd_bvoltages':
			if (cacti_sizeof($output)) {
				return 'high_transfer:' . ss_upsd_check_null($output['ups_hitrans'])       .
					' line_volts:'      . ss_upsd_check_null($output['ups_line_voltage'])     .
					' low_transfer:'    . ss_upsd_check_null($output['ups_lowtrans']);
			} else {
				return 'high_transfer:U line_volts:U low_transfer:U';
			}

			break;
		case 'upsd_timeleft':
			if (cacti_sizeof($output)) {
				$value = ss_upsd_check_null($output['ups_timeleft']);
				return 'timeleft:' . $value;
			} else {
				return 'timeleft:U';
			}

			break;
		case 'upsd_load':
			if (cacti_sizeof($output)) {
				$value = ss_upsd_check_null($output['ups_load_percent']);
				return 'loadpct:' . $value;
			} else {
				return 'loadpct:U';
			}

			break;
		case 'upsd_frequency':
			if (cacti_sizeof($output)) {
				$value = ss_upsd_check_null($output['ups_line_frequency']);
				return 'frequency:' . $value;
			} else {
				return 'frequency:U';
			}

			break;
		case 'upsd_temp':
			if (cacti_sizeof($output)) {
				$value = ss_upsd_check_null($output['ups_internal_temp']);
				return 'internal_temp:' . $value;
			} else {
				return 'internal_temp:U';
			}

			break;
		case 'upsd_battvolts':
			if (cacti_sizeof($output)) {
				$value = ss_upsd_check_null($output['ups_battery_voltage']);
				return 'batt_volts:' . $value;
			} else {
				return 'batt_volts:U';
			}

			break;
		case 'upsd_batttimes':
			if (cacti_sizeof($output)) {
				$value1 = ss_upsd_check_null($output['ups_cumonbatt']);
				$value2 = ss_upsd_check_null($output['ups_numxfers']);
				return 'cumonbatt:' . $value1 . ' numxfers:' . $value2;
			} else {
				return 'cumonbatt:U numxfers:U';
			}

			break;
		case 'upsd_bcharge':
			if (cacti_sizeof($output)) {
				$value = ss_upsd_check_null($output['ups_battery_charge']);
				return 'batt_charge:' . $value;
			} else {
				return 'batt_charge:U';
			}

			break;
		default:
			return 'U';
	}
}
