<?php

error_reporting(0);

// include some cacti files for ease of use
include(__DIR__ . '/../include/global.php');
include(__DIR__ . '/../lib/snmp.php');

// define all OIDs we need for further processing
$oids = [
	'index'  => '.1.3.6.1.4.1.14823.2.3.3.1.1.7.1.1',
	'wlan'   => '.1.3.6.1.4.1.14823.2.3.3.1.1.7.1.2',
	'count'  => '.1.3.6.1.4.1.14823.2.3.3.1.1.7.1.4',
];

$xml_delimiter = '!';

if (count($_SERVER['argv']) < 2) {
	print 'Invalid use of script query, required parameters:' . PHP_EOL;
	print '    <host_id> <cmd> [query_field]' . PHP_EOL;
	die();
}

$host_id     = $_SERVER['argv'][1];
$cmd         = $_SERVER['argv'][2];
$query_index = 0;

if ($_SERVER['argv'][3]) {
	$query_field = $_SERVER['argv'][3];
}

if ($_SERVER['argv'][4]) {
	$query_index = $_SERVER['argv'][4];
	$query_index--;
}

if (empty($host_id) || $host_id === null || !is_numeric($host_id)) {
	return '0';
}

$host = db_fetch_row_prepared('SELECT *
	FROM host
	WHERE id = ?',
	[$host_id]);

if (!cacti_sizeof($host)) {
	print '';
	exit;
}

// -------------------------------------------------------------------------
// main code starts here
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// script MUST respond to index queries
//       the command for this is defined within the XML file as
//       <arg_index>index</arg_index>
//       you may replace the string "index" both in the XML and here
// -------------------------------------------------------------------------
//       php -q <script> <parms> index
// will list all indices of the target values
// e.g. in case of interfaces
//      it has to respond with the list of interface indices
// -------------------------------------------------------------------------
if ($cmd == 'index') {
	$return_arr  = cacti_snmp_walk($host['hostname'],
		$host['snmp_community'],
		$oids['index'],
		$host['snmp_version'],
		$host['snmp_username'],
		$host['snmp_password'],
		$host['snmp_auth_protocol'],
		$host['snmp_priv_passphrase'],
		$host['snmp_priv_protocol'],
		$host['snmp_context'],
		$host['snmp_port'],
		$host['snmp_timeout'],
		$host['ping_retries'],
		SNMP_POLLER,
		$host['snmp_engine_id']);

	foreach ($return_arr as $key=>$value) {
		print ($key + 1) . PHP_EOL;
	}
} elseif ($cmd == 'query' && isset($query_field)) {
	if ($query_field == 'name') {
		$return_arr  = reindex(cacti_snmp_walk($host['hostname'],
			$host['snmp_community'],
			$oids['wlan'],
			$host['snmp_version'],
			$host['snmp_username'],
			$host['snmp_password'],
			$host['snmp_auth_protocol'],
			$host['snmp_priv_passphrase'],
			$host['snmp_priv_protocol'],
			$host['snmp_context'],
			$host['snmp_port'],
			$host['snmp_timeout'],
			$host['ping_retries'],
			SNMP_POLLER,
			$host['snmp_engine_id']));

		// index begins with 0, need increment
		foreach ($return_arr as $key=>$value) {
			print ($key + 1) . $xml_delimiter . $value . PHP_EOL;
		}
	} elseif ($query_field == 'count') {
		$new = [];

		$return_arr  = reindex(cacti_snmp_walk($host['hostname'],
			$host['snmp_community'],
			$oids['count'],
			$host['snmp_version'],
			$host['snmp_username'],
			$host['snmp_password'],
			$host['snmp_auth_protocol'],
			$host['snmp_priv_passphrase'],
			$host['snmp_priv_protocol'],
			$host['snmp_context'],
			$host['snmp_port'],
			$host['snmp_timeout'],
			$host['ping_retries'],
			SNMP_POLLER,
			$host['snmp_engine_id']));

		foreach ($return_arr as $key=>$value) {
			$new[$key + 1] = $value;
		}

		foreach ($new as $key=>$value) {
			print $key . $xml_delimiter . $value . PHP_EOL;
		}

		// output "1!5\n2!60\n3!13\n";
	} elseif ($query_field == 'index') {
		$return_arr  = reindex(cacti_snmp_walk($host['hostname'],
			$host['snmp_community'],
			$oids['count'],
			$host['snmp_version'],
			$host['snmp_username'],
			$host['snmp_password'],
			$host['snmp_auth_protocol'],
			$host['snmp_priv_passphrase'],
			$host['snmp_priv_protocol'],
			$host['snmp_context'],
			$host['snmp_port'],
			$host['snmp_timeout'],
			$host['ping_retries'],
			SNMP_POLLER,
			$host['snmp_engine_id']));

		foreach ($return_arr as $key=>$value) {
			print ($key + 1) . $xml_delimiter . ($key + 1) . PHP_EOL;
		}

		//	output  "1!1\n2!2\n3!3\n";
	}
} elseif ($cmd == 'get' && isset($query_field) && is_numeric($query_index)) {
	if ($query_field == 'name') {
		$pom = cacti_snmp_get($host['hostname'],
			$host['snmp_community'],
			$oids['wlan'] . '.' . $query_index,
			$host['snmp_version'],
			$host['snmp_username'],
			$host['snmp_password'],
			$host['snmp_auth_protocol'],
			$host['snmp_priv_passphrase'],
			$host['snmp_priv_protocol'],
			$host['snmp_context'],
			$host['snmp_port'],
			$host['snmp_timeout'],
			$host['ping_retries'],
			SNMP_POLLER,
			$host['snmp_engine_id']);

		print $pom;
	} elseif ($query_field == 'count') {
		$pom = cacti_snmp_get($host['hostname'],
			$host['snmp_community'],
			$oids['count'] . '.' . $query_index,
			$host['snmp_version'],
			$host['snmp_username'],
			$host['snmp_password'],
			$host['snmp_auth_protocol'],
			$host['snmp_priv_passphrase'],
			$host['snmp_priv_protocol'],
			$host['snmp_context'],
			$host['snmp_port'],
			$host['snmp_timeout'],
			$host['ping_retries'],
			SNMP_POLLER,
			$host['snmp_engine_id']);

		print $pom;
	}
}

function reindex($arr) {
	$return_arr = [];

	for ($i = 0; ($i < sizeof($arr)); $i++) {
		$return_arr[$i] = $arr[$i]['value'];
	}

	return $return_arr;
}
