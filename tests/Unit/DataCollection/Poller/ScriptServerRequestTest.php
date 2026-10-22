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
 * Script Server request framing and argument parsing. The poller writes one
 * request per line and the server must never read a fragment of an overlong
 * line as a request of its own.
 */

require_once dirname(__DIR__, 4) . '/lib/poller.php';
require_once dirname(__DIR__, 4) . '/lib/script_server.php';

function ss_request_stream(string $contents) : mixed {
	$stream = fopen('php://memory', 'w+');
	fwrite($stream, $contents);
	rewind($stream);

	return $stream;
}

test('the reader and writer share one request limit that matches the spine buffer', function () {
	// spine formats "%s\r\n" into a 1024 byte buffer, leaving 1021 bytes of request
	expect(SCRIPT_SERVER_MAX_REQUEST_LENGTH)->toBe(1021);
});

test('a request within the limit is returned whole', function () {
	$stream = ss_request_stream("/path/script.php ss_func 'a'\r\nquit\r\n");

	[$line, $overlong] = script_server_read_request($stream);

	expect($line)->toBe("/path/script.php ss_func 'a'\r\n")
		->and($overlong)->toBeFalse();

	[$line, $overlong] = script_server_read_request($stream);

	expect($line)->toBe("quit\r\n")->and($overlong)->toBeFalse();
});

test('a request of exactly the limit is accepted', function () {
	$request = str_repeat('a', SCRIPT_SERVER_MAX_REQUEST_LENGTH);
	$stream  = ss_request_stream($request . "\r\n");

	[$line, $overlong] = script_server_read_request($stream);

	expect(rtrim($line))->toBe($request)->and($overlong)->toBeFalse();
});

test('an overlong request is discarded through its line ending and the next request survives', function () {
	$stream = ss_request_stream(str_repeat('x', 5000) . "\r\nnext request\r\n");

	[, $overlong] = script_server_read_request($stream);

	expect($overlong)->toBeTrue();

	[$line, $overlong] = script_server_read_request($stream);

	expect($line)->toBe("next request\r\n")->and($overlong)->toBeFalse();
});

test('a request one byte over the limit whose LF fits the buffer is still refused', function () {
	$stream = ss_request_stream(str_repeat('y', SCRIPT_SERVER_MAX_REQUEST_LENGTH + 1) . "\n");

	[, $overlong] = script_server_read_request($stream);

	expect($overlong)->toBeTrue();
});

test('an unterminated overlong request does not wait for a line ending that never comes', function () {
	// spine truncates an overlong request without CR LF and then waits for the reply
	$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

	fwrite($pair[0], str_repeat('z', 1023));

	$start = microtime(true);

	[, $overlong] = script_server_read_request($pair[1], SCRIPT_SERVER_MAX_REQUEST_LENGTH, 0.1);

	expect($overlong)->toBeTrue()
		->and(microtime(true) - $start)->toBeLessThan(2.0);

	fwrite($pair[0], "after\r\n");

	[$line] = script_server_read_request($pair[1]);

	expect($line)->toBe("after\r\n");

	fclose($pair[0]);
	fclose($pair[1]);
})->skip(!function_exists('stream_socket_pair') || DIRECTORY_SEPARATOR === '\\', 'needs a unix socket pair');

test('end of input is reported as no request', function () {
	[$line, $overlong] = script_server_read_request(ss_request_stream(''));

	expect($line)->toBeFalse()->and($overlong)->toBeFalse();
});

test('exec_poll_php refuses a command longer than the reader accepts', function () {
	$proc = proc_open(['cat'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

	expect(exec_poll_php(str_repeat('a', SCRIPT_SERVER_MAX_REQUEST_LENGTH + 1), true, $pipes, $proc))->toBe('U');

	// nothing was written, so the next request is answered in step
	expect(trim(exec_poll_php('ping', true, $pipes, $proc)))->toBe('ping');

	fclose($pipes[0]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($proc);
});

test('exec_poll_php refuses a command with an embedded line break', function () {
	$proc = proc_open(['cat'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

	expect(exec_poll_php("first\nsecond", true, $pipes, $proc))->toBe('U');
	expect(trim(exec_poll_php(str_repeat('b', SCRIPT_SERVER_MAX_REQUEST_LENGTH), true, $pipes, $proc)))
		->toBe(str_repeat('b', SCRIPT_SERVER_MAX_REQUEST_LENGTH));

	fclose($pipes[0]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($proc);
});

/*
 * parseArgs() must undo cacti_escapeshellarg(), which on Unix produces POSIX
 * single quoting with '\'' for an embedded apostrophe.
 */
dataset('script server arguments', [
	'apostrophe'    => ["it's"],
	'two quotes'    => ["a'b'c"],
	'only quote'    => ["'"],
	'spaces'        => ['public community'],
	'double quotes' => ['say "hi"'],
	'colons'        => ['2:161:500:1:10:pub:lic'],
	'backslash'     => ['a\\b'],
	'empty'         => [''],
]);

test('an escaped argument parses back to the same single argument', function (string $value) {
	$args = [];

	expect(parseArgs("'host' " . escapeshellarg($value) . " 'get'", $args))->toBeTrue()
		->and($args)->toBe(['host', $value, 'get']);
})->with('script server arguments');

test('the Windows escaping form parses back to one argument', function () {
	$args = [];

	expect(parseArgs('"say \\"hi\\"" "C:\\cacti"', $args))->toBeTrue()
		->and($args)->toBe(['say "hi"', 'C:\\cacti']);
});

test('unquoted arguments keep the simple split', function () {
	$args = [];

	expect(parseArgs('a b  c', $args))->toBeTrue()->and($args)->toBe(['a', 'b', '', 'c']);
});

test('an escaped quote outside quotes is literal', function () {
	$args = [];

	expect(parseArgs("a\\'b 'c'", $args))->toBeTrue()->and($args)->toBe(["a'b", 'c']);
});

test('malformed parameter strings are refused without writing to stdout', function (string $input) {
	$args = ['stale'];

	ob_start();
	$ok     = parseArgs($input, $args);
	$output = ob_get_clean();

	expect($ok)->toBeFalse()
		->and($args)->toBe([])
		->and($output)->toBe('');
})->with([
	'unterminated' => ["'open"],
	'backtick'     => ["'a' `b`"],
	'bad escape'   => ["'a' \\x"],
	'dangling'     => ["'a' \\"],
]);

test('debug mode prints the input, output and errors', function () {
	$args = [];

	ob_start();
	parseArgs("'a' b", $args, true);
	parseArgs('a b', $args, true);
	parseArgs("'open", $args, true);
	$output = ob_get_clean();

	expect($output)->toContain("String: ''a' b'")
		->and($output)->toContain("Output: 'a,b'")
		->and($output)->toContain('ERROR: Parse error');
});

test('SNMP secrets in the colon joined argument are masked for logging', function () {
	$args = ['host', '7', '3:161:500:1:10::user:secret:SHA:privsecret:AES:ctx', 'get', 'usage', '1'];

	expect(script_server_redact_args($args))->toBe(['host', '7', '3:161:500:1:10:<redacted>', 'get', 'usage', '1']);

	// the reduced form carries no secret and is logged as is
	expect(script_server_redact_args(['2:161:500:1:10']))->toBe(['2:161:500:1:10']);
});

test('a whole request line is masked for logging', function () {
	$command = "/cacti/scripts/ss_host_cpu.php ss_host_cpu 'host' '7' '2:161:500:1:10:it'\\''s secret' 'get' 'usage' '1'";
	$logged  = script_server_redact_command($command);

	expect($logged)->toContain('2:161:500:1:10:<redacted>')
		->and($logged)->not->toContain('secret')
		->and(script_server_redact_command("'open"))->toBe('<unparsable request>');
});

dataset('logged secrets', [
	'apostrophe'   => ["pa'ss"],
	'double quote' => ['pa"ss'],
	'space'        => ['pa ss'],
	'backslash'    => ['pa\\ss'],
]);

test('a known secret is masked in whatever escaped form the command carries it', function (string $secret) {
	$command = '/cacti/scripts/query.php ' . escapeshellarg('host') . ' ' . escapeshellarg($secret) . ' ' . escapeshellarg('index');
	$logged  = script_server_redact_command($command, ['', $secret]);

	expect($logged)->toBe('/cacti/scripts/query.php host <redacted> index')
		->and($logged)->not->toContain('pa');
})->with('logged secrets');

test('a secret contained in a longer secret leaves nothing of the longer one behind', function () {
	expect(script_server_redact_command("'abc' 'abcdef' 'xabcdefx'", ['abc', 'abcdef']))
		->toBe('<redacted> <redacted> x<redacted>x');
});

test('an empty secret masks nothing', function () {
	expect(script_server_redact_args(['host', 'index'], ['', '']))->toBe(['host', 'index']);
});
