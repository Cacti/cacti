<?php

if (!isset($called_by_script_server)) {
	include_once(__DIR__ . '/../include/cli_check.php');
}

include_once(__DIR__ . '/../lib/snmp.php');

if (!isset($called_by_script_server)) {
	array_shift($_SERVER['argv']);
	print call_user_func_array('ss_multicpu_avg', $_SERVER['argv']);
}

function ss_multicpu_avg(int $device_id) : string {
	$host = db_fetch_row_prepared('SELECT *
		FROM host
		WHERE id = ?',
		[$device_id]);

	if (!cacti_sizeof($host)) {
		return "load:0\n";
	}

	$snmp_retries = read_config_option('snmp_retries');

	$oid_cpus = '.1.3.6.1.2.1.25.3.3.1.2';

	$array = cacti_snmp_walk(
		$host['hostname'],
		$host['snmp_community'],
		$oid_cpus,
		$host['snmp_version'],
		$host['snmp_username'],
		$host['snmp_password'],
		$host['snmp_auth_protocol'],
		$host['snmp_priv_passphrase'],
		$host['snmp_priv_protocol'],
		$host['snmp_context'],
		$host['snmp_port'],
		$host['snmp_timeout'],
		$snmp_retries
	);

	return 'load:' . ss_multicpu_avg_load($array) . "\n";
}

/**
 * ss_multicpu_avg_load - average the hrProcessorLoad rows
 *
 * Devices can return non-numeric rows, which are left out of the average.
 *
 * @param array $rows The walk result
 *
 * @return mixed The average load, or 0 when no row is numeric
 */
function ss_multicpu_avg_load(array $rows) : mixed {
	$load  = 0;
	$valid = 0;

	foreach ($rows as $row) {
		if (isset($row['value']) && is_numeric($row['value'])) {
			$load += $row['value'];
			$valid++;
		}
	}

	return $valid ? $load / $valid : 0;
}
