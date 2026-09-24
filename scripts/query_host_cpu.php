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
	} else {
		// Index absent — return U so RRDtool records an unknown sample.
		print 'U';
	}
}

/**
 * Retrieves the CPU usage. Used as part of Cacti's scripts functionality.
 *
 * @param string $hostname The hostname.
 * @param string $snmp_community The SNMP community.
 * @param int $snmp_version The SNMP version.
 * @param string $snmp_auth_username The SNMP auth username.
 * @param string $snmp_auth_password The SNMP auth password.
 * @param string $snmp_auth_protocol The SNMP auth protocol.
 * @param string $snmp_priv_passphrase The SNMP priv passphrase.
 * @param string $snmp_priv_protocol The SNMP priv protocol.
 * @param string $snmp_context The SNMP context.
 * @param int $snmp_port The SNMP port.
 * @param int $snmp_timeout The SNMP timeout.
 * @param mixed $ping_retries The ping retries.
 * @param int $max_oids The max OIDS.
 *
 * @return array An array of results.
 */
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

/**
 * Retrieves the indexes. Used as part of Cacti's scripts functionality.
 *
 * @param string $hostname The hostname.
 * @param string $snmp_community The SNMP community.
 * @param int $snmp_version The SNMP version.
 * @param string $snmp_auth_username The SNMP auth username.
 * @param string $snmp_auth_password The SNMP auth password.
 * @param string $snmp_auth_protocol The SNMP auth protocol.
 * @param string $snmp_priv_passphrase The SNMP priv passphrase.
 * @param string $snmp_priv_protocol The SNMP priv protocol.
 * @param string $snmp_context The SNMP context.
 * @param int $snmp_port The SNMP port.
 * @param int $snmp_timeout The SNMP timeout.
 * @param mixed $ping_retries The ping retries.
 * @param int $max_oids The max OIDS.
 *
 * @return array An array of results.
 */
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

/**
 * Handles the reindex. Used as part of Cacti's scripts functionality.
 *
 * @param array $arr The arr.
 *
 * @return array An array of results.
 */
function reindex($arr) {
	$return_arr = array();

	for ($i=0;($i<cacti_sizeof($arr));$i++) {
		$return_arr[$i] = $arr[$i]['value'];
	}

	return $return_arr;
}

