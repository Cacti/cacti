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
	'path_snmpget'                 => dirname(__DIR__) . '/fixtures/snmp_command_probe.php',
	'path_snmpgetnext'             => dirname(__DIR__) . '/fixtures/snmp_command_probe.php',
	'path_snmpwalk'                => dirname(__DIR__) . '/fixtures/snmp_command_probe.php',
	'path_snmpbulkwalk'            => dirname(__DIR__) . '/fixtures/snmp_command_probe.php'
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

function debug_log_insert(string $category, string $message) : void {
	$GLOBALS['snmp_coverage_debug'][] = [$category, $message];
}

function __esc(string $message, mixed ...$args) : string {
	return $args === [] ? $message : vsprintf($message, $args);
}

function exec_into_array(string $command) : array {
	$output = [];
	exec($command, $output);

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

require_once dirname(__DIR__, 4) . '/lib/snmp.php';

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
	$GLOBALS['snmp_coverage_config']['path_snmpbulkwalk']            = dirname(__DIR__) . '/fixtures/snmp_command_probe.php';
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

	$session->errno = phpsnmp\SNMP::ERRNO_TIMEOUT;
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
		->and(snmp_escape_string('a"b', 'win32'))->toBe('"a\\"b"')
		->and(snmp_escape_string('public', 'win32'))->toBe("'public'")
		->and(snmp_get_method('get', 1, '', '', SNMP_STRING_OUTPUT_GUESS, false))->toBe(SNMP_METHOD_BINARY)
		->and(snmp_get_method('get', 1, '', '', SNMP_STRING_OUTPUT_HEX))->toBe(SNMP_METHOD_BINARY)
		->and(snmp_get_method('get', 3))->toBe(SNMP_METHOD_BINARY)
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
		->and(cacti_snmp_session('127.0.0.1', '', '3', 'user', 'secretpass', 'INVALID', '', '[None]'))->toBeFalse()
		->and(cacti_snmp_session('127.0.0.1', 'public', 'invalid'))->toBeFalse();
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
	expect(cacti_snmp_get($host, 'public', $oid, 3, 'user', '', '[None]', '', '[None]', port: $port))->toBe('coverage value')
		->and(cacti_snmp_get_raw($host, 'public', $oid, 3, 'user', '', '[None]', '', '[None]', port: $port))->toContain('coverage value')
		->and(cacti_snmp_getnext($host, 'public', $oid, 3, 'user', '', '[None]', '', '[None]', port: $port))->toBe('coverage value');

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

	$GLOBALS['snmp_coverage_config']['path_snmpbulkwalk']            = dirname(__DIR__) . '/fixtures/snmp_command_probe.php';
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

	expect($bulk[0]['value'])->toContain('coverage agent')
		->and($regular[0]['value'])->toContain('coverage agent')
		->and($timeout)->toBe([])
		->and($too_big)->toBe([])
		->and($without_oid_check)->not->toBe([])
		->and($invalid)->toBe([])
		->and(implode(' ', array_column($GLOBALS['snmp_coverage_logs'], 0)))->toContain('exploit attempted')
		->toContain('Timeout');
});
