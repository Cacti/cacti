<?php

if (!isset($called_by_script_server)) {
	include_once(__DIR__ . '/../include/cli_check.php');
}

include_once(__DIR__ . '/../lib/snmp.php');

if (!isset($called_by_script_server)) {
	array_shift($_SERVER['argv']);
	print call_user_func_array('ss_esxi_vhosts', $_SERVER['argv']);
}

function ss_esxi_vhosts(int $device_id) : string {
	$host = db_fetch_row_prepared('SELECT *
		FROM host
		WHERE id = ?',
		[$device_id]);

	if (!is_array($host) || !cacti_sizeof($host)) {
		return ("vh:0 vh_state:0 tools_run:0 tools_nrun:0 tools_ninst:0\n");
	}

	$oids = [
		'vh'       => '.1.3.6.1.4.1.6876.2.1.1.2',
		'vh_state' => '.1.3.6.1.4.1.6876.2.1.1.6',
		'vh_tools' => '.1.3.6.1.4.1.6876.2.1.1.4'
	];

	$vh_tools_run   = 0;
	$vh_state       = 0;
	$vh_tools_ninst = 0;
	$vh_tools_run   = 0;
	$vh_tools_nrun  = 0;

	$array  = ss_esxi_vhosts_walk($host, $oids['vh_state']);
	$vhosts = cacti_count($array);

	if (cacti_sizeof($array)) {
		foreach ($array as $key => $value) {
			if (cacti_strtolower(trim($value['value'])) == 'powered on' || cacti_strtolower(trim($value['value'])) == 'poweredon') {
				$vh_state++;
			}
		}
	}

	$array  = ss_esxi_vhosts_walk($host, $oids['vh_tools']);

	if (cacti_sizeof($array)) {
		foreach ($array as $key => $value) {
			if (str_contains($value['value'], 'not installed')) {
				$vh_tools_ninst++;
			} elseif (str_contains($value['value'], 'not running')) {
				$vh_tools_nrun++;
			} else {
				$vh_tools_run++;
			}
		}
	}

	return ("vh:$vhosts vh_state:$vh_state tools_run:$vh_tools_run tools_nrun:$vh_tools_nrun tools_ninst:$vh_tools_ninst\n");
}

/**
 * ss_esxi_vhosts_walk - walk an OID using the device's own SNMP settings
 *
 * @param array  $host The host row
 * @param string $oid  The OID to walk
 *
 * @return array The walk result
 */
function ss_esxi_vhosts_walk(array $host, string $oid) : array {
	return cacti_snmp_walk($host['hostname'], $host['snmp_community'], $oid, $host['snmp_version'],
		$host['snmp_username'], $host['snmp_password'], $host['snmp_auth_protocol'],
		$host['snmp_priv_passphrase'], $host['snmp_priv_protocol'], $host['snmp_context'],
		$host['snmp_port'], $host['snmp_timeout'], $host['snmp_retries'], $host['max_oids'],
		SNMP_POLLER, $host['snmp_engine_id']);
}
