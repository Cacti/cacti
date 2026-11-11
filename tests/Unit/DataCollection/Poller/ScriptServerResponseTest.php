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
 * request must be exactly one line: output printed by the script function is
 * discarded, line breaks inside its result are removed and a request that
 * cannot be parsed prints nothing ahead of its U reply and is logged without
 * its arguments.  Every log line that names the arguments masks the device
 * credentials among them.  The production
 * functions are loaded unchanged into a fixture namespace so the Script
 * Server's main loop never runs.
 */

namespace CactiScriptServerResponseFixture;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

$GLOBALS['ss_response_log'] = [];

function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['ss_response_log'][] = $string;
}

function db_fetch_assoc($sql) {
	return [
		['snmp_community' => 'c0mmunity', 'snmp_password' => 'authpass', 'snmp_priv_passphrase' => ''],
	];
}

function ss_response_fixture_echo($a) {
	print "community $a\n";

	return "value:$a";
}

function ss_response_fixture_multiline() {
	return "a:1\nb:2";
}

function ss_response_fixture_echo_throws() {
	print 'partial output';
	ob_start();
	print 'nested buffer left open';

	throw new \RuntimeException('script failure');
}

if (!function_exists(__NAMESPACE__ . '\\script_server_call')) {
	foreach (['parseArgs', 'script_server_log_parse_failure', 'script_server_log_missing_function', 'script_server_call', 'script_server_response', 'sig_handler'] as $function) {
		\cacti_fixture_source_function(dirname(__DIR__, 4) . '/script_server.php', $function, __NAMESPACE__);
	}

	foreach (['script_server_log_request', 'script_server_redact_params'] as $function) {
		\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/poller.php', $function, __NAMESPACE__);
	}
}

const SS_RESPONSE_PARAMS = ['host1', 'c0mmunity', '3:161:500:1:10::user:authpass:SHA'];

function ss_response_expect_masked(string $log) : void {
	expect($log)->not->toContain('c0mmunity')
		->and($log)->not->toContain('authpass')
		->and($log)->toContain("'host1', '********', '3:161:500:1:10::user:********:SHA'");
}

beforeEach(function () {
	$GLOBALS['ss_response_log'] = [];
});

test('output printed by a script is discarded and only its size is logged', function () {
	$level = ob_get_level();

	ob_start();
	$result  = script_server_call(__NAMESPACE__ . '\\ss_response_fixture_echo', 'secret-value', ['secret-value']);
	$printed = ob_get_clean();

	expect($printed)->toBe('')
		->and($result)->toBe('value:secret-value')
		->and(ob_get_level())->toBe($level);

	$log = implode("\n", $GLOBALS['ss_response_log']);

	expect($log)->toContain('wrote 23 bytes of output, discarded')
		->and($log)->not->toContain('community')
		->and($log)->not->toContain('secret-value');
});

test('output of a script that throws is discarded with any buffer it left open', function () {
	$level = ob_get_level();

	ob_start();

	try {
		script_server_call(__NAMESPACE__ . '\\ss_response_fixture_echo_throws', '', []);
		$thrown = false;
	} catch (\RuntimeException $e) {
		$thrown = true;
	}

	$printed = ob_get_clean();

	expect($thrown)->toBeTrue()
		->and($printed)->toBe('')
		->and(ob_get_level())->toBe($level);
});

test('a script that prints nothing logs nothing', function () {
	expect(script_server_call(__NAMESPACE__ . '\\ss_response_fixture_multiline', '', []))->toBe("a:1\nb:2")
		->and($GLOBALS['ss_response_log'])->toBe([]);
});

test('a multipart result with line breaks is sent as one line', function () {
	$line = script_server_response(script_server_call(__NAMESPACE__ . '\\ss_response_fixture_multiline', '', [])) . "\n";

	expect($line)->toBe("a:1 b:2\n")
		->and(script_server_response("\r\na:1\r\nb:2\rc:3 \n"))->toBe('a:1 b:2 c:3');
});

test('scalar and empty results keep their response text', function () {
	expect(script_server_response(42))->toBe('42')
		->and(script_server_response(1.5))->toBe('1.5')
		->and(script_server_response(null))->toBe('')
		->and(script_server_response(' U '))->toBe('U');
});

test('a non-scalar result is sent as U without a warning', function () {
	// a script function returning an array or object has no single value the
	// poller can store; (string) on it would warn or throw
	set_error_handler(function (int $errno, string $errstr) : bool {
		throw new \ErrorException($errstr, 0, $errno);
	});

	try {
		expect(script_server_response(['a' => 1]))->toBe('U')
			->and(script_server_response(new \stdClass()))->toBe('U');
	} finally {
		restore_error_handler();
	}
});

test('the main loop writes the single line response', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/script_server.php');

	expect($source)->toContain('fputs(STDOUT, script_server_response($result) . "\n");')
		->and($source)->not->toContain('fputs(STDOUT, trim($result)');
});

test('an unparsable request writes nothing ahead of the U reply', function () {
	$parsed = [];

	ob_start();
	$ok      = parseArgs("'open", $parsed);
	$printed = ob_get_clean();

	expect($ok)->toBeFalse()
		->and($printed)->toBe('');
});

test('a parse error is still printed when debugging', function () {
	$parsed = [];

	ob_start();
	$ok      = parseArgs("'open", $parsed, true);
	$printed = ob_get_clean();

	expect($ok)->toBeFalse()
		->and($printed)->toContain("ERROR: Parse error attempting to parse string ''open'");
});

test('an unparsable argument list is logged by size, never by content', function () {
	$parameters = "'host1' 'c0mmunity";

	script_server_log_parse_failure('ss_example', $parameters);

	$log = implode("\n", $GLOBALS['ss_response_log']);

	expect($log)->not->toContain($parameters)
		->and($log)->not->toContain('c0mmunity')
		->and($log)->toContain('could not parse the ' . strlen($parameters) . ' byte argument list for ss_example');
});

test('the debug request log masks device credentials', function () {
	global $config;

	$saved = $config[OPTIONS_CLI] ?? null;

	$config[OPTIONS_CLI]['selective_debug']        = '';
	$config[OPTIONS_CLI]['selective_plugin_debug'] = '';
	$config[OPTIONS_CLI]['log_verbosity']          = POLLER_VERBOSITY_DEBUG;

	try {
		script_server_log_request(1234, 1, '/scripts/ss_example.php', 'ss_example', SS_RESPONSE_PARAMS);
	} finally {
		$config[OPTIONS_CLI] = $saved;
	}

	ss_response_expect_masked(implode("\n", $GLOBALS['ss_response_log']));
});

test('the missing function warning masks device credentials', function () {
	script_server_log_missing_function('/scripts/ss_example.php', 'ss_missing', SS_RESPONSE_PARAMS);

	$log = implode("\n", $GLOBALS['ss_response_log']);

	ss_response_expect_masked($log);
	expect($log)->toContain("FUNC: 'ss_missing'");
});

test('a signal logs the argument count, never the arguments', function () {
	$saved = [$GLOBALS['include_file'] ?? null, $GLOBALS['function'] ?? null, $GLOBALS['parameters'] ?? null, $GLOBALS['parameter_array'] ?? null];

	$GLOBALS['include_file']    = '/scripts/ss_example.php';
	$GLOBALS['function']        = 'ss_example';
	$GLOBALS['parameters']      = "'host1' 'c0mmunity' '3:161:500:1:10::user:authpass:SHA'";
	$GLOBALS['parameter_array'] = SS_RESPONSE_PARAMS;

	try {
		sig_handler(SIGUSR1);
	} finally {
		[$GLOBALS['include_file'], $GLOBALS['function'], $GLOBALS['parameters'], $GLOBALS['parameter_array']] = $saved;
	}

	$log = implode("\n", $GLOBALS['ss_response_log']);

	expect($log)->not->toContain('c0mmunity')
		->and($log)->not->toContain('authpass')
		->and($log)->toContain("function:'ss_example', params:'3 argument(s)'");
})->skip(!defined('SIGUSR1'), 'pcntl is not available');

test('credentials are masked whole and as colon separated fields', function () {
	$secrets = ['c0mmunity' => true, 'authpass' => true];

	expect(script_server_redact_params(['host1', 'c0mmunity', '3:161:500:1:10::user:authpass:SHA', 'index', 42], $secrets))
		->toBe(['host1', '********', '3:161:500:1:10::user:********:SHA', 'index', 42])
		->and(script_server_redact_params(['xc0mmunityx', ''], $secrets))->toBe(['xc0mmunityx', '']);
});
