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

/*
 * The poller reads one line per Script Server request, so the response to a
 * request must be exactly one line: line breaks inside a result become spaces
 * and a request that cannot be parsed prints nothing ahead of its U reply and
 * is never logged with its arguments.  Every log line that names the arguments
 * masks the device credentials among them.  Output printed by a script function
 * is discarded by the worker's output guard, which the live worker tests in
 * ScriptServerProcessTest cover.  The production functions are loaded
 * unchanged into a fixture namespace so the Script Server's main loop never
 * runs.
 */

namespace CactiScriptServerResponseFixture;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 4) . '/lib/script_server.php';

$GLOBALS['ss_response_log'] = [];

function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['ss_response_log'][] = $string;
}

function db_fetch_assoc($sql) {
	return [
		['snmp_community' => 'c0mmunity', 'snmp_password' => 'authpass', 'snmp_priv_passphrase' => ''],
	];
}

function ss_response_fixture_multiline() {
	return "a:1\nb:2";
}

if (!function_exists(__NAMESPACE__ . '\\script_server_run')) {
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/script_server.php', 'sig_handler', __NAMESPACE__);
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/script_server.php', 'script_server_run', __NAMESPACE__);

	foreach (['script_server_log_request', 'script_server_redact_params'] as $function) {
		\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/poller.php', $function, __NAMESPACE__);
	}
}

const SS_RESPONSE_PARAMS = ['host1', 'c0mmunity', '3:161:500:1:10::user:authpass:SHA'];

function ss_response_expect_masked(string $log) : void {
	expect($log)->not->toContain('c0mmunity')
		->and($log)->not->toContain('authpass')
		->and($log)->not->toContain('user');
}

beforeEach(function () {
	$GLOBALS['ss_response_log'] = [];
});

test('a multipart result with line breaks is sent as one line', function () {
	expect(\script_server_dispatch(__NAMESPACE__ . '\\ss_response_fixture_multiline', []))->toBe('a:1 b:2')
		->and(\script_server_normalize_response("\r\na:1\r\nb:2\rc:3 \n"))->toBe('a:1 b:2 c:3');
});

test('scalar results keep their response text and an empty one is U', function () {
	expect(\script_server_normalize_response(42))->toBe('42')
		->and(\script_server_normalize_response(1.5))->toBe('1.5')
		->and(\script_server_normalize_response(null))->toBe('U')
		->and(\script_server_normalize_response(' U '))->toBe('U');
});

test('an unparsable request writes nothing ahead of the U reply', function () {
	$parsed = [];

	ob_start();
	$ok      = \parseArgs("'open", $parsed);
	$printed = ob_get_clean();

	expect($ok)->toBeFalse()
		->and($printed)->toBe('');
});

test('a parse error is still printed when debugging', function () {
	$parsed = [];

	ob_start();
	$ok      = \parseArgs("'open", $parsed, true);
	$printed = ob_get_clean();

	expect($ok)->toBeFalse()
		->and($printed)->toContain('ERROR: Parse error attempting to parse string');
});

test('an unparsable argument list is refused without logging its content', function () {
	$request = dirname(__DIR__, 4) . "/scripts/ss_example.php ss_example 'host1' 'c0mmunity";

	expect(\script_server_validate_request($request, \script_server_allowed_roots()))->toBeFalse()
		->and(implode("\n", $GLOBALS['ss_response_log']))->not->toContain('c0mmunity');
});

test('the debug request log masks device credentials', function () {
	script_server_log_request(1234, 1, '/scripts/ss_example.php', 'ss_example', SS_RESPONSE_PARAMS, true);

	$log = implode("\n", $GLOBALS['ss_response_log']);

	ss_response_expect_masked($log);
	expect($log)->toContain("'host1', '********', '3:161:500:1:10:<redacted>'");
});

test('the missing function warning never logs the arguments', function () {
	expect(script_server_run('ss_missing', SS_RESPONSE_PARAMS, '/scripts/ss_example.php'))->toBe('U');

	$log = implode("\n", $GLOBALS['ss_response_log']);

	ss_response_expect_masked($log);
	expect($log)->toContain("Function 'ss_missing' is not defined");
});

test('a signal logs the argument count, never the arguments', function () {
	$saved = [$GLOBALS['include_file'] ?? null, $GLOBALS['function'] ?? null, $GLOBALS['parameter_array'] ?? null];

	$GLOBALS['include_file']    = '/scripts/ss_example.php';
	$GLOBALS['function']        = 'ss_example';
	$GLOBALS['parameter_array'] = SS_RESPONSE_PARAMS;

	try {
		sig_handler(SIGUSR1);
	} finally {
		[$GLOBALS['include_file'], $GLOBALS['function'], $GLOBALS['parameter_array']] = $saved;
	}

	$log = implode("\n", $GLOBALS['ss_response_log']);

	ss_response_expect_masked($log);
	expect($log)->toContain("file:'ss_example.php', function:'ss_example', params:'3 argument(s)'");
})->skip(!defined('SIGUSR1'), 'pcntl is not available');

test('credentials are masked whole and as colon separated fields', function () {
	$secrets = ['c0mmunity' => true, 'authpass' => true];

	expect(script_server_redact_params(['host1', 'c0mmunity', '3:161:500:1:10::user:authpass:SHA', 'index', 42], $secrets))
		->toBe(['host1', '********', '3:161:500:1:10::user:********:SHA', 'index', 42])
		->and(script_server_redact_params(['xc0mmunityx', ''], $secrets))->toBe(['xc0mmunityx', '']);
});
