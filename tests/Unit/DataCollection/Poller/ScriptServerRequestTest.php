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
	expect(SCRIPT_SERVER_MAX_REQUEST_LENGTH)->toBe(1021)
		->and(SCRIPT_SERVER_MAX_RESPONSE_LENGTH)->toBe(8190);
});

test('a request within the limit is returned whole', function () {
	$stream = ss_request_stream("/path/script.php ss_func 'a'\r\nquit\r\n");

	[$line, $overlong, $framing_healthy] = script_server_read_request($stream);

	expect($line)->toBe("/path/script.php ss_func 'a'\r\n")
		->and($overlong)->toBeFalse()
		->and($framing_healthy)->toBeTrue();

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

	[, $overlong, $framing_healthy] = script_server_read_request($stream);

	expect($overlong)->toBeTrue()->and($framing_healthy)->toBeTrue();

	[$line, $overlong] = script_server_read_request($stream);

	expect($line)->toBe("next request\r\n")->and($overlong)->toBeFalse();
});

test('a request one byte over the limit whose LF fits the buffer is still refused', function () {
	$stream = ss_request_stream(str_repeat('y', SCRIPT_SERVER_MAX_REQUEST_LENGTH + 1) . "\n");

	[, $overlong] = script_server_read_request($stream);

	expect($overlong)->toBeTrue();
});

test('draining a request restores the socket blocking mode', function (bool $blocking) {
	$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
	stream_set_blocking($pair[1], $blocking);
	fwrite($pair[0], "discard this line\nkeep this line\n");

	script_server_drain_line($pair[1], 0.1);

	expect(stream_get_meta_data($pair[1])['blocked'])->toBe($blocking)
		->and(fgets($pair[1]))->toBe("keep this line\n");

	fclose($pair[0]);
	fclose($pair[1]);
})->with([true, false])
	->skip(!function_exists('stream_socket_pair') || DIRECTORY_SEPARATOR === '\\', 'needs a unix socket pair');

test('a persistent nonblocking reader stays aligned and enforces line completion', function () {
	$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
	stream_set_blocking($pair[1], false);
	fwrite($pair[0], "first request\nsecond request\n");

	[$first, $first_overlong, $first_healthy]    = script_server_read_request($pair[1], SCRIPT_SERVER_MAX_REQUEST_LENGTH, 0.1, true);
	[$second, $second_overlong, $second_healthy] = script_server_read_request($pair[1], SCRIPT_SERVER_MAX_REQUEST_LENGTH, 0.1, true);

	expect($first)->toBe("first request\n")
		->and($first_overlong)->toBeFalse()
		->and($first_healthy)->toBeTrue()
		->and($second)->toBe("second request\n")
		->and($second_overlong)->toBeFalse()
		->and($second_healthy)->toBeTrue()
		->and(stream_get_meta_data($pair[1])['blocked'])->toBeFalse();

	fwrite($pair[0], 'partial request');
	[$partial, $partial_overlong, $partial_healthy] = script_server_read_request($pair[1], SCRIPT_SERVER_MAX_REQUEST_LENGTH, 0.05, true);

	expect($partial)->toBe('partial request')
		->and($partial_overlong)->toBeTrue()
		->and($partial_healthy)->toBeFalse()
		->and(stream_get_meta_data($pair[1])['blocked'])->toBeFalse();

	fclose($pair[0]);
	fclose($pair[1]);
})->skip(!function_exists('stream_socket_pair') || DIRECTORY_SEPARATOR === '\\', 'needs a unix socket pair');

test('an unterminated request does not wait forever for a line ending that never comes', function (int $length) {
	// spine truncates an overlong request without CR LF and then waits for the reply
	$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

	fwrite($pair[0], str_repeat('z', $length));

	$start = microtime(true);

	[, $overlong, $framing_healthy] = script_server_read_request($pair[1], SCRIPT_SERVER_MAX_REQUEST_LENGTH, 0.1);

	expect($overlong)->toBeTrue()
		->and($framing_healthy)->toBeFalse()
		->and(stream_get_meta_data($pair[1])['blocked'])->toBeTrue()
		->and(microtime(true) - $start)->toBeLessThan(2.0);

	fclose($pair[0]);
	fclose($pair[1]);
})->with([
	'one byte over the request limit' => [SCRIPT_SERVER_MAX_REQUEST_LENGTH + 1],
	'full protocol buffer'            => [SCRIPT_SERVER_MAX_REQUEST_LENGTH + 2],
])->skip(!function_exists('stream_socket_pair') || DIRECTORY_SEPARATOR === '\\', 'needs a unix socket pair');

test('an incomplete bounded request is framing corruption', function () {
	$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
	fwrite($pair[0], 'partial request');

	[$line, $overlong, $framing_healthy] = script_server_read_request($pair[1], SCRIPT_SERVER_MAX_REQUEST_LENGTH, 0.1);

	expect($line)->toBe('partial request')
		->and($overlong)->toBeTrue()
		->and($framing_healthy)->toBeFalse();

	fclose($pair[0]);
	fclose($pair[1]);
})->skip(!function_exists('stream_socket_pair') || DIRECTORY_SEPARATOR === '\\', 'needs a unix socket pair');

test('end of input is reported as no request', function () {
	[$line, $overlong] = script_server_read_request(ss_request_stream(''));

	expect($line)->toBeFalse()->and($overlong)->toBeFalse();

	[$line, $overlong, $framing_healthy] = script_server_read_request(ss_request_stream('partial'));

	expect($line)->toBe('partial')
		->and($overlong)->toBeTrue()
		->and($framing_healthy)->toBeFalse();
});

test('the bounded blocking fallback reports complete incomplete and overlong input', function () {
	[$line, $overlong, $healthy] = script_server_read_request_blocking(ss_request_stream(''), 8, 0.01);

	expect($line)->toBeFalse()->and($overlong)->toBeFalse()->and($healthy)->toBeTrue();

	[$line, $overlong, $healthy] = script_server_read_request_blocking(ss_request_stream('partial'), 8, 0.01);

	expect($line)->toBe('partial')->and($overlong)->toBeTrue()->and($healthy)->toBeFalse();

	[$line, $overlong, $healthy] = script_server_read_request_blocking(ss_request_stream("valid\n"), 8, 0.01);

	expect($line)->toBe("valid\n")->and($overlong)->toBeFalse()->and($healthy)->toBeTrue();

	[, $overlong, $healthy] = script_server_read_request_blocking(ss_request_stream(str_repeat('x', 32)), 8, 0.01);

	expect($overlong)->toBeTrue()->and($healthy)->toBeFalse();
});

test('one line terminator is removed and embedded controls are refused', function () {
	expect(script_server_normalize_request("  script.php function 'arg'\r\n"))->toBe("script.php function 'arg'")
		->and(script_server_normalize_request("script.php function a\rb\n"))->toBeFalse()
		->and(script_server_normalize_request("script.php\tfunction\n"))->toBeFalse()
		->and(script_server_normalize_request("quit\r\r\n"))->toBeFalse();
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

test('exec_poll_php preserves the legacy ERROR response mapping', function () {
	$proc    = proc_open(['cat'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$healthy = true;

	expect(exec_poll_php('ERROR from script', true, $pipes, $proc, 1.0, $healthy))->toBe('U')
		->and($healthy)->toBeTrue();

	script_server_close($proc, $pipes, false);
});

test('response handling rejects invalid worker handles and an unframed oversized body', function () {
	$healthy = true;

	expect(script_server_read_response([], false, $healthy, 0.1))->toBeFalse()
		->and($healthy)->toBeFalse();

	$healthy = true;

	expect(exec_poll_php('probe', true, false, false, 0.1, $healthy))->toBe('U')
		->and($healthy)->toBeFalse();

	$process = proc_open(['cat'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	fclose($pipes[0]);
	$healthy = true;

	expect(exec_poll_php('probe', true, $pipes, $process, 0.1, $healthy))->toBe('U')
		->and($healthy)->toBeFalse();

	script_server_close($process, $pipes, false);

	$code    = 'while (($line = fgets(STDIN)) !== false) { fwrite(STDOUT, str_repeat("x", ' . (SCRIPT_SERVER_MAX_RESPONSE_LENGTH + 2) . ')); usleep(500000); }';
	$process = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$healthy = true;

	expect(exec_poll_php('probe', true, $pipes, $process, 1.0, $healthy))->toBe('U')
		->and($healthy)->toBeFalse();

	script_server_close($process, $pipes, false);
});

test('the direct fallback executes an allowed script without a command shell', function () {
	$script = CACTI_PATH_SCRIPTS . '/.script-server-direct-' . bin2hex(random_bytes(8)) . '.php';
	$marker = sys_get_temp_dir() . '/script-server-direct-' . bin2hex(random_bytes(8));
	$value  = 'literal; $(echo not-a-command)';

	file_put_contents($script, '<?php file_put_contents(' . var_export($marker, true) . ", 'loaded'); if (\$argv[1] === 'oversized') { fwrite(STDOUT, str_repeat('x', " . (SCRIPT_SERVER_MAX_RESPONSE_LENGTH + 3) . ")); } elseif (\$argv[1] === 'multiple') { fwrite(STDOUT, \"first\\nsecond\\n\"); } elseif (\$argv[1] === 'line') { fwrite(STDOUT, \"line\\n\"); } elseif (\$argv[1] === 'crlf') { fwrite(STDOUT, \"crlf\\r\\n\"); } else { fwrite(STDOUT, \$argv[2]); }");

	try {
		expect(exec_poll_php(escapeshellarg($script), false, false, false, 2.0))->toBe('U')
			->and(file_exists($marker))->toBeFalse();

		$command = escapeshellarg($script) . ' Class::method ' . escapeshellarg($value);

		expect(exec_poll_php($command, false, false, false, 2.0))->toBe('U')
			->and(file_exists($marker))->toBeFalse();

		$command = escapeshellarg($script) . ' ignored ' . escapeshellarg($value);

		expect(exec_poll_php($command, false, false, false, 2.0))->toBe($value)
			->and(file_exists($marker))->toBeTrue();

		$command = escapeshellarg($script) . ' oversized';

		expect(exec_poll_php($command, false, false, false, 2.0))->toBe('U');

		$command = escapeshellarg($script) . ' ignored ' . escapeshellarg("first\nsecond");

		expect(exec_poll_php($command, false, false, false, 2.0))->toBe('U');

		$missing = CACTI_PATH_SCRIPTS . '/.script-server-missing-' . bin2hex(random_bytes(8)) . '.php';

		expect(exec_poll_php(escapeshellarg($missing) . ' ignored', false, false, false, 2.0))->toBe('U')
			->and(exec_poll_php(escapeshellarg($script) . ' multiple', false, false, false, 2.0))->toBe('U')
			->and(exec_poll_php(escapeshellarg($script) . ' line', false, false, false, 2.0))->toBe('line')
			->and(exec_poll_php(escapeshellarg($script) . ' crlf', false, false, false, 2.0))->toBe('crlf');
	} finally {
		@unlink($script);
		@unlink($marker);
	}
});

test('exec_poll_php drains stderr while waiting for a framed response', function () {
	$code    = 'fwrite(STDERR, str_repeat("x", 1048576)); while (($line = fgets(STDIN)) !== false) { fwrite(STDOUT, $line); }';
	$proc    = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$healthy = true;

	expect(exec_poll_php('ping', true, $pipes, $proc, 3.0, $healthy))->toBe('ping')
		->and($healthy)->toBeTrue();

	script_server_close($proc, $pipes, false);
});

test('exec_poll_php terminates a worker that misses its deadline', function () {
	$code    = 'while (($line = fgets(STDIN)) !== false) { usleep(500000); fwrite(STDOUT, "late\\n"); }';
	$proc    = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$healthy = true;
	$start   = hrtime(true);

	expect(exec_poll_php('slow', true, $pipes, $proc, 0.05, $healthy))->toBe('U')
		->and($healthy)->toBeFalse()
		->and((hrtime(true) - $start) / 1000000000)->toBeLessThan(1.0);

	script_server_close($proc, $pipes, false);
});

test('deadline termination escalates when a worker ignores the first signal', function () {
	$code = 'pcntl_async_signals(true); pcntl_signal(SIGTERM, static function () : void {}); ' .
		'while (($line = fgets(STDIN)) !== false) { sleep(10); }';
	$proc    = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$healthy = true;
	$start   = hrtime(true);

	expect(exec_poll_php('slow', true, $pipes, $proc, 0.05, $healthy))->toBe('U')
		->and($healthy)->toBeFalse()
		->and((hrtime(true) - $start) / 1000000000)->toBeLessThan(1.0);

	script_server_close($proc, $pipes, false);
})->skip(DIRECTORY_SEPARATOR === '\\' || !function_exists('pcntl_signal'), 'requires Unix signals');

test('exec_poll_php replaces workers that emit invalid responses', function (string $body) {
	$code    = 'while (($line = fgets(STDIN)) !== false) { ' . $body . ' }';
	$proc    = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$healthy = true;

	expect(exec_poll_php('probe', true, $pipes, $proc, 1.0, $healthy))->toBe('U')
		->and($healthy)->toBeFalse();

	script_server_close($proc, $pipes, false);
})->with([
	'oversized' => ['fwrite(STDOUT, str_repeat("x", ' . (SCRIPT_SERVER_MAX_RESPONSE_LENGTH + 1) . ') . "\\n");'],
	'multiple'  => ['fwrite(STDOUT, "first\\nsecond\\n");'],
	'empty'     => ['fwrite(STDOUT, "\\n");'],
	'bad CR'    => ['fwrite(STDOUT, "first\\r\\r\\n");'],
	'control'   => ['fwrite(STDOUT, "first\\tsecond\\n");'],
]);

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
	'plain tick'   => ['a `b`'],
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
