<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2011 The Cacti Group                                 |
 | Portions Copyright (C) 2010 Boris Lytochkin, Sponsored by Yandex LLC    |
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

define('REGEXP_SNMP_TRIM', '/(hex|counter(32|64)|gauge|gauge(32|64)|float|ipaddress|string|integer):|(up|down)\(|\)$/i');

define('SNMP_METHOD_PHP', 1);
define('SNMP_METHOD_BINARY', 2);

if (!defined('SNMP_STRING_OUTPUT_GUESS')) {
	define('SNMP_STRING_OUTPUT_GUESS', 1);
}

if (!defined('SNMP_STRING_OUTPUT_ASCII')) {
	define('SNMP_STRING_OUTPUT_ASCII', 2);
}

if (!defined('SNMP_STRING_OUTPUT_HEX')) {
	define('SNMP_STRING_OUTPUT_HEX', 3);
}

global $banned_snmp_strings;
$banned_snmp_strings = array('End of MIB', 'No Such', 'No more');

if (!class_exists('SNMP')) {
	include_once($config['include_path'] . '/phpsnmp/classSNMP.php');
}

function cacti_snmp_session($hostname, $community, $version, $username, $password,
	$auth_proto, $priv_pass, $priv_proto, $context, $engineid,
	$port = 161, $timeout = 500, $retries = 0, $max_oids = 10) {

	switch ($version) {
		case '1':
			$version = SNMP::VERSION_1;
			break;
		case '2':
			$version = SNMP::VERSION_2c;
			break;
		case '3':
			$version = SNMP::VERSION_3;
			break;
	}

	try {
		$session = new SNMP($version, $hostname . ':' . $port, ($version == 3 ? $username : $community), $timeout * 1000, $retries);
	} catch (Exception $e) {
		return false;
	}

	if (defined('SNMP_OID_OUTPUT_NUMERIC')) {
		$session->oid_output_format = SNMP_OID_OUTPUT_NUMERIC;
		$session->valueretrieval = SNMP_VALUE_LIBRARY;
	}

	$session->max_oids = $max_oids;

	if (read_config_option('oid_increasing_check_disable') == 'on') {
		$session->oid_increasing_check = false;
	}

	if ($version != SNMP::VERSION_3) {
		return $session;
	}

	if ($priv_proto == '[None]') {
		$sec_level = 'authNoPriv';
		$priv_proto = '';
	} else {
		$sec_level = 'authPriv';
	}

	try {
		$session->setSecurity($sec_level, $auth_proto, $password, $priv_proto, $priv_pass, $context, $engineid);
	} catch (Exception $e) {
		return false;
	}

	return $session;
}

function cacti_snmp_get($hostname, $community, $oid, $version, $username, $password,
	$auth_proto, $priv_pass, $priv_proto, $context,
	$port = 161, $timeout = 500, $retries = 0, $environ = SNMP_POLLER,
	$engineid = '', $value_output_format = SNMP_STRING_OUTPUT_GUESS) {

	global $config, $snmp_error;

	$max_oids = 1;

	if (!cacti_snmp_options_sanitize($version, $community, $port, $timeout, $retries, $max_oids)) {
		return 'U';
	}

	if (snmp_get_method('get', $version, $context, $engineid, $value_output_format) == SNMP_METHOD_PHP) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */
		snmp_set_quick_print(0);

		if (function_exists('snmp_set_enum_print')) {
			snmp_set_enum_print(true);
		}

		if ($version == '1') {
			$snmp_value = snmpget($hostname . ':' . $port, $community, $oid, ($timeout * 1000), $retries);
		} elseif ($version == '2') {
			$snmp_value = snmp2_get($hostname . ':' . $port, $community, $oid, ($timeout * 1000), $retries);
		} else {
			if ($priv_proto == '[None]' || $priv_pass == '') {
				$sec_level = 'authNoPriv';
				$priv_proto = '';
			} else {
				$sec_level = 'authPriv';
			}

			$snmp_value = snmp3_get($hostname . ':' . $port, $username, $sec_level, $auth_proto, $password, $priv_proto, $priv_pass, $oid, ($timeout * 1000), $retries);
		}

		if ($snmp_value === false) {
			cacti_log("WARNING: SNMP Error:'$snmp_error', Device:'$hostname', OID:'$oid'", false);
			$snmp_value = 'U';
		} else {
			$snmp_value = format_snmp_string($snmp_value, false, $value_output_format);
		}
	} else {
		$snmp_value = '';

		/* net snmp want the timeout in seconds */
		$timeout = ceil($timeout / 1000);

		if ($version == '1') {
			$snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
		} elseif ($version == '2') {
			$snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
			$version = '2c'; /* ucd/net snmp prefers this over '2' */
		} elseif ($version == '3') {
			if ($priv_proto == '[None]' || $priv_pass == '') {
				$sec_level = 'authNoPriv';
				$priv_proto = '';
			} else {
				$sec_level = 'authPriv';
			}

			if ($priv_pass != '') {
				$priv_pass = '-X ' . snmp_escape_string($priv_pass) . ' -x ' . snmp_escape_string($priv_proto);
			} else {
				$priv_pass = '';
			}

			if ($context != '') {
				$context = '-n ' . snmp_escape_string($context);
			} else {
				$context = '';
			}

			if ($engineid != '') {
				$engineid = '-e ' . snmp_escape_string($engineid);
			} else {
				$engineid = '';
			}

			$snmp_auth = trim('-u ' . snmp_escape_string($username) .
				' -l ' . snmp_escape_string($sec_level) .
				' -a ' . snmp_escape_string($auth_proto) .
				' -A ' . snmp_escape_string($password) .
				' '    . $priv_pass .
				' '    . $context .
				' '    . $engineid);
		}

		/* no valid snmp version has been set, get out */
		if (empty($snmp_auth)) { return; }

		exec(cacti_escapeshellcmd(read_config_option('path_snmpget')) .
			' -O fntevU' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ':' ') . $snmp_auth .
			' -v ' . $version .
			' -t ' . $timeout .
			' -r ' . $retries .
			' '    . cacti_escapeshellarg($hostname) . ':' . $port .
			' '    . cacti_escapeshellarg($oid), $snmp_value);

		/* fix for multi-line snmp output */
		if (is_array($snmp_value)) {
			$snmp_value = implode(' ', $snmp_value);
		}

		if (substr_count($snmp_value, 'Timeout:')) {
			cacti_log("WARNING: SNMP Error:'Timeout', Device:'$hostname', OID:'$oid'", false);
			$snmp_value = 'U';
		} else {
			$snmp_value = format_snmp_string($snmp_value, false, $value_output_format);
		}
	}

	return $snmp_value;
}

function cacti_snmp_get_raw($hostname, $community, $oid, $version, $username, $password,
	$auth_proto, $priv_pass, $priv_proto, $context,
	$port = 161, $timeout = 500, $retries = 0, $environ = SNMP_POLLER,
	$engineid = '', $value_output_format = SNMP_STRING_OUTPUT_GUESS) {

	global $config, $snmp_error;

	$max_oids = 1;

	if (!cacti_snmp_options_sanitize($version, $community, $port, $timeout, $retries, $max_oids)) {
		return 'U';
	}

	if (snmp_get_method('get', $version, $context, $engineid, $value_output_format) == SNMP_METHOD_PHP) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */
		snmp_set_quick_print(0);

		if (function_exists('snmp_set_enum_print')) {
			snmp_set_enum_print(true);
		}

		if ($version == '1') {
			$snmp_value = snmpget($hostname . ':' . $port, $community, $oid, ($timeout * 1000), $retries);
		} elseif ($version == '2') {
			$snmp_value = snmp2_get($hostname . ':' . $port, $community, $oid, ($timeout * 1000), $retries);
		} else {
			if ($priv_proto == '[None]' || $priv_pass == '') {
				$sec_level = 'authNoPriv';
				$priv_proto = '';
			} else {
				$sec_level = 'authPriv';
			}

			$snmp_value = snmp3_get($hostname . ':' . $port, $username, $sec_level, $auth_proto, $password, $priv_proto, $priv_pass, $oid, ($timeout * 1000), $retries);
		}

		if ($snmp_value === false) {
			cacti_log("WARNING: SNMP Error:'$snmp_error', Device:'$hostname', OID:'$oid'", false);
			$snmp_value = 'U';
		}
	} else {
		$snmp_value = '';

		/* net snmp want the timeout in seconds */
		$timeout = ceil($timeout / 1000);

		if ($version == '1') {
			$snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
		} elseif ($version == '2') {
			$snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
			$version = '2c'; /* ucd/net snmp prefers this over '2' */
		} elseif ($version == '3') {
			if ($priv_proto == '[None]' || $priv_pass == '') {
				$sec_level = 'authNoPriv';
				$priv_proto = '';
			} else {
				$sec_level = 'authPriv';
			}

			if ($priv_pass != '') {
				$priv_pass = '-X ' . snmp_escape_string($priv_pass) . ' -x ' . snmp_escape_string($priv_proto);
			} else {
				$priv_pass = '';
			}

			if ($context != '') {
				$context = '-n ' . snmp_escape_string($context);
			} else {
				$context = '';
			}

			if ($engineid != '') {
				$engineid = '-e ' . snmp_escape_string($engineid);
			} else {
				$engineid = '';
			}

			$snmp_auth = trim('-u ' . snmp_escape_string($username) .
				' -l ' . snmp_escape_string($sec_level) .
				' -a ' . snmp_escape_string($auth_proto) .
				' -A ' . snmp_escape_string($password) .
				' '    . $priv_pass .
				' '    . $context .
				' '    . $engineid);
		}

		/* no valid snmp version has been set, get out */
		if (empty($snmp_auth)) { return; }

		exec(cacti_escapeshellcmd(read_config_option('path_snmpget')) .
			' -O fntev' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ':' ') . $snmp_auth .
			' -v ' . $version .
			' -t ' . $timeout .
			' -r ' . $retries .
			' '    . cacti_escapeshellarg($hostname) . ':' . $port .
			' '    . cacti_escapeshellarg($oid), $snmp_value);

		/* fix for multi-line snmp output */
		if (is_array($snmp_value)) {
			$snmp_value = implode(' ', $snmp_value);
		}

		if (substr_count($snmp_value, 'Timeout:')) {
			cacti_log("WARNING: SNMP Error:'Timeout', Device:'$hostname', OID:'$oid'", false);
			$snmp_value = 'U';
		}
	}

	return $snmp_value;
}

function cacti_snmp_getnext($hostname, $community, $oid, $version, $username, $password,
	$auth_proto, $priv_pass, $priv_proto, $context,
	$port = 161, $timeout = 500, $retries = 0, $environ = SNMP_POLLER,
	$engineid = '', $value_output_format = SNMP_STRING_OUTPUT_GUESS) {

	global $config, $snmp_error;

	$max_oids = 1;

	if (!cacti_snmp_options_sanitize($version, $community, $port, $timeout, $retries, $max_oids)) {
		return 'U';
	}

	if (snmp_get_method('getnext', $version, $context, $engineid, $value_output_format) == SNMP_METHOD_PHP) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */
		snmp_set_quick_print(0);

		if ($version == '1') {
			$snmp_value = snmpgetnext($hostname . ':' . $port, $community, $oid, ($timeout * 1000), $retries);
		} elseif ($version == '2') {
			$snmp_value = snmp2_getnext($hostname . ':' . $port, $community, $oid, ($timeout * 1000), $retries);
		} else {
			if ($priv_proto == '[None]' || $priv_pass == '') {
				$sec_level = 'authNoPriv';
				$priv_proto = '';
			} else {
				$sec_level = 'authPriv';
			}

			$snmp_value = snmp3_getnext($hostname . ':' . $port, $username, $sec_level, $auth_proto, $password, $priv_proto, $priv_pass, $oid, ($timeout * 1000), $retries);
		}

		if ($snmp_value === false) {
			cacti_log("WARNING: SNMP Error:'$snmp_error', Device:'$hostname', OID:'$oid'", false);
			$snmp_value = 'U';
		} else {
			$snmp_value = format_snmp_string($snmp_value, false, $value_output_format);
		}
	} else {
		$snmp_value = '';

		/* net snmp want the timeout in seconds */
		$timeout = ceil($timeout / 1000);

		if ($version == '1') {
			$snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
		} elseif ($version == '2') {
			$snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
			$version = '2c'; /* ucd/net snmp prefers this over '2' */
		} elseif ($version == '3') {
			if ($priv_proto == '[None]' || $priv_pass == '') {
				$sec_level = 'authNoPriv';
				$priv_proto = '';
			} else {
				$sec_level = 'authPriv';
			}

			if ($priv_pass != '') {
				$priv_pass = '-X ' . snmp_escape_string($priv_pass) . ' -x ' . snmp_escape_string($priv_proto);
			} else {
				$priv_pass = '';
			}

			if ($context != '') {
				$context = '-n ' . snmp_escape_string($context);
			} else {
				$context = '';
			}

			if ($engineid != '') {
				$engineid = '-e ' . snmp_escape_string($engineid);
			} else {
				$engineid = '';
			}

			$snmp_auth = trim('-u ' . snmp_escape_string($username) .
				' -l ' . snmp_escape_string($sec_level) .
				' -a ' . snmp_escape_string($auth_proto) .
				' -A ' . snmp_escape_string($password) .
				' '    . $priv_pass .
				' '    . $context .
				' '    . $engineid);
		}

		/* no valid snmp version has been set, get out */
		if (empty($snmp_auth)) {
			return;
		}

		exec(cacti_escapeshellcmd(read_config_option('path_snmpgetnext')) .
			' -O fntevU' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ':' ') . $snmp_auth .
			' -v ' . $version .
			' -t ' . $timeout .
			' -r ' . $retries .
			' '    . cacti_escapeshellarg($hostname) . ':' . $port .
			' '    . cacti_escapeshellarg($oid), $snmp_value);

		/* fix for multi-line snmp output */
		if (is_array($snmp_value)) {
			$snmp_value = implode(' ', $snmp_value);
		}

		if (substr_count($snmp_value, 'Timeout:')) {
			cacti_log("WARNING: SNMP Error:'Timeout', Device:'$hostname', OID:'$oid'", false);
		}

		/* strip out non-snmp data */
		$snmp_value = format_snmp_string($snmp_value, false, $value_output_format);
	}

	return $snmp_value;
}

function cacti_snmp_session_walk($session, $oid, $dummy = false, $max_repetitions = NULL,
	$non_repeaters = NULL, $value_output_format = SNMP_STRING_OUTPUT_GUESS) {

	$info = $session->info;
	if (is_array($oid) && sizeof($oid) == 0) {
		cacti_log('Empty OID!', false);
		return array();
	}

	$session->value_output_format = $value_output_format;

	if ($non_repeaters === NULL)
		$non_repeaters = 0;
	if ($max_repetitions === NULL)
		$max_repetitions = $session->max_oids;

	if ($max_repetitions <= 0)
		$max_repetitions = 10;

	try {
		$out = $session->walk($oid, false, $max_repetitions, $non_repeaters);
	} catch (Exception $e) {
		$out = false;
	}

	if ($out === false) {
		if ($oid == '.1.3.6.1.2.1.47.1.1.1.1.2' ||
			$oid == '.1.3.6.1.4.1.9.9.68.1.2.2.1.2' ||
			$oid == '.1.3.6.1.4.1.9.9.46.1.6.1.1.5' ||
			$oid == '.1.3.6.1.4.1.9.9.46.1.6.1.1.14' ||
			$oid == '.1.3.6.1.4.1.9.9.23.1.2.1.1.6') {
			/* do nothing */
		} elseif ($session->getErrno() == SNMP::ERRNO_TIMEOUT) {
			cacti_log('WARNING: SNMP Error:\'Timeout (' . ($info['timeout']/1000) . " ms)', Device:'" . $info['hostname'] . "', OID:'$oid'", false);
		}

		return array();
	}

	if (sizeof($out)) {
		foreach($out as $oid => $value){
			$out[$oid] = format_snmp_string($value, false, $value_output_format);
		}
	}

	return $out;
}

function cacti_snmp_session_get($session, $oid) {
	$info = $session->info;

	if (is_array($oid) && sizeof($oid) == 0) {
		cacti_log('Empty OID!', false);
		return array();
	}

	try {
		$out = $session->get($oid);
	} catch (Exception $e) {
		$out = false;
	}

	if (is_array($oid)) {
		$oid = implode(',', $oid);
	}

	if ($out === false) {
		if ($session->getErrno() == SNMP::ERRNO_TIMEOUT) {
			cacti_log('WARNING: SNMP Error:\'Timeout (' . ($info['timeout']/1000) . " ms)', Device:'" . $info['hostname'] . "', OID:'$oid'", false);
		}
		return false;
	}

	if (is_array($out)) {
		foreach($out as $oid => $value){
			$out[$oid] = format_snmp_string($value, false);
		}
	} else {
		$out = format_snmp_string($out, false);
	}

	return $out;
}

function cacti_snmp_session_getnext($session, $oid) {
	$info = $session->info;
	if (is_array($oid) && sizeof($oid) == 0) {
		cacti_log('Empty OID!', false);
		return array();
	}

	try {
		$out = $session->getnext($oid);
	} catch (Exception $e) {
		$out = false;
	}

	if (is_array($oid)) {
		$oid = implode(',', $oid);
	} elseif ($out === false) {
		if ($session->getErrno() == SNMP::ERRNO_TIMEOUT) {
			cacti_log('WARNING: SNMP Error:\'Timeout (' . ($info['timeout']/1000) . " ms)', Device:'" . $info['hostname'] . "', OID:'$oid'", false);
		}

		return false;
	}

	if (is_array($out)) {
		foreach($out as $oid => $value){
			$out[$oid] = format_snmp_string($value, false);
		}
	} else {
		$out = format_snmp_string($out, false);
	}

	return $out;
}

function cacti_snmp_walk($hostname, $community, $oid, $version, $username, $password,
	$auth_proto, $priv_pass, $priv_proto, $context,
	$port = 161, $timeout = 500, $retries = 0, $max_oids = 10, $environ = SNMP_POLLER,
	$engineid = '', $value_output_format = SNMP_STRING_OUTPUT_GUESS) {

	global $config, $banned_snmp_strings, $snmp_error;

	$snmp_oid_included = true;
	$snmp_auth	       = '';
	$snmp_array        = array();
	$temp_array        = array();

	if (!cacti_snmp_options_sanitize($version, $community, $port, $timeout, $retries, $max_oids)) {
		return array();
	}

	$path_snmpbulkwalk = read_config_option('path_snmpbulkwalk');

	if (snmp_get_method('walk', $version, $context, $engineid, $value_output_format) == SNMP_METHOD_PHP) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */

		/* force php to return numeric oid's */
		cacti_oid_numeric_format();

		if (function_exists('snmprealwalk')) {
			$snmp_oid_included = false;
		}

		snmp_set_quick_print(0);

		if ($version == '1') {
			$temp_array = snmprealwalk($hostname . ':' . $port, $community, $oid, ($timeout * 1000), $retries);
		} elseif ($version == 2) {
			$temp_array = snmp2_real_walk($hostname . ':' . $port, $community, $oid, ($timeout * 1000), $retries);
		} else {
			if ($priv_proto == '[None]' || $priv_pass == '') {
				$sec_level = 'authNoPriv';
				$priv_proto = '';
			} else {
				$sec_level = 'authPriv';
			}

			$temp_array = snmp3_real_walk($hostname . ':' . $port, $username, $sec_level, $auth_proto, $password, $priv_proto, $priv_pass, $oid, ($timeout * 1000), $retries);
		}

		if ($temp_array === false) {
			if ($temp_array === false) {
				cacti_log("WARNING: SNMP Error:'$snmp_error', Device:'$hostname', OID:'$oid'", false);
			} elseif ($oid == '.1.3.6.1.2.1.47.1.1.1.1.2' ||
				$oid == '.1.3.6.1.4.1.9.9.68.1.2.2.1.2' ||
				$oid == '.1.3.6.1.4.1.9.9.46.1.6.1.1.5' ||
				$oid == '.1.3.6.1.4.1.9.9.46.1.6.1.1.14' ||
				$oid == '.1.3.6.1.4.1.9.9.23.1.2.1.1.6') {
				/* do nothing */
			} else {
				cacti_log("WARNING: SNMP Error, Device:'$hostname', OID:'$oid'", false);
			}
		}

		/* check for bad entries */
		if ($temp_array !== false && sizeof($temp_array)) {
			foreach($temp_array as $key => $value) {
				foreach($banned_snmp_strings as $item) {
					if (strstr($value, $item) != '') {
						unset($temp_array[$key]);
						continue 2;
					}
				}
			}

			$o = 0;
			for (reset($temp_array); $i = key($temp_array); next($temp_array)) {
				if ($temp_array[$i] != 'NULL') {
					$snmp_array[$o]['oid'] = preg_replace('/^\./', '', $i);
					$snmp_array[$o]['value'] = format_snmp_string($temp_array[$i], $snmp_oid_included, $value_output_format);
				}
				$o++;
			}
		}
	} else {
		/* ucd/net snmp want the timeout in seconds */
		$timeout = ceil($timeout / 1000);

		if ($version == '1') {
			$snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
		} elseif ($version == '2') {
			$snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
			$version = '2c'; /* ucd/net snmp prefers this over '2' */
		} elseif ($version == '3') {
			if ($priv_proto == '[None]' || $priv_pass == '') {
				$sec_level = 'authNoPriv';
				$priv_proto = '';
			} else {
				$sec_level = 'authPriv';
			}

			if ($priv_pass != '') {
				$priv_pass = '-X ' . snmp_escape_string($priv_pass) . ' -x ' . snmp_escape_string($priv_proto);
			} else {
				$priv_pass = '';
			}

			if ($context != '') {
				$context = '-n ' . snmp_escape_string($context);
			} else {
				$context = '';
			}

			if ($engineid != '') {
				$engineid = '-e ' . snmp_escape_string($engineid);
			} else {
				$engineid = '';
			}

			$snmp_auth = trim('-u ' . snmp_escape_string($username) .
				' -l ' . snmp_escape_string($sec_level) .
				' -a ' . snmp_escape_string($auth_proto) .
				' -A ' . snmp_escape_string($password) .
				' '    . $priv_pass .
				' '    . $context .
				' '    . $engineid);
		}

		if (read_config_option('oid_increasing_check_disable') == 'on') {
			$oidCheck = '-Cc';
		} else {
			$oidCheck = '';
		}

		if (file_exists($path_snmpbulkwalk) && ($version > 1) && ($max_oids > 1)) {
			$temp_array = exec_into_array(cacti_escapeshellcmd($path_snmpbulkwalk) .
				' -O QnU'  . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ':' ') . $snmp_auth .
				' -v '     . $version .
				' -t '     . $timeout .
				' -r '     . $retries .
				' -Cr'     . $max_oids .
				' '        . $oidCheck . ' ' .
				cacti_escapeshellarg($hostname) . ':' . $port . ' ' .
				cacti_escapeshellarg($oid));
		} else {
			$temp_array = exec_into_array(cacti_escapeshellcmd(read_config_option('path_snmpwalk')) .
				' -O QnU ' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ':' ') . $snmp_auth .
				' -v '     . $version .
				' -t '     . $timeout .
				' -r '     . $retries .
				' '        . $oidCheck . ' ' .
				' '        . cacti_escapeshellarg($hostname) . ':' . $port .
				' '        . cacti_escapeshellarg($oid));
		}

		if (substr_count(implode(' ', $temp_array), 'Timeout:')) {
			cacti_log("WARNING: SNMP Error:'Timeout', Device:'$hostname', OID:'$oid'", false);
		}

		/* check for bad entries */
		if (is_array($temp_array) && sizeof($temp_array)) {
			foreach($temp_array as $key => $value) {
				foreach($banned_snmp_strings as $item) {
					if (strstr($value, $item) != '') {
						unset($temp_array[$key]);
						continue 2;
					}
				}
			}

			$i = 0;
			foreach($temp_array as $index => $value) {
				if (preg_match('/(.*) =.*/', $value)) {
					$snmp_array[$i]['oid']   = trim(preg_replace('/(.*) =.*/', "\\1", $value));
					$snmp_array[$i]['value'] = format_snmp_string($value, true, $value_output_format);
					$i++;
				} else {
					$snmp_array[$i-1]['value'] .= $value;
				}
			}
		}
	}

	return $snmp_array;
}

function format_snmp_string($string, $snmp_oid_included, $value_output_format = SNMP_STRING_OUTPUT_GUESS) {
	global $banned_snmp_strings;

	$string = preg_replace(REGEXP_SNMP_TRIM, '', trim($string));

	if ($snmp_oid_included) {
		/* strip off all leading junk (the oid and stuff) */
		$string_array = explode('=', $string);

		if (sizeof($string_array) == 1) {
			/* trim excess first */
			$string = trim($string);
		} elseif ((substr($string, 0, 1) == '.') || (strpos($string, '::') !== false)) {
			/* drop the OID from the array */
			array_shift($string_array);
			$string = trim(implode('=', $string_array));
		} else {
			$string = trim(implode('=', $string_array));
		}
	} else {
		$string = trim($string);
	}

	/* return the easiest value */
	if ($string == '') {
		return $string;
	}

	/* now check for the second most obvious */
	if (is_numeric($string)) {
		return $string;
	}

	/* remove ALL quotes, and other special delimiters */
	$string = str_replace(array('"', "'", '>', '<', "\\", "\n", "\r"), '', $string);

	/* account for invalid MIB files */
	if (strpos($string, 'Wrong Type') !== false) {
		$string = strrev($string);
		if ($position = strpos($string, ':')) {
			$string = trim(strrev(substr($string, 0, $position)));
		} else {
			$string = trim(strrev($string));
		}
	}

	/* Remove invalid chars, if the string output is to be numeric */
	if ($value_output_format == SNMP_STRING_OUTPUT_GUESS) {
		$k = strlen($string);
		for ($i=0; $i < $k; $i++) {
			if ((ord($string[$i]) <= 31) || (ord($string[$i]) >= 127)) {
				$string[$i] = ' ';
			}
		}
	}

	/* Trim the string of trailing and leading spaces */
	$string = trim($string);

	/* convert hex strings to numeric values */
	if (is_hex_string($string) && $value_output_format == SNMP_STRING_OUTPUT_GUESS) {
		$output = '';
		$parts  = explode(' ', $string);

		/* convert the hex string into an ascii string */
		foreach($parts as $part) {
			$output .= chr(hexdec($part));
		}

		if (is_numeric($output)) {
			$string = number_format($output, 0, '', '');
		} else {
			$string = $output;
		}
	} elseif (preg_match('/hex-/i', $string)) {
		$output = '';

		/* strip off the 'Hex-STRING:' */
		$string = preg_replace('/hex- ?/i', '', $string);

		/* normalize some forms */
		$string = str_replace(array(' ', '-', '.'), ':', $string);
		$parts  = explode(':', $string);

		if (is_mac_address($string)) {
			$mac = true;
		} else {
			$mac = false;
		}

		/* convert the hex string into an ascii string */
		foreach($parts as $part) {
			if ($mac == false) {
				$output .= ($output != '' ? ':' : '');
				if ($part == '00') {
					$output .= '00';
				} else  {
					$output .= str_pad($part, 2, '0', STR_PAD_LEFT);
				}
			} else {
				$output .= ($output != '' ? ':' : '') . $part;
			}
		}

		if (is_numeric($output)) {
			$string = number_format($output, 0, '', '');
		} else {
			$string = $output;
		}
	} elseif (preg_match('/(hex:\?)?([a-fA-F0-9]{1,2}(:|\s)){5}/i', $string)) {
		$octet = '';

		/* strip off the 'hex:' */
		$string = preg_replace('/hex: ?/i', '', $string);

		/* split the hex on the delimiter */
		$octets = preg_split('/\s|:/', $string);

		/* loop through each octet and format it accordingly */
		for ($i=0;($i<count($octets));$i++) {
			$octet .= str_pad($octets[$i], 2, '0', STR_PAD_LEFT);

			if (($i+1) < count($octets)) {
				$octet .= ':';
			}
		}

		/* copy the final result and make it upper case */
		$string = strtoupper($octet);
	} elseif (preg_match('/Timeticks:\s\((\d+)\)\s/', $string, $matches)) {
		$string = $matches[1];
	}

	foreach($banned_snmp_strings as $item) {
		if (strpos($string, $item) !== false) {
			$string = '';
			break;
		}
	}

	return $string;
}

function snmp_escape_string($string) {
	global $config;

	if (! defined('SNMP_ESCAPE_CHARACTER')) {
		if ($config['cacti_server_os'] == 'win32') {
			define('SNMP_ESCAPE_CHARACTER', '"');
		} else {
			define('SNMP_ESCAPE_CHARACTER', "'");
		}
	}

	if (substr_count($string, SNMP_ESCAPE_CHARACTER)) {
		$string = substr_replace(SNMP_ESCAPE_CHARACTER, "\\" . SNMP_ESCAPE_CHARACTER, $string);
	}

	return SNMP_ESCAPE_CHARACTER . $string . SNMP_ESCAPE_CHARACTER;
}

function snmp_get_method($type = 'walk', $version = 1, $context = '', $engineid = '',
	$value_output_format = SNMP_STRING_OUTPUT_GUESS) {

	if ($value_output_format == SNMP_STRING_OUTPUT_HEX) {
		return SNMP_METHOD_BINARY;
	} elseif ($version == 3 && $context != '') {
		return SNMP_METHOD_BINARY;
	} elseif ($version == 3 && $engineid != '') {
		return SNMP_METHOD_BINARY;
	} elseif ($type == 'walk' && file_exists('path_snmpbulkwalk')) {
		return SNMP_METHOD_BINARY;
	} elseif (function_exists('snmpget') && $version == 1) {
		return SNMP_METHOD_PHP;
	} elseif (function_exists('snmp2_get') && $version == 2) {
		return SNMP_METHOD_PHP;
	} elseif (function_exists('snmp3_get') && $version == 3) {
		return SNMP_METHOD_PHP;
	} else {
		return SNMP_METHOD_BINARY;
	}
}

function cacti_snmp_options_sanitize($version, $community, &$port, &$timeout, &$retries, &$max_oids) {
	/* determine default retries */
	if (($retries == 0) || (!is_numeric($retries))) {
		$retries = read_config_option('snmp_retries');
		if ($retries == '') $retries = 3;
	}

	/* determine default max_oids */
	if (($max_oids == 0) || (!is_numeric($max_oids))) {
		$max_oids = read_config_option('max_get_size');

		if ($max_oids == '') $max_oids = 10;
	}

	/* determine default port */
	if (empty($port)) {
		$port = '161';
	}

	//cacti_log('Version:' . $version . ', Port:' . $port . ', MaxOIDS:' . $max_oids . ', Retries:' . $retries . ', Timeout:' . $timeout);

	/* do not attempt to poll invalid combinations */
	if (($version == 0) || (!is_numeric($version)) ||
		(!is_numeric($max_oids)) ||
		(!is_numeric($port)) ||
		(!is_numeric($retries)) ||
		(!is_numeric($timeout)) ||
		(($community == '') && ($version != 3))
		) {

		return false;
	}

	return true;
}

