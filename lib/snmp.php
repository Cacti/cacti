<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* trim all but hex-string:, which will return 'hex-' */
#define('REGEXP_SNMP_TRIM', '/(counter(32|64):|gauge:|gauge(32|64):|float:|ipaddress:|string:|integer:)$/i');
define('REGEXP_SNMP_TRIM', '/(hex|counter(32|64)|gauge|gauge(32|64)|float|ipaddress|string|integer):/i');

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

if ($config['php_snmp_support']) {
	include_once(CACTI_PATH_INCLUDE . '/vendor/phpsnmp/extension.php');
} else {
	include_once(CACTI_PATH_INCLUDE . '/vendor/phpsnmp/classSNMP.php');
}

use phpsnmp\SNMP;

function cacti_snmp_session($hostname, $community, $version, $auth_user = '', $auth_pass = '',
	$auth_proto = '', $priv_pass = '', $priv_proto = '', $context = '', $engineid = '',
	$port = 161, $timeout_ms = 500, $retries = 0, $max_oids = 10, $bulk_walk_size = 10) {
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

	$timeout_us = (int) ($timeout_ms * 1000);

	try {
		$session = @new SNMP($version, $hostname . ':' . $port, ($version == 3 ? $auth_user : $community), $timeout_us, $retries);
	} catch (Exception $e) {
		return false;
	}

	if (defined('SNMP_OID_OUTPUT_NUMERIC')) {
		$session->oid_output_format = SNMP_OID_OUTPUT_NUMERIC;
		$session->valueretrieval    = SNMP_VALUE_PLAIN;
	}

	$session->quick_print    = false;
	$session->max_oids       = $max_oids;
	$session->bulk_walk_size = $bulk_walk_size;

	if (read_config_option('oid_increasing_check_disable') == 'on') {
		$session->oid_increasing_check = false;
	}

	if ($version != SNMP::VERSION_3) {
		return $session;
	}

	if ($priv_proto == '[None]' || $priv_pass == '') {
		if ($auth_pass == '' || $auth_proto == '[None]') {
			$sec_level   = 'noAuthNoPriv';
		} else {
			$sec_level   = 'authNoPriv';
		}

		$priv_proto = '';
	} else {
		$sec_level = 'authPriv';
	}

	try {
		$session->setSecurity($sec_level, $auth_proto, $auth_pass, $priv_proto, $priv_pass, $context, $engineid);
	} catch (Exception $e) {
		return false;
	}

	return $session;
}

function cacti_snmp_get($hostname, $community, $oid, $version, $auth_user = '', $auth_pass = '',
	$auth_proto = '', $priv_pass = '', $priv_proto = '', $context = '',
	$port = 161, $timeout_ms = 500, $retries = 0, $environ = 'SNMP',
	$engineid = '', $value_output_format = SNMP_STRING_OUTPUT_GUESS) {
	global $config, $snmp_error;

	$max_oids   = 1;
	$snmp_error = '';

	if (!cacti_snmp_options_sanitize($version, $community, $port, $timeout_ms, $retries, $max_oids)) {
		return 'U';
	}

	if (snmp_get_method('get', $version, $context, $engineid, $value_output_format) == SNMP_METHOD_PHP) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */
		snmp_set_quick_print(0);

		if (function_exists('snmp_set_enum_print')) {
			snmp_set_enum_print(true);
		}

		$timeout_us = (int) ($timeout_ms * 1000);
		$snmp_value = 'U';

		try {
			if ($version == '1') {
				$snmp_value = @snmpget($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
			} elseif ($version == '2') {
				$snmp_value = @snmp2_get($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
			} else {
				if ($priv_proto == '[None]' || $priv_pass == '') {
					if ($auth_pass == '' || $auth_proto == '[None]') {
						$sec_level   = 'noAuthNoPriv';
					} else {
						$sec_level   = 'authNoPriv';
					}

					$priv_proto = '';
				} else {
					$sec_level = 'authPriv';
				}

				$snmp_value = @snmp3_get($hostname . ':' . $port, $auth_user, $sec_level, $auth_proto, $auth_pass, $priv_proto, $priv_pass, $oid, $timeout_us, $retries);
			}
		} catch (Exception $ex) {
			$snmp_error = $ex->getMessage();
		}

		if ($snmp_value === false) {
			cacti_log("WARNING: SNMP Error:'$snmp_error', Device:'$hostname', OID:'$oid'", false, $environ);
			$snmp_value = 'U';
		} else {
			$snmp_value = format_snmp_string($snmp_value, false, $value_output_format);
		}
	} else {
		$snmp_value = '';

		/* net snmp want the timeout in seconds */
		$timeout_s = (int) ceil($timeout_ms / 1000);

		if ($version == '1') {
			$snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
		} elseif ($version == '2') {
			$snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
			$version   = '2c'; /* ucd/net snmp prefers this over '2' */
		} elseif ($version == '3') {
			$snmp_auth = cacti_get_snmpv3_auth($auth_proto, $auth_user, $auth_pass, $priv_proto, $priv_pass, $context, $engineid);
		}

		/* no valid snmp version has been set, get out */
		if (empty($snmp_auth)) {
			return;
		}

		exec(cacti_escapeshellcmd(read_config_option('path_snmpget')) .
			' -O fntevU' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ':' ') . $snmp_auth .
			' -v ' . $version .
			' -t ' . $timeout_s .
			' -r ' . $retries .
			' '    . cacti_escapeshellarg($hostname) . ':' . $port .
			' '    . cacti_escapeshellarg($oid), $snmp_value);

		/* fix for multi-line snmp output */
		if (is_array($snmp_value)) {
			$snmp_value = implode(' ', $snmp_value);
		}

		if (strpos($snmp_value, 'Timeout') !== false) {
			cacti_log("WARNING: SNMP Error:'Timeout', Device:'$hostname', OID:'$oid'", false, 'SNMP', POLLER_VERBOSITY_HIGH);
			$snmp_value = 'U';
		} else {
			$snmp_value = format_snmp_string($snmp_value, false, $value_output_format);
		}
	}

	return $snmp_value;
}

function cacti_snmp_get_raw($hostname, $community, $oid, $version, $auth_user = '', $auth_pass = '',
	$auth_proto = '', $priv_pass = '', $priv_proto = '', $context = '',
	$port = 161, $timeout_ms = 500, $retries = 0, $environ = SNMP_POLLER,
	$engineid = '', $value_output_format = SNMP_STRING_OUTPUT_GUESS) {
	global $config, $snmp_error;

	$max_oids   = 1;
	$snmp_error = '';

	if (!cacti_snmp_options_sanitize($version, $community, $port, $timeout_ms, $retries, $max_oids)) {
		return 'U';
	}

	if (snmp_get_method('get', $version, $context, $engineid, $value_output_format) == SNMP_METHOD_PHP) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */
		snmp_set_quick_print(0);

		$timeout_us = (int) ($timeout_ms * 1000);

		if (function_exists('snmp_set_enum_print')) {
			snmp_set_enum_print(true);
		}

		if ($version == '1') {
			$snmp_value = @snmpget($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
		} elseif ($version == '2') {
			$snmp_value = @snmp2_get($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
		} else {
			if ($priv_proto == '[None]' || $priv_pass == '') {
				if ($auth_pass == '' || $auth_proto == '[None]') {
					$sec_level   = 'noAuthNoPriv';
				} else {
					$sec_level   = 'authNoPriv';
				}

				$priv_proto = '';
			} else {
				$sec_level = 'authPriv';
			}

			$snmp_value = @snmp3_get($hostname . ':' . $port, $auth_user, $sec_level, $auth_proto, $auth_pass, $priv_proto, $priv_pass, $oid, $timeout_us, $retries);
		}

		if ($snmp_value === false) {
			cacti_log("WARNING: SNMP Error:'$snmp_error', Device:'$hostname', OID:'$oid'", false);
			$snmp_value = 'U';
		}
	} else {
		$snmp_value = '';

		/* net snmp want the timeout in seconds */
		$timeout_s = (int) ceil($timeout_ms / 1000);

		if ($version == '1') {
			$snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
		} elseif ($version == '2') {
			$snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
			$version   = '2c'; /* ucd/net snmp prefers this over '2' */
		} elseif ($version == '3') {
			$snmp_auth = cacti_get_snmpv3_auth($auth_proto, $auth_user, $auth_pass, $priv_proto, $priv_pass, $context, $engineid);
		}

		/* no valid snmp version has been set, get out */
		if (empty($snmp_auth)) {
			return;
		}

		exec(cacti_escapeshellcmd(read_config_option('path_snmpget')) .
			' -O fntev' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ':' ') . $snmp_auth .
			' -v ' . $version .
			' -t ' . $timeout_s .
			' -r ' . $retries .
			' '    . cacti_escapeshellarg($hostname) . ':' . $port .
			' '    . cacti_escapeshellarg($oid), $snmp_value);

		/* fix for multi-line snmp output */
		if (is_array($snmp_value)) {
			$snmp_value = implode(' ', $snmp_value);
		}

		if (strpos($snmp_value, 'Timeout') !== false) {
			cacti_log("WARNING: SNMP Error:'Timeout', Device:'$hostname', OID:'$oid'", false, 'SNMP', POLLER_VERBOSITY_HIGH);
			$snmp_value = 'U';
		}
	}

	return $snmp_value;
}

function cacti_snmp_getnext($hostname, $community, $oid, $version, $auth_user = '', $auth_pass = '',
	$auth_proto = '', $priv_pass = '', $priv_proto = '', $context = '',
	$port = 161, $timeout_ms = 500, $retries = 0, $environ = 'SNMP',
	$engineid = '', $value_output_format = SNMP_STRING_OUTPUT_GUESS) {
	global $config, $snmp_error;

	$max_oids   = 1;
	$snmp_error = '';

	if (!cacti_snmp_options_sanitize($version, $community, $port, $timeout_ms, $retries, $max_oids)) {
		return 'U';
	}

	if (snmp_get_method('getnext', $version, $context, $engineid, $value_output_format) == SNMP_METHOD_PHP) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */
		snmp_set_quick_print(0);

		$timeout_us = (int) ($timeout_ms * 1000);

		if ($version == '1') {
			$snmp_value = @snmpgetnext($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
		} elseif ($version == '2') {
			$snmp_value = @snmp2_getnext($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
		} else {
			if ($priv_proto == '[None]' || $priv_pass == '') {
				if ($auth_pass == '' || $auth_proto == '[None]') {
					$sec_level   = 'noAuthNoPriv';
				} else {
					$sec_level   = 'authNoPriv';
				}
				$priv_proto = '';
			} else {
				$sec_level = 'authPriv';
			}

			$snmp_value = @snmp3_getnext($hostname . ':' . $port, $auth_user, $sec_level, $auth_proto, $auth_pass, $priv_proto, $priv_pass, $oid, $timeout_us, $retries);
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
		$timeout_s = (int) ceil($timeout_ms / 1000);

		if ($version == '1') {
			$snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
		} elseif ($version == '2') {
			$snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
			$version   = '2c'; /* ucd/net snmp prefers this over '2' */
		} elseif ($version == '3') {
			$snmp_auth = cacti_get_snmpv3_auth($auth_proto, $auth_user, $auth_pass, $priv_proto, $priv_pass, $context, $engineid);
		}

		/* no valid snmp version has been set, get out */
		if (empty($snmp_auth)) {
			return;
		}

		exec(cacti_escapeshellcmd(read_config_option('path_snmpgetnext')) .
			' -O fntevU' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ':' ') . $snmp_auth .
			' -v ' . $version .
			' -t ' . $timeout_s .
			' -r ' . $retries .
			' '    . cacti_escapeshellarg($hostname) . ':' . $port .
			' '    . cacti_escapeshellarg($oid), $snmp_value);

		/* fix for multi-line snmp output */
		if (is_array($snmp_value)) {
			$snmp_value = implode(' ', $snmp_value);
		}

		if (strpos($snmp_value, 'Timeout') !== false) {
			cacti_log("WARNING: SNMP Error:'Timeout', Device:'$hostname', OID:'$oid'", false, 'SNMP', POLLER_VERBOSITY_HIGH);
		}

		/* strip out non-snmp data */
		$snmp_value = format_snmp_string($snmp_value, false, $value_output_format);
	}

	return $snmp_value;
}

function cacti_get_snmpv3_auth($auth_proto, $auth_user, $auth_pass, $priv_proto, $priv_pass, $context, $engineid) {
	$sec_details = ' -a ' . snmp_escape_string($auth_proto) . ' -A ' . snmp_escape_string($auth_pass);

	if ($priv_proto == '[None]' || $priv_pass == '') {
		if ($auth_pass == '' || $auth_proto == '[None]') {
			$sec_level   = 'noAuthNoPriv';
			$sec_details = '';
		} else {
			$sec_level   = 'authNoPriv';
		}

		$priv_proto = '';
		$priv_pass  = '';
	} else {
		$sec_level = 'authPriv';
		$priv_pass = '-X ' . snmp_escape_string($priv_pass) . ' -x ' . snmp_escape_string($priv_proto);
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

	return trim('-u ' . snmp_escape_string($auth_user) .
		' -l ' . snmp_escape_string($sec_level) .
		' '    . $sec_details .
		' '    . $priv_pass .
		' '    . $context .
		' '    . $engineid);
}

function cacti_snmp_session_walk($session, $oid, $dummy = false, $max_repetitions = null,
	$non_repeaters = null, $value_output_format = SNMP_STRING_OUTPUT_GUESS) {
	$info = $session->info;

	if (is_array($oid) && cacti_sizeof($oid) == 0) {
		cacti_log('Empty OID!', false);

		return array();
	}

	if (is_array($oid)) {
		foreach($oid as $index => $o) {
			$oid[$index] = trim($o);
		}
	} else {
		$oid = trim($oid);
	}

	$session->value_output_format = $value_output_format;

	if ($non_repeaters === null) {
		$non_repeaters = 0;
	}

	if ($max_repetitions === null) {
		$max_repetitions = $session->bulk_walk_size;
	}

	if ($max_repetitions <= 0) {
		$max_repetitions = 10;
	}

	try {
		$out = @$session->walk($oid, false, $max_repetitions, $non_repeaters);
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
			cacti_log('WARNING: SNMP Error:\'Timeout (' . ($info['timeout'] / 1000) . " ms)', Device:'" . $info['hostname'] . "', OID:'$oid'", false, 'SNMP', POLLER_VERBOSITY_HIGH);
		}

		return array();
	}

	if (cacti_sizeof($out)) {
		foreach($out as $oid => $value){
			if (is_array($value)) {
				foreach($value as $index => $sval) {
					$out[$oid][$index] = format_snmp_string($sval, false, $value_output_format);
				}
			} elseif ($out[$oid] !== false) {
				$out[$oid] = format_snmp_string($value, false, $value_output_format);
			}
		}
	} else {
		$out = format_snmp_string($oid, false, $value_output_format);
	}

	return $out;
}

function cacti_snmp_session_get($session, $oid, $strip_alpha = false) {
	$info = $session->info;

	if (is_array($oid) && cacti_sizeof($oid) == 0) {
		cacti_log('Empty OID!', false);

		return array();
	} elseif (is_array($oid)) {
		foreach($oid as $index => $o) {
			$oid[$index] = trim($o);
		}
	} else {
		$oid = trim($oid);
	}

	try {
		$out = @$session->get($oid);
	} catch (Exception $e) {
		$out = false;
	}

	if (is_array($oid)) {
		$oid = implode(',', $oid);
	}

	if ($out === false) {
		if ($session->getErrno() == SNMP::ERRNO_TIMEOUT) {
			cacti_log('WARNING: SNMP Error:\'Timeout (' . round($info['timeout'] / 1000,0) . " ms)', Device:'" . $info['hostname'] . "', OID:'$oid'", false, 'SNMP', POLLER_VERBOSITY_HIGH);
		}

		return false;
	}

	if (is_array($out)) {
		foreach ($out as $oid => $value) {
			$out[$oid] = format_snmp_string($value, false, SNMP_STRING_OUTPUT_GUESS, $strip_alpha);
		}
	} else {
		$out = format_snmp_string($out, false, SNMP_STRING_OUTPUT_GUESS, $strip_alpha);
	}

	return $out;
}

function cacti_snmp_session_getnext($session, $oid) {
	$info = $session->info;

	if (is_array($oid) && cacti_sizeof($oid) == 0) {
		cacti_log('Empty OID!', false);

		return array();
	}

	if (is_array($oid)) {
		foreach($oid as $index => $o) {
			$oid[$index] = trim($o);
		}
	} else {
		$oid = trim($oid);
	}

	try {
		$out = @$session->getnext($oid);
	} catch (Exception $e) {
		$out = false;
	}

	if (is_array($oid)) {
		$oid = implode(',', $oid);
	} elseif ($out === false) {
		if ($session->getErrno() == SNMP::ERRNO_TIMEOUT) {
			cacti_log('WARNING: SNMP Error:\'Timeout (' . round($info['timeout'] / 1000, 0) . " ms)', Device:'" . $info['hostname'] . "', OID:'$oid'", false, 'SNMP', POLLER_VERBOSITY_HIGH);
		}

		return false;
	}

	if (is_array($out)) {
		foreach ($out as $oid => $value) {
			$out[$oid] = format_snmp_string($value, false);
		}
	} else {
		$out = format_snmp_string($out, false);
	}

	return $out;
}

function cacti_snmp_walk($hostname, $community, $oid, $version, $auth_user = '', $auth_pass = '',
	$auth_proto = '', $priv_pass = '', $priv_proto = '', $context = '',
	$port = 161, $timeout_ms = 500, $retries = 0, $bulk_walk_size = 10, $environ = 'SNMP',
	$engineid = '', $value_output_format = SNMP_STRING_OUTPUT_GUESS) {
	global $config, $banned_snmp_strings, $snmp_error;

	$snmp_error        = '';
	$snmp_oid_included = true;
	$snmp_auth	        = '';
	$snmp_array        = array();
	$temp_array        = array();

	if (!cacti_snmp_options_sanitize($version, $community, $port, $timeout_ms, $retries, $bulk_walk_size)) {
		return array();
	}

	$path_snmpbulkwalk = read_config_option('path_snmpbulkwalk');

	if (snmp_get_method('walk', $version, $context, $engineid, $value_output_format) == SNMP_METHOD_PHP) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */

		$timeout_us = (int) ($timeout_ms * 1000);

		/* force php to return numeric oid's */
		cacti_oid_numeric_format();

		if (function_exists('snmprealwalk')) {
			$snmp_oid_included = false;
		}

		snmp_set_quick_print(0);

		if ($version == '1') {
			$temp_array = snmprealwalk($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
		} elseif ($version == 2) {
			$temp_array = snmp2_real_walk($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
		} else {
			if ($priv_proto == '[None]' || $priv_pass == '') {
				if ($auth_pass == '') {
					$sec_level   = 'noAuthNoPriv';
				} else {
					$sec_level   = 'authNoPriv';
				}
				$priv_proto = '';
			} else {
				$sec_level = 'authPriv';
			}

			$temp_array = @snmp3_real_walk($hostname . ':' . $port, $auth_user, $sec_level, $auth_proto, $auth_pass, $priv_proto, $priv_pass, $oid, $timeout_us, $retries);
		}

		/* check for bad entries */
		if ($temp_array !== false && cacti_sizeof($temp_array)) {
			foreach ($temp_array as $key => $value) {
				foreach ($banned_snmp_strings as $item) {
					if (strstr($value, $item) != '') {
						unset($temp_array[$key]);

						continue 2;
					}
				}
			}

			$o = 0;

			for (reset($temp_array); $i = key($temp_array); next($temp_array)) {
				if ($temp_array[$i] != 'NULL') {
					$snmp_array[$o]['oid']   = preg_replace('/^\./', '', $i);
					$snmp_array[$o]['value'] = format_snmp_string($temp_array[$i], $snmp_oid_included, $value_output_format);
				}
				$o++;
			}
		}
	} else {
		/* ucd/net snmp want the timeout in seconds */
		$timeout_s = (int) ceil($timeout_ms / 1000);

		if ($version == '1') {
			$snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
		} elseif ($version == '2') {
			$snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
			$version   = '2c'; /* ucd/net snmp prefers this over '2' */
		} elseif ($version == '3') {
			$snmp_auth = cacti_get_snmpv3_auth($auth_proto, $auth_user, $auth_pass, $priv_proto, $priv_pass, $context, $engineid);
		}

		if (read_config_option('oid_increasing_check_disable') == 'on') {
			$oidCheck = '-Cc';
		} else {
			$oidCheck = '';
		}

		if (file_exists($path_snmpbulkwalk) && ($version > 1) && ($bulk_walk_size > 1)) {
			$temp_array = exec_into_array(cacti_escapeshellcmd($path_snmpbulkwalk) .
				' -O QnU'  . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ':' ') . $snmp_auth .
				' -v '     . $version .
				' -t '     . $timeout_s .
				' -r '     . $retries .
				' -Cr'     . $bulk_walk_size .
				' '        . $oidCheck . ' ' .
				cacti_escapeshellarg($hostname) . ':' . $port . ' ' .
				cacti_escapeshellarg($oid));
		} else {
			$temp_array = exec_into_array(cacti_escapeshellcmd(read_config_option('path_snmpwalk')) .
				' -O QnU' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ':' ') . $snmp_auth .
				' -v '     . $version .
				' -t '     . $timeout_s .
				' -r '     . $retries .
				' '        . $oidCheck . ' ' .
				' '        . cacti_escapeshellarg($hostname) . ':' . $port .
				' '        . cacti_escapeshellarg($oid));
		}

		if (strpos(implode(' ', $temp_array), 'Timeout') !== false) {
			cacti_log("WARNING: SNMP Error:'Timeout', Device:'$hostname', OID:'$oid'", false, 'SNMP', POLLER_VERBOSITY_HIGH);
		}

		if (strpos(implode(' ', $temp_array), '(tooBig)') !== false) {
			cacti_log("WARNING: SNMP Error:'Error in packet.  Response message would have been too large.', Device:'$hostname', OID:'$oid'", false, 'SNMP', POLLER_VERBOSITY_HIGH);
		}

		/* check for bad entries */
		if (is_array($temp_array) && cacti_sizeof($temp_array)) {
			foreach ($temp_array as $key => $value) {
				foreach ($banned_snmp_strings as $item) {
					if (strstr($value, $item) != '') {
						unset($temp_array[$key]);

						continue 2;
					}
				}
			}

			$i = 0;

			foreach ($temp_array as $index => $value) {
				if (preg_match('/(.*) =.*/', $value)) {
					$snmp_array[$i]['oid']   = trim(preg_replace('/(.*) =.*/', '\\1', $value));
					$snmp_array[$i]['value'] = format_snmp_string($value, true, $value_output_format);
					$i++;
				} else {
					$snmp_array[$i - 1]['value'] .= $value;
				}
			}
		}
	}

	return $snmp_array;
}

function format_snmp_string($string, $snmp_oid_included, $value_output_format = SNMP_STRING_OUTPUT_GUESS, $strip_alpha = false) {
	global $banned_snmp_strings;

	if ($string === null) {
		return '';
	}

	$string = preg_replace(REGEXP_SNMP_TRIM, '', trim($string));

	if ($snmp_oid_included) {
		/* strip off all leading junk (the oid and stuff) */
		$string_array = explode('=', $string);

		if (cacti_sizeof($string_array) == 1) {
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

	/* remove quotes and extraneous data */
	$string = trim($string, " \n\r\v\"'");

	/* return the easiest value */
	if ($string == '') {
		return $string;
	}

	/* now check for the second most obvious */
	if (is_numeric($string)) {
		return $string;
	}

	/* remove ALL quotes, and other special delimiters */
	$string = str_replace(array('"', "'", '>', '<', '\\', "\n", "\r"), '', $string);

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
	if ($strip_alpha && $value_output_format == SNMP_STRING_OUTPUT_GUESS) {
		$string = trim(str_ireplace('hex:', '', $string));
		$len    = strlen($string);
		$pos    = $len - 1;

		while ($pos > 0) {
			$value = ord($string[$pos]);

			if (($value < 48 || $value > 57) && $value != 32) {
				$string[$pos] = ' ';
			} else {
				break;
			}

			$pos--;
		}

		$string = trim($string);
		$len    = strlen($string);
		$pos    = 0;

		while ($pos < $len) {
			$value = ord($string[$pos]);

			if (($value < 48 || $value > 57) && $value != 32) {
				$string[$pos] = ' ';
			} else {
				break;
			}

			$pos++;
		}

		$string = trim($string);

		if ($string == '') {
			return 'U';
		}
	}

	/* Remove non-printable characters, allow UTF-8 */
	if ($value_output_format == SNMP_STRING_OUTPUT_GUESS) {
		$string = preg_replace('/[^[:print:]\r\n]/', '', $string);
	}

	/* Trim the string of trailing and leading spaces */
	$string = trim($string);

	/* convert hex strings to numeric values */
	if (is_hex_string($string)) {
		/* the is_hex_string() function will remove the hex:
		 * and hex-string: from the passed value
		 */
		$output = '';

		$parts  = explode(' ', $string);

		if (cacti_sizeof($parts) == 4) {
			$possible_ip = true;

			$ip_address = '';

			/* convert the hex string into an ascii string */
			foreach ($parts as $part) {
				if ($possible_ip && hexdec($part) >= 0 && hexdec($part) <= 255) {
					$ip_address .= ($ip_address != '' ? '.':'') . hexdec($part);
				} else {
					$possible_ip = false;
				}

				$output .= chr(hexdec($part));
			}

			if ($possible_ip && is_ipaddress($ip_address)) {
				$string = $ip_address;
			} else {
				$string = $output;
			}
			/* hex string is mac-address */
		} elseif (cacti_sizeof($parts) == 6) {
			$possible_ip = false;

			/* convert the hex string into an ascii string */
			foreach ($parts as $part) {
				$output .= ($output != '' ? ':' : '');

				if ($part == '00') {
					$output .= '00';
				} else {
					$output .= str_pad($part, 2, '0', STR_PAD_LEFT);
				}
			}

			if (is_numeric($output)) {
				$string = number_format($output, 0, '', '');
			} else {
				$string = $output;
			}
		} else {
			$possible_ip = false;
		}
	} elseif (substr(strtolower($string), 0, 4) == 'hex:') {
		$output = '';

		/* strip off the 'Hex:' */
		$string = trim(str_ireplace('hex:', '', $string));

		/* normalize some forms */
		$string = str_replace(array(' ', '-', '.'), ':', $string);
		$parts  = explode(':', $string);

		if (!is_mac_address($string)) {
			/* convert the hex string into an ascii string */
			foreach ($parts as $part) {
				$output .= ($output != '' ? ':' : '');

				if ($part == '00') {
					$output .= '00';
				} else {
					$output .= str_pad($part, 2, '0', STR_PAD_LEFT);
				}
			}

			if (is_numeric($output)) {
				$string = number_format($output, 0, '', '');
			} else {
				$string = $output;
			}
		}
	} elseif (preg_match('/Timeticks:\s\((\d+)\)\s/', $string, $matches)) {
		$string = $matches[1];
	}

	foreach ($banned_snmp_strings as $item) {
		if (strpos($string, $item) !== false) {
			$string = '';

			break;
		}
	}

	return $string;
}

function snmp_escape_string($string) {
	global $config;

	if (!defined('SNMP_ESCAPE_CHARACTER')) {
		if ($config['cacti_server_os'] == 'win32') {
			define('SNMP_ESCAPE_CHARACTER', '"');
		}

		if (substr_count($string, SNMP_ESCAPE_CHARACTER)) {
			$string = str_replace(SNMP_ESCAPE_CHARACTER, "\\" . SNMP_ESCAPE_CHARACTER, $string);
			return SNMP_ESCAPE_CHARACTER . $string . SNMP_ESCAPE_CHARACTER;
		} else {
			return cacti_escapeshellarg($string);
		}
	} else {
		return cacti_escapeshellarg($string);
	}
}

function snmp_get_method($type = 'walk', $version = 1, $context = '', $engineid = '',
	$value_output_format = SNMP_STRING_OUTPUT_GUESS) {
	global $config;

	if (isset($config['php_snmp_support']) && !$config['php_snmp_support']) {
		return SNMP_METHOD_BINARY;
	}

	if ($value_output_format == SNMP_STRING_OUTPUT_HEX) {
		return SNMP_METHOD_BINARY;
	}

	if ($version == 3) {
		return SNMP_METHOD_BINARY;
	}

	if ($type == 'walk' && file_exists(read_config_option('path_snmpbulkwalk'))) {
		return SNMP_METHOD_BINARY;
	}

	if (function_exists('snmpget') && $version == 1) {
		return SNMP_METHOD_PHP;
	}

	if (function_exists('snmp2_get') && $version == 2) {
		return SNMP_METHOD_PHP;
	} else {
		return SNMP_METHOD_BINARY;
	}
}

function cacti_snmp_options_sanitize($version, $community, &$port, &$timeout, &$retries, &$max_oids) {
	/* determine default retries */
	if ($retries == 0 || !is_numeric($retries)) {
		$retries = read_config_option('snmp_retries');

		if ($retries == '') {
			$retries = 3;
		}
	}

	/* determine default max_oids */
	if ($max_oids == 0 || !is_numeric($max_oids)) {
		$max_oids = read_config_option('max_get_size');

		if ($max_oids == '') {
			$max_oids = 10;
		}
	}

	/* determine default port */
	if (empty($port)) {
		$port = '161';
	}

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
