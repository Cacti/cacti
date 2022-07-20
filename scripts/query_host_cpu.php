<?php

error_reporting(0);

include(dirname(__FILE__) . '/../include/cli_check.php');
include(dirname(__FILE__) . '/../lib/snmp.php');

$oids = array(
	'index' => '.1.3.6.1.2.1.25.3.3.1',
	'usage' => '.1.3.6.1.2.1.25.3.3.1'
);

$hostname 	= $_SERVER['argv'][1];
$host_id 	= $_SERVER['argv'][2];
$snmp_auth 	= $_SERVER['argv'][3];
$cmd 		= $_SERVER['argv'][4];

/* support for SNMP V2 and SNMP V3 parameters */
$snmp = explode(':', $snmp_auth);
$snmp_version 	= $snmp[0];
$snmp_port    	= $snmp[1];
$snmp_timeout 	= $snmp[2];
$ping_retries 	= $snmp[3];
$max_oids		= $snmp[4];

$snmp_auth_username   	= '';
$snmp_auth_password   	= '';
$snmp_auth_protocol  	= '';
$snmp_priv_passphrase 	= '';
$snmp_priv_protocol   	= '';
$snmp_context         	= '';
$snmp_community 		= '';

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

/*
 * process INDEX requests
 */
if ($cmd == 'index') {
	$arr_index = get_indexes($hostname, $snmp_community, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER);

	for ($i=0;($i<cacti_sizeof($arr_index));$i++) {
		print $arr_index[$i] . PHP_EOL;
	}

/*
 * process NUM_INDEXES requests
 */
} elseif ($cmd == 'num_indexes') {
	$arr_index = get_indexes($hostname, $snmp_community, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER);

	print cacti_sizeof($arr_index) . PHP_EOL;

/*
 * process QUERY requests
 */
} elseif ($cmd == 'query') {
	$arg = $_SERVER['argv'][5];

	$arr_index = get_indexes($hostname, $snmp_community, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids);
	$arr = get_cpu_usage($hostname, $snmp_community, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids);

	for ($i=0;($i<cacti_sizeof($arr_index));$i++) {
		if ($arg == 'usage') {
			print $arr_index[$i] . '!' . $arr[$i] . PHP_EOL;
		} elseif ($arg == 'index') {
			print $arr_index[$i] . '!' . $arr_index[$i] . PHP_EOL;
		}
	}
} elseif ($cmd == 'get') {
	$arg = $_SERVER['argv'][5];
	$index = $_SERVER['argv'][6];

	$arr_index = get_indexes($hostname, $snmp_community, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids);
	$arr = get_cpu_usage($hostname, $snmp_community, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids);

	if (isset($arr_index[$index])) {
		print $arr[$index];
	}
}

function get_cpu_usage($hostname, $snmp_community, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids) {
	$arr = reindex(cacti_snmp_walk($hostname, $snmp_community, '.1.3.6.1.2.1.25.3.3.1', $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER));
	$return_arr = array();

	$j = 0;

	for ($i=0;($i<cacti_sizeof($arr));$i++) {
		if (preg_match('/^[0-9]+$/', $arr[$i])) {
			$return_arr[$j] = $arr[$i];
			$j++;
		}
	}

	return $return_arr;
}

function get_indexes($hostname, $snmp_community, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids) {
	$arr = reindex(cacti_snmp_walk($hostname, $snmp_community, '.1.3.6.1.2.1.25.3.3.1', $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER));
	$return_arr = array();

	$j = 0;

	for ($i=0;($i<cacti_sizeof($arr));$i++) {
		if (preg_match('/^[0-9]+$/', $arr[$i])) {
			$return_arr[$j] = $j;
			$j++;
		}
	}

	return $return_arr;
}

function reindex($arr) {
	$return_arr = array();

	for ($i=0;($i<cacti_sizeof($arr));$i++) {
		$return_arr[$i] = $arr[$i]['value'];
	}

	return $return_arr;
}

