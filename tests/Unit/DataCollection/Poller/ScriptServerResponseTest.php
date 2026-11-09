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
 * discarded and line breaks inside its result are removed.  The production
 * functions are loaded unchanged into a fixture namespace so the Script
 * Server's main loop never runs.
 */

namespace CactiScriptServerResponseFixture;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

$GLOBALS['ss_response_log'] = [];

function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['ss_response_log'][] = $string;
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
	foreach (['script_server_call', 'script_server_response'] as $function) {
		\cacti_fixture_source_function(dirname(__DIR__, 4) . '/script_server.php', $function, __NAMESPACE__);
	}
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

test('the main loop writes the single line response', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/script_server.php');

	expect($source)->toContain('fputs(STDOUT, script_server_response($result) . "\n");')
		->and($source)->not->toContain('fputs(STDOUT, trim($result)');
});
