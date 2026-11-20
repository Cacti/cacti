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
	print call_user_func_array('ss_mikrotik_health', $_SERVER['argv']);
}

/**
 * Return a health metric of a MikroTik device.
 *
 * The MikroTik graph templates pass the plugin_mikrotik_system_health column
 * name (HlVoltage, HlCpuTemperature, ...).  Before plugin schema 3.4 that
 * column is the only source.  From schema 3.4 RouterOS 7 devices report
 * health through the mtxrGaugeTable, collected into
 * plugin_mikrotik_health_sensors, which is preferred when it has a value.
 *
 * @param int|string $host_id The Cacti host id
 * @param string     $column  The health column, or one of its aliases
 *
 * @return string The metric value, U when there is no data
 */
function ss_mikrotik_health($host_id = 0, $column = '') {
	// the table only exists when the mikrotik plugin is installed
	if (!db_table_exists('plugin_mikrotik_system_health', false)) {
		return 'U';
	}

	$columns = ss_mikrotik_health_columns();
	$aliases = ss_mikrotik_health_aliases();
	$column  = (string) $column;

	if (isset($aliases[$column])) {
		$sensors = $aliases[$column]['sensors'];
		$column  = $aliases[$column]['column'];
	} elseif (isset($columns[$column])) {
		$sensors = $columns[$column];
	} else {
		return 'U';
	}

	$host_id = (int) $host_id;

	// plugin schema 3.4 and later: RouterOS 7 gauge sensors
	if (cacti_sizeof($sensors) && db_table_exists('plugin_mikrotik_health_sensors', false)) {
		foreach ($sensors as $sensor) {
			$value = db_fetch_cell_prepared('SELECT value
				FROM plugin_mikrotik_health_sensors
				WHERE host_id = ?
				AND name = ?
				AND present = 1',
				[$host_id, $sensor]);

			if (is_numeric($value)) {
				return (string) $value;
			}
		}
	}

	// pre RouterOS 7 devices, and plugin versions before schema 3.4
	$value = db_fetch_cell_prepared('SELECT `' . $column . '`
		FROM plugin_mikrotik_system_health
		WHERE host_id = ?',
		[$host_id]);

	return is_numeric($value) ? (string) $value : 'U';
}

/**
 * The numeric plugin_mikrotik_system_health columns, each with the RouterOS 7
 * mtxrGaugeTable sensor names the plugin poller maps onto it, in order of
 * preference.
 *
 * @return array<string, array<int, string>>
 */
function ss_mikrotik_health_columns() {
	return [
		'HlCoreVoltage'            => ['core-voltage'],
		'HlThreeDotThreeVoltage'   => ['3.3-voltage'],
		'HlFiveVoltage'            => ['5-voltage'],
		'HlTwelveVoltage'          => ['12-voltage'],
		'HlVoltage'                => ['voltage', 'psu1-voltage'],
		'HlSensorTemperature'      => ['sensor-temperature'],
		'HlCpuTemperature'         => ['cpu-temperature'],
		'HlBoardTemperature'       => ['board-temperature', 'board-temperature1'],
		'HlTemperature'            => ['temperature'],
		'HlProcessorTemperature'   => [],
		'HlPower'                  => ['power-consumption', 'power'],
		'HlCurrent'                => ['current', 'psu1-current'],
		'HlProcessorFrequency'     => ['cpu-frequency'],
		'HlPowerSupplyState'       => ['psu1-state'],
		'HlBackupPowerSupplyState' => ['psu2-state'],
		'HlFanSpeed1'              => ['fan1-speed', 'fan1'],
		'HlFanSpeed2'              => ['fan2-speed', 'fan2'],
	];
}

/**
 * Metric names accepted in place of the health column name, each with the
 * RouterOS 7 gauge sensors it prefers and its legacy fallback column.
 *
 * @return array<string, array{sensors: array<int, string>, column: string}>
 */
function ss_mikrotik_health_aliases() {
	return [
		'voltage'              => ['sensors' => ['voltage', 'psu1-voltage'],          'column' => 'HlVoltage'],
		'temperature'          => ['sensors' => ['temperature', 'board-temperature'], 'column' => 'HlTemperature'],
		'processorTemperature' => ['sensors' => ['cpu-temperature'],                  'column' => 'HlProcessorTemperature'],
		'current'              => ['sensors' => ['current', 'psu1-current'],          'column' => 'HlCurrent'],
		'power'                => ['sensors' => ['power-consumption', 'power'],       'column' => 'HlPower'],
		'fanSpeed'             => ['sensors' => ['fan1-speed', 'fan1'],               'column' => 'HlFanSpeed1'],
		'cpuFrequency'         => ['sensors' => ['cpu-frequency'],                    'column' => 'HlProcessorFrequency'],
	];
}
