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

// trim all but hex-string:, which will return 'hex-'
// define('REGEXP_SNMP_TRIM', '/(counter(32|64):|gauge:|gauge(32|64):|float:|ipaddress:|string:|integer:)$/i');
define('REGEXP_SNMP_TRIM', '/(counter(32|64)|gauge|gauge(32|64)|float|ipaddress|string|integer):/i');

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

	if (snmp_get_method('get', $version, $context, $engineid, $value_output_format) == SNMP_METHOD_PHP) {
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
			' ' . cacti_escapeshellarg($hostname) . ':' . $port .
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

	if (snmp_get_method('get', $version, $context, $engineid, $value_output_format) == SNMP_METHOD_PHP) {
		$snmp_value = false;

		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */
		snmp_set_quick_print(false);

		$timeout_us = (int) ($timeout_ms * 1000);

		if (function_exists('snmp_set_enum_print')) {
			snmp_set_enum_print(true);
		}

		if ($version == '1') {
			$snmp_value = @snmpget($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
		} elseif ($version == '2') {
			$snmp_value = @snmp2_get($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
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
			' ' . cacti_escapeshellarg($hostname) . ':' . $port .
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

	if (snmp_get_method('getnext', $version, $context, $engineid, $value_output_format) == SNMP_METHOD_PHP) {
		$snmp_value = false;

		// make sure snmp* is verbose so we can see what types of data we are getting back
		snmp_set_quick_print(false);

		$timeout_us = (int) ($timeout_ms * 1000);

		if ($version == '1') {
			$snmp_value = snmpgetnext($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
		} elseif ($version == '2') {
			$snmp_value = snmp2_getnext($hostname . ':' . $port, $community, $oid, $timeout_us, $retries);
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
			' ' . cacti_escapeshellarg($hostname) . ':' . $port .
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
				cacti_escapeshellarg($hostname) . ':' . $port . ' ' .
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
				' ' . cacti_escapeshellarg($hostname) . ':' . $port .
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
		$string_array = explode('=', $string, 2);

		if (cacti_sizeof($string_array) == 1) {
			// trim excess first
			$string = trim($string);
		} elseif ((str_starts_with($string, '.')) || (str_contains($string, '::'))) {
			// drop the OID from the array
			array_shift($string_array);
			$string = trim(implode('=', $string_array));
		} else {
			$string = trim(implode('=', $string_array));
		}
	} else {
		$string = trim($string);
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
	$string = trim($string);

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
	if (!defined('SNMP_ESCAPE_CHARACTER')) {
		define('SNMP_ESCAPE_CHARACTER', '"');
	}

	if ($server_os == 'win32') {
		if (substr_count($string, SNMP_ESCAPE_CHARACTER)) {
			$string = str_replace(SNMP_ESCAPE_CHARACTER, '\\' . SNMP_ESCAPE_CHARACTER, $string);

			return SNMP_ESCAPE_CHARACTER . $string . SNMP_ESCAPE_CHARACTER;
		}
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
 * @param bool   $php_snmp            Whether the PHP SNMP extension is available.
 *
 * @return int One of the `SNMP_METHOD_*` constants.
 */
function snmp_get_method(string $type = 'walk', mixed $version = 1, mixed $context = '', mixed $engineid = '',
	int $value_output_format = SNMP_STRING_OUTPUT_GUESS, bool $php_snmp = CACTI_PHP_SNMP) : int {
	if (!$php_snmp) {
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
