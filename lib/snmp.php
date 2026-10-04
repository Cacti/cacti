<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
define('REGEXP_SNMP_TRIM', '/^(?:hex|counter(?:32|64)|gauge(?:32|64)?|float|ipaddress|string|integer):\s*/i');

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
	include_once($config['include_path'] . '/vendor/phpsnmp/extension.php');
} else {
	include_once($config['include_path'] . '/vendor/phpsnmp/classSNMP.php');
}

use phpsnmp\SNMP;

require_once(__DIR__ . '/cache.php');

/**
 * Select a reliable uptime value from sysUpTime and snmpEngineTime. Some agents, notably OpenBSD
 * snmpd, return the current Unix timestamp for snmpEngineTime. That value is not an uptime and
 * must not replace the real sysUpTime value. Legitimate engine time remains useful after the
 * 32-bit TimeTicks value wraps, so retain the existing preference when it is at least the system
 * uptime and does not resemble wall-clock time. Used as part of Cacti's lib functionality.
 *
 * @param mixed $system_uptime sysUpTime in hundredths of a second.
 * @param mixed $engine_time snmpEngineTime in seconds.
 * @param int|null $now Current Unix time, injectable for tests.
 * @param bool $prefer_engine_time When true, skip BOTH the wall-clock rejection and the "prefer
 *   whichever is larger" comparison, and always use engine time once it is numeric and positive.
 *   Spine's own reindex assert re-check (poller.c) always prefers the engine OID whenever it is
 *   numeric - with no wall-clock awareness and no magnitude comparison of its own; the recache
 *   baseline stored for spine to compare against must use the exact same rule, or a device whose
 *   engine time is legitimately smaller than sysUpTime (e.g. the SNMP agent restarted more recently
 *   than the OS), or an OpenBSD-style agent returning the Unix clock as engine time, causes a
 *   permanent mismatch and an infinite RECACHE ASSERT loop.
 *
 * @return int|false Selected uptime in hundredths of a second.
 */
function cacti_snmp_select_uptime($system_uptime, $engine_time, $now = null, $prefer_engine_time = false) {
	$system_uptime = is_numeric($system_uptime) && $system_uptime >= 0 ? (int) $system_uptime : false;

	if (!is_numeric($engine_time) || $engine_time <= 0) {
		return $system_uptime;
	}

	$engine_time   = (int) $engine_time;
	$engine_uptime = $engine_time * 100;

	// spine's own reindex re-check has no wall-clock awareness at all - it
	// unconditionally prefers any numeric engine time. Paths that must agree
	// with spine's live comparison value have to replicate that exactly,
	// including on OpenBSD-style agents that return the Unix clock as engine
	// time, or the stored baseline and spine's re-check permanently disagree
	// and the RECACHE ASSERT loop persists for those devices too.
	if ($prefer_engine_time) {
		return $engine_uptime;
	}

	$now         = $now ?? time();
	$epoch_range = 5 * 366 * 86400;

	if ($now > $epoch_range && abs($engine_time - $now) <= $epoch_range) {
		return $system_uptime;
	}

	return $system_uptime === false || $engine_uptime >= $system_uptime ? $engine_uptime : $system_uptime;
}

/**
 * Handles the cacti SNMP session. Used as part of Cacti's lib functionality.
 *
 * @param string $hostname The hostname.
 * @param mixed $community The community.
 * @param mixed $version The version.
 * @param mixed $auth_user The auth user.
 * @param mixed $auth_pass The auth pass.
 * @param mixed $auth_proto The auth proto.
 * @param mixed $priv_pass The priv pass.
 * @param mixed $priv_proto The priv proto.
 * @param mixed $context The context.
 * @param mixed $engineid The engineid.
 * @param mixed $port The port.
 * @param mixed $timeout_ms The timeout ms.
 * @param mixed $retries The retries.
 * @param mixed $max_oids The max OIDS.
 * @param mixed $bulk_walk_size The bulk walk size.
 *
 * @return mixed The result of the operation, or false on failure.
 */
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

	/* Encapsulate IPv6 addresses in brackets to prevent the SNMP library
	   from interpreting the port as an IPv6 hextet */
	$snmp_hostname = $hostname;
	if (strpos($snmp_hostname, ':') !== false && strpos($snmp_hostname, '[') === false) {
		$snmp_hostname = '[' . $snmp_hostname . ']';
	}

	try {
		$session = @new SNMP($version, $snmp_hostname . ':' . $port, ($version == 3 ? $auth_user : $community), $timeout_us, $retries);
	} catch (Exception $e) {
		return false;
	}

	if (defined('SNMP_OID_OUTPUT_NUMERIC')) {
		$session->oid_output_format = SNMP_OID_OUTPUT_NUMERIC;
		$session->valueretrieval = SNMP_VALUE_PLAIN;
	}

	$session->quick_print = false;
	$session->max_oids = $max_oids;
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

/**
 * Gets a single SNMP value through the native extension or configured binary. Used as part of
 * Cacti's lib functionality.
 *
 * @param string $hostname The hostname.
 * @param mixed $community The community.
 * @param string $oid The OID.
 * @param mixed $version The version.
 * @param mixed $auth_user The auth user.
 * @param mixed $auth_pass The auth pass.
 * @param mixed $auth_proto The auth proto.
 * @param mixed $priv_pass The priv pass.
 * @param mixed $priv_proto The priv proto.
 * @param mixed $context The context.
 * @param mixed $port The port.
 * @param mixed $timeout_ms The timeout ms.
 * @param mixed $retries The retries.
 * @param mixed $environ The environ.
 * @param mixed $engineid The engineid.
 * @param int $value_output_format The value output format.
 *
 * @return string Formatted SNMP value, or `U` when the request fails.
 */
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

				$snmp_value = @snmp3_get(snmp_format_agent($hostname, $port), $auth_user, $sec_level, snmp_native_protocol($auth_proto), $auth_pass, snmp_native_protocol($priv_proto), $priv_pass, $oid, $timeout_us, $retries);
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
		$snmp_value = array();
		$hostname = cacti_format_ipv6_colon($hostname);

		/* net snmp want the timeout in seconds */
		$timeout_s = (int) ceil($timeout_ms / 1000);

		$snmp_auth = cacti_get_snmp_auth_args($version, $community, $auth_proto, $auth_user,
			$auth_pass, $priv_proto, $priv_pass, $context, $engineid);

		/* no valid snmp version has been set, get out */
		if (empty($snmp_auth)) {
			return;
		}

		$binary = read_config_option('path_snmpget');
		$args   = array_merge(array('-OfntevU' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x' : '')),
			$snmp_auth, array('-v', $version, '-t', (string) $timeout_s, '-r', (string) $retries,
			snmp_format_target_arg($hostname, $port), $oid));

		cacti_snmp_debug_command($binary, $args);
		cacti_exec($binary, $args, $snmp_value, cacti_snmp_command_timeout($timeout_s, $retries));

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

/**
 * Handles the cacti SNMP get raw. Used as part of Cacti's lib functionality.
 *
 * @param string $hostname The hostname.
 * @param mixed $community The community.
 * @param string $oid The OID.
 * @param mixed $version The version.
 * @param mixed $auth_user The auth user.
 * @param string $auth_pass The auth pass.
 * @param mixed $auth_proto The auth proto.
 * @param mixed $priv_pass The priv pass.
 * @param mixed $priv_proto The priv proto.
 * @param mixed $context The context.
 * @param mixed $port The port.
 * @param mixed $timeout_ms The timeout ms.
 * @param mixed $retries The retries.
 * @param mixed $environ The environ.
 * @param string $engineid The engineid.
 * @param int $value_output_format The value output format.
 *
 * @return string The resulting string.
 */
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

			$snmp_value = @snmp3_get(snmp_format_agent($hostname, $port), $auth_user, $sec_level, snmp_native_protocol($auth_proto), $auth_pass, snmp_native_protocol($priv_proto), $priv_pass, $oid, $timeout_us, $retries);
		}

		if ($snmp_value === false) {
			cacti_log("WARNING: SNMP Error:'$snmp_error', Device:'$hostname', OID:'$oid'", false);
			$snmp_value = 'U';
		}
	} else {
		$snmp_value = array();
		$hostname = cacti_format_ipv6_colon($hostname);

		/* net snmp want the timeout in seconds */
		$timeout_s = (int) ceil($timeout_ms / 1000);

		$snmp_auth = cacti_get_snmp_auth_args($version, $community, $auth_proto, $auth_user,
			$auth_pass, $priv_proto, $priv_pass, $context, $engineid);

		/* no valid snmp version has been set, get out */
		if (empty($snmp_auth)) {
			return;
		}

		$binary = read_config_option('path_snmpget');
		$args   = array_merge(array('-Ofntev' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x' : '')),
			$snmp_auth, array('-v', $version, '-t', (string) $timeout_s, '-r', (string) $retries,
			snmp_format_target_arg($hostname, $port), $oid));

		cacti_snmp_debug_command($binary, $args);
		cacti_exec($binary, $args, $snmp_value, cacti_snmp_command_timeout($timeout_s, $retries));

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

/**
 * Handles the cacti SNMP getnext. Used as part of Cacti's lib functionality.
 *
 * @param string $hostname The hostname.
 * @param mixed $community The community.
 * @param mixed $oid The OID.
 * @param mixed $version The version.
 * @param mixed $auth_user The auth user.
 * @param mixed $auth_pass The auth pass.
 * @param mixed $auth_proto The auth proto.
 * @param mixed $priv_pass The priv pass.
 * @param mixed $priv_proto The priv proto.
 * @param mixed $context The context.
 * @param mixed $port The port.
 * @param mixed $timeout_ms The timeout ms.
 * @param mixed $retries The retries.
 * @param mixed $environ The environ.
 * @param string $engineid The engineid.
 * @param int $value_output_format The value output format.
 *
 * @return string The resulting string.
 */
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

			$snmp_value = @snmp3_getnext(snmp_format_agent($hostname, $port), $auth_user, $sec_level, snmp_native_protocol($auth_proto), $auth_pass, snmp_native_protocol($priv_proto), $priv_pass, $oid, $timeout_us, $retries);
		}

		if ($snmp_value === false) {
			cacti_log("WARNING: SNMP Error:'$snmp_error', Device:'$hostname', OID:'$oid'", false);
			$snmp_value = 'U';
		} else {
			$snmp_value = format_snmp_string($snmp_value, false, $value_output_format);
		}
	} else {
		$snmp_value = array();
		$hostname = cacti_format_ipv6_colon($hostname);

		/* net snmp want the timeout in seconds */
		$timeout_s = (int) ceil($timeout_ms / 1000);

		$snmp_auth = cacti_get_snmp_auth_args($version, $community, $auth_proto, $auth_user,
			$auth_pass, $priv_proto, $priv_pass, $context, $engineid);

		/* no valid snmp version has been set, get out */
		if (empty($snmp_auth)) {
			return;
		}

		$binary = read_config_option('path_snmpgetnext');
		$args   = array_merge(array('-OfntevU' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x' : '')),
			$snmp_auth, array('-v', $version, '-t', (string) $timeout_s, '-r', (string) $retries,
			snmp_format_target_arg($hostname, $port), $oid));

		cacti_snmp_debug_command($binary, $args);
		cacti_exec($binary, $args, $snmp_value, cacti_snmp_command_timeout($timeout_s, $retries));

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

/**
 * Handles the cacti get SNMP auth args. Used as part of Cacti's lib functionality.
 *
 * @param mixed &$version The version.
 * @param mixed $community The community.
 * @param mixed $auth_proto The auth proto.
 * @param mixed $auth_user The auth user.
 * @param mixed $auth_pass The auth pass.
 * @param mixed $priv_proto The priv proto.
 * @param mixed $priv_pass The priv pass.
 * @param mixed $context The context.
 * @param mixed $engineid The engineid.
 *
 * @return array An array of results.
 */
function cacti_get_snmp_auth_args(&$version, $community, $auth_proto, $auth_user, $auth_pass,
	$priv_proto, $priv_pass, $context, $engineid) {

	if ($version == '1') {
		return array('-c', (string) $community);
	}

	if ($version == '2') {
		$version = '2c';

		return array('-c', (string) $community);
	}

	if ($version != '3') {
		return array();
	}

	$cred = snmp_auth_cache_cred_lookup($community, $auth_user, $auth_pass, $auth_proto, $priv_pass, $priv_proto);

	if (!is_array($cred)) {
		$cred = snmp_build_v3_cred_args($auth_proto, $auth_user, $auth_pass, $priv_proto, $priv_pass);
	}

	if (empty($cred)) {
		return array();
	}

	/* context and engine id are per-device, not part of the cached credential
	 * identity, so they are appended here - the single place they enter the
	 * SNMPv3 argument vector. */
	if ($context != '') {
		$cred = array_merge($cred, array('-n', (string) $context));
	}

	if ($engineid != '') {
		$cred = array_merge($cred, array('-e', (string) $engineid));
	}

	return $cred;
}

/**
 * Handles the cacti get snmpv3 auth args. Used as part of Cacti's lib functionality.
 *
 * @param mixed $auth_proto The auth proto.
 * @param mixed $auth_user The auth user.
 * @param mixed $auth_pass The auth pass.
 * @param mixed $priv_proto The priv proto.
 * @param mixed $priv_pass The priv pass.
 * @param mixed $context The context.
 * @param mixed $engineid The engineid.
 *
 * @return array An array of results.
 */
function cacti_get_snmpv3_auth_args($auth_proto, $auth_user, $auth_pass, $priv_proto, $priv_pass, $context, $engineid) {
	$args = snmp_build_v3_cred_args($auth_proto, $auth_user, $auth_pass, $priv_proto, $priv_pass);

	if (empty($args)) {
		return array();
	}

	if ($context != '') {
		$args = array_merge($args, array('-n', (string) $context));
	}

	if ($engineid != '') {
		$args = array_merge($args, array('-e', (string) $engineid));
	}

	return $args;
}

/**
 * Build the credential-only portion of the SNMPv3 Net-SNMP argument vector
 * (-u/-a/-A/-x/-X/-l), excluding the per-device context and engine id. This is
 * the costly part of SNMPv3 argument hardening and the unit stored by the
 * shared SNMP auth cache.
 *
 * @param mixed $auth_proto The auth protocol.
 * @param mixed $auth_user The auth user.
 * @param mixed $auth_pass The auth pass.
 * @param mixed $priv_proto The priv protocol.
 * @param mixed $priv_pass The priv pass.
 *
 * @return array Net-SNMP credential arguments, or array() when the protocols are invalid.
 */
function snmp_build_v3_cred_args($auth_proto, $auth_user, $auth_pass, $priv_proto, $priv_pass) {
	global $snmp_priv_protocols, $snmp_auth_protocols;

	$args = array('-u', (string) $auth_user);

	if ($priv_proto == '[None]' || $priv_pass == '') {
		if ($auth_pass == '' || $auth_proto == '[None]') {
			$sec_level = 'noAuthNoPriv';
		} else {
			$sec_level = 'authNoPriv';

			if (!isset($snmp_auth_protocols[$auth_proto])) {
				return array();
			}

			$args = array_merge($args, array('-a', (string) $snmp_auth_protocols[$auth_proto], '-A', (string) $auth_pass));
		}
	} else {
		if (!isset($snmp_auth_protocols[$auth_proto], $snmp_priv_protocols[$priv_proto])) {
			return array();
		}

		$sec_level = 'authPriv';
		$args = array_merge($args, array(
			'-a', (string) $snmp_auth_protocols[$auth_proto], '-A', (string) $auth_pass,
			'-x', (string) $snmp_priv_protocols[$priv_proto], '-X', (string) $priv_pass
		));
	}

	return array_merge($args, array('-l', $sec_level));
}

/**
 * Report whether the legacy MD5 authentication and DES privacy SNMPv3
 * algorithms are offered to operators. Both are weak and are absent from
 * hardened (FIPS) PHP and Net-SNMP builds, so the setting lets an operator drop
 * them from the Device and Automation SNMP Option pickers.
 *
 * @return bool True when MD5/DES may be selected, false when they are disabled.
 */
function snmp_md5_des_enabled() {
	return read_config_option('snmp_md5_des_enabled') == 'on';
}

/**
 * Return the SNMPv3 authentication protocol choices for a form picker, dropping
 * the legacy MD5 entry when it has been disabled in Settings.
 *
 * @return array Map of protocol key => display label.
 */
function snmp_auth_protocol_options() {
	global $snmp_auth_protocols;

	$protocols = $snmp_auth_protocols;

	if (!snmp_md5_des_enabled()) {
		unset($protocols['MD5']);
	}

	return $protocols;
}

/**
 * Return the SNMPv3 privacy protocol choices for a form picker, dropping the
 * legacy DES entry when it has been disabled in Settings.
 *
 * @return array Map of protocol key => display label.
 */
function snmp_priv_protocol_options() {
	global $snmp_priv_protocols;

	$protocols = $snmp_priv_protocols;

	if (!snmp_md5_des_enabled()) {
		unset($protocols['DES']);
	}

	return $protocols;
}

/**
 * Return the process-wide shared SNMP authentication cache.
 *
 * poller.php builds a map of sha1(credential tuple) => pre-hardened SNMPv3
 * credential arguments once per credential change and seals it into a
 * cross-process cache. cmd.php and script_server.php decode it once at startup
 * so in-flight SNMP calls reuse the pre-hardened arguments instead of rebuilding
 * them on every request. The live hardening path remains as a fallback whenever
 * the cache is absent.
 *
 * @return \Cacti\Cache\SharedCache
 */
function snmp_auth_cache(): \Cacti\Cache\SharedCache {
	static $cache = null;

	if ($cache === null) {
		$cache = new \Cacti\Cache\SharedCache('snmp_auth', array('encrypted' => true));
	}

	return $cache;
}

/**
 * Canonical cache key: sha1 over the six credential columns, in the DISTINCT
 * query column order, of the pre-hardened values.
 *
 * @param mixed $community The community.
 * @param mixed $username The username.
 * @param mixed $password The password.
 * @param mixed $auth_proto The auth protocol.
 * @param mixed $priv_pass The priv passphrase.
 * @param mixed $priv_proto The priv protocol.
 *
 * @return string
 */
function snmp_auth_cache_key($community, $username, $password, $auth_proto, $priv_pass, $priv_proto): string {
	return sha1(implode("\x1f", array(
		(string) $community, (string) $username, (string) $password,
		(string) $auth_proto, (string) $priv_pass, (string) $priv_proto
	)));
}

/**
 * Distinct SNMP credential tuples from host and poller_item.
 *
 * @return array
 */
function snmp_auth_cache_rows(): array {
	$columns = 'snmp_community, snmp_username, snmp_password, snmp_auth_protocol, snmp_priv_passphrase, snmp_priv_protocol';

	$hosts = db_fetch_assoc("SELECT DISTINCT $columns FROM host WHERE snmp_version > 0");
	$items = db_fetch_assoc("SELECT DISTINCT $columns FROM poller_item WHERE snmp_version > 0");

	return array_merge(is_array($hosts) ? $hosts : array(), is_array($items) ? $items : array());
}

/**
 * Change token for the credential set, salted with the per-installation secret
 * key so the plaintext checksum sidecar cannot be used to confirm a guessed
 * credential set.
 *
 * @param array $rows The credential rows.
 *
 * @return string
 */
function snmp_auth_cache_signature(array $rows): string {
	$salt = (string) read_config_option('secret_encryption_key');

	return hash('sha256', $salt . serialize($rows));
}

/**
 * Build the sha1(tuple) => pre-hardened SNMPv3 credential args map. v1/v2
 * community hardening is trivial and resolved live, so only SNMPv3 tuples
 * (those with a username) are stored.
 *
 * @param array $rows The credential rows.
 *
 * @return array
 */
function snmp_auth_cache_build_map(array $rows): array {
	$map = array();

	foreach ($rows as $row) {
		$username = isset($row['snmp_username']) ? $row['snmp_username'] : '';

		if ($username === '') {
			continue;
		}

		$key = snmp_auth_cache_key(
			$row['snmp_community'] ?? '', $username, $row['snmp_password'] ?? '',
			$row['snmp_auth_protocol'] ?? '', $row['snmp_priv_passphrase'] ?? '', $row['snmp_priv_protocol'] ?? ''
		);

		if (isset($map[$key])) {
			continue;
		}

		$map[$key] = snmp_build_v3_cred_args(
			$row['snmp_auth_protocol'] ?? '', $username, $row['snmp_password'] ?? '',
			$row['snmp_priv_protocol'] ?? '', $row['snmp_priv_passphrase'] ?? ''
		);
	}

	return $map;
}

/**
 * Build the SNMP auth map directly from the database (no cache involved).
 *
 * @return array
 */
function snmp_auth_cache_build(): array {
	return snmp_auth_cache_build_map(snmp_auth_cache_rows());
}

/**
 * Whether the shared SNMP credential cache is enabled (Console > Settings >
 * Poller > Enable Credential Cache; defaults on). When off, every call hardens
 * its SNMPv3 arguments live.
 *
 * @return bool
 */
function snmp_auth_cache_enabled(): bool {
	return read_config_option('snmp_credential_cache') == 'on';
}

/**
 * Rebuild and reseal the shared SNMP auth cache, but only when the credential
 * set has changed since the last build. Intended to be called once at
 * poller.php startup.
 *
 * @return void
 */
function snmp_auth_cache_refresh(): void {
	if (!snmp_auth_cache_enabled()) {
		return;
	}

	$rows      = snmp_auth_cache_rows();
	$signature = snmp_auth_cache_signature($rows);
	$cache     = snmp_auth_cache();

	if ($cache->checksum() === $signature) {
		return;
	}

	$cache->store(snmp_auth_cache_build_map($rows), $signature);
}

/**
 * Decode the shared SNMP auth cache into process memory exactly once. When no
 * shared cache is available or populated, build a per-process copy from the
 * database so lookups still succeed.
 *
 * @return void
 */
function snmp_auth_cache_load(): void {
	static $loaded = false;

	if ($loaded) {
		return;
	}

	$loaded = true;

	if (!snmp_auth_cache_enabled()) {
		$GLOBALS['snmp_auth_cache_map'] = array();

		return;
	}

	$data = snmp_auth_cache()->fetch();

	if (!is_array($data)) {
		$data = snmp_auth_cache_build();
	}

	$GLOBALS['snmp_auth_cache_map'] = $data;
}

/**
 * Return the pre-hardened SNMPv3 credential args for a credential tuple, or null
 * when the tuple is not cached (the caller then hardens live).
 *
 * @param mixed $community The community.
 * @param mixed $username The username.
 * @param mixed $password The password.
 * @param mixed $auth_proto The auth protocol.
 * @param mixed $priv_pass The priv passphrase.
 * @param mixed $priv_proto The priv protocol.
 *
 * @return array|null
 */
function snmp_auth_cache_cred_lookup($community, $username, $password, $auth_proto, $priv_pass, $priv_proto): ?array {
	if (!isset($GLOBALS['snmp_auth_cache_map'])) {
		snmp_auth_cache_load();
	}

	$key = snmp_auth_cache_key($community, $username, $password, $auth_proto, $priv_pass, $priv_proto);
	$map = $GLOBALS['snmp_auth_cache_map'] ?? array();

	return (isset($map[$key]) && is_array($map[$key])) ? $map[$key] : null;
}

/**
 * Calls a native SNMP session method and captures its suppressed warning. Some PHP SNMP failures
 * emit their only useful diagnostic as a warning while leaving the session error number and
 * message empty. Used as part of Cacti's lib functionality.
 *
 * @param object $session Native SNMP session wrapper.
 * @param string $method Native SNMP method name.
 * @param array $args Method arguments.
 * @param mixed &$warning Captured warning message.
 *
 * @return mixed Native SNMP method result.
 */
function cacti_snmp_session_call($session, $method, $args, &$warning) {
	$warning = '';

	$previous_handler = set_error_handler(function($level, $message, $file = '', $line = 0, $context = array()) use (&$warning, &$previous_handler) {
		if (($level & (E_WARNING | E_USER_WARNING)) != 0) {
			if ($warning === '') {
				$warning = $message;
			}

			return true;
		}

		if (is_callable($previous_handler)) {
			return call_user_func($previous_handler, $level, $message, $file, $line, $context);
		}

		return false;
	});

	try {
		$result = @call_user_func_array(array($session, $method), $args);

		/* The compatibility session used when ext-snmp is unavailable delegates
		 * to cacti_snmp_*(), whose failure sentinel is "U" (or an empty walk),
		 * rather than false. Normalize it before callers inspect the result. */
		if (get_parent_class($session) === false &&
			($result === 'U' || ($method === 'walk' && $result === array()))) {
			global $snmp_error;

			if ($warning === '') {
				$warning = !empty($snmp_error) ? $snmp_error : 'SNMP request failed';
			}

			return false;
		}

		return $result;
	} finally {
		restore_error_handler();
	}
}

/**
 * Logs the error reported by a native SNMP session operation. Used as part of Cacti's lib
 * functionality.
 *
 * @param object $session Native SNMP session wrapper.
 * @param array $info Session connection metadata.
 * @param string|array $oid OID or OID list used by the failed operation.
 * @param string $warning Warning captured while calling the operation.
 *
 * @return void No value is returned.
 */
function cacti_snmp_log_session_error($session, $info, $oid, $warning = '') {
	$error_number = $session->getErrno();

	if ($error_number == SNMP::ERRNO_TIMEOUT) {
		$error = 'Timeout (' . round($info['timeout'] / 1000, 0) . ' ms)';
	} else {
		$error = trim((string) $session->getError());

		if ($error === '') {
			$error = trim($warning);
		}

		if ($error === '') {
			$error = 'Error Number ' . $error_number;
		}
	}

	$error = str_replace(array("\r", "\n"), ' ', $error);
	$oid   = is_array($oid) ? implode(',', $oid) : $oid;

	cacti_log("WARNING: SNMP Error:'$error', Device:'" . $info['hostname'] . "', OID:'$oid'", false, 'SNMP', POLLER_VERBOSITY_HIGH);
}

/**
 * Handles the cacti SNMP session walk. Used as part of Cacti's lib functionality.
 *
 * @param object $session The session.
 * @param mixed $oid The OID.
 * @param bool $dummy The dummy.
 * @param mixed $max_repetitions The max repetitions.
 * @param mixed $non_repeaters The non repeaters.
 * @param int $value_output_format The value output format.
 *
 * @return mixed The result of the operation, or false on failure.
 */
function cacti_snmp_session_walk($session, $oid, $dummy = false, $max_repetitions = NULL,
	$non_repeaters = NULL, $value_output_format = SNMP_STRING_OUTPUT_GUESS) {

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

	if ($non_repeaters === NULL) {
		$non_repeaters = 0;
	}

	if ($max_repetitions === NULL) {
		$max_repetitions = $session->bulk_walk_size;
	}

	if ($max_repetitions <= 0) {
		$max_repetitions = 10;
	}

	$warning = '';

	try {
		$out = cacti_snmp_session_call($session, 'walk', array($oid, false, $max_repetitions, $non_repeaters), $warning);
	} catch (Exception $e) {
		$out = false;

		if ($warning === '') {
			$warning = $e->getMessage();
		}
	}

	if ($out === false) {
		if ($oid == '.1.3.6.1.2.1.47.1.1.1.1.2' ||
			$oid == '.1.3.6.1.4.1.9.9.68.1.2.2.1.2' ||
			$oid == '.1.3.6.1.4.1.9.9.46.1.6.1.1.5' ||
			$oid == '.1.3.6.1.4.1.9.9.46.1.6.1.1.14' ||
			$oid == '.1.3.6.1.4.1.9.9.23.1.2.1.1.6') {
			/* do nothing */
		} else {
			cacti_snmp_log_session_error($session, $info, $oid, $warning);
		}

		return array();
	}

	if (cacti_sizeof($out)) {
		foreach($out as $oid => $value) {
			if (is_array($value)) {
				foreach($value as $index => $sval) {
					$out[$oid][$index] = format_snmp_string($sval, false, $value_output_format);
				}
			} elseif ($out[$oid] !== false) {
				$out[$oid] = format_snmp_string($value, false, $value_output_format);
			}
		}
	} else {
		$out = array();
	}

	return $out;
}

/**
 * Handles the cacti SNMP session get. Used as part of Cacti's lib functionality.
 *
 * @param object $session The session.
 * @param mixed $oid The OID.
 * @param bool $strip_alpha The strip alpha.
 *
 * @return mixed The result of the operation, or false on failure.
 */
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

	$warning = '';

	try {
		$out = cacti_snmp_session_call($session, 'get', array($oid), $warning);
	} catch (Exception $e) {
		$out = false;

		if ($warning === '') {
			$warning = $e->getMessage();
		}
	}

	if (is_array($oid)) {
		$oid = implode(',', $oid);
	}

	if ($out === false) {
		cacti_snmp_log_session_error($session, $info, $oid, $warning);

		return false;
	}

	if (is_array($out)) {
		foreach($out as $oid => $value) {
			$out[$oid] = format_snmp_string($value, false, SNMP_STRING_OUTPUT_GUESS, $strip_alpha);
		}
	} else {
		$out = format_snmp_string($out, false, SNMP_STRING_OUTPUT_GUESS, $strip_alpha);
	}

	return $out;
}

/**
 * Handles the cacti SNMP session getnext. Used as part of Cacti's lib functionality.
 *
 * @param object $session The session.
 * @param mixed $oid The OID.
 *
 * @return mixed The result of the operation, or false on failure.
 */
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

	$warning = '';

	try {
		$out = cacti_snmp_session_call($session, 'getnext', array($oid), $warning);
	} catch (Exception $e) {
		$out = false;

		if ($warning === '') {
			$warning = $e->getMessage();
		}
	}

	if (is_array($oid)) {
		$oid = implode(',', $oid);
	}

	if ($out === false) {
		cacti_snmp_log_session_error($session, $info, $oid, $warning);

		return false;
	}

	if (is_array($out)) {
		foreach($out as $oid => $value) {
			$out[$oid] = format_snmp_string($value, false);
		}
	} else {
		$out = format_snmp_string($out, false);
	}

	return $out;
}

/**
 * Handles the cacti SNMP validate OID. Used as part of Cacti's lib functionality.
 *
 * @param string $oid The OID.
 *
 * @return bool True on success, false otherwise.
 */
function cacti_snmp_validate_oid($oid) {
	$oid = ltrim((string) $oid, '.');

	return $oid !== '' && preg_match('/^(?:0|[1-9][0-9]*)(?:\.(?:0|[1-9][0-9]*))*$/D', $oid) === 1;
}

/**
 * Handles the cacti SNMP walk. Used as part of Cacti's lib functionality.
 *
 * @param string $hostname The hostname.
 * @param mixed $community The community.
 * @param string $oid The OID.
 * @param mixed $version The version.
 * @param mixed $auth_user The auth user.
 * @param mixed $auth_pass The auth pass.
 * @param mixed $auth_proto The auth proto.
 * @param mixed $priv_pass The priv pass.
 * @param mixed $priv_proto The priv proto.
 * @param mixed $context The context.
 * @param mixed $port The port.
 * @param mixed $timeout_ms The timeout ms.
 * @param mixed $retries The retries.
 * @param mixed $bulk_walk_size The bulk walk size.
 * @param mixed $environ The environ.
 * @param mixed $engineid The engineid.
 * @param int $value_output_format The value output format.
 *
 * @return array An array of results.
 */
function cacti_snmp_walk($hostname, $community, $oid, $version, $auth_user = '', $auth_pass = '',
	$auth_proto = '', $priv_pass = '', $priv_proto = '', $context = '',
	$port = 161, $timeout_ms = 500, $retries = 0, $bulk_walk_size = 10, $environ = 'SNMP',
	$engineid = '', $value_output_format = SNMP_STRING_OUTPUT_GUESS) {

	global $config, $banned_snmp_strings, $snmp_error;

	$snmp_error        = '';
	$snmp_oid_included = true;
	$snmp_auth	       = '';
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
		$timeout_s = (int) ceil($timeout_ms / 1000);
		$hostname = cacti_format_ipv6_colon($hostname);

		$snmp_auth = cacti_get_snmp_auth_args($version, $community, $auth_proto, $auth_user,
			$auth_pass, $priv_proto, $priv_pass, $context, $engineid);

		if (empty($snmp_auth)) {
			return array();
		}

		if (read_config_option('oid_increasing_check_disable') == 'on') {
			$oidCheck = '-Cc';
		} else {
			$oidCheck = '';
		}

		if (file_exists($path_snmpbulkwalk) && ($version > 1) && ($bulk_walk_size > 1)) {
			$binary = $path_snmpbulkwalk;
			$args   = array_merge(array('-OQnU' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x' : '')),
				$snmp_auth, array('-v', $version, '-t', (string) $timeout_s, '-r', (string) $retries,
				'-Cr' . $bulk_walk_size));

			if ($oidCheck !== '') {
				$args[] = $oidCheck;
			}

			$args[] = snmp_format_target_arg($hostname, $port);
			$args[] = $oid;
			cacti_snmp_debug_command($binary, $args);
			cacti_exec($binary, $args, $temp_array, cacti_snmp_command_timeout($timeout_s, $retries));
		} else {
			$binary = read_config_option('path_snmpwalk');
			$args   = array_merge(array('-OQnU' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x' : '')),
				$snmp_auth, array('-v', $version, '-t', (string) $timeout_s, '-r', (string) $retries));

			if ($oidCheck !== '') {
				$args[] = $oidCheck;
			}

			$args[] = snmp_format_target_arg($hostname, $port);
			$args[] = $oid;
			cacti_snmp_debug_command($binary, $args);
			cacti_exec($binary, $args, $temp_array, cacti_snmp_command_timeout($timeout_s, $retries));
		}

		if (strpos(implode(' ', $temp_array), 'Timeout') !== false) {
			cacti_log("WARNING: SNMP Error:'Timeout', Device:'$hostname', OID:'$oid'", false, 'SNMP', POLLER_VERBOSITY_HIGH);
		}

		if (strpos(implode(' ', $temp_array), '(tooBig)') !== false) {
			cacti_log("WARNING: SNMP Error:'Error in packet.  Response message would have been too large.', Device:'$hostname', OID:'$oid'", false, 'SNMP', POLLER_VERBOSITY_HIGH);
		}

		/* check for bad entries */
		if (is_array($temp_array) && cacti_sizeof($temp_array)) {
			foreach($temp_array as $key => $value) {
				foreach($banned_snmp_strings as $item) {
					if (strstr($value, $item) != '') {
						unset($temp_array[$key]);
						continue 2;
					}
				}
			}

			/**
			 * Using this technique to catch multi-line
			 * snmpwalk responses from net-snmp.  This happens
			 * usually on sysDescr on Cisco devices.
			 */
			$i = 0;

			foreach($temp_array as $index => $value) {
				if (preg_match('/(.*) =.*/', $value)) {
					$parts   = explode('=', $value, 2);
					$t_oid   = trim($parts[0]);
					$t_value = $parts[1];

					if (!cacti_snmp_validate_oid($t_oid)) {
						cacti_log(sprintf('WARNING: SNMP Agent exploit attempted on SNMP agent from host ip: %s with oid: %s', $hostname, $t_oid), false, 'SECURITY');
						continue;
					}

					$snmp_array[$i]['oid']   = $t_oid;
					$snmp_array[$i]['value'] = $t_value;
					$i++;
				} elseif ($i > 0) {
					$snmp_array[$i-1]['value'] .= $value;
				}
			}
		}
	}

	/**
	 * replay the array to escape value data in case of a multi-line exploit
	 */
	if (cacti_sizeof($snmp_array)) {
		foreach($snmp_array as $index => $data) {
			$snmp_array[$index]['value'] = format_snmp_string($data['value'], false, $value_output_format);
		}
	}

	return $snmp_array;
}

/**
 * Formats the SNMP string. Used as part of Cacti's lib functionality.
 *
 * @param string $string The string.
 * @param bool $snmp_oid_included The SNMP OID included.
 * @param int $value_output_format The value output format.
 * @param bool $strip_alpha The strip alpha.
 *
 * @return string The resulting string.
 */
function format_snmp_string($string, $snmp_oid_included, $value_output_format = SNMP_STRING_OUTPUT_GUESS, $strip_alpha = false) {
	global $banned_snmp_strings;

	if ($string === null) {
		return '';
	}

	$string = preg_replace(REGEXP_SNMP_TRIM, '', trim($string));

	if ($snmp_oid_included) {
		/* strip off all leading junk (the oid and stuff) */
		$string_array = explode('=', $string, 2);

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
			foreach($parts as $part) {
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
			foreach($parts as $part) {
				$output .= ($output != '' ? ':' : '');
				if ($part == '00') {
					$output .= '00';
				} else  {
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
		/* strip off the 'Hex:' */
		$string = trim(str_ireplace('hex:', '', $string));

		/* normalize some forms */
		$output = '';
		$string = str_replace(array(' ', '-', '.'), ':', $string);
		$parts  = explode(':', $string);

		if (!is_mac_address($string)) {
			/* convert the hex string into an ascii string */
			foreach($parts as $part) {
				$output .= ($output != '' ? ':' : '');
				if ($part == '00') {
					$output .= '00';
				} else  {
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

	foreach($banned_snmp_strings as $item) {
		if (strpos($string, $item) !== false) {
			$string = '';
			break;
		}
	}

	return $string;
}

/**
 * Format hostname:port for binary SNMP commands, forcing udp6: transport for IPv6 to prevent DNS
 * ambiguity. Used as part of Cacti's lib functionality.
 *
 * @param string $hostname The target hostname or IP.
 * @param int $port The SNMP port.
 *
 * @return string The formatted target string.
 */
function snmp_format_target($hostname, $port) {
	global $config;

	/* a hostname/IP never legitimately contains cmd.exe metacharacters; strip
	 * them so a crafted device address cannot chain commands on Windows. */
	$hostname = str_replace(array('"', '&', '|', '^', '<', '>', '(', ')'), '', $hostname);

	/* '%' is a valid IPv6 zone-id separator (e.g. fe80::1%eth0) on Unix;
	 * only cmd.exe expands it, so only strip it on win32. */
	if ($config['cacti_server_os'] == 'win32') {
		$hostname = str_replace('%', '', $hostname);
	}

	if (strpos($hostname, ':') !== false) {
		/* IPv6: force udp6: transport and bracket-encapsulate */
		$clean = str_replace(array('[', ']'), '', $hostname);

		return cacti_escapeshellarg('udp6:[' . $clean . ']:' . $port);
	}

	return cacti_escapeshellarg($hostname) . ':' . $port;
}

/**
 * Return a Net-SNMP target as one unescaped argv value. Used as part of Cacti's lib
 * functionality.
 *
 * @param mixed $hostname The hostname.
 * @param mixed $port The port.
 *
 * @return string The resulting string.
 */
function snmp_format_target_arg($hostname, $port) {
	if (strpos($hostname, ':') !== false) {
		$clean = str_replace(array('[', ']'), '', $hostname);

		return 'udp6:[' . $clean . ']:' . $port;
	}

	return $hostname . ':' . $port;
}

/**
 * Normalize a Cacti SNMPv3 protocol token for the procedural php-snmp API, which
 * passes it straight to libnetsnmp and expects the dash-less spelling (SHA256,
 * AES256C) rather than the dashed form the Net-SNMP CLI accepts (SHA-256,
 * AES-256-C). Used as part of Cacti's lib functionality.
 *
 * @param string $protocol Stored auth or priv protocol token.
 *
 * @return string The dash-less native token.
 */
function snmp_native_protocol($protocol) {
	return str_replace('-', '', (string) $protocol);
}

/**
 * Build the agent target for the procedural php-snmp calls. libnetsnmp needs the
 * host bracketed once a non-default port is attached, and an IPv6 literal is
 * always bracketed so the trailing :port is not read as another hextet. An
 * explicit transport or a pre-bracketed target is left untouched. Used as part
 * of Cacti's lib functionality.
 *
 * @param string $hostname Device hostname or IP (may already carry a transport).
 * @param mixed  $port     SNMP port.
 *
 * @return string The agent target, e.g. host, [2001:db8::1]:1161 or udp6:[host]:161.
 */
function snmp_format_agent($hostname, $port) {
	$hostname = trim((string) $hostname);

	if (strpos($hostname, '[') !== false || preg_match('/^(udp6?|tcp6?|unix):/i', $hostname)) {
		return $hostname;
	}

	if (filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
		return '[' . $hostname . ']:' . $port;
	}

	if ((int) $port === 161) {
		return $hostname;
	}

	return '[' . $hostname . ']:' . $port;
}

/**
 * Fetch several OIDs in max_oids-sized batches through the procedural php-snmp
 * API (one PDU per batch), which accepts an OID array and reaches libnetsnmp
 * directly. This is the in-process counterpart to the SNMP-class session get and
 * avoids a per-OID process spawn. Callers that must use the binary (hex output,
 * or no ext-snmp) fall back to per-OID cacti_snmp_get(). Used as part of Cacti's
 * lib functionality.
 *
 * @param string $hostname The hostname.
 * @param mixed  $community The community.
 * @param array  $oids The OIDs to fetch.
 * @param mixed  $version The version.
 * @param mixed  $auth_user The auth user.
 * @param mixed  $auth_pass The auth pass.
 * @param mixed  $auth_proto The auth protocol.
 * @param mixed  $priv_pass The priv passphrase.
 * @param mixed  $priv_proto The priv protocol.
 * @param mixed  $context The context.
 * @param mixed  $port The port.
 * @param mixed  $timeout_ms The timeout in milliseconds.
 * @param mixed  $retries The retries.
 * @param mixed  $max_oids The maximum OIDs per request.
 * @param mixed  $environ The environ.
 * @param string $engineid The engine id.
 * @param int    $value_output_format The value output format.
 *
 * @return array Map of OID => formatted value ('U' on a per-OID failure).
 */
function cacti_snmp_get_multi($hostname, $community, $oids, $version, $auth_user = '', $auth_pass = '',
	$auth_proto = '', $priv_pass = '', $priv_proto = '', $context = '',
	$port = 161, $timeout_ms = 500, $retries = 0, $max_oids = 10, $environ = 'SNMP',
	$engineid = '', $value_output_format = SNMP_STRING_OUTPUT_GUESS) {

	global $snmp_error;

	$snmp_error = '';

	if (!is_array($oids)) {
		$oids = array($oids);
	}

	if (cacti_sizeof($oids) == 0) {
		return array();
	}

	if (!cacti_snmp_options_sanitize($version, $community, $port, $timeout_ms, $retries, $max_oids)) {
		return array();
	}

	/* The array-OID form only exists on the procedural ext-snmp path; anything
	 * that must use the binary (hex output, no ext-snmp) is served one OID at a
	 * time through the usual single get. */
	if (snmp_get_method('get', $version, $context, $engineid, $value_output_format) != SNMP_METHOD_PHP || !function_exists('snmpget')) {
		$results = array();

		foreach ($oids as $oid) {
			$results[$oid] = cacti_snmp_get($hostname, $community, $oid, $version, $auth_user, $auth_pass,
				$auth_proto, $priv_pass, $priv_proto, $context, $port, $timeout_ms, $retries, $environ,
				$engineid, $value_output_format);
		}

		return $results;
	}

	snmp_set_quick_print(0);

	if (function_exists('snmp_set_enum_print')) {
		snmp_set_enum_print(true);
	}

	$timeout_us  = (int) ($timeout_ms * 1000);
	$agent       = snmp_format_agent($hostname, $port);
	$auth_native = '';
	$priv_native = '';
	$sec_level   = '';

	if ($version == '3') {
		if ($priv_proto == '[None]' || $priv_pass == '') {
			$sec_level  = ($auth_pass == '' || $auth_proto == '[None]') ? 'noAuthNoPriv' : 'authNoPriv';
			$priv_proto = '';
		} else {
			$sec_level = 'authPriv';
		}

		$auth_native = snmp_native_protocol($auth_proto);
		$priv_native = snmp_native_protocol($priv_proto);
	}

	$results = array();

	foreach (array_chunk($oids, max(1, (int) $max_oids)) as $chunk) {
		try {
			if ($version == '1') {
				$values = @snmpget($agent, $community, $chunk, $timeout_us, $retries);
			} elseif ($version == '2') {
				$values = @snmp2_get($agent, $community, $chunk, $timeout_us, $retries);
			} else {
				$values = @snmp3_get($agent, $auth_user, $sec_level, $auth_native, $auth_pass, $priv_native, $priv_pass, $chunk, $timeout_us, $retries);
			}
		} catch (Exception $e) {
			$values     = false;
			$snmp_error = $e->getMessage();
		}

		if (!is_array($values)) {
			foreach ($chunk as $oid) {
				$results[$oid] = 'U';
			}

			continue;
		}

		/* php-snmp returns the values keyed by the requested OID; fall back to
		 * positional association when a key does not match verbatim. */
		$positional = array_values($values);
		$index      = 0;

		foreach ($chunk as $oid) {
			if (array_key_exists($oid, $values)) {
				$value = $values[$oid];
			} elseif (isset($positional[$index])) {
				$value = $positional[$index];
			} else {
				$value = false;
			}

			$results[$oid] = ($value === false) ? 'U' : format_snmp_string($value, false, $value_output_format);
			$index++;
		}
	}

	return $results;
}

/**
 * Derive a process timeout for cacti_exec() that sits just beyond Net-SNMP's own
 * timeout budget, so the process wait reaps a genuinely hung snmp binary promptly
 * without killing one that is still performing its configured retries. Used as
 * part of Cacti's lib functionality.
 *
 * @param mixed $timeout Per-attempt Net-SNMP timeout, in seconds.
 * @param mixed $retries Net-SNMP retry count.
 *
 * @return float Seconds to allow the process: the full retry budget plus a smidge.
 */
function cacti_snmp_command_timeout($timeout, $retries) {
	return max(1, (int) $timeout * ((int) $retries + 1)) + 0.5;
}

/**
 * Log binary SNMP use without placing communities or SNMPv3 secrets in logs. Used as part of
 * Cacti's lib functionality.
 *
 * @param mixed $binary The binary.
 * @param array $args The args.
 *
 * @return void No value is returned.
 */
function cacti_snmp_debug_command($binary, array $args) {
	if (isset($_SESSION)) {
		debug_log_insert('data_query', __esc('SNMP command: %s (%d arguments; credentials redacted)',
			basename($binary), cacti_sizeof($args)));
	}
}

/**
 * Escapes an SNMP command argument for the active server operating system. Used as part of
 * Cacti's lib functionality.
 *
 * @param string $string Argument to escape.
 *
 * @return string Escaped command argument.
 */
function snmp_escape_string($string) {
	global $config;

	if (!defined('SNMP_ESCAPE_CHARACTER')) {
		define('SNMP_ESCAPE_CHARACTER', '"');
	}

	if ($config['cacti_server_os'] == 'win32') {
		/* This legacy helper is retained for plugin compatibility. Core SNMP
		 * execution uses argv arrays. cmd.exe tokenizes & | ^ < > ( ) and toggles
		 * quoting on every " it sees (it does not use backslash to escape a
		 * quote), and also expands %VAR%/!VAR! and treats \r\n specially, so
		 * wrapping in quotes cannot neutralize any of these. SNMP community and
		 * v3 credential values never legitimately contain them, so fail closed
		 * instead of attempting lossy shell quoting. */
		if (preg_match('/["&|^%!<>()\r\n]/', $string)) {
			return '';
		}

		return SNMP_ESCAPE_CHARACTER . $string . SNMP_ESCAPE_CHARACTER;
	}

	return cacti_escapeshellarg($string);
}

/**
 * Selects the native PHP extension or command-line SNMP implementation. Used as part of Cacti's
 * lib functionality.
 *
 * @param string $type SNMP operation type.
 * @param mixed $version SNMP protocol version.
 * @param mixed $context SNMPv3 context.
 * @param mixed $engineid SNMPv3 engine identifier.
 * @param int $value_output_format Requested output format.
 *
 * @return int One of the `SNMP_METHOD_*` constants.
 */
function snmp_get_method($type = 'walk', $version = 1, $context = '', $engineid = '',
    $value_output_format = SNMP_STRING_OUTPUT_GUESS) {

	global $config;

	/* No ext-snmp: everything shells out to the Net-SNMP binaries. */
	if (isset($config['php_snmp_support']) && !$config['php_snmp_support']) {
		return SNMP_METHOD_BINARY;
	}

	/* Only the binaries can emit a chosen output format (e.g. hex, -Ox). */
	if ($value_output_format == SNMP_STRING_OUTPUT_HEX) {
		return SNMP_METHOD_BINARY;
	}

	/* ext-snmp cannot GETBULK; walks stay on snmpbulkwalk when it is present. */
	if ($type == 'walk' && file_exists(read_config_option('path_snmpbulkwalk'))) {
		return SNMP_METHOD_BINARY;
	}

	/* SNMPv3 get/getnext: the procedural snmp3_*() calls reach libnetsnmp directly
	 * and handle the AESxxx[C] and SHA families on PHP 8.2+, so prefer them over
	 * the binary and its per-call process spawn. */
	if ($version == 3) {
		return function_exists('snmp3_get') ? SNMP_METHOD_PHP : SNMP_METHOD_BINARY;
	}

	if ($version == 1 && function_exists('snmpget')) {
		return SNMP_METHOD_PHP;
	}

	if ($version == 2 && function_exists('snmp2_get')) {
		return SNMP_METHOD_PHP;
	}

	return SNMP_METHOD_BINARY;
}

/**
 * Handles the cacti SNMP options sanitize. Used as part of Cacti's lib functionality.
 *
 * @param mixed $version The version.
 * @param mixed $community The community.
 * @param mixed &$port The port.
 * @param mixed &$timeout The timeout.
 * @param mixed &$retries The retries.
 * @param mixed &$max_oids The max OIDS.
 *
 * @return bool True on success, false otherwise.
 */
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
