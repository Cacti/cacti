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

/**
 * Select a reliable uptime value from sysUpTime and snmpEngineTime.
 *
 * Some agents, notably OpenBSD snmpd, return the current Unix timestamp for
 * snmpEngineTime.  That value is not an uptime and must not replace the real
 * sysUpTime value.  Legitimate engine time remains useful after the 32-bit
 * TimeTicks value wraps, so retain the existing preference when it is at
 * least the system uptime and does not resemble wall-clock time.
 *
 * @param mixed    $system_uptime      sysUpTime in hundredths of a second.
 * @param mixed    $engine_time        snmpEngineTime in seconds.
 * @param int|null $now                Current Unix time, injectable for tests.
 * @param bool     $prefer_engine_time When true, skip BOTH the wall-clock rejection and
 *                                     the "prefer whichever is larger" comparison, and
 *                                     always use engine time once it is numeric and
 *                                     positive. Spine's own reindex assert re-check
 *                                     (poller.c) always prefers the engine OID whenever
 *                                     it is numeric - with no wall-clock awareness and no
 *                                     magnitude comparison of its own; the recache
 *                                     baseline stored for spine to compare against must
 *                                     use the exact same rule, or a device whose engine
 *                                     time is legitimately smaller than sysUpTime (e.g.
 *                                     the SNMP agent restarted more recently than the OS),
 *                                     or an OpenBSD-style agent returning the Unix clock
 *                                     as engine time, causes a permanent mismatch and an
 *                                     infinite RECACHE ASSERT loop.
 *
 * @return int|false Selected uptime in hundredths of a second.
 */
function cacti_snmp_select_uptime(mixed $system_uptime, mixed $engine_time, ?int $now = null, bool $prefer_engine_time = false) : int|false {
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

// trim all but hex-string:, which will return 'hex-'
// define('REGEXP_SNMP_TRIM', '/(counter(32|64):|gauge:|gauge(32|64):|float:|ipaddress:|string:|integer:)$/i');
define('REGEXP_SNMP_TRIM', '/(counter(32|64)|gauge|gauge(32|64)|float|ipaddress|string|integer):/i');

define('SNMP_METHOD_PHP', 1);
define('SNMP_METHOD_BINARY', 2);

/* the shared SNMP credential cache (\Cacti\Cache\SharedCache) is not covered by
 * the PSR-4 autoloader, so load it here wherever SNMP support is pulled in */
require_once(__DIR__ . '/cache.php');

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
$banned_snmp_strings = ['End of MIB', 'No Such', 'No more'];

if (CACTI_PHP_SNMP) {
	include_once(CACTI_PATH_INCLUDE . '/vendor/phpsnmp/extension.php');
} else {
	include_once(CACTI_PATH_INCLUDE . '/vendor/phpsnmp/classSNMP.php');
}

use phpsnmp\SNMP;

function cacti_snmp_session(string $hostname, mixed $community, mixed $version, mixed $auth_user = '', mixed $auth_pass = '',
	mixed $auth_proto = '', mixed $priv_pass = '', mixed $priv_proto = '', mixed $context = '', mixed $engineid = '',
	mixed $port = 161, mixed $timeout_ms = 500, mixed $retries = 0, mixed $max_oids = 10, mixed $bulk_walk_size = 10) : mixed {
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
		$session = new SNMP($version, $hostname . ':' . (is_numeric($port) ? (int) $port : 161), ($version == 3 ? $auth_user : $community), $timeout_us, $retries);
	} catch (Throwable $e) {
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
	} catch (Throwable) {
		return false;
	}

	return $session;
}

/**
 * Gets a single SNMP value through the native extension or configured binary.
 *
 * @param callable|null $native_get Optional native getter override used by isolated callers and tests.
 *
 * @return string Formatted SNMP value, or `U` when the request fails.
 */
function cacti_snmp_get(string $hostname, mixed $community, string $oid, mixed $version, mixed $auth_user = '', mixed $auth_pass = '',
	mixed $auth_proto = '', mixed $priv_pass = '', mixed $priv_proto = '', mixed $context = '',
	mixed $port = 161, mixed $timeout_ms = 500, mixed $retries = 0, mixed $environ = 'SNMP',
	mixed $engineid = '', int $value_output_format = SNMP_STRING_OUTPUT_GUESS, ?callable $native_get = null) : string {
	global $snmp_error;

	$max_oids   = 1;
	$snmp_error = '';

	if (!cacti_snmp_options_sanitize($version, $community, $port, $timeout_ms, $retries, $max_oids)) {
		return 'U';
	}

	if (snmp_get_method('get', $version, $context, $engineid, $value_output_format, $auth_proto, $priv_proto) == SNMP_METHOD_PHP) {
		// make sure snmp* is verbose so we can see what types of data we are getting back
		snmp_set_quick_print(false);

		if (function_exists('snmp_set_enum_print')) {
			snmp_set_enum_print(true);
		}

		$timeout_us = (int) ($timeout_ms * 1000);
		$snmp_value = 'U';

		try {
			if ($native_get !== null) {
				$snmp_value = $native_get();
			} elseif ($version == '1') {
				$snmp_value = @snmpget($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
			} elseif ($version == '2') {
				$snmp_value = @snmp2_get($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
			} else {
				if ($priv_proto == '[None]' || $priv_pass == '') {
					$sec_level  = ($auth_pass == '' || $auth_proto == '[None]') ? 'noAuthNoPriv' : 'authNoPriv';
					$priv_proto = '';
				} else {
					$sec_level = 'authPriv';
				}

				$snmp_value = @snmp3_get(snmp_format_agent($hostname, $port), $auth_user, $sec_level, snmp_native_protocol($auth_proto), $auth_pass, snmp_native_protocol($priv_proto), $priv_pass, $oid, $timeout_us, $retries);
			}
		} catch (Throwable $ex) {
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
		$hostname   = cacti_format_ipv6_colon($hostname);

		// net snmp want the timeout in seconds
		$timeout_s = (int) ceil($timeout_ms / 1000);

		if ($version == '1') {
			$snmp_auth = '-c ' . snmp_escape_string($community); // v1/v2 - community string
		} elseif ($version == '2') {
			$snmp_auth = '-c ' . snmp_escape_string($community); // v1/v2 - community string
			$version   = '2c'; // ucd/net snmp prefers this over '2'
		} elseif ($version == '3') {
			$snmp_auth = cacti_get_snmpv3_auth($auth_proto, $auth_user, $auth_pass, $priv_proto, $priv_pass, $context, $engineid);
		}

		// no valid snmp version has been set, get out
		if (empty($snmp_auth)) {
			return 'U';
		}

		$command = cacti_escapeshellcmd(read_config_option('path_snmpget')) .
			' -O fntevU' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ' : ' ') . $snmp_auth .
			' -v ' . $version .
			' -t ' . $timeout_s .
			' -r ' . $retries .
			' ' . cacti_escapeshellarg_cmd($hostname, true, true) . ':' . $port .
			' ' . cacti_escapeshellarg($oid);

		if (isset($_SESSION)) {
			debug_log_insert('data_query', __esc('SNMP Command is: %s', $command));
		}

		$return_var = 0;
		exec($command, $snmp_value, $return_var);

		$snmp_value = trim(implode(' ', $snmp_value));

		// a non-zero exit signals snmpget failure even when the output omits 'Timeout'
		if (str_contains($snmp_value, 'Timeout') || $return_var != 0) {
			$reason = str_contains($snmp_value, 'Timeout') ? 'Timeout' : "Exit Code $return_var, Output:'$snmp_value'";

			cacti_log("WARNING: SNMP Error:'$reason', Device:'$hostname', OID:'$oid'", false, 'SNMP', POLLER_VERBOSITY_HIGH);
			$snmp_value = 'U';
		} else {
			$snmp_value = format_snmp_string($snmp_value, false, $value_output_format);
		}
	}

	return $snmp_value;
}

function cacti_snmp_get_raw(string $hostname, mixed $community, string $oid, mixed $version, mixed $auth_user = '', string $auth_pass = '',
	mixed $auth_proto = '', mixed $priv_pass = '', mixed $priv_proto = '', mixed $context = '',
	mixed $port = 161, mixed $timeout_ms = 500, mixed $retries = 0, mixed $environ = SNMP_POLLER,
	string $engineid = '', int $value_output_format = SNMP_STRING_OUTPUT_GUESS) : string {
	global $snmp_error;

	$max_oids   = 1;
	$snmp_error = '';

	if (!cacti_snmp_options_sanitize($version, $community, $port, $timeout_ms, $retries, $max_oids)) {
		return 'U';
	}

	if (snmp_get_method('get', $version, $context, $engineid, $value_output_format, $auth_proto, $priv_proto) == SNMP_METHOD_PHP) {
		$snmp_value = false;

		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */
		snmp_set_quick_print(false);

		$timeout_us = (int) ($timeout_ms * 1000);

		if (function_exists('snmp_set_enum_print')) {
			snmp_set_enum_print(true);
		}

		try {
			if ($version == '1') {
				$snmp_value = @snmpget($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
			} elseif ($version == '2') {
				$snmp_value = @snmp2_get($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
			} else {
				if ($priv_proto == '[None]' || $priv_pass == '') {
					$sec_level  = ($auth_pass == '' || $auth_proto == '[None]') ? 'noAuthNoPriv' : 'authNoPriv';
					$priv_proto = '';
				} else {
					$sec_level = 'authPriv';
				}

				$snmp_value = @snmp3_get(snmp_format_agent($hostname, $port), $auth_user, $sec_level, snmp_native_protocol($auth_proto), $auth_pass, snmp_native_protocol($priv_proto), $priv_pass, $oid, $timeout_us, $retries);
			}
		} catch (Throwable $ex) {
			$snmp_error = $ex->getMessage();
			$snmp_value = false;
		}

		if ($snmp_value === false) {
			cacti_log("WARNING: SNMP Error:'$snmp_error', Device:'$hostname', OID:'$oid'", false);
			$snmp_value = 'U';
		}
	} else {
		$snmp_value = '';
		$hostname   = cacti_format_ipv6_colon($hostname);

		// net snmp want the timeout in seconds
		$timeout_s = (int) ceil($timeout_ms / 1000);

		if ($version == '1') {
			$snmp_auth = '-c ' . snmp_escape_string($community); // v1/v2 - community string
		} elseif ($version == '2') {
			$snmp_auth = '-c ' . snmp_escape_string($community); // v1/v2 - community string
			$version   = '2c'; // ucd/net snmp prefers this over '2'
		} elseif ($version == '3') {
			$snmp_auth = cacti_get_snmpv3_auth($auth_proto, $auth_user, $auth_pass, $priv_proto, $priv_pass, $context, $engineid);
		}

		// no valid snmp version has been set, get out
		if (empty($snmp_auth)) {
			return 'U';
		}

		$command = cacti_escapeshellcmd(read_config_option('path_snmpget')) .
			' -O fntev' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ' : ' ') . $snmp_auth .
			' -v ' . $version .
			' -t ' . $timeout_s .
			' -r ' . $retries .
			' ' . cacti_escapeshellarg_cmd($hostname, true, true) . ':' . $port .
			' ' . cacti_escapeshellarg($oid);

		if (isset($_SESSION)) {
			debug_log_insert('data_query', __esc('SNMP Command is: %s', $command));
		}

		$return_var = 0;
		exec($command, $snmp_value, $return_var);

		// fix for multi-line snmp output
		$snmp_value = trim(implode(' ', $snmp_value));

		// a non-zero exit signals snmpget failure even when the output omits 'Timeout'
		if (str_contains($snmp_value, 'Timeout') || $return_var != 0) {
			$reason = str_contains($snmp_value, 'Timeout') ? 'Timeout' : "Exit Code $return_var, Output:'$snmp_value'";

			cacti_log("WARNING: SNMP Error:'$reason', Device:'$hostname', OID:'$oid'", false, 'SNMP', POLLER_VERBOSITY_HIGH);
			$snmp_value = 'U';
		}
	}

	return $snmp_value;
}

function cacti_snmp_getnext(string $hostname, mixed $community, mixed $oid, mixed $version, mixed $auth_user = '', mixed $auth_pass = '',
	mixed $auth_proto = '', mixed $priv_pass = '', mixed $priv_proto = '', mixed $context = '',
	mixed $port = 161, mixed $timeout_ms = 500, mixed $retries = 0, mixed $environ = 'SNMP',
	string $engineid = '', int $value_output_format = SNMP_STRING_OUTPUT_GUESS) : string {
	global $snmp_error;

	$max_oids   = 1;
	$snmp_error = '';

	if (!cacti_snmp_options_sanitize($version, $community, $port, $timeout_ms, $retries, $max_oids)) {
		return 'U';
	}

	if (snmp_get_method('getnext', $version, $context, $engineid, $value_output_format, $auth_proto, $priv_proto) == SNMP_METHOD_PHP) {
		$snmp_value = false;

		// make sure snmp* is verbose so we can see what types of data we are getting back
		snmp_set_quick_print(false);

		$timeout_us = (int) ($timeout_ms * 1000);

		try {
			if ($version == '1') {
				$snmp_value = @snmpgetnext($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
			} elseif ($version == '2') {
				$snmp_value = @snmp2_getnext($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
			} else {
				if ($priv_proto == '[None]' || $priv_pass == '') {
					$sec_level  = ($auth_pass == '' || $auth_proto == '[None]') ? 'noAuthNoPriv' : 'authNoPriv';
					$priv_proto = '';
				} else {
					$sec_level = 'authPriv';
				}

				$snmp_value = @snmp3_getnext(snmp_format_agent($hostname, $port), $auth_user, $sec_level, snmp_native_protocol($auth_proto), $auth_pass, snmp_native_protocol($priv_proto), $priv_pass, $oid, $timeout_us, $retries);
			}
		} catch (Throwable $ex) {
			$snmp_error = $ex->getMessage();
			$snmp_value = false;
		}

		if ($snmp_value === false) {
			cacti_log("WARNING: SNMP Error:'$snmp_error', Device:'$hostname', OID:'$oid'", false);
			$snmp_value = 'U';
		} else {
			$snmp_value = format_snmp_string($snmp_value, false, $value_output_format);
		}
	} else {
		$snmp_value = '';
		$hostname   = cacti_format_ipv6_colon($hostname);

		// net snmp want the timeout in seconds
		$timeout_s = (int) ceil($timeout_ms / 1000);

		if ($version == '1') {
			$snmp_auth = '-c ' . snmp_escape_string($community); // v1/v2 - community string
		} elseif ($version == '2') {
			$snmp_auth = '-c ' . snmp_escape_string($community); // v1/v2 - community string
			$version   = '2c'; // ucd/net snmp prefers this over '2'
		} elseif ($version == '3') {
			$snmp_auth = cacti_get_snmpv3_auth($auth_proto, $auth_user, $auth_pass, $priv_proto, $priv_pass, $context, $engineid);
		}

		// no valid snmp version has been set, get out
		if (empty($snmp_auth)) {
			return 'U';
		}

		$command = cacti_escapeshellcmd(read_config_option('path_snmpgetnext')) .
			' -O fntevU' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ' : ' ') . $snmp_auth .
			' -v ' . $version .
			' -t ' . $timeout_s .
			' -r ' . $retries .
			' ' . cacti_escapeshellarg_cmd($hostname, true, true) . ':' . $port .
			' ' . cacti_escapeshellarg($oid);

		if (isset($_SESSION)) {
			debug_log_insert('data_query', __esc('SNMP Command is: %s', $command));
		}

		$return_var = 0;
		exec($command, $snmp_value, $return_var);

		$snmp_value = trim(implode(' ', $snmp_value));

		// a non-zero exit signals snmpgetnext failure even when the output omits 'Timeout'
		if (str_contains($snmp_value, 'Timeout') || $return_var != 0) {
			$reason = str_contains($snmp_value, 'Timeout') ? 'Timeout' : "Exit Code $return_var, Output:'$snmp_value'";

			cacti_log("WARNING: SNMP Error:'$reason', Device:'$hostname', OID:'$oid'", false, 'SNMP', POLLER_VERBOSITY_HIGH);
		}

		// strip out non-snmp data
		$snmp_value = format_snmp_string($snmp_value, false, $value_output_format);
	}

	return $snmp_value;
}

function cacti_get_snmpv3_auth(mixed $auth_proto, mixed $auth_user, mixed $auth_pass, mixed $priv_proto, mixed $priv_pass, mixed $context, mixed $engineid) : string {
	global $snmp_priv_protocols, $snmp_auth_protocols;

	/* When the shared SNMPv3 credential cache is enabled, reuse the pre-assembled
	 * credential arguments for this tuple instead of rebuilding them each call.
	 * The cached value is the credential-only arg list; the per-call context and
	 * engine id are still appended below. */
	if (snmp_auth_cache_enabled()) {
		$cred = snmp_auth_cache_cred_lookup('', $auth_user, $auth_pass, $auth_proto, $priv_pass, $priv_proto);

		if ($cred === null) {
			$cred = snmp_build_v3_cred_args($auth_proto, $auth_user, $auth_pass, $priv_proto, $priv_pass);
		}

		$count = cacti_sizeof($cred);

		if ($count == 0) {
			return '';
		}

		$sec_details = '';

		for ($i = 0; $i + 1 < $count; $i += 2) {
			$sec_details .= ' ' . $cred[$i] . ' ' . snmp_escape_string($cred[$i + 1]);
		}

		if ($context != '') {
			$sec_details .= ' -n ' . snmp_escape_string($context);
		}

		if ($engineid != '') {
			$sec_details .= ' -e ' . snmp_escape_string($engineid);
		}

		return trim($sec_details);
	}

	$sec_details = '';

	if ($priv_proto == '[None]' || $priv_pass == '') {
		if ($auth_pass == '' || $auth_proto == '[None]') {
			$sec_level   = 'noAuthNoPriv';
		} else {
			if (!array_key_exists($auth_proto, $snmp_auth_protocols)) {
				return '';
			}

			$sec_level   = 'authNoPriv';
			$sec_details = ' -a ' . snmp_escape_string($snmp_auth_protocols[$auth_proto]) . ' -A ' . snmp_escape_string($auth_pass);
		}

		$priv_proto = '';
		$priv_pass  = '';
	} else {
		if (!array_key_exists($auth_proto, $snmp_auth_protocols) || !array_key_exists($priv_proto, $snmp_priv_protocols)) {
			return '';
		}

		$sec_level   = 'authPriv';
		$sec_details = ' -a ' . snmp_escape_string($snmp_auth_protocols[$auth_proto]) . ' -A ' . snmp_escape_string($auth_pass);
		$priv_proto  = $snmp_priv_protocols[$priv_proto];
		$priv_pass   = '-X ' . snmp_escape_string($priv_pass) . ' -x ' . snmp_escape_string($priv_proto);
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
		' ' . $sec_details .
		' ' . $priv_pass .
		' ' . $context .
		' ' . $engineid);
}

/**
 * cacti_snmp_session_from_host - build an SNMP session from a device row.
 *
 * cacti_snmp_session() takes fifteen positional arguments. Callers across the
 * poller, data queries, and automation each spelled out the same mapping from
 * a $host row, which is where SNMPv3 credential handling drifted between them.
 * This assembles the arguments once. Missing keys fall back to the same
 * defaults cacti_snmp_session() already applies, so a partial row behaves as
 * the explicit-argument calls did.
 *
 * @param array $host           A device row with the snmp_* and ping_retries columns.
 * @param mixed $bulk_walk_size Optional bulk walk size override.
 *
 * @return mixed The SNMP session object, or false on failure.
 */
function cacti_snmp_session_from_host(array $host, mixed $bulk_walk_size = 10) : mixed {
	return cacti_snmp_session(
		$host['hostname'] ?? '',
		$host['snmp_community'] ?? '',
		$host['snmp_version'] ?? '',
		$host['snmp_username'] ?? '',
		$host['snmp_password'] ?? '',
		$host['snmp_auth_protocol'] ?? '',
		$host['snmp_priv_passphrase'] ?? '',
		$host['snmp_priv_protocol'] ?? '',
		$host['snmp_context'] ?? '',
		$host['snmp_engine_id'] ?? '',
		$host['snmp_port'] ?? 161,
		$host['snmp_timeout'] ?? 500,
		$host['ping_retries'] ?? 0,
		$host['max_oids'] ?? 10,
		$bulk_walk_size
	);
}

/**
 * Calls a native SNMP session method and captures its suppressed warning.
 *
 * Some PHP SNMP failures emit their only useful diagnostic as a warning while
 * leaving the session error number and message empty.
 *
 * @param object              $session          Native SNMP session wrapper.
 * @param string              $method           Native SNMP method name.
 * @param array               $args             Method arguments.
 * @param string              $warning          Captured warning message.
 * @param callable|false|null $fallback_handler Previous handler override for isolated callers and tests.
 *
 * @return mixed Native SNMP method result.
 */
function cacti_snmp_session_call(object $session, string $method, array $args, string &$warning, callable|false|null $fallback_handler = null) : mixed {
	$warning = '';

	$previous_handler = set_error_handler(function (int $level, string $message, string $file = '', int $line = 0, array $context = []) use (&$warning, &$previous_handler) : bool {
		if (($level & (E_WARNING | E_USER_WARNING)) !== 0) {
			if ($warning === '') {
				$warning = $message;
			}

			return true;
		}

		if (is_callable($previous_handler)) {
			return (bool) call_user_func($previous_handler, $level, $message, $file, $line, $context);
		}

		return false;
	});

	if ($fallback_handler !== null) {
		$previous_handler = $fallback_handler;
	}

	try {
		return @call_user_func_array([$session, $method], $args);
	} finally {
		restore_error_handler();
	}
}

/**
 * Logs the error reported by a native SNMP session operation.
 *
 * @param object       $session Native SNMP session wrapper.
 * @param array        $info    Session connection metadata.
 * @param string|array $oid     OID or OID list used by the failed operation.
 * @param string       $warning Warning captured while calling the operation.
 *
 * @return void
 */
function cacti_snmp_log_session_error(object $session, array $info, string|array $oid, string $warning = '') : void {
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

	$error = str_replace(["\r", "\n"], ' ', $error);
	$oid   = is_array($oid) ? implode(',', $oid) : $oid;

	cacti_log("WARNING: SNMP Error:'$error', Device:'" . $info['hostname'] . "', OID:'$oid'", false, 'SNMP', POLLER_VERBOSITY_HIGH);
}

function cacti_snmp_session_walk(object $session, mixed $oid, bool $dummy = false, mixed $max_repetitions = null,
	mixed $non_repeaters = null, int $value_output_format = SNMP_STRING_OUTPUT_GUESS) : mixed {
	$info = $session->info;
	$out  = [];

	if (is_array($oid) && cacti_sizeof($oid) == 0) {
		cacti_log('Empty OID!', false);

		return $out;
	}

	if (is_array($oid)) {
		foreach ($oid as $index => $o) {
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

	$warning = '';

	try {
		$out = cacti_snmp_session_call($session, 'walk', [$oid, false, $max_repetitions, $non_repeaters], $warning);
	} catch (Exception $e) {
		$out     = false;

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
			// do nothing
		} else {
			cacti_snmp_log_session_error($session, $info, $oid, $warning);
		}

		return [];
	}

	if (cacti_sizeof($out)) {
		foreach ($out as $oid => $value) {
			if (is_array($value)) {
				foreach ($value as $index => $sval) {
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

function cacti_snmp_session_get(object $session, mixed $oid, bool $strip_alpha = false) : mixed {
	$info = $session->info;
	$out  = [];

	if (is_array($oid) && cacti_sizeof($oid) == 0) {
		cacti_log('Empty OID!', false);

		return $out;
	}

	if (is_array($oid)) {
		foreach ($oid as $index => $o) {
			$oid[$index] = trim($o);
		}
	} else {
		$oid = trim($oid);
	}

	$warning = '';

	try {
		$out = cacti_snmp_session_call($session, 'get', [$oid], $warning);
	} catch (Exception $e) {
		$out     = false;

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
		foreach ($out as $oid => $value) {
			$out[$oid] = format_snmp_string($value, false, SNMP_STRING_OUTPUT_GUESS, $strip_alpha);
		}
	} else {
		$out = format_snmp_string($out, false, SNMP_STRING_OUTPUT_GUESS, $strip_alpha);
	}

	return $out;
}

function cacti_snmp_session_getnext(object $session, mixed $oid) : mixed {
	$info = $session->info;
	$out  = [];

	if (is_array($oid) && cacti_sizeof($oid) == 0) {
		cacti_log('Empty OID!', false);

		return $out;
	}

	if (is_array($oid)) {
		foreach ($oid as $index => $o) {
			$oid[$index] = trim($o);
		}
	} else {
		$oid = trim($oid);
	}

	$warning = '';

	try {
		$out = cacti_snmp_session_call($session, 'getnext', [$oid], $warning);
	} catch (Exception $e) {
		$out     = false;

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
		foreach ($out as $oid => $value) {
			$out[$oid] = format_snmp_string($value, false);
		}
	} else {
		$out = format_snmp_string($out, false);
	}

	return $out;
}

function cacti_snmp_validate_oid(string $oid) : bool {
	$oid = ltrim($oid, '.');

	if ($oid === '') {
		return false;
	}

	$validate = array_map('is_numeric', explode('.', $oid));

	return !in_array(false, $validate, true);
}

function cacti_snmp_walk(string $hostname, mixed $community, string $oid, mixed $version, mixed $auth_user = '', mixed $auth_pass = '',
	mixed $auth_proto = '', mixed $priv_pass = '', mixed $priv_proto = '', mixed $context = '',
	mixed $port = 161, mixed $timeout_ms = 500, mixed $retries = 0, mixed $bulk_walk_size = 10, mixed $environ = 'SNMP',
	mixed $engineid = '', int $value_output_format = SNMP_STRING_OUTPUT_GUESS) : array {
	global $banned_snmp_strings, $snmp_error;

	$snmp_error        = '';
	$snmp_oid_included = true;
	$snmp_auth	        = '';
	$snmp_array        = [];
	$temp_array        = [];

	if (!cacti_snmp_options_sanitize($version, $community, $port, $timeout_ms, $retries, $bulk_walk_size)) {
		return $snmp_array;
	}

	$path_snmpbulkwalk = read_config_option('path_snmpbulkwalk');

	if (snmp_get_method('walk', $version, $context, $engineid, $value_output_format) == SNMP_METHOD_PHP) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */

		$timeout_us = (int) ($timeout_ms * 1000);

		// force php to return numeric oid's
		cacti_oid_numeric_format();

		if (function_exists('snmprealwalk')) {
			$snmp_oid_included = false;
		}

		snmp_set_quick_print(false);

		if ($version == '1') {
			$temp_array = snmprealwalk($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
		} elseif ($version == 2) {
			$temp_array = snmp2_real_walk($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
		}

		// check for bad entries
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
		// ucd/net snmp want the timeout in seconds
		$timeout_s = (int) ceil($timeout_ms / 1000);
		$hostname  = cacti_format_ipv6_colon($hostname);

		if ($version == '1') {
			$snmp_auth = '-c ' . snmp_escape_string($community); // v1/v2 - community string
		} elseif ($version == '2') {
			$snmp_auth = '-c ' . snmp_escape_string($community); // v1/v2 - community string
			$version   = '2c'; // ucd/net snmp prefers this over '2'
		} elseif ($version == '3') {
			$snmp_auth = cacti_get_snmpv3_auth($auth_proto, $auth_user, $auth_pass, $priv_proto, $priv_pass, $context, $engineid);
		}

		// cacti_get_snmpv3_auth() returns '' for an unknown protocol. Without this
		// the walk builds a credential-less snmpwalk -v 3 and fails with no log
		// line, unlike cacti_snmp_get/get_raw/getnext which all bail out here.
		if (empty($snmp_auth)) {
			cacti_log("WARNING: SNMP Error:'Missing credentials', Device:'$hostname', OID:'$oid'", false, 'SNMP', POLLER_VERBOSITY_HIGH);

			return [];
		}

		if (read_config_option('oid_increasing_check_disable') == 'on') {
			$oidCheck = '-Cc';
		} else {
			$oidCheck = '';
		}

		$return_code = 0;

		if (file_exists($path_snmpbulkwalk) && ($version > 1) && ($bulk_walk_size > 1)) {
			$command = cacti_escapeshellcmd($path_snmpbulkwalk) .
				' -O QnU' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ' : ' ') . $snmp_auth .
				' -v ' . $version .
				' -t ' . $timeout_s .
				' -r ' . $retries .
				' -Cr' . $bulk_walk_size .
				' ' . $oidCheck . ' ' .
				cacti_escapeshellarg_cmd($hostname, true, true) . ':' . $port . ' ' .
				cacti_escapeshellarg($oid);

			if (isset($_SESSION)) {
				debug_log_insert('data_query', __esc('SNMP Command is: %s', $command));
			}

			$temp_array = exec_into_array($command, $return_code);
		} else {
			$command = cacti_escapeshellcmd(read_config_option('path_snmpwalk')) .
				' -O QnU' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ' : ' ') . $snmp_auth .
				' -v ' . $version .
				' -t ' . $timeout_s .
				' -r ' . $retries .
				' ' . $oidCheck . ' ' .
				' ' . cacti_escapeshellarg_cmd($hostname, true, true) . ':' . $port .
				' ' . cacti_escapeshellarg($oid);

			if (isset($_SESSION)) {
				debug_log_insert('data_query', __esc('SNMP Command is: %s', $command));
			}

			$temp_array = exec_into_array($command, $return_code);
		}

		// net-snmp reports a timeout or an oversized response on stderr, which
		// exec() does not capture, so the walk output can never contain either.
		// Matching them against stdout only ever matched device data such as an
		// ifAlias of 'Timeout Monitor', and discarded the whole walk. The exit
		// code is the signal that actually distinguishes a failed walk.
		if ($return_code != 0) {
			cacti_log("WARNING: SNMP Error:'Exit Code $return_code', Device:'$hostname', OID:'$oid'", false, 'SNMP', POLLER_VERBOSITY_HIGH);

			return [];
		}

		// check for bad entries
		if (cacti_sizeof($temp_array)) {
			foreach ($temp_array as $key => $value) {
				foreach ($banned_snmp_strings as $item) {
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

			foreach ($temp_array as $value) {
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
				} else {
					$snmp_array[$i - 1]['value'] .= $value;
				}
			}
		}
	}

	/**
	 * replay the array to escape value data in case of a multi-line exploit
	 */
	if (cacti_sizeof($snmp_array)) {
		foreach ($snmp_array as $index => $data) {
			$snmp_array[$index]['value'] = format_snmp_string($data['value'], false, $value_output_format);
		}
	}

	return $snmp_array;
}

function format_snmp_string(string $string, bool $snmp_oid_included, int $value_output_format = SNMP_STRING_OUTPUT_GUESS, bool $strip_alpha = false) : string {
	global $banned_snmp_strings;

	$string = preg_replace(REGEXP_SNMP_TRIM, '', trim($string));

	if ($snmp_oid_included) {
		// strip off all leading junk (the oid and stuff)
		$string_array = explode('=', (string) $string, 2);

		if (cacti_sizeof($string_array) == 1) {
			// trim excess first
			$string = trim((string) $string);
		} elseif ((str_starts_with((string) $string, '.')) || (str_contains((string) $string, '::'))) {
			// drop the OID from the array
			array_shift($string_array);
			$string = trim(implode('=', $string_array));
		} else {
			$string = trim(implode('=', $string_array));
		}
	} else {
		$string = trim((string) $string);
	}

	// remove quotes and extraneous data
	$string = trim($string, " \n\r\v\"'");

	// return the easiest value
	if ($string == '') {
		return $string;
	}

	// now check for the second most obvious
	if (is_numeric($string)) {
		return $string;
	}

	// remove ALL quotes, and other special delimiters
	$string = str_replace(['"', "'", '>', '<', '\\', "\n", "\r"], '', $string);

	// account for invalid MIB files
	if (str_contains($string, 'Wrong Type')) {
		$string = strrev($string);

		if ($position = strpos($string, ':')) {
			$string = trim(strrev(substr($string, 0, $position)));
		} else {
			$string = trim(strrev($string));
		}
	}

	// Remove invalid chars, if the string output is to be numeric
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

	// Remove non-printable characters, allow UTF-8
	if ($value_output_format == SNMP_STRING_OUTPUT_GUESS) {
		$string = preg_replace('/[^[:print:]\r\n]/', '', $string);
	}

	// Trim the string of trailing and leading spaces
	$string = trim((string) $string);

	// convert hex strings to numeric values
	if (is_hex_string($string)) {
		/* the is_hex_string() function will remove the hex:
		 * and hex-string: from the passed value
		 */
		$output = '';
		$parts  = explode(' ', $string);

		if (cacti_sizeof($parts) == 4) {
			$ip_address = '';

			// convert the hex string into an ascii string
			foreach ($parts as $part) {
				$decimal = hexdec($part);

				$ip_address .= ($ip_address != '' ? '.' : '') . $decimal;
				$output .= chr($decimal);
			}

			if (is_ipaddress($ip_address)) {
				$string = $ip_address;
			} else {
				$string = $output;
			}
			// hex string is mac-address
		} elseif (cacti_sizeof($parts) == 6) {
			// convert the hex string into an ascii string
			foreach ($parts as $part) {
				$output .= ($output != '' ? ':' : '');

				if ($part == '00') {
					$output .= '00';
				} else {
					$output .= str_pad($part, 2, '0', STR_PAD_LEFT);
				}
			}

			$string = $output;
		}
	} elseif (str_starts_with(cacti_strtolower($string), 'hex:')) {
		// strip off the 'Hex:'
		$string = trim(str_ireplace('hex:', '', $string));

		// normalize some forms
		$output = '';
		$string = str_replace([' ', '-', '.'], ':', $string);
		$parts  = explode(':', $string);

		if (!is_mac_address($string)) {
			// convert the hex string into an ascii string
			foreach ($parts as $part) {
				$output .= ($output != '' ? ':' : '');

				if ($part == '00') {
					$output .= '00';
				} else {
					$output .= str_pad($part, 2, '0', STR_PAD_LEFT);
				}
			}

			if (is_numeric($output)) {
				$string = number_format((float) $output, 0, '', '');
			} else {
				$string = $output;
			}
		}
	} elseif (preg_match('/Timeticks:\s\((\d+)\)\s/', $string, $matches)) {
		$string = $matches[1];
	}

	foreach ($banned_snmp_strings as $item) {
		if (str_contains($string, $item)) {
			$string = '';

			break;
		}
	}

	return $string;
}

/**
 * Escapes an SNMP command argument for the active server operating system.
 *
 * @param string $string    Argument to escape.
 * @param string $server_os Server operating system identifier.
 *
 * @return string Escaped command argument.
 */
function snmp_escape_string(string $string, string $server_os = CACTI_SERVER_OS) : string {
	if ($server_os == 'win32') {
		/* GHSA-rjvj-r52f-8v5q: cmd.exe ignores the \" escape and toggles
		 * quoting on every ", so wrapping cannot neutralize & | ^ < > ( ).
		 * SNMP values never legitimately contain these, so strip them. */
		$string = str_replace(['"', '&', '|', '^', '<', '>', '(', ')'], '', $string);
	}

	return cacti_escapeshellarg($string);
}

/**
 * Selects the native PHP extension or command-line SNMP implementation.
 *
 * @param string $type                SNMP operation type.
 * @param mixed  $version             SNMP protocol version.
 * @param mixed  $context             SNMPv3 context.
 * @param mixed  $engineid            SNMPv3 engine identifier.
 * @param int    $value_output_format Requested output format.
 * @param mixed  $auth_proto          SNMPv3 authentication protocol token.
 * @param mixed  $priv_proto          SNMPv3 privacy protocol token.
 * @param bool   $php_snmp            Whether the PHP SNMP extension is available.
 *
 * @return int One of the `SNMP_METHOD_*` constants.
 */
function snmp_get_method(string $type = 'walk', mixed $version = 1, mixed $context = '', mixed $engineid = '',
	int $value_output_format = SNMP_STRING_OUTPUT_GUESS, mixed $auth_proto = '', mixed $priv_proto = '',
	bool $php_snmp = CACTI_PHP_SNMP) : int {
	if (!$php_snmp) {
		return SNMP_METHOD_BINARY;
	}

	if ($value_output_format == SNMP_STRING_OUTPUT_HEX) {
		return SNMP_METHOD_BINARY;
	}

	if ($type == 'walk' && file_exists(read_config_option('path_snmpbulkwalk'))) {
		return SNMP_METHOD_BINARY;
	}

	/* SNMPv3 get/getnext: prefer the procedural snmp3_*() calls (they reach
	 * libnetsnmp directly and avoid a per-call process spawn), but only when the
	 * running PHP can actually service the request. The procedural API has no
	 * context or engine-id parameters, and ext-snmp before PHP 8.6 rejects the
	 * SHA-224/SHA-384 auth and AES-192/256[C] privacy tokens with a ValueError
	 * (SHA256/SHA512 + DES/AES/AES128 are accepted from the 8.3 floor), so those
	 * requests fall back to the Net-SNMP binary. */
	if ($version == 3) {
		if (!function_exists('snmp3_get')) {
			return SNMP_METHOD_BINARY;
		}

		if ($context != '' || $engineid != '' || !snmp_php_v3_protocols_supported($auth_proto, $priv_proto)) {
			return SNMP_METHOD_BINARY;
		}

		return SNMP_METHOD_PHP;
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

function cacti_snmp_options_sanitize(mixed $version, mixed $community, mixed &$port, mixed &$timeout, mixed &$retries, mixed &$max_oids) : bool {
	// determine default retries
	if ($retries == 0 || !is_numeric($retries)) {
		$retries = intval(read_config_option('snmp_retries'));

		if (empty($retries)) {
			$retries = 3;
		}
	}

	$version = intval($version);

	// determine default max_oids
	if ($max_oids == 0 || !is_numeric($max_oids)) {
		$max_oids = intval(read_config_option('max_get_size'));

		if (empty($max_oids)) {
			$max_oids = 10;
		}
	}

	// determine default timeout
	if ($timeout == 0 || !is_numeric($timeout)) {
		$timeout = intval(read_config_option('snmp_timeout'));

		if (empty($timeout)) {
			$timeout = 500;
		}
	}

	// determine default port, and force it to an integer. Every net-snmp exec
	// path interpolates $port raw into the command line (hostname:port); an int
	// can never carry shell metacharacters, so this holds even if a caller ever
	// passes a request-derived port rather than the mediumint column value.
	if (empty($port) || !is_numeric($port)) {
		$port = 161;
	} else {
		$port = (int) $port;
	}

	// do not attempt to poll invalid combinations
	if ($version == 0 || ($community == '' && $version != 3)) {
		return false;
	}

	return true;
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
 * the legacy MD5 entry when it has been disabled in Settings. An existing MD5
 * selection ($current) is retained so editing a device/preset that already uses
 * it does not silently reset the field to [None] on save.
 *
 * @param string $current The currently stored auth protocol for this form.
 *
 * @return array Map of protocol key => display label.
 */
function snmp_auth_protocol_options($current = '') {
	global $snmp_auth_protocols;

	$protocols = $snmp_auth_protocols;

	if (!snmp_md5_des_enabled() && $current !== 'MD5') {
		unset($protocols['MD5']);
	}

	return $protocols;
}

/**
 * Return the SNMPv3 privacy protocol choices for a form picker, dropping the
 * legacy DES entry when it has been disabled in Settings. An existing DES
 * selection ($current) is retained so editing a device/preset that already uses
 * it does not silently reset the field to [None] on save.
 *
 * @param string $current The currently stored privacy protocol for this form.
 *
 * @return array Map of protocol key => display label.
 */
function snmp_priv_protocol_options($current = '') {
	global $snmp_priv_protocols;

	$protocols = $snmp_priv_protocols;

	if (!snmp_md5_des_enabled() && $current !== 'DES') {
		unset($protocols['DES']);
	}

	return $protocols;
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

	/* An explicit transport prefix carries its own target; leave it untouched. */
	if (preg_match('/^(udp6?|tcp6?|unix):/i', $hostname)) {
		return $hostname;
	}

	/* Already bracketed (IPv6): keep an embedded port, but attach the configured
	 * one when the target is bracket-only, e.g. [2001:db8::1] -> [2001:db8::1]:1161. */
	if (strpos($hostname, '[') !== false) {
		return preg_match('/\]:\d+$/', $hostname) ? $hostname : $hostname . ':' . $port;
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
 * Whether the running PHP build's procedural snmp3_*() calls accept the given
 * SNMPv3 auth and privacy protocol tokens. ext-snmp accepts MD5/SHA/SHA256/SHA512
 * auth and DES/AES/AES128 privacy from PHP 8.2; the SHA-224/SHA-384 auth variants
 * and the AES-192/256[C] privacy tokens were only added in PHP 8.6. Earlier builds
 * raise a ValueError for those, so those combinations must use the Net-SNMP binary.
 *
 * @param string $auth_proto Stored auth protocol token (e.g. SHA, SHA256).
 * @param string $priv_proto Stored privacy protocol token (e.g. AES, AES256C).
 *
 * @return bool True when the procedural API accepts both tokens on this PHP.
 */
function snmp_php_v3_protocols_supported($auth_proto, $priv_proto) {
	$auth = snmp_native_protocol($auth_proto);
	$priv = snmp_native_protocol($priv_proto);

	/* Allowlist of tokens the procedural snmp3_*() calls accept. MD5/SHA/SHA256/
	 * SHA512 auth and DES/AES/AES128 privacy are accepted from the PHP 8.2 floor;
	 * the SHA-224/SHA-384 auth variants and AES-192/256[C] privacy tokens were
	 * only added in PHP 8.6. An unknown token (automation SNMP protocol fields are
	 * stored without an allowlist) is never routed to snmp3_*(), which would
	 * otherwise raise an uncaught ValueError. */
	$auth_ok = array('', '[None]', 'MD5', 'SHA', 'SHA256', 'SHA512');
	$priv_ok = array('', '[None]', 'DES', 'AES', 'AES128');

	if (PHP_VERSION_ID >= 80600) {
		$auth_ok = array_merge($auth_ok, array('SHA224', 'SHA384'));
		$priv_ok = array_merge($priv_ok, array('AES192', 'AES192C', 'AES256', 'AES256C'));
	}

	if (!in_array($auth, $auth_ok, true) || !in_array($priv, $priv_ok, true)) {
		return false;
	}

	/* SNMPv3 privacy requires authentication. A privacy protocol selected without
	 * an auth protocol is an invalid authPriv combination that would raise a
	 * ValueError in snmp3_*(), so route it to the binary instead. */
	$auth_set = !in_array($auth, array('', '[None]'), true);
	$priv_set = !in_array($priv, array('', '[None]'), true);

	if ($priv_set && !$auth_set) {
		return false;
	}

	return true;
}

/**
 * The SNMP credential change token: a tiny opaque value in the settings table,
 * bumped on device save and poller-cache flush whenever SNMP credentials may
 * have changed. The poller compares it to the token stamped into the shared
 * cache to decide whether a rebuild (and its DISTINCT scan) is needed, so normal
 * polls never rescan. Seeded on first read so a fresh install builds once. Kept
 * small so it always fits settings.value.
 *
 * @return string
 */
function snmp_cred_version(): string {
	$version = read_config_option('snmp_cred_version');

	if ($version === '' || $version === null || $version === false) {
		$version = uniqid('', true);
		set_config_option('snmp_cred_version', $version);
	}

	return (string) $version;
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
 * Distinct SNMPv3 credential tuples from host and poller_item. Only read when the
 * credential version token has moved, so the DISTINCT scan runs on a credential
 * change rather than on every poll. poller_item is included because a device can
 * carry a per-data-source SNMP override (e.g. a second agent on another port)
 * that the host row does not reflect. Only v3 rows are scanned because v1/v2
 * community hardening is trivial and resolved live, so only v3 tuples are cached.
 *
 * @return array
 */
function snmp_auth_cache_rows(): array {
	$columns = 'snmp_community, snmp_username, snmp_password, snmp_auth_protocol, snmp_priv_passphrase, snmp_priv_protocol';

	$hosts = db_fetch_assoc("SELECT DISTINCT $columns FROM host WHERE snmp_version = 3");
	$items = db_fetch_assoc("SELECT DISTINCT $columns FROM poller_item WHERE snmp_version = 3");

	return array_merge(is_array($hosts) ? $hosts : array(), is_array($items) ? $items : array());
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
 * Poller > Enable Credential Cache; disabled by default). When off, every call
 * hardens its SNMPv3 arguments live.
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

	$version = snmp_cred_version();
	$cache   = snmp_auth_cache();

	/* Nothing has changed since the cache was last built: skip the scan. */
	if ($cache->checksum() === $version) {
		return;
	}

	$cache->store(snmp_auth_cache_build_map(snmp_auth_cache_rows()), $version);
}

/**
 * Force a full rebuild of the shared SNMP auth cache from a fresh scan and
 * reseal it at the current credential version. Unlike snmp_auth_cache_refresh()
 * this skips the version check, so it can run out of band (poller maintenance)
 * to sweep orphaned tuples that credential removals leave behind. Resealing at
 * the current version means pollers keep using the cache with no startup rebuild.
 *
 * @return void
 */
function snmp_auth_cache_rebuild(): void {
	if (!snmp_auth_cache_enabled()) {
		return;
	}

	snmp_auth_cache()->store(snmp_auth_cache_build_map(snmp_auth_cache_rows()), snmp_cred_version());
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
	if (snmp_get_method('get', $version, $context, $engineid, $value_output_format, $auth_proto, $priv_proto) != SNMP_METHOD_PHP || !function_exists('snmpget')) {
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
		} catch (\Throwable $e) {
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

