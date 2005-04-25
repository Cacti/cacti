<?php
$no_http_headers = true;

/* display No errors */
error_reporting(E_ERROR);

include_once(dirname(__FILE__) . "/../lib/snmp.php");

if (!isset($called_by_script_server)) {
	include_once(dirname(__FILE__) . "/../include/config.php");

	array_shift($_SERVER["argv"]);
	print call_user_func_array("ss_host_cpu", $_SERVER["argv"]);
}

function ss_host_cpu($hostname, $snmp_community, $snmp_version, $snmp_port = 161, $snmp_timeout = 500, $cmd, $arg1 = "", $arg2 = "") {
	$oids = array(
		"index" => ".1.3.6.1.2.1.25.3.3.1",
		"usage" => ".1.3.6.1.2.1.25.3.3.1"
		);

	if (($cmd == "index") && (func_num_args() == "4")) {
		$arr_index = ss_host_cpu_get_indexes($hostname, $snmp_community, $snmp_version, $snmp_port, $snmp_timeout);

		for ($i=0;($i<sizeof($arr_index));$i++) {
			print $arr_index[$i] . "\n";
		}
	} elseif ($cmd == "query") {
		$arg = $arg1;

		$arr_index = ss_host_cpu_get_indexes($hostname, $snmp_community, $snmp_version, $snmp_port, $snmp_timeout);
		$arr = ss_host_cpu_get_cpu_usage($hostname, $snmp_community, $snmp_version, $snmp_port, $snmp_timeout);

		for ($i=0;($i<sizeof($arr_index));$i++) {
			if ($arg == "usage") {
				print $arr_index[$i] . "!" . $arr[$i] . "\n";
			}elseif ($arg == "index") {
				print $arr_index[$i] . "!" . $arr_index[$i] . "\n";
			}
		}
	} elseif ($cmd == "get") {
		$arg = $arg1;
		$index = rtrim($arg2);
		$arr_index = ss_host_cpu_get_indexes($hostname, $snmp_community, $snmp_version, $snmp_port, $snmp_timeout);
		$arr = ss_host_cpu_get_cpu_usage($hostname, $snmp_community, $snmp_version, $snmp_port, $snmp_timeout);
		if (isset($arr_index[$index])) {
			return $arr[$index];
		} else {
			return "ERROR: Invalid Return Value";
		}
	}
}

function ss_host_cpu_get_cpu_usage($hostname, $snmp_community, $snmp_version, $snmp_port, $snmp_timeout) {
	$arr = ss_host_cpu_reindex(cacti_snmp_walk($hostname, $snmp_community, ".1.3.6.1.2.1.25.3.3.1", $snmp_version, "", "", $snmp_port, $snmp_timeout, SNMP_POLLER));
	$return_arr = array();

	$j = 0;

	for ($i=0;($i<sizeof($arr));$i++) {
		if (ereg("^[0-9]+$", $arr[$i])) {
			$return_arr[$j] = $arr[$i];
			$j++;
		}
	}

	return $return_arr;
}

function ss_host_cpu_get_indexes($hostname, $snmp_community, $snmp_version, $snmp_port, $snmp_timeout) {
	$arr = ss_host_cpu_reindex(cacti_snmp_walk($hostname, $snmp_community, ".1.3.6.1.2.1.25.3.3.1", $snmp_version, "", "", $snmp_port, $snmp_timeout, SNMP_POLLER));
	$return_arr = array();

	$j = 0;

	for ($i=0;($i<sizeof($arr));$i++) {
		if (ereg("^[0-9]+$", $arr[$i])) {
			$return_arr[$j] = $j;
			$j++;
		}
	}

	return $return_arr;
}

function ss_host_cpu_reindex($arr) {
	$return_arr = array();
	for ($i=0;($i<sizeof($arr));$i++) {
		$return_arr[$i] = $arr[$i]["value"];
	}
	return $return_arr;
}

?>