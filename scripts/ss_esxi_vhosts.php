<?php

include_once(__DIR__ . '/../include/cli_check.php');
include_once(__DIR__ . '/../lib/snmp.php');

if (!isset($called_by_script_server)) {
	array_shift($_SERVER['argv']);
	print call_user_func_array('ss_esxi_vhosts', $_SERVER['argv']);
}

function ss_esxi_vhosts($device_id) {
	$host = db_fetch_row_prepared('SELECT *
		FROM host
		WHERE id = ?',
		[$device_id]);

	if (!cacti_sizeof($host)) {
		return ("vh:0 vh_state:0 tools_run:0 tools_nrun:0 tools_ninst:0\n");
	}

	$hostname  = $host['hostname'];
	$community = $host['snmp_community'];
	$port      = $host['snmp_port'];
	$timeout   = $host['snmp_timeout'];

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

	$array  = cacti_snmp_walk($hostname, $community, $oids['vh_state'], 1, '', '', '', '', '', '', $port, $timeout, 2, 20, SNMP_POLLER);
	$vhosts = cacti_count($array);

	$array  = cacti_snmp_walk($hostname, $community, $oids['vh_state'], 1, '','', '','', '', '', $port, $timeout, 2, 20, SNMP_POLLER);

	if (cacti_sizeof($array)) {
		foreach ($array as $key => $value) {
			if (strtolower(trim($value['value'])) == 'powered on' || strtolower(trim($value['value'])) == 'poweredon') {
				$vh_state++;
			}
		}
	}

	$array  = cacti_snmp_walk($hostname, $community, $oids['vh_tools'], 1, '', '', '', '', '', '', $port, $timeout, 2, 20, SNMP_POLLER);

	if (cacti_sizeof($array)) {
		foreach ($array as $key => $value) {
			if (strpos($value['value'], 'not installed')) {
				$vh_tools_ninst++;
			} elseif (strpos($value['value'], 'not running') !== false) {
				$vh_tools_nrun++;
			} else {
				$vh_tools_run++;
			}
		}
	}

	return ("vh:$vhosts vh_state:$vh_state tools_run:$vh_tools_run tools_nrun:$vh_tools_nrun tools_ninst:$vh_tools_ninst\n");
}
