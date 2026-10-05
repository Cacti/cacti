<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

define('CACTI_PHP_SNMP', true);
define('CACTI_PATH_INCLUDE', dirname(__DIR__, 4) . '/include');
define('CACTI_SERVER_OS', 'unix');
define('SNMP_POLLER', 'SNMP');
define('POLLER_VERBOSITY_HIGH', 4);

$GLOBALS['snmp_coverage_config'] = [
	'oid_increasing_check_disable' => 'off',
	'snmp_retries'                 => 0,
	'max_get_size'                 => 0,
	'snmp_timeout'                 => 0,
	'path_snmpget'                 => dirname(__DIR__, 3) . '/fixtures/snmp_command_probe.php',
	'path_snmpgetnext'             => dirname(__DIR__, 3) . '/fixtures/snmp_command_probe.php',
	'path_snmpwalk'                => dirname(__DIR__, 3) . '/fixtures/snmp_command_probe.php',
	'path_snmpbulkwalk'            => dirname(__DIR__, 3) . '/fixtures/snmp_command_probe.php'
];
$GLOBALS['snmp_coverage_logs']   = [];
$GLOBALS['snmp_coverage_debug']  = [];
$GLOBALS['snmp_priv_protocols']  = ['AES' => 'AES'];
$GLOBALS['snmp_auth_protocols']  = ['SHA' => 'SHA'];

if (!function_exists(__NAMESPACE__ . '\\read_config_option') && !function_exists('\\read_config_option')) {
	function read_config_option(string $name) : mixed {
		return $GLOBALS['snmp_coverage_config'][$name] ?? '';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cacti_sizeof') && !function_exists('\\cacti_sizeof')) {
	function cacti_sizeof(mixed $value) : int {
		return is_array($value) ? count($value) : 0;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cacti_log') && !function_exists('\\cacti_log')) {
	function cacti_log(string $message, bool $output, string $environ = '', int $level = 0) : bool {
		$GLOBALS['snmp_coverage_logs'][] = [$message, $output, $environ, $level];

		return true;
	}
}

function cacti_format_ipv6_colon(string $hostname) : string {
	return str_contains($hostname, ':') ? '[' . trim($hostname, '[]') . ']' : $hostname;
}

function cacti_escapeshellcmd(string $command) : string {
	return escapeshellcmd($command);
}

function cacti_escapeshellarg(string $argument) : string {
	return escapeshellarg($argument);
}

function cacti_escapeshellarg_cmd(string $argument, bool $quote = true, bool $strip_env = false) : string {
	if (CACTI_SERVER_OS == 'win32') {
		$argument = str_replace(['"', '&', '|', '^', '<', '>', '(', ')'], '', $argument);

		if ($strip_env) {
			$argument = str_replace('%', '', $argument);
		}
	}

	return cacti_escapeshellarg($argument, $quote);
}

function debug_log_insert(string $category, string $message) : void {
	$GLOBALS['snmp_coverage_debug'][] = [$category, $message];
}

function __esc(string $message, mixed ...$args) : string {
	return $args === [] ? $message : vsprintf($message, $args);
}

function exec_into_array(string $command, int &$return_code = 0) : array {
	$output = [];
	exec($command, $output, $return_code);

	return $output;
}

function cacti_oid_numeric_format() : void {
}

function is_hex_string(string &$string) : bool {
	$lower = strtolower($string);

	if (str_starts_with($lower, 'hex-string:')) {
		$check = trim(substr($string, strlen('hex-string:')));
	} elseif (str_starts_with($lower, 'hex-')) {
		$check = trim(substr($string, strlen('hex-')));
	} else {
		return false;
	}

	if (preg_match('/^(?:[0-9A-Fa-f]{2} ){1,}[0-9A-Fa-f]{2}$/', $check) !== 1) {
		return false;
	}

	$string = $check;

	return true;
}

function is_ipaddress(string $value) : bool {
	if (!empty($GLOBALS['snmp_coverage_reject_ip'])) {
		return false;
	}

	return filter_var($value, FILTER_VALIDATE_IP) !== false;
}

function is_mac_address(string $value) : bool {
	return preg_match('/^(?:[0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $value) === 1;
}

function cacti_strtolower(string $value) : string {
	return strtolower($value);
}

if (!function_exists('set_config_option')) {
	function set_config_option(string $name, mixed $value) : void {
		$GLOBALS['snmp_coverage_config'][$name] = $value;
	}
}

if (!function_exists('db_fetch_assoc')) {
	function db_fetch_assoc(string $sql) : array {
		if (str_contains($sql, 'FROM host')) {
			return $GLOBALS['snmp_coverage_host_rows'] ?? [];
		}

		if (str_contains($sql, 'FROM poller_item')) {
			return $GLOBALS['snmp_coverage_item_rows'] ?? [];
		}

		return [];
	}
}

if (!function_exists('cacti_encrypt_secret')) {
	function cacti_encrypt_secret(string $plain) : string {
		return 'enc:' . base64_encode($plain);
	}
}

if (!function_exists('cacti_decrypt_secret')) {
	function cacti_decrypt_secret(string $wire) : string|false {
		return str_starts_with($wire, 'enc:') ? (base64_decode(substr($wire, 4), true) ?: false) : false;
	}
}

require_once dirname(__DIR__, 4) . '/lib/snmp.php';

test('uptime selection rejects wall-clock engine times and preserves wrap handling', function () : void {
	$now = 1784363931;

	expect(cacti_snmp_select_uptime(3015, $now, $now))->toBe(3015)
		->and(cacti_snmp_select_uptime(3015, 'U', $now))->toBe(3015)
		->and(cacti_snmp_select_uptime(250000, 50000000, $now))->toBe(5000000000)
		->and(cacti_snmp_select_uptime(4000000, 600, $now))->toBe(4000000)
		->and(cacti_snmp_select_uptime(false, 600, $now))->toBe(60000)
		->and(cacti_snmp_select_uptime('U', 'U', $now))->toBeFalse();
});

test('prefer_engine_time forces the spine-compatible engine-OID preference', function () : void {
	$now = 1784363931;

	// spine (poller.c) always prefers a numeric engine time over sysUpTime with no
	// magnitude comparison and no wall-clock awareness of its own; the recache
	// baseline must use the exact same rule
	expect(cacti_snmp_select_uptime(999999999, 600, $now, true))->toBe(60000)
		->and(cacti_snmp_select_uptime(4000000, 600, $now, true))->toBe(60000)
		->and(cacti_snmp_select_uptime(false, 600, $now, true))->toBe(60000)
		// spine has no wall-clock rejection either, so an OpenBSD-style engine time
		// that looks like the Unix clock must still be used here, not rejected
		->and(cacti_snmp_select_uptime(3015, $now, $now, true))->toBe($now * 100)
		->and(cacti_snmp_select_uptime('U', 'U', $now, true))->toBeFalse();
});

final class CoverageSnmpSession {
	public array $info              = ['timeout' => 1500, 'hostname' => 'coverage-host'];
	public int $bulk_walk_size      = 5;
	public int $value_output_format = SNMP_STRING_OUTPUT_GUESS;
	public mixed $result            = false;
	public int $errno               = 9;
	public string $error            = '';
	public bool $throw              = false;
	public bool $warn               = false;

	public function walk(mixed ...$args) : mixed {
		return $this->respond();
	}

	public function get(mixed ...$args) : mixed {
		return $this->respond();
	}

	public function getnext(mixed ...$args) : mixed {
		return $this->respond();
	}

	public function notice() : bool {
		trigger_error('delegated notice', E_USER_NOTICE);

		return true;
	}

	public function getErrno() : int {
		return $this->errno;
	}

	public function getError() : string {
		return $this->error;
	}

	private function respond() : mixed {
		if ($this->warn) {
			trigger_error('transport warning', E_USER_WARNING);
		}

		if ($this->throw) {
			throw new RuntimeException('operation exception');
		}

		return $this->result;
	}
}

beforeEach(function () : void {
	$GLOBALS['snmp_coverage_logs']                                   = [];
	$GLOBALS['snmp_coverage_debug']                                  = [];
	$GLOBALS['snmp_coverage_config']['oid_increasing_check_disable'] = 'off';
	$GLOBALS['snmp_coverage_config']['path_snmpbulkwalk']            = dirname(__DIR__, 3) . '/fixtures/snmp_command_probe.php';
	$GLOBALS['snmp_coverage_reject_ip']                              = false;
	unset($_SESSION);
	putenv('CACTI_SNMP_PROBE_MODE=get');
});

test('session wrappers capture diagnostics and normalize native results', function () : void {
	$session = new CoverageSnmpSession();

	expect(cacti_snmp_session_walk($session, []))->toBe([])
		->and(cacti_snmp_session_get($session, []))->toBe([])
		->and(cacti_snmp_session_getnext($session, []))->toBe([]);

	$session->result = ['.1' => ['STRING: one', 'INTEGER: 2'], '.2' => false, '.3' => 'STRING: three'];
	expect(cacti_snmp_session_walk($session, [' .1 '], false, 0))->toBe(['.1' => ['one', '2'], '.2' => false, '.3' => 'three']);

	$session->result = [];
	expect(cacti_snmp_session_walk($session, ' .1 '))->toBe('.1');

	$session->result = ['.1' => 'STRING: one'];
	expect(cacti_snmp_session_get($session, [' .1 ']))->toBe(['.1' => 'one'])
		->and(cacti_snmp_session_getnext($session, [' .1 ']))->toBe(['.1' => 'one']);

	$session->result = 'STRING: scalar';
	expect(cacti_snmp_session_get($session, '.1'))->toBe('scalar')
		->and(cacti_snmp_session_getnext($session, '.1'))->toBe('scalar');

	$session->result = false;
	$session->warn   = true;
	expect(cacti_snmp_session_walk($session, '.1'))->toBe([])
		->and(cacti_snmp_session_walk($session, '.1.3.6.1.2.1.47.1.1.1.1.2'))->toBe([])
		->and(cacti_snmp_session_get($session, '.1'))->toBeFalse()
		->and(cacti_snmp_session_getnext($session, '.1'))->toBeFalse();

	$session->throw = true;
	$session->warn  = false;
	expect(cacti_snmp_session_walk($session, '.1'))->toBe([])
		->and(cacti_snmp_session_get($session, '.1'))->toBeFalse()
		->and(cacti_snmp_session_getnext($session, '.1'))->toBeFalse();
});

test('session warning handler delegates non-warning errors and error logging is complete', function () : void {
	$session   = new CoverageSnmpSession();
	$warning   = '';
	$delegated = [];

	set_error_handler(function (int $level, string $message) use (&$delegated) : bool {
		$delegated[] = [$level, $message];

		return true;
	});

	try {
		expect(cacti_snmp_session_call($session, 'notice', [], $warning))->toBeTrue();
	} finally {
		restore_error_handler();
	}

	expect(cacti_snmp_session_call($session, 'notice', [], $warning, false))->toBeTrue();

	$session->errno = \SNMP::ERRNO_TIMEOUT;
	cacti_snmp_log_session_error($session, $session->info, ['.1', '.2']);
	$session->errno = 8;
	$session->error = "native\r\nerror";
	cacti_snmp_log_session_error($session, $session->info, '.1');
	$session->error = '';
	cacti_snmp_log_session_error($session, $session->info, '.1', 'warning reason');
	cacti_snmp_log_session_error($session, $session->info, '.1');

	expect($delegated[0][0])->toBe(E_USER_NOTICE)
		->and($GLOBALS['snmp_coverage_logs'])->toHaveCount(4)
		->and($GLOBALS['snmp_coverage_logs'][0][0])->toContain('Timeout (2 ms)')
		->and($GLOBALS['snmp_coverage_logs'][1][0])->toContain('native  error')
		->and($GLOBALS['snmp_coverage_logs'][2][0])->toContain('warning reason')
		->and($GLOBALS['snmp_coverage_logs'][3][0])->toContain('Error Number 8');
});

test('SNMP value formatting covers scalar, OID, hex, timetick, and rejection forms', function () : void {
	$ip                                 = format_snmp_string('Hex-STRING: C0 A8 01 01', false);
	$GLOBALS['snmp_coverage_reject_ip'] = true;
	$ascii                              = format_snmp_string('Hex-STRING: 41 42 43 44', false);
	$GLOBALS['snmp_coverage_reject_ip'] = false;

	expect(format_snmp_string('', false))->toBe('')
		->and(format_snmp_string('INTEGER: 42', false))->toBe('42')
		->and(format_snmp_string('.1 = STRING: value', true))->toBe('value')
		->and(format_snmp_string('MIB::oid = STRING: value', true))->toBe('value')
		->and(format_snmp_string('STRING: value', true))->toBe('value')
		->and(format_snmp_string('prefix = STRING: value', true))->toBe('prefix =  value')
		->and(format_snmp_string('STRING: Wrong Type: corrected', false))->toBe('corrected')
		->and(format_snmp_string('STRING: Wrong Type without separator', false))->toBe('Wrong Type without separator')
		->and(format_snmp_string('abc123xyz', false, SNMP_STRING_OUTPUT_GUESS, true))->toBe('123')
		->and(format_snmp_string('alphabetic', false, SNMP_STRING_OUTPUT_GUESS, true))->toBe('U')
		->and(format_snmp_string("STRING: printable\x01", false))->toBe('printable')
		->and($ip)->toBe('192.168.1.1')
		->and($ascii)->toBe('ABCD')
		->and(format_snmp_string('Hex-STRING: 00 01 02 03 04 05', false))->toBe('00:01:02:03:04:05')
		->and(format_snmp_string('Hex-STRING: 01 02 03 04 05', false))->toBe('01 02 03 04 05')
		->and(format_snmp_string('Hex: 01-02-03', false))->toBe('01:02:03')
		->and(format_snmp_string('Hex: 00-01-02', false))->toBe('00:01:02')
		->and(format_snmp_string('Hex: 123', false))->toBe('123')
		->and(format_snmp_string('Hex: 00:11:22:33:44:55', false))->toBe('00:11:22:33:44:55')
		->and(format_snmp_string('Timeticks: (123) 0:00:01.23', false))->toBe('123')
		->and(format_snmp_string('End of MIB', false))->toBe('');
});

test('OID validation, escaping, method selection, options, and v3 auth cover all policies', function () : void {
	expect(cacti_snmp_validate_oid('.1.3.6'))->toBeTrue()
		->and(cacti_snmp_validate_oid('.'))->toBeFalse()
		->and(cacti_snmp_validate_oid('1.bad'))->toBeFalse()
		->and(snmp_escape_string('public'))->toBe("'public'")
		->and(snmp_escape_string('a"b', 'win32'))->toBe("'ab'")
		->and(snmp_escape_string('public', 'win32'))->toBe("'public'")
		->and(snmp_get_method('get', 1, '', '', SNMP_STRING_OUTPUT_GUESS, false))->toBe(SNMP_METHOD_BINARY)
		->and(snmp_get_method('get', 1, '', '', SNMP_STRING_OUTPUT_HEX))->toBe(SNMP_METHOD_BINARY)
		->and(snmp_get_method('get', 3))->toBe(SNMP_METHOD_PHP)
		->and(snmp_get_method('get', 3, 'ctx'))->toBe(SNMP_METHOD_BINARY)
		->and(snmp_get_method('get', 3, auth_proto: 'INVALID'))->toBe(SNMP_METHOD_BINARY)
		->and(snmp_get_method('walk', 1))->toBe(SNMP_METHOD_BINARY)
		->and(snmp_get_method('get', 1))->toBe(SNMP_METHOD_PHP)
		->and(snmp_get_method('get', 2))->toBe(SNMP_METHOD_PHP);
	expect(snmp_get_method('get', 4))->toBe(SNMP_METHOD_BINARY);

	$port = $timeout = $retries = $max_oids = 0;
	expect(cacti_snmp_options_sanitize(1, 'public', $port, $timeout, $retries, $max_oids))->toBeTrue()
		->and([$port, $timeout, $retries, $max_oids])->toBe([161, 500, 3, 10]);

	$port     = 161;
	$timeout  = 10;
	$retries  = 1;
	$max_oids = 2;
	expect(cacti_snmp_options_sanitize(0, '', $port, $timeout, $retries, $max_oids))->toBeFalse()
		->and(cacti_snmp_options_sanitize(2, '', $port, $timeout, $retries, $max_oids))->toBeFalse()
		->and(cacti_snmp_options_sanitize(3, '', $port, $timeout, $retries, $max_oids))->toBeTrue();

	expect(cacti_get_snmpv3_auth('[None]', 'user', '', '[None]', '', '', ''))->toContain('noAuthNoPriv')
		->and(cacti_get_snmpv3_auth('SHA', 'user', 'secret', '[None]', '', '', ''))->toContain('authNoPriv')
		->and(cacti_get_snmpv3_auth('SHA', 'user', 'secret', 'AES', 'private', 'ctx', 'engine'))->toContain('authPriv')
		->toContain('-n')
		->toContain('-e')
		->and(cacti_get_snmpv3_auth('invalid', 'user', 'secret', '[None]', '', '', ''))->toBe('')
		->and(cacti_get_snmpv3_auth('SHA', 'user', 'secret', 'invalid', 'private', '', ''))->toBe('')
		->and(cacti_get_snmpv3_auth('invalid', 'user', 'secret', 'AES', 'private', '', ''))->toBe('');
});

test('native sessions cover versions and security levels without network I/O', function () : void {
	$GLOBALS['snmp_coverage_config']['oid_increasing_check_disable'] = 'on';

	expect(cacti_snmp_session('127.0.0.1', 'public', '1'))->toBeObject()
		->and(cacti_snmp_session('127.0.0.1', 'public', '2'))->toBeObject()
		->and(cacti_snmp_session('127.0.0.1', '', '3', 'user', '', '[None]', '', '[None]'))->toBeObject()
		->and(cacti_snmp_session('127.0.0.1', '', '3', 'user', 'secretpass', 'SHA', '', '[None]'))->toBeObject()
		->and(cacti_snmp_session('127.0.0.1', '', '3', 'user', 'secretpass', 'SHA', 'privatepass', 'AES'))->toBeObject()
		->and(cacti_snmp_session('127.0.0.1', '', '3', 'user', 'secretpass', 'INVALID', '', '[None]'))->toBeObject()
		->and(cacti_snmp_session('127.0.0.1', 'public', 'invalid'))->toBeFalse();

	// cacti_snmp_session_from_host maps a device row onto the arguments above (#7835).
	expect(cacti_snmp_session_from_host([
		'hostname' => '127.0.0.1', 'snmp_community' => 'public', 'snmp_version' => '2',
	]))->toBeObject()
		->and(cacti_snmp_session_from_host([
			'hostname'             => '127.0.0.1', 'snmp_version' => '3', 'snmp_username' => 'user',
			'snmp_password'        => 'secretpass', 'snmp_auth_protocol' => 'SHA',
			'snmp_priv_passphrase' => 'privatepass', 'snmp_priv_protocol' => 'AES',
		]))->toBeObject()
		->and(cacti_snmp_session_from_host(['hostname' => '127.0.0.1', 'snmp_community' => 'public', 'snmp_version' => 'invalid']))->toBeFalse();
});

test('native and binary get operations cover success and failure results', function () : void {
	$host = getenv('CACTI_SNMP_COVERAGE_HOST') ?: '127.0.0.1';
	$port = (int) (getenv('CACTI_SNMP_COVERAGE_PORT') ?: 21161);
	$oid  = '.1.3.6.1.2.1.1.1.0';

	expect(cacti_snmp_get($host, 'public', $oid, 1, port: $port, timeout_ms: 500, retries: 0))->not->toBe('U')
		->and(cacti_snmp_get($host, 'public', $oid, 2, port: $port, timeout_ms: 500, retries: 0))->not->toBe('U')
		->and(cacti_snmp_get_raw($host, 'public', $oid, 1, port: $port, timeout_ms: 500, retries: 0))->not->toBe('U')
		->and(cacti_snmp_get_raw($host, 'public', $oid, 2, port: $port, timeout_ms: 500, retries: 0))->not->toBe('U')
		->and(cacti_snmp_getnext($host, 'public', '.1.3.6.1.2.1.1', 1, port: $port, timeout_ms: 500, retries: 0))->not->toBe('U')
		->and(cacti_snmp_getnext($host, 'public', '.1.3.6.1.2.1.1', 2, port: $port, timeout_ms: 500, retries: 0))->not->toBe('U');
	set_error_handler(static fn () : bool => true, E_WARNING);

	try {
		$raw_failure     = cacti_snmp_get_raw($host, 'wrong', $oid, 2, port: $port, timeout_ms: 1, retries: 1);
		$getnext_failure = cacti_snmp_getnext($host, 'wrong', $oid, 1, port: $port, timeout_ms: 1, retries: 1);
	} finally {
		restore_error_handler();
	}

	expect(cacti_snmp_get($host, 'public', $oid, 1, port: $port, native_get: fn () => false))->toBe('U')
		->and($raw_failure)->toBe('U')
		->and($getnext_failure)->toBe('U');
	expect(cacti_snmp_get($host, 'public', $oid, 1, port: $port, native_get: function () : never {
		throw new RuntimeException('native failure');
	}))->toBe('U');

	$_SESSION = [];
	expect(cacti_snmp_get($host, 'public', $oid, 3, 'user', '', '[None]', '', '[None]', port: $port))->not->toBe('U')
		->and(cacti_snmp_get_raw($host, 'public', $oid, 3, 'user', '', '[None]', '', '[None]', port: $port))->not->toBe('U')
		->and(cacti_snmp_getnext($host, 'public', $oid, 3, 'user', '', '[None]', '', '[None]', port: $port))->not->toBe('U');

	putenv('CACTI_SNMP_PROBE_MODE=timeout');
	expect(cacti_snmp_get($host, 'public', $oid, 1, port: $port, value_output_format: SNMP_STRING_OUTPUT_HEX))->toBe('U')
		->and(cacti_snmp_get($host, 'public', $oid, 2, port: $port, value_output_format: SNMP_STRING_OUTPUT_HEX))->toBe('U')
		->and(cacti_snmp_get_raw($host, 'public', $oid, 1, port: $port, value_output_format: SNMP_STRING_OUTPUT_HEX))->toBe('U')
		->and(cacti_snmp_get_raw($host, 'public', $oid, 2, port: $port, value_output_format: SNMP_STRING_OUTPUT_HEX))->toBe('U');

	putenv('CACTI_SNMP_PROBE_MODE=error');
	expect(cacti_snmp_getnext($host, 'public', $oid, 1, port: $port, value_output_format: SNMP_STRING_OUTPUT_HEX))->toBe('probe failure')
		->and(cacti_snmp_getnext($host, 'public', $oid, 2, port: $port, value_output_format: SNMP_STRING_OUTPUT_HEX))->toBe('probe failure');

	$port = $timeout = $retries = $max_oids = 0;
	expect(cacti_snmp_get($host, '', $oid, 1, port: $port))->toBe('U')
		->and(cacti_snmp_get_raw($host, '', $oid, 1, port: $port))->toBe('U')
		->and(cacti_snmp_getnext($host, '', $oid, 1, port: $port))->toBe('U')
		->and(cacti_snmp_get($host, 'public', $oid, 4, port: $port))->toBe('U')
		->and(cacti_snmp_get_raw($host, 'public', $oid, 4, port: $port))->toBe('U')
		->and(cacti_snmp_getnext($host, 'public', $oid, 4, port: $port))->toBe('U');
});

test('native and binary walks cover parsing, filtering, and diagnostics', function () : void {
	$host = getenv('CACTI_SNMP_COVERAGE_HOST') ?: '127.0.0.1';
	$port = (int) (getenv('CACTI_SNMP_COVERAGE_PORT') ?: 21161);
	$oid  = '.1.3.6.1.2.1.1';

	$GLOBALS['snmp_coverage_config']['path_snmpbulkwalk'] = '/missing/snmpbulkwalk';
	expect(cacti_snmp_walk($host, 'public', $oid, 1, port: $port, timeout_ms: 500, retries: 1))->not->toBe([])
		->and(cacti_snmp_walk($host, 'public', $oid, 2, port: $port, timeout_ms: 500, retries: 1))->not->toBe([]);
	$GLOBALS['banned_snmp_strings'][] = 'Linux';
	cacti_snmp_walk($host, 'public', $oid, 1, port: $port, timeout_ms: 500, retries: 1);
	array_pop($GLOBALS['banned_snmp_strings']);

	$GLOBALS['snmp_coverage_config']['path_snmpbulkwalk']            = dirname(__DIR__, 3) . '/fixtures/snmp_command_probe.php';
	$GLOBALS['snmp_coverage_config']['oid_increasing_check_disable'] = 'on';
	putenv('CACTI_SNMP_PROBE_MODE=walk');
	$_SESSION = [];
	$bulk     = cacti_snmp_walk($host, 'public', $oid, 2, port: $port, bulk_walk_size: 10);

	$GLOBALS['snmp_coverage_config']['path_snmpbulkwalk'] = '/missing/snmpbulkwalk';
	$regular                                              = cacti_snmp_walk($host, 'public', $oid, 1, port: $port, value_output_format: SNMP_STRING_OUTPUT_HEX);

	putenv('CACTI_SNMP_PROBE_MODE=timeout');
	$timeout = cacti_snmp_walk($host, 'public', $oid, 3, 'user', '', '[None]', '', '[None]', port: $port);
	putenv('CACTI_SNMP_PROBE_MODE=tooBig');
	$too_big = cacti_snmp_walk($host, 'public', $oid, 3, 'user', '', '[None]', '', '[None]', port: $port);

	putenv('CACTI_SNMP_PROBE_MODE=walk');
	$invalid                                                         = cacti_snmp_walk($host, '', $oid, 1, port: $port);
	$GLOBALS['snmp_coverage_config']['oid_increasing_check_disable'] = 'off';
	$without_oid_check                                               = cacti_snmp_walk($host, 'public', $oid, 3, 'user', '', '[None]', '', '[None]', port: $port);

	// An auth protocol outside $snmp_auth_protocols makes cacti_get_snmpv3_auth()
	// return '', which used to build a credential-less snmpwalk -v 3.
	$no_credentials = cacti_snmp_walk($host, 'public', $oid, 3, 'user', 'secret', 'BOGUS', '', '[None]', port: $port);

	expect($bulk[0]['value'])->toContain('coverage agent')
		->and($regular[0]['value'])->toContain('coverage agent')
		->and($timeout)->toBe([])
		->and($too_big)->toBe([])
		->and($without_oid_check)->not->toBe([])
		->and($no_credentials)->toBe([])
		->and($invalid)->toBe([])
		->and(implode(' ', array_column($GLOBALS['snmp_coverage_logs'], 0)))->toContain('exploit attempted')
		// A failed walk is now reported by its exit code. The previous wording
		// came from matching 'Timeout' against the walk output, which only ever
		// matched device data.
		->toContain('Exit Code')
		->toContain('Missing credentials');
});
test('protocol pickers, native tokens, agent formatting, and v3 support gating', function () : void {
	$GLOBALS['snmp_auth_protocols'] = ['MD5' => 'MD5', 'SHA' => 'SHA'];
	$GLOBALS['snmp_priv_protocols'] = ['DES' => 'DES', 'AES' => 'AES'];

	$GLOBALS['snmp_coverage_config']['snmp_md5_des_enabled'] = '';
	expect(snmp_md5_des_enabled())->toBeFalse()
		->and(snmp_auth_protocol_options())->not->toHaveKey('MD5')
		->and(snmp_auth_protocol_options('MD5'))->toHaveKey('MD5')
		->and(snmp_priv_protocol_options())->not->toHaveKey('DES')
		->and(snmp_priv_protocol_options('DES'))->toHaveKey('DES');

	$GLOBALS['snmp_coverage_config']['snmp_md5_des_enabled'] = 'on';
	expect(snmp_md5_des_enabled())->toBeTrue()
		->and(snmp_auth_protocol_options())->toHaveKey('MD5')
		->and(snmp_priv_protocol_options())->toHaveKey('DES');

	expect(snmp_native_protocol('SHA-256'))->toBe('SHA256')
		->and(snmp_native_protocol('AES-256-C'))->toBe('AES256C');

	expect(snmp_format_agent('udp:device', 161))->toBe('udp:device')
		->and(snmp_format_agent('[2001:db8::1]', 161))->toBe('[2001:db8::1]:161')
		->and(snmp_format_agent('[2001:db8::1]:1161', 500))->toBe('[2001:db8::1]:1161')
		->and(snmp_format_agent('2001:db8::1', 1161))->toBe('[2001:db8::1]:1161')
		->and(snmp_format_agent('127.0.0.1', 161))->toBe('127.0.0.1')
		->and(snmp_format_agent('127.0.0.1', 1161))->toBe('127.0.0.1:1161')
		->and(snmp_format_agent('unix:/var/agentx/master', 1161))->toBe('unix:/var/agentx/master')
		->and(snmp_format_agent('udp6:[2001:db8::1]', 1161))->toBe('udp6:[2001:db8::1]:1161')
		->and(snmp_format_agent('udp6:[2001:db8::1]:1161', 500))->toBe('udp6:[2001:db8::1]:1161')
		->and(snmp_format_agent('udp:device:1161', 500))->toBe('udp:device:1161')
		->and(snmp_format_agent('udp:device', 1161))->toBe('udp:device:1161');

	expect(snmp_php_v3_protocols_supported('SHA', 'AES'))->toBeTrue()
		->and(snmp_php_v3_protocols_supported('[None]', '[None]'))->toBeTrue()
		->and(snmp_php_v3_protocols_supported('BOGUS', '[None]'))->toBeFalse()
		->and(snmp_php_v3_protocols_supported('[None]', 'AES'))->toBeFalse();
});

test('v3 credential argument hardening and cache map construction', function () : void {
	$GLOBALS['snmp_auth_protocols'] = ['SHA' => 'SHA'];
	$GLOBALS['snmp_priv_protocols'] = ['AES' => 'AES'];

	expect(snmp_build_v3_cred_args('[None]', 'user', '', '[None]', ''))->toBe(['-u', 'user', '-l', 'noAuthNoPriv'])
		->and(snmp_build_v3_cred_args('SHA', 'user', 'secret', '[None]', ''))->toBe(['-u', 'user', '-a', 'SHA', '-A', 'secret', '-l', 'authNoPriv'])
		->and(snmp_build_v3_cred_args('BOGUS', 'user', 'secret', '[None]', ''))->toBe([])
		->and(snmp_build_v3_cred_args('SHA', 'user', 'secret', 'AES', 'priv'))->toBe(['-u', 'user', '-a', 'SHA', '-A', 'secret', '-x', 'AES', '-X', 'priv', '-l', 'authPriv'])
		->and(snmp_build_v3_cred_args('BOGUS', 'user', 'secret', 'AES', 'priv'))->toBe([]);

	$key = snmp_auth_cache_key('', 'user', 'secret', 'SHA', '', '[None]');
	expect($key)->toBe(snmp_auth_cache_key('', 'user', 'secret', 'SHA', '', '[None]'))
		->and(strlen($key))->toBe(40);

	$rows = [
		['snmp_username' => '', 'snmp_community' => 'public'],
		['snmp_username' => 'user', 'snmp_community' => '', 'snmp_password' => 'secret', 'snmp_auth_protocol' => 'SHA', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '[None]'],
		['snmp_username' => 'user', 'snmp_community' => '', 'snmp_password' => 'secret', 'snmp_auth_protocol' => 'SHA', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '[None]'],
	];
	expect(snmp_auth_cache_build_map($rows))->toHaveCount(1);
});

test('shared SNMP auth cache lifecycle', function () : void {
	$dir = sys_get_temp_dir() . '/snmpcov_' . uniqid();
	mkdir($dir);
	$GLOBALS['config']['cache_dir']     = $dir;
	$GLOBALS['snmp_auth_protocols']     = ['SHA' => 'SHA'];
	$GLOBALS['snmp_priv_protocols']     = ['AES' => 'AES'];
	$GLOBALS['snmp_coverage_host_rows'] = [
		['snmp_community' => '', 'snmp_username' => 'user', 'snmp_password' => 'secret', 'snmp_auth_protocol' => 'SHA', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '[None]'],
	];
	$GLOBALS['snmp_coverage_item_rows'] = [];

	unset($GLOBALS['snmp_coverage_config']['snmp_cred_version']);
	$version = snmp_cred_version();
	expect($version)->toBeString()->not->toBe('')
		->and(snmp_cred_version())->toBe($version)
		->and(snmp_auth_cache_rows())->toHaveCount(1)
		->and(snmp_auth_cache_build())->toHaveCount(1);

	// disabled: refresh/rebuild short-circuit; load yields an empty map
	$GLOBALS['snmp_coverage_config']['snmp_credential_cache'] = '';
	expect(snmp_auth_cache_enabled())->toBeFalse();
	snmp_auth_cache_refresh();
	snmp_auth_cache_rebuild();
	unset($GLOBALS['snmp_auth_cache_loaded'], $GLOBALS['snmp_auth_cache_map']);
	snmp_auth_cache_load();
	expect($GLOBALS['snmp_auth_cache_map'])->toBe([]);

	// enabled + empty cache: load() builds from the database (fallback branch)
	$GLOBALS['snmp_coverage_config']['snmp_credential_cache'] = 'on';
	snmp_auth_cache()->invalidate();
	unset($GLOBALS['snmp_auth_cache_loaded'], $GLOBALS['snmp_auth_cache_map']);
	snmp_auth_cache_load();
	expect($GLOBALS['snmp_auth_cache_map'])->toHaveCount(1);

	// refresh seals it (first stores, second short-circuits on checksum); rebuild forces it
	snmp_auth_cache_refresh();
	snmp_auth_cache_refresh();
	snmp_auth_cache_rebuild();

	// load() now decodes the sealed cache instead of rebuilding
	unset($GLOBALS['snmp_auth_cache_loaded'], $GLOBALS['snmp_auth_cache_map']);
	snmp_auth_cache_load();
	expect($GLOBALS['snmp_auth_cache_map'])->toHaveCount(1);

	// cred lookup: a matching tuple hits, an unknown tuple misses
	expect(snmp_auth_cache_cred_lookup('', 'user', 'secret', 'SHA', '', '[None]'))->toBeArray()
		->and(snmp_auth_cache_cred_lookup('', 'nobody', '', 'SHA', '', '[None]'))->toBeNull();

	// cred lookup also seeds the map itself when it has not been loaded
	unset($GLOBALS['snmp_auth_cache_loaded'], $GLOBALS['snmp_auth_cache_map']);
	expect(snmp_auth_cache_cred_lookup('', 'user', 'secret', 'SHA', '', '[None]'))->toBeArray();

	// a second load() short-circuits on the process guard
	snmp_auth_cache_load();
});

test('binary-delegating session routes through the Net-SNMP command path', function () : void {
	$host = getenv('CACTI_SNMP_COVERAGE_HOST') ?: '127.0.0.1';
	$port = (int) (getenv('CACTI_SNMP_COVERAGE_PORT') ?: 21161);

	putenv('CACTI_SNMP_PROBE_MODE=get');
	$session = cacti_snmp_session($host, '', '3', 'user', 'secret', 'INVALID', '', '[None]', port: $port);
	expect($session)->toBeObject();

	expect($session->get('.1.3.6.1.2.1.1.1.0'))->toBeString()
		->and($session->get(['.1.3.6.1.2.1.1.1.0']))->toBeArray()
		->and($session->getnext('.1.3.6.1.2.1.1.1.0'))->toBeString()
		->and($session->walk(['.1']))->toBeFalse()
		->and($session->close())->toBeTrue()
		->and($session->getErrno())->toBe(0)
		->and($session->getError())->toBe('');

	putenv('CACTI_SNMP_PROBE_MODE=walk');
	expect($session->walk('.1.3.6.1.2.1.1'))->toBeArray();

	// A v2 binary session walk returns parsed entries, exercising the result map.
	$v2 = new CactiSnmpBinarySession([
		'hostname'   => $host, 'community' => 'public', 'version' => '2',
		'auth_user'  => '', 'auth_pass' => '', 'auth_proto' => '', 'priv_pass' => '',
		'priv_proto' => '', 'context' => '', 'engineid' => '',
		'port'       => $port, 'timeout' => 500, 'retries' => 0,
	], 10);
	expect($v2->walk('.1.3.6.1.2.1.1'))->toBeArray()->not->toBe([]);
});

test('multi-OID get batches, falls back, and maps results', function () : void {
	$host = getenv('CACTI_SNMP_COVERAGE_HOST') ?: '127.0.0.1';
	$port = (int) (getenv('CACTI_SNMP_COVERAGE_PORT') ?: 21161);
	$oids = ['.1.3.6.1.2.1.1.1.0', '.1.3.6.1.2.1.1.3.0'];

	putenv('CACTI_SNMP_PROBE_MODE=get');
	expect(cacti_snmp_get_multi($host, 'public', $oids, 2, port: $port, max_oids: 1))->toHaveCount(2)
		->and(cacti_snmp_get_multi($host, 'public', $oids, 1, port: $port))->toHaveCount(2)
		->and(cacti_snmp_get_multi($host, '', $oids, 3, 'user', '', '[None]', '', '[None]', port: $port))->toHaveCount(2)
		->and(cacti_snmp_get_multi($host, 'public', '.1.3.6.1.2.1.1.1.0', 1, port: $port, value_output_format: SNMP_STRING_OUTPUT_HEX))->toHaveCount(1)
		->and(cacti_snmp_get_multi($host, 'public', [], 1, port: $port))->toBe([]);

	$fail = cacti_snmp_get_multi($host, 'wrong', $oids, 2, port: $port, timeout_ms: 1, retries: 0);
	expect($fail['.1.3.6.1.2.1.1.1.0'])->toBe('U');
});

test('v3 authNoPriv and authPriv exercise every security-level branch', function () : void {
	$host = getenv('CACTI_SNMP_COVERAGE_HOST') ?: '127.0.0.1';
	$port = (int) (getenv('CACTI_SNMP_COVERAGE_PORT') ?: 21161);
	$oid  = '.1.3.6.1.2.1.1.1.0';

	$GLOBALS['snmp_auth_protocols']                           = ['SHA' => 'SHA'];
	$GLOBALS['snmp_priv_protocols']                           = ['AES' => 'AES', 'DES' => 'DES'];
	$GLOBALS['snmp_coverage_config']['snmp_credential_cache'] = '';

	// The fixture only serves a noAuthNoPriv user, so authNoPriv/authPriv requests
	// return 'U' but still drive the native sec_level branches. A privacy protocol
	// the running net-snmp rejects (DES on AES-only builds) makes snmp3_*() throw,
	// which the native try/catch maps to 'U'.
	foreach (['cacti_snmp_get', 'cacti_snmp_get_raw', 'cacti_snmp_getnext'] as $fn) {
		expect($fn($host, '', $oid, 3, 'user', 'secretpass', 'SHA', '', '[None]', port: $port))->toBe('U')
			->and($fn($host, '', $oid, 3, 'user', 'secretpass', 'SHA', 'privpass1', 'AES', port: $port))->toBe('U');

		// hex output forces the Net-SNMP command path through cacti_get_snmpv3_auth
		putenv('CACTI_SNMP_PROBE_MODE=get');
		$fn($host, '', $oid, 3, 'user', 'secretpass', 'SHA', '', '[None]', port: $port, value_output_format: SNMP_STRING_OUTPUT_HEX);
	}

	// A native getter that throws drives the try/catch of each getter portably,
	// independent of whether the running net-snmp rejects a given protocol.
	$boom = function () : never { throw new RuntimeException('native failure'); };
	expect(cacti_snmp_get_raw($host, 'public', $oid, 1, port: $port, native_get: $boom))->toBe('U')
		->and(cacti_snmp_getnext($host, 'public', $oid, 1, port: $port, native_get: $boom))->toBe('U')
		->and(cacti_snmp_get_multi($host, 'public', [$oid], 1, port: $port, native_get: $boom))->toBe([$oid => 'U']);

	// native multi-get: authPriv success branch + sanitize failure
	expect(cacti_snmp_get_multi($host, '', [$oid], 3, 'user', 'secretpass', 'SHA', 'privpass1', 'AES', port: $port))->toHaveCount(1)
		->and(cacti_snmp_get_multi($host, '', [$oid], 0, port: $port))->toBe([]);

	// result association without depending on MIB availability: numeric output makes
	// a leading-dot request key-match verbatim, a dot-less request (net-snmp
	// normalizes the returned key to a leading dot) falls through to the positional
	// slot, and a duplicate dot-less OID exhausts it for the miss path.
	snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
	expect(cacti_snmp_get_multi($host, 'public', ['.1.3.6.1.2.1.1.1.0'], 2, port: $port))->toHaveCount(1)
		->and(cacti_snmp_get_multi($host, 'public', ['1.3.6.1.2.1.1.1.0'], 2, port: $port))->toHaveCount(1)
		->and(cacti_snmp_get_multi($host, 'public', ['1.3.6.1.2.1.1.1.0', '1.3.6.1.2.1.1.1.0'], 2, port: $port))->toHaveCount(1);
});

test('cached v3 auth assembly reuses pre-hardened credential args', function () : void {
	$GLOBALS['snmp_auth_protocols']                           = ['SHA' => 'SHA'];
	$GLOBALS['snmp_priv_protocols']                           = ['AES' => 'AES'];
	$GLOBALS['snmp_coverage_config']['snmp_credential_cache'] = 'on';
	$GLOBALS['snmp_auth_cache_loaded']                        = true;
	$GLOBALS['snmp_auth_cache_map']                           = [];

	// not cached -> builds live, then appends context + engine id
	expect(cacti_get_snmpv3_auth('SHA', 'user', 'secret', 'AES', 'priv', 'ctx', 'engine'))
		->toContain('-n')->toContain('-e');

	// a cached tuple is reused verbatim
	$key                                  = snmp_auth_cache_key('', 'user', 'secret', 'SHA', '', '[None]');
	$GLOBALS['snmp_auth_cache_map'][$key] = ['-u', 'user', '-a', 'SHA', '-A', 'secret', '-l', 'authNoPriv'];
	expect(cacti_get_snmpv3_auth('SHA', 'user', 'secret', '[None]', '', '', ''))->toContain('authNoPriv');

	// an unknown protocol builds an empty arg list -> ''
	expect(cacti_get_snmpv3_auth('BOGUS', 'user', 'secret', '[None]', '', '', ''))->toBe('');

	$GLOBALS['snmp_coverage_config']['snmp_credential_cache'] = '';
});
