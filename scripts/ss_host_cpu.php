<?php

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

global $config;

$no_http_headers = true;

/* display No errors */
error_reporting(0);

if (!isset($called_by_script_server)) {
	include_once(dirname(__FILE__) . '/../include/global.php');
	include_once(dirname(__FILE__) . '/../lib/snmp.php');

	array_shift($_SERVER['argv']);

	print call_user_func_array('ss_host_cpu', $_SERVER['argv']);
}else{
	include_once($config['library_path'] . '/snmp.php');
}

function ss_host_cpu($hostname, $host_id, $snmp_auth, $cmd, $arg1 = '', $arg2 = '') {
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

	$oids = array(
		'index' => '.1.3.6.1.2.1.25.3.3.1.2',
		'usage' => '.1.3.6.1.2.1.25.3.3.1.2'
		);

	if (($cmd == 'index')) {
		$value = api_plugin_hook_function('hmib_get_cpu_indexes', array('host_id' => $host_id));

		if (is_array($value)) {
			$arr_index = ss_host_cpu_get_indexes($hostname, $snmp_community, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids);

			foreach($arr_index as $value) {
				print $value . "\n";
			}
		} else {
			$indexes = explode(',', $value);
			foreach($indexes as $index) {
				print $index . "\n";
			}
		}
	} elseif (($cmd == 'num_indexes')) {
		$value = api_plugin_hook_function('hmib_get_cpu_indexes', array('host_id' => $host_id));

		if (is_array($value)) {
			$arr_index = ss_host_cpu_get_indexes($hostname, $snmp_community, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids);

			return sizeof($arr_index);
		} else {
			$indexes = explode(',', $value);
			return sizeof($indexes);
		}
	} elseif ($cmd == 'query') {
		$value = api_plugin_hook_function('hmib_get_cpu_indexes', array('host_id' => $host_id));

		if (is_array($value)) {
			$arg = $arg1;

			$arr_index = ss_host_cpu_get_indexes($hostname, $snmp_community, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids);
			$arr = ss_host_cpu_get_cpu_usage($hostname, $snmp_community, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids);

			foreach ($arr_index as $index => $value) {
				if ($arg == 'usage') {
					print $index . '!' . $arr[$index] . "\n";
				} elseif ($arg == 'index') {
					print $index . '!' . $value . "\n";
				}
			}
		} else {
			$indexes = explode(',', $value);
			foreach($indexes as $index) {
				print $index . '!' . $index . "\n";
			}
		}
	} elseif ($cmd == 'get') {
		$arg = $arg1;
		$index = rtrim($arg2);

		$value = api_plugin_hook_function('hmib_get_cpu', array('host_id' => $host_id, 'arg' => $arg, 'index' => $index));

		if (is_array($value)) {
			$arr_index = ss_host_cpu_get_indexes($hostname, $snmp_community, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids);
			$arr = ss_host_cpu_get_cpu_usage($hostname, $snmp_community, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids);
			if (isset($arr_index[$index]) && isset($arr[$index])) {
				return $arr[$index];
			} else {
				cacti_log('ERROR: Invalid Return Value in ss_host_cpu.php for get ' . $index . ' and host_id ' . $host_id, false);

				return 'U';
			}
		} else {
			return $value;
		}
	}
}

function ss_host_cpu_get_cpu_usage($hostname, $snmp_community, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids) {
	$arr = ss_host_cpu_reindex(cacti_snmp_walk($hostname, $snmp_community, '.1.3.6.1.2.1.25.3.3.1.2', $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER));
	$return_arr = array();

	$sum = 0;

	foreach($arr as $index => $value) {
		if (is_numeric($value)) {
			$return_arr[$index] = $value;
			$sum += $value;
		}
	}

	if (sizeof($return_arr)) {
		$return_arr[4000] = round($sum / sizeof($return_arr));
	} else {
		$return_arr[4000] = 0;
	}

	return $return_arr;
}

function ss_host_cpu_get_indexes($hostname, $snmp_community, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids) {
	$arr = ss_host_cpu_reindex(cacti_snmp_walk($hostname, $snmp_community, '.1.3.6.1.2.1.25.3.3.1.2', $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER));
	$return_arr = array();

	foreach($arr as $index => $value) {
		if (is_numeric($value)) {
			$return_arr[$index] = $index;
		}
	}

	$return_arr[4000] = 'Total';

	return $return_arr;
}

function ss_host_cpu_reindex($arr) {
	$return_arr = array();

	foreach($arr as $index => $value) {
		$return_arr[$index] = $value['value'];
	}

	return $return_arr;
}

