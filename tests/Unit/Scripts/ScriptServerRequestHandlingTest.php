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
 * Request handling in script_server.php and the matching writer limit in
 * lib/poller.php: bounded request lines, contained script failures, argument
 * parsing of cacti_escapeshellarg() output and credential masking in logs.
 * The production functions are loaded unchanged into a fixture namespace so
 * the script server's main loop never runs.
 */

namespace CactiScriptServerRequestFixture;

require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$GLOBALS['ss_request_log'] = array();

function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['ss_request_log'][] = $string;
}

function db_fetch_assoc($sql) {
	return array(
		array('snmp_community' => 'c0mmunity', 'snmp_password' => 'authpass', 'snmp_priv_passphrase' => ''),
	);
}

function ss_request_fixture_throws() {
	throw new \RuntimeException('script failure');
}

function ss_request_fixture_type_error($value) {
	return \gmp_init($value);
}

function ss_request_fixture_array() {
	return array(1, 2);
}

function ss_request_fixture_ok($a, $b) {
	return "$a:$b";
}

if (!function_exists(__NAMESPACE__ . '\\parseArgs')) {
	$root = dirname(__DIR__, 3);

	foreach (array('script_server_path_is_allowed', 'parseArgs', 'script_server_read_request', 'script_server_drain_line', 'script_server_call', 'script_server_redact_params') as $function) {
		\cacti_fixture_source_function($root . '/script_server.php', $function, __NAMESPACE__);
	}

	foreach (array('exec_poll_php', 'poller_redact_snmp_secrets') as $function) {
		\cacti_fixture_source_function($root . '/lib/poller.php', $function, __NAMESPACE__);
	}
}

function ss_request_stream($contents) {
	$stream = fopen('php://memory', 'w+');
	fwrite($stream, $contents);
	rewind($stream);

	return $stream;
}

function ss_request_escape($value) {
	/* cacti_escapeshellarg() on unix is escapeshellarg() */
	return escapeshellarg($value);
}

beforeEach(function () {
	$GLOBALS['ss_request_log'] = array();
});

test('a request at the limit is returned whole', function () {
	$request = str_repeat('a', SCRIPT_SERVER_MAX_REQUEST_LENGTH);
	$stream  = ss_request_stream($request . "\r\nquit\r\n");

	expect(script_server_read_request($stream))->toBe($request . "\r\n");
	expect(script_server_read_request($stream))->toBe("quit\r\n");
});

test('an over-length request is discarded and its tail is not read as a new request', function () {
	$request = str_repeat('a', SCRIPT_SERVER_MAX_REQUEST_LENGTH * 3) . ' quit';
	$stream  = ss_request_stream($request . "\r\nnext request\r\n");

	expect(script_server_read_request($stream))->toBeNull();
	expect(script_server_read_request($stream))->toBe("next request\r\n");
});

test('a request one byte over the limit is refused even with a bare newline', function () {
	$stream = ss_request_stream(str_repeat('a', SCRIPT_SERVER_MAX_REQUEST_LENGTH + 1) . "\nnext\n");

	expect(script_server_read_request($stream))->toBeNull();
	expect(script_server_read_request($stream))->toBe("next\n");
});

test('end of input is reported as false', function () {
	$stream = ss_request_stream('');

	expect(script_server_read_request($stream))->toBeFalse();
});

/* what spine's php_cmd() writes: snprintf(command, BUFSIZE = 1024, "%s\r\n") */
function ss_request_spine_write($payload) {
	return substr($payload . "\r\n", 0, 1023);
}

function ss_request_pipe() {
	return stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
}

test('the limit is the longest payload spine sends with its CR LF intact', function () {
	expect(SCRIPT_SERVER_MAX_REQUEST_LENGTH)->toBe(1021);
	expect(ss_request_spine_write(str_repeat('a', 1021)))->toEndWith("\r\n");
	expect(ss_request_spine_write(str_repeat('a', 1022)))->not->toEndWith("\n");
});

test('a full length request from spine is read whole from a pipe', function () {
	list($spine, $server) = ss_request_pipe();
	$payload = str_repeat('a', SCRIPT_SERVER_MAX_REQUEST_LENGTH);

	fwrite($spine, ss_request_spine_write($payload));

	expect(script_server_read_request($server, 0.2))->toBe($payload . "\r\n");
});

foreach (array(1022, 1023, 5000) as $length) {
	test("a $length byte request truncated by spine is refused without waiting for a line feed", function () use ($length) {
		list($spine, $server) = ss_request_pipe();

		/* spine keeps the pipe open and waits for the reply */
		fwrite($spine, ss_request_spine_write(str_repeat('a', $length)));

		$start  = microtime(true);
		$result = script_server_read_request($server, 0.2);
		$waited = microtime(true) - $start;

		expect($result)->toBeNull();
		expect($waited)->toBeLessThan(1.0);

		fwrite($spine, "next\r\n");

		expect(script_server_read_request($server, 0.2))->toBe("next\r\n");
	});
}

test('the default wait for a missing line feed is half a second', function () {
	list($spine, $server) = ss_request_pipe();

	fwrite($spine, ss_request_spine_write(str_repeat('a', 2000)));

	$start = microtime(true);

	expect(script_server_read_request($server))->toBeNull();
	expect(microtime(true) - $start)->toBeGreaterThanOrEqual(0.4)->toBeLessThan(1.5);
});

test('the rest of an over-length line is drained when it arrives in pieces', function () {
	list($writer, $server) = ss_request_pipe();

	fwrite($writer, str_repeat('a', 1500));

	$child = pcntl_fork();

	if ($child === 0) {
		usleep(50000);
		fwrite($writer, str_repeat('b', 3000));
		usleep(50000);
		fwrite($writer, "tail\r\nnext\r\n");
		exit(0);
	}

	expect(script_server_read_request($server, 0.5))->toBeNull();
	pcntl_waitpid($child, $status);
	expect(script_server_read_request($server, 0.5))->toBe("next\r\n");
})->skip(!function_exists('pcntl_fork'), 'pcntl is not available');

test('the poller refuses to send a command longer than the script server limit', function () {
	$to_server   = fopen('php://memory', 'w+');
	$from_server = ss_request_stream("42\n");
	$proc        = fopen('php://memory', 'r');

	$result = exec_poll_php(str_repeat('a', SCRIPT_SERVER_MAX_REQUEST_LENGTH + 1), 1, array($to_server, $from_server), $proc);

	expect($result)->toBe('U');
	rewind($to_server);
	expect(stream_get_contents($to_server))->toBe('');
	expect(implode("\n", $GLOBALS['ss_request_log']))->toContain('byte limit');
});

test('the poller refuses a command that carries its own line break', function () {
	foreach (array("a\nb", "a\rb", "a\r\n") as $command) {
		$to_server   = fopen('php://memory', 'w+');
		$from_server = ss_request_stream("42\n");
		$proc        = fopen('php://memory', 'r');

		expect(exec_poll_php($command, 1, array($to_server, $from_server), $proc))->toBe('U');
		rewind($to_server);
		expect(stream_get_contents($to_server))->toBe('');
	}
});

test('the poller still sends a command at the limit', function () {
	$to_server   = fopen('php://memory', 'w+');
	$from_server = ss_request_stream("42\n");
	$proc        = fopen('php://memory', 'r');
	$command     = str_repeat('a', SCRIPT_SERVER_MAX_REQUEST_LENGTH);

	expect(exec_poll_php($command, 1, array($to_server, $from_server), $proc))->toBe("42\n");
	rewind($to_server);
	expect(stream_get_contents($to_server))->toBe($command . "\r\n");
});

test('a script that throws answers U and the parameters stay out of the log', function () {
	$result = script_server_call(__NAMESPACE__ . '\\ss_request_fixture_throws', 'secret-value', array('secret-value'));

	expect($result)->toBe('U');
	$log = implode("\n", $GLOBALS['ss_request_log']);
	expect($log)->toContain('RuntimeException');
	expect($log)->not->toContain('secret-value');
});

test('an engine error raised by a script is contained', function () {
	expect(script_server_call(__NAMESPACE__ . '\\ss_request_fixture_type_error', 'x', array('not-a-number')))->toBe('U');
});

test('a script returning an array answers U', function () {
	expect(script_server_call(__NAMESPACE__ . '\\ss_request_fixture_array', '', array()))->toBe('U');
});

test('a normal script result is passed through', function () {
	expect(script_server_call(__NAMESPACE__ . '\\ss_request_fixture_ok', 'a b', array('a', 'b')))->toBe('a:b');
});

test('the dispatch keeps the reflection guard in front of the call', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/script_server.php');

	expect(strpos($source, '$ref->isInternal()'))->toBeLessThan(strpos($source, '$result = script_server_call('));
});

test('values escaped by cacti_escapeshellarg parse back to one argument each', function () {
	$values = array('plain', "it's", "a'b'c", 'with space', 'double"quote', "mix \"of' both", '', 'back\\slash', "'");

	$string = implode(' ', array_map(__NAMESPACE__ . '\\ss_request_escape', $values));

	expect(parseArgs($string, $parsed))->toBeTrue();
	expect($parsed)->toBe($values);
});

test('a host_cpu style argument with an apostrophe in a credential stays one argument', function () {
	$string = implode(' ', array(ss_request_escape('host1'), ss_request_escape('12'), ss_request_escape("2:161:500:1:10:pub'lic:::::::"), ss_request_escape('index')));

	expect(parseArgs($string, $parsed))->toBeTrue();
	expect($parsed)->toBe(array('host1', '12', "2:161:500:1:10:pub'lic:::::::", 'index'));
});

test('unquoted arguments still split on spaces', function () {
	expect(parseArgs('a b  c', $parsed))->toBeTrue();
	expect($parsed)->toBe(array('a', 'b', '', 'c'));
});

test('an unterminated quote fails without writing to standard output', function () {
	ob_start();
	$ok = parseArgs("'open", $parsed);
	$printed = ob_get_clean();

	expect($ok)->toBeFalse();
	expect($printed)->toBe('');
});

test('logged parameters mask device credentials, including colon separated fields', function () {
	$secrets = array('c0mmunity' => true, 'authpass' => true);

	expect(script_server_redact_params(array('host1', 'c0mmunity', '3:161:500:1:10::user:authpass:SHA', 'index'), $secrets))
		->toBe(array('host1', '********', '3:161:500:1:10::user:********:SHA', 'index'));
});

test('credentials for redaction are loaded from the device table when not supplied', function () {
	expect(script_server_redact_params(array('c0mmunity', 'other')))->toBe(array('********', 'other'));
});

test('cmd.php log arguments mask the item credentials', function () {
	$item = array('snmp_community' => 'pub', 'snmp_password' => 'pubpass', 'snmp_priv_passphrase' => '');

	expect(poller_redact_snmp_secrets("/x/ss.php f 'h' 'pubpass' 'pub'", $item))->toBe("/x/ss.php f 'h' '********' '********'");
	expect(poller_redact_snmp_secrets('no secrets here', array()))->toBe('no secrets here');
});

function ss_request_command($os, array $args, $cmd = false) {
	$saved = $GLOBALS['config']['cacti_server_os'] ?? null;

	$GLOBALS['config']['cacti_server_os'] = $os;

	$parts = array('/opt/cacti/scripts/ss_x.php', 'ss_x');

	foreach ($args as $arg) {
		$parts[] = ($cmd ? \cacti_escapeshellarg_cmd($arg) : \cacti_escapeshellarg($arg));
	}

	$GLOBALS['config']['cacti_server_os'] = $saved;

	return implode(' ', $parts);
}

$ss_redact_secrets = array(
	'apostrophe'   => "it's'secret",
	'double quote' => 'say"secret"',
	'backslash'    => 'back\\slash\\secret',
	'space'        => 'secret with spaces',
	'cmd metachar' => 'a&b|c^d<e>(f)%g',
);

$ss_redact_forms = array(
	'unix'      => array('unix', false),
	'win32'     => array('win32', false),
	'win32 cmd' => array('win32', true),
);

foreach ($ss_redact_secrets as $label => $secret) {
	foreach ($ss_redact_forms as $form => $how) {
		test("cmd.php log arguments mask a credential with a $label in its $form escaped form", function () use ($secret, $how) {
			$item    = array('snmp_community' => '', 'snmp_password' => $secret, 'snmp_priv_passphrase' => '');
			$command = ss_request_command($how[0], array('host1', $secret, "3:161:500:1:10::user:$secret:SHA"), $how[1]);
			$masked  = poller_redact_snmp_secrets($command, $item);

			expect(substr_count($masked, '********'))->toBe(2);
			expect($masked)->toContain('host1');

			/* no word of the credential survives, whatever escaping wrapped it */
			foreach (preg_split('/[^A-Za-z]+/', $secret, -1, PREG_SPLIT_NO_EMPTY) as $word) {
				if (strlen($word) >= 4) {
					expect($masked)->not->toContain($word);
				}
			}
		});
	}
}

test('cmd.php log arguments mask a credential that contains another credential whole', function () {
	$item = array('snmp_community' => 'pub', 'snmp_password' => 'pubpubpass', 'snmp_priv_passphrase' => '');

	expect(poller_redact_snmp_secrets("f 'pubpubpass' 'pub'", $item))->toBe("f '********' '********'");
});

test('cmd.php log arguments ignore empty credentials', function () {
	$item = array('snmp_community' => '', 'snmp_password' => '', 'snmp_priv_passphrase' => '0');

	expect(poller_redact_snmp_secrets("f '' 'x0y'", $item))->toBe("f '' 'x********y'");
	expect(poller_redact_snmp_secrets("f '' 'xy'", array('snmp_community' => '')))->toBe("f '' 'xy'");
});

test('script roots admit configured custom scripts and reject sibling prefixes', function () {
	$base = sys_get_temp_dir() . '/cacti-script-roots-' . bin2hex(random_bytes(4));

	foreach (['cacti', 'custom', 'custom-other'] as $name) {
		mkdir($base . '/' . $name, 0700, true);
	}

	try {
		$roots = [$base . '/cacti', $base . '/custom'];
		expect(script_server_path_is_allowed($base . '/cacti/collector.php', $roots))->toBeTrue();
		expect(script_server_path_is_allowed($base . '/custom/collector.php', $roots))->toBeTrue();
		expect(script_server_path_is_allowed($base . '/custom-other/collector.php', $roots))->toBeFalse();
		expect(script_server_path_is_allowed($base . '/custom/collector.php', [$base . '/cacti']))->toBeFalse();
		expect(script_server_path_is_allowed(false, $roots))->toBeFalse();
	} finally {
		foreach (['cacti', 'custom', 'custom-other'] as $name) {
			rmdir($base . '/' . $name);
		}
		rmdir($base);
	}
});
