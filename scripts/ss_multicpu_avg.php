<?php

include_once(dirname(__FILE__) . '/../include/cli_check.php');
include_once(dirname(__FILE__) . '/../lib/snmp.php');

if (!isset($called_by_script_server)) {
	array_shift($_SERVER['argv']);
	print call_user_func_array('ss_multicpu_avg', $_SERVER['argv']);
}

function ss_multicpu_avg($device_id) {
	$host = db_fetch_row_prepared('SELECT *
		FROM host
		WHERE id = ?',
		array($device_id));

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
		$snmp_retries,
	);

	$load = 0;

	if (cacti_sizeof($array)) {
		foreach ($array as $key=>$value)	{
		    $load += $value['value'];
		}
	} else {
		return "load:0\n";
	}

	$load = $load / cacti_count($array);

	return "load:$load\n";
}

