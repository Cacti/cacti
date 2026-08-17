<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or any later version.                                   |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

if (!defined('POLLER_VERBOSITY_LOW')) {
	define('POLLER_VERBOSITY_LOW', 2);
}

if (!defined('RRDTOOL_OUTPUT_NULL')) {
	define('RRDTOOL_OUTPUT_NULL', 0);
}

if (!defined('RRDTOOL_OUTPUT_STDOUT')) {
	define('RRDTOOL_OUTPUT_STDOUT', 1);
}

if (!defined('RRDTOOL_OUTPUT_STDERR')) {
	define('RRDTOOL_OUTPUT_STDERR', 2);
}

if (!defined('RRDTOOL_OUTPUT_GRAPH_DATA')) {
	define('RRDTOOL_OUTPUT_GRAPH_DATA', 3);
}

if (!defined('RRDTOOL_OUTPUT_BOOLEAN')) {
	define('RRDTOOL_OUTPUT_BOOLEAN', 4);
}

if (!defined('RRDTOOL_OUTPUT_RETURN_STDERR')) {
	define('RRDTOOL_OUTPUT_RETURN_STDERR', 5);
}

if (!defined('CACTI_PATH_RRA')) {
	define('CACTI_PATH_RRA', '/var/lib/cacti/rra');
}

if (!defined('RRD_PROXY_TEST_IO_TIMEOUT')) {
	define('RRD_PROXY_TEST_IO_TIMEOUT', 10);
}

if (!defined('RRD_PROXY_TEST_REAP_TIMEOUT')) {
	define('RRD_PROXY_TEST_REAP_TIMEOUT', 30);
}

require_once dirname(__DIR__, 2) . '/lib/rrd.php';

beforeEach(function () {
	global $config, $encryption;

	$this->rrdConfigOptions = $config[OPTIONS_CLI] ?? [];
	$this->rrdLocalStorage  = $config['local_storage'] ?? null;
	$config[OPTIONS_CLI]    = [];
	$encryption             = false;
});

afterEach(function () {
	global $config, $encryption;

	$config[OPTIONS_CLI] = $this->rrdConfigOptions;

	if ($this->rrdLocalStorage === null) {
		unset($config['local_storage']);
	} else {
		$config['local_storage'] = $this->rrdLocalStorage;
	}

	$encryption = false;
});

function rrd_proxy_socket_pair() : array {
	$sockets = [];

	expect(socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets))->toBeTrue();

	return $sockets;
}

function rrd_proxy_execute_response(string $response, int $output_flag, ?string &$request = null) : mixed {
	[$client, $server] = rrd_proxy_socket_pair();

	rrdtool_proxy_write($server, $response . RRD_PROXY_END_OF_SEQUENCE);
	$result  = __rrd_proxy_execute('info test.rrd', false, $output_flag, [$client, 'unused']);
	$request = socket_read($server, 4096, PHP_BINARY_READ);

	socket_close($client);
	socket_close($server);

	return $result;
}

function rrd_proxy_test_read_sequence(Socket $socket) : string|false {
	return rrdtool_proxy_read_frame($socket, RRD_PROXY_END_OF_SEQUENCE, 1048576);
}

/**
 * Publish proxy settings through Cacti's CLI option cache. This is the same
 * configuration path read_config_option() uses under the canonical test
 * bootstrap, so the test exercises production configuration behavior.
 *
 * @param array<string,mixed> $options Proxy settings for this test
 *
 * @return void
 */
function rrd_proxy_test_set_options(array $options) : void {
	global $config;

	$config[OPTIONS_CLI] = array_merge($config[OPTIONS_CLI] ?? [], $options);
}

/**
 * Accept one peer with bounded IO.
 *
 * accept() ignores SO_RCVTIMEO, and an accepted socket does not inherit the
 * listener's timeouts, so a forked child would otherwise block in accept() or
 * read() for as long as the job is allowed to run.
 *
 * @param Socket $listener Bound and listening socket
 *
 * @return Socket|false The accepted peer, or false when accept timed out
 */
function rrd_proxy_test_accept(Socket $listener) : Socket|false {
	$read   = [$listener];
	$write  = null;
	$except = null;

	// SO_RCVTIMEO does not apply to accept(), so wait for readiness explicitly.
	if (socket_select($read, $write, $except, RRD_PROXY_TEST_IO_TIMEOUT) !== 1) {
		return false;
	}

	$peer = socket_accept($listener);

	if (!($peer instanceof Socket)) {
		return false;
	}

	rrdtool_proxy_set_timeouts($peer, RRD_PROXY_TEST_IO_TIMEOUT);

	return $peer;
}

/**
 * Reap a forked child without blocking forever.
 *
 * A child that deadlocks against the parent's own read timeout would hang the
 * whole suite under a bare pcntl_waitpid(), so give up on it and report the
 * failure instead.
 *
 * @param int $pid Child process id
 *
 * @return array{0: bool, 1: int} Whether the child exited on its own, and its status
 */
function rrd_proxy_test_reap(int $pid) : array {
	$status   = 0;
	$deadline = time() + RRD_PROXY_TEST_REAP_TIMEOUT;

	while (time() < $deadline) {
		$reaped = pcntl_waitpid($pid, $status, WNOHANG);

		if ($reaped === $pid) {
			return [true, $status];
		}

		if ($reaped === -1) {
			return [false, $status];
		}

		usleep(50000);
	}

	posix_kill($pid, SIGKILL);
	pcntl_waitpid($pid, $status);

	return [false, $status];
}

it('configures bounded proxy socket IO', function () {
	[$client, $server] = rrd_proxy_socket_pair();

	expect(rrdtool_proxy_set_timeouts($client, 2))->toBeTrue()
		->and(socket_get_option($client, SOL_SOCKET, SO_RCVTIMEO)['sec'])->toBe(2)
		->and(socket_get_option($client, SOL_SOCKET, SO_SNDTIMEO)['sec'])->toBe(2)
		->and(rrdtool_proxy_set_timeouts($client, 0))->toBeFalse();

	socket_close($client);
	socket_close($server);
});

it('connects to a listening proxy socket with an explicit deadline', function () {
	$listener = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	expect($listener)->toBeInstanceOf(Socket::class)
		->and(socket_bind($listener, '127.0.0.1', 0))->toBeTrue()
		->and(socket_listen($listener, 1))->toBeTrue();

	$address = '';
	$port    = 0;
	socket_getsockname($listener, $address, $port);

	$client = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	expect($client)->toBeInstanceOf(Socket::class)
		->and(rrdtool_proxy_connect($client, $address, $port, 2))->toBeTrue();

	$server = socket_accept($listener);
	expect($server)->toBeInstanceOf(Socket::class);

	socket_close($server);
	socket_close($client);
	socket_close($listener);
});

it('rejects an invalid proxy connection deadline', function () {
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

	expect($socket)->toBeInstanceOf(Socket::class)
		->and(rrdtool_proxy_connect($socket, '127.0.0.1', 1, 0))->toBeFalse();

	socket_close($socket);
});

it('rejects a refused proxy connection', function () {
	$listener = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	socket_bind($listener, '127.0.0.1', 0);
	$address = '';
	$port    = 0;
	socket_getsockname($listener, $address, $port);
	socket_close($listener);

	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	expect(rrdtool_proxy_connect($socket, $address, $port, 1))->toBeFalse();
	socket_close($socket);
});

it('writes a complete proxy frame', function () {
	[$client, $server] = rrd_proxy_socket_pair();
	$frame             = str_repeat('frame-data-', 128) . RRD_PROXY_END_OF_SEQUENCE;

	expect(rrdtool_proxy_write($client, $frame))->toBeTrue()
		->and(socket_read($server, strlen($frame), PHP_BINARY_READ))->toBe($frame);

	socket_close($client);
	socket_close($server);
});

it('fails a proxy write when the peer has closed', function () {
	[$client, $server] = rrd_proxy_socket_pair();
	socket_close($server);

	expect(rrdtool_proxy_write($client, 'request'))->toBeFalse();
	socket_close($client);
});

it('reads a complete frame without its terminator', function () {
	[$client, $server] = rrd_proxy_socket_pair();

	rrdtool_proxy_write($server, 'response' . RRD_PROXY_END_OF_SEQUENCE);

	expect(rrdtool_proxy_read_frame($client, RRD_PROXY_END_OF_SEQUENCE, 128))->toBe('response');

	socket_close($client);
	socket_close($server);
});

it('rejects invalid frame limits and terminators', function () {
	[$client, $server] = rrd_proxy_socket_pair();

	expect(rrdtool_proxy_read_frame($client, '', 128))->toBeFalse()
		->and(rrdtool_proxy_read_frame($client, RRD_PROXY_END_OF_SEQUENCE, 0))->toBeFalse()
		->and(rrdtool_proxy_gzdecode('data', 0))->toBeFalse();

	socket_close($client);
	socket_close($server);
});

it('fails closed on truncated and oversized frames', function () {
	[$client, $server] = rrd_proxy_socket_pair();

	rrdtool_proxy_write($server, 'truncated');
	socket_close($server);

	expect(rrdtool_proxy_read_frame($client, RRD_PROXY_END_OF_SEQUENCE, 128))->toBeFalse();
	socket_close($client);

	[$client, $server] = rrd_proxy_socket_pair();
	rrdtool_proxy_write($server, 'too-large' . RRD_PROXY_END_OF_SEQUENCE);

	expect(rrdtool_proxy_read_frame($client, RRD_PROXY_END_OF_SEQUENCE, 4))->toBeFalse();

	socket_close($client);
	socket_close($server);
});

it('fails closed when a framed read times out', function () {
	[$client, $server] = rrd_proxy_socket_pair();
	rrdtool_proxy_set_timeouts($client, 1);

	$started = microtime(true);
	$result  = rrdtool_proxy_read_frame($client, RRD_PROXY_END_OF_SEQUENCE, 128);

	expect($result)->toBeFalse()
		->and(microtime(true) - $started)->toBeGreaterThanOrEqual(0.5)
		->and(microtime(true) - $started)->toBeLessThan(2.5);

	socket_close($client);
	socket_close($server);
});

it('parses terminal status only from the final packet', function () {
	$payload = "legend contains OK u and ERROR: as data\n";
	$frame   = $payload . RRD_PROXY_END_OF_PACKET . 'OK u:0.01 s:0.02 r:0.03';
	$result  = rrdtool_proxy_decode_response($frame);

	expect($result)->toBeArray()
		->and($result['output'])->toBe($payload)
		->and($result['status'])->toBe('OK u:0.01 s:0.02 r:0.03')
		->and($result['success'])->toBeTrue();
});

it('preserves an explicit proxy error result', function () {
	$result = rrdtool_proxy_decode_response('details' . RRD_PROXY_END_OF_PACKET . 'ERROR: failed');

	expect($result)->toBeArray()
		->and($result['output'])->toBe('details')
		->and($result['status'])->toBe('ERROR: failed')
		->and($result['success'])->toBeFalse();
});

it('fails closed on missing status malformed compression and decoded size limits', function () {
	$bad_gzip = "\x1f\x8bnot-gzip" . RRD_PROXY_END_OF_PACKET . 'OK u:0.01';
	$oversize = '12345' . RRD_PROXY_END_OF_PACKET . 'OK u:0.01';
	$zip_bomb = gzencode(str_repeat('x', 100)) . RRD_PROXY_END_OF_PACKET . 'OK u:0.01';

	expect(rrdtool_proxy_decode_response(''))->toBeFalse()
		->and(rrdtool_proxy_decode_response('payload only'))->toBeFalse()
		->and(rrdtool_proxy_decode_response($bad_gzip))->toBeFalse()
		->and(rrdtool_proxy_decode_response($oversize, 4))->toBeFalse()
		->and(rrdtool_proxy_decode_response($zip_bomb, 50))->toBeFalse();
});

it('decodes compressed packets within the configured limit', function () {
	$compressed = gzencode('compressed payload');
	$frame      = $compressed . RRD_PROXY_END_OF_PACKET . 'OK u:0.01';
	$result     = rrdtool_proxy_decode_response($frame, 1024);

	expect($result)->toBeArray()
		->and($result['output'])->toBe('compressed payload')
		->and($result['success'])->toBeTrue();
});

it('rejects malformed encrypted payloads instead of returning raw input', function () {
	global $encryption;

	$encryption = true;

	expect(decrypt('not-an-encrypted-packet'))->toBeFalse()
		->and(rrdtool_proxy_decode_response('not-an-encrypted-packet' . RRD_PROXY_END_OF_PACKET . 'OK u:0.01'))->toBeFalse();
});

it('rejects a structurally valid encrypted packet when the private key is invalid', function () {
	$packet = '004' . base64_encode('key') . base64_encode('ciphertext');

	expect(rrdtool_proxy_decrypt('000x', 'invalid private key'))->toBeFalse()
		->and(rrdtool_proxy_decrypt('004@@@@x', 'invalid private key'))->toBeFalse()
		->and(rrdtool_proxy_decrypt($packet, 'invalid private key'))->toBeFalse();
});

it('round trips encrypted packets using the official proxy cipher contract', function () {
	global $encryption;

	$private_key = phpseclib3\Crypt\RSA::createKey(2048);
	$public_key  = $private_key->getPublicKey();
	$encryption  = true;
	$packet      = encrypt('encrypted payload', (string) $public_key);

	expect(rrdtool_proxy_decrypt($packet, (string) $private_key))->toBe('encrypted payload');
});

it('accepts legacy oversized Rijndael keys by applying the phpseclib 2 truncation contract', function () {
	$private_key = phpseclib3\Crypt\RSA::createKey(4096);
	$public_key  = $private_key->getPublicKey();
	$legacy_key  = phpseclib3\Crypt\Random::string(192);
	$aes         = new phpseclib3\Crypt\Rijndael('cbc');
	$aes->setKey(substr($legacy_key, 0, 32));
	$aes->setIV(str_repeat("\0", 16));

	$encrypted_key = base64_encode($public_key->encrypt($legacy_key));
	$ciphertext    = base64_encode($aes->encrypt('legacy payload'));
	$packet        = str_pad(dechex(strlen($encrypted_key)), 3, '0', STR_PAD_LEFT) . $encrypted_key . $ciphertext;

	expect(rrdtool_proxy_decrypt($packet, (string) $private_key))->toBe('legacy payload');
});

it('accepts array commands through the proxy execution handoff', function () {
	[$client, $server] = rrd_proxy_socket_pair();

	rrdtool_proxy_write($server, 'OK u:0.01' . RRD_PROXY_END_OF_SEQUENCE);

	$result  = __rrd_proxy_execute(['info', 'file name.rrd'], false, RRDTOOL_OUTPUT_BOOLEAN, [$client, 'unused']);
	$request = socket_read($server, 4096, PHP_BINARY_READ);

	expect($result)->toBeTrue()
		->and($request)->toContain("info 'file name.rrd'")
		->and($request)->toEndWith(RRD_PROXY_END_OF_SEQUENCE);

	socket_close($client);
	socket_close($server);
});

it('rejects empty or non-string proxy command argument lists without throwing', function () {
	expect(__rrd_proxy_execute([], false))->toBeFalse()
		->and(__rrd_proxy_execute(['info', 42], false))->toBeFalse()
		->and(__rrd_proxy_execute(['info', null], false))->toBeFalse();
});

it('normalizes successful proxy output modes', function () {
	$ok       = 'OK u:0.01';
	$payload  = 'payload' . RRD_PROXY_END_OF_PACKET . $ok;
	$binary   = "\x89PNGdata\0\r\n" . RRD_PROXY_END_OF_PACKET . $ok;
	$png      = "\x89PNGdata" . RRD_PROXY_END_OF_PACKET . $ok;
	$gif      = 'GIF87data' . RRD_PROXY_END_OF_PACKET . $ok;
	$svg      = '<?xml version="1.0"?>' . RRD_PROXY_END_OF_PACKET . $ok;
	$raw      = "payload\n$ok";
	$request  = null;

	expect(rrd_proxy_execute_response($ok, RRDTOOL_OUTPUT_NULL))->toBe('OK')
		->and(rrd_proxy_execute_response($payload, RRDTOOL_OUTPUT_STDOUT))->toBe('payload')
		->and(rrd_proxy_execute_response($payload, RRDTOOL_OUTPUT_GRAPH_DATA))->toBe('payload')
		->and(rrd_proxy_execute_response($binary, RRDTOOL_OUTPUT_GRAPH_DATA))->toBe("\x89PNGdata\0\r\n")
		->and(rrd_proxy_execute_response($png, RRDTOOL_OUTPUT_STDERR))->toBe('OK')
		->and(rrd_proxy_execute_response($gif, RRDTOOL_OUTPUT_STDERR))->toBe('OK')
		->and(rrd_proxy_execute_response($svg, RRDTOOL_OUTPUT_STDERR))->toBe('SVG/XML Output OK')
		->and(rrd_proxy_execute_response($payload, RRDTOOL_OUTPUT_RETURN_STDERR))->toBe($raw)
		->and(rrd_proxy_execute_response($ok, RRDTOOL_OUTPUT_BOOLEAN, $request))->toBeTrue()
		->and($request)->toEndWith(RRD_PROXY_END_OF_SEQUENCE)
		->and(rrd_proxy_execute_response($ok, 999))->toBeFalse();
});

it('fails closed for unsuccessful proxy output modes', function () {
	$error = 'details' . RRD_PROXY_END_OF_PACKET . 'ERROR: failed';

	expect(rrd_proxy_execute_response($error, RRDTOOL_OUTPUT_NULL))->toBeFalse()
		->and(rrd_proxy_execute_response($error, RRDTOOL_OUTPUT_STDOUT))->toBeFalse()
		->and(rrd_proxy_execute_response($error, RRDTOOL_OUTPUT_GRAPH_DATA))->toBeFalse()
		->and(rrd_proxy_execute_response($error, RRDTOOL_OUTPUT_BOOLEAN))->toBeFalse()
		->and(rrd_proxy_execute_response($error, RRDTOOL_OUTPUT_RETURN_STDERR))->toBe("details\nERROR: failed");
});

it('prints explicit stderr payloads and errors only in stderr mode', function () {
	$ok    = 'diagnostic' . RRD_PROXY_END_OF_PACKET . 'OK u:0.01';
	$error = 'details' . RRD_PROXY_END_OF_PACKET . 'ERROR: failed';

	ob_start();
	$ok_result = rrd_proxy_execute_response($ok, RRDTOOL_OUTPUT_STDERR);
	$ok_output = ob_get_clean();

	ob_start();
	$error_result = rrd_proxy_execute_response($error, RRDTOOL_OUTPUT_STDERR);
	$error_output = ob_get_clean();

	expect($ok_result)->toBeTrue()
		->and($ok_output)->toBe('diagnostic')
		->and($error_result)->toBeFalse()
		->and($error_output)->toBe("details\nERROR: failed");
});

it('rejects invalid proxy connection state', function () {
	expect(__rrd_proxy_execute('info test.rrd', false, RRDTOOL_OUTPUT_BOOLEAN, ['not-a-socket', 'unused']))->toBeFalse();
});

it('closes a proxy connection with a framed quit command', function () {
	[$client, $server] = rrd_proxy_socket_pair();

	__rrd_proxy_close([$client, 'unused']);
	$request = socket_read($server, 4096, PHP_BINARY_READ);

	expect($request)->toBe('quit' . RRD_PROXY_END_OF_SEQUENCE);
	socket_close($server);
});

it('still closes when encoding the quit command fails', function () {
	global $encryption;

	[$client, $server] = rrd_proxy_socket_pair();
	$encryption        = true;

	__rrd_proxy_close([$client, 'invalid private key']);

	expect(socket_read($server, 4096, PHP_BINARY_READ))->toBe('');
	socket_close($server);
});

it('fails closed across proxy write read decode and encode failures', function () {
	global $encryption;

	[$client, $server] = rrd_proxy_socket_pair();
	socket_close($server);
	expect(__rrd_proxy_execute('info test.rrd', false, RRDTOOL_OUTPUT_BOOLEAN, [$client, 'unused']))->toBeFalse();
	socket_close($client);

	[$client, $server] = rrd_proxy_socket_pair();
	socket_shutdown($server, 1);
	expect(__rrd_proxy_execute('info test.rrd', false, RRDTOOL_OUTPUT_BOOLEAN, [$client, 'unused']))->toBeFalse();
	socket_close($client);
	socket_close($server);

	[$client, $server] = rrd_proxy_socket_pair();
	rrdtool_proxy_write($server, 'payload without status' . RRD_PROXY_END_OF_SEQUENCE);
	expect(__rrd_proxy_execute('info test.rrd', false, RRDTOOL_OUTPUT_BOOLEAN, [$client, 'unused']))->toBeFalse();
	socket_close($client);
	socket_close($server);

	[$client, $server] = rrd_proxy_socket_pair();
	$encryption        = true;
	expect(__rrd_proxy_execute('info test.rrd', false, RRDTOOL_OUTPUT_BOOLEAN, [$client, 'invalid private key']))->toBeFalse();
	socket_close($client);
	socket_close($server);
});

it('compresses large proxy commands before handoff', function () {
	[$client, $server] = rrd_proxy_socket_pair();
	rrdtool_proxy_write($server, 'OK u:0.01' . RRD_PROXY_END_OF_SEQUENCE);

	$result  = __rrd_proxy_execute('graph ' . str_repeat('A', 9000), false, RRDTOOL_OUTPUT_BOOLEAN, [$client, 'unused']);
	$request = socket_read($server, 4096, PHP_BINARY_READ);

	expect($result)->toBeTrue()
		->and($request)->toStartWith("\x1f\x8b")
		->and($request)->toEndWith(RRD_PROXY_END_OF_SEQUENCE);

	socket_close($client);
	socket_close($server);
});

it('negotiates an encrypted official-protocol connection end to end', function () {
	global $config, $encryption;

	if (!function_exists('pcntl_fork')) {
		$this->markTestSkipped('pcntl is required for the proxy handshake test');
	}

	$listener = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	socket_bind($listener, '127.0.0.1', 0);
	socket_listen($listener, 1);
	$address = '';
	$port    = 0;
	socket_getsockname($listener, $address, $port);

	$client_private = phpseclib3\Crypt\RSA::createKey(2048);
	$client_public  = $client_private->getPublicKey();
	$server_private = phpseclib3\Crypt\RSA::createKey(2048);
	$server_public  = $server_private->getPublicKey();

	$proxy_options = [
		'storage_location'          => 'remote',
		'rrdp_load_balancing'       => 'off',
		'rrdp_server'               => $address,
		'rrdp_port'                 => $port,
		'rrdp_server_backup'        => '',
		'rrdp_port_backup'          => 0,
		'rrdp_fingerprint'          => $server_public->getFingerprint('md5'),
		'rrdp_fingerprint_backup'   => '',
		'rsa_public_key'            => (string) $client_public,
		'rsa_private_key'           => (string) $client_private,
		'path_rrdtool'              => '/usr/bin/rrdtool',
		'path_rrdtool_default_font' => 'Arial'
	];

	$config['local_storage'] = false;
	rrd_proxy_test_set_options($proxy_options);

	$pid = pcntl_fork();

	expect($pid)->not->toBe(-1);

	if ($pid === 0) {
		$encryption = true;
		$peer       = rrd_proxy_test_accept($listener);

		if ($peer === false) {
			exit(10);
		}

		$received_key = rrd_proxy_test_read_sequence($peer);

		if (trim((string) $received_key) !== trim((string) $client_public)) {
			exit(11);
		}

		if (!rrdtool_proxy_write($peer, (string) $server_public . RRD_PROXY_END_OF_SEQUENCE)) {
			exit(12);
		}

		$setenv_command = rrd_proxy_test_read_sequence($peer);

		if ($setenv_command === false || rrdtool_proxy_decrypt($setenv_command, (string) $server_private) !== "setenv RRD_DEFAULT_FONT 'Arial'") {
			exit(13);
		}

		if (!rrdtool_proxy_write($peer, encrypt('OK u:0.00', (string) $client_public) . RRD_PROXY_END_OF_SEQUENCE)) {
			exit(14);
		}

		$command = rrd_proxy_test_read_sequence($peer);

		if ($command === false || rrdtool_proxy_decrypt($command, (string) $server_private) !== 'info test.rrd') {
			exit(15);
		}

		$response = encrypt('OK u:0.00', (string) $client_public);

		if (!rrdtool_proxy_write($peer, $response . RRD_PROXY_END_OF_SEQUENCE)) {
			exit(16);
		}

		$quit = rrd_proxy_test_read_sequence($peer);

		if ($quit === false || rrdtool_proxy_decrypt($quit, (string) $server_private) !== 'quit') {
			exit(17);
		}
		socket_close($peer);
		socket_close($listener);

		exit(0);
	}

	$result = __rrd_proxy_execute('info test.rrd', false, RRDTOOL_OUTPUT_BOOLEAN, '', 'TEST');
	expect($result)->toBeTrue()
		->and($encryption)->toBeTrue();

	socket_close($listener);
	[$reaped, $status] = rrd_proxy_test_reap($pid);

	expect($reaped)->toBeTrue()
		->and(pcntl_wifexited($status))->toBeTrue()
		->and(pcntl_wexitstatus($status))->toBe(0);
});

it('fails closed when configured proxy endpoints are unavailable', function () {
	$listener = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	socket_bind($listener, '127.0.0.1', 0);
	$address = '';
	$port    = 0;
	socket_getsockname($listener, $address, $port);
	socket_close($listener);

	rrd_proxy_test_set_options([
		'rrdp_load_balancing' => 'off',
		'rrdp_server'         => $address,
		'rrdp_port'           => $port
	]);

	expect(__rrd_proxy_init('TEST'))->toBeFalse();

	rrd_proxy_test_set_options([
		'rrdp_load_balancing' => 'on',
		'rrdp_server'         => $address,
		'rrdp_port'           => $port,
		'rrdp_server_backup'  => $address,
		'rrdp_port_backup'    => $port
	]);

	expect(__rrd_proxy_init('TEST'))->toBeFalse();
});

it('fails closed on absent invalid and mismatched proxy keys', function () {
	if (!function_exists('pcntl_fork')) {
		$this->markTestSkipped('pcntl is required for the proxy key exchange test');
	}

	$client_private = phpseclib3\Crypt\RSA::createKey(2048);
	$client_public  = $client_private->getPublicKey();
	$server_private = phpseclib3\Crypt\RSA::createKey(2048);
	$server_public  = $server_private->getPublicKey();

	$run_exchange = function (?string $reply, string $fingerprint) use ($client_private, $client_public) : mixed {
		$listener = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_bind($listener, '127.0.0.1', 0);
		socket_listen($listener, 1);
		$address = '';
		$port    = 0;
		socket_getsockname($listener, $address, $port);

		rrd_proxy_test_set_options([
			'rrdp_load_balancing'       => 'off',
			'rrdp_server'               => $address,
			'rrdp_port'                 => $port,
			'rrdp_server_backup'        => '',
			'rrdp_port_backup'          => 0,
			'rrdp_fingerprint'          => $fingerprint,
			'rrdp_fingerprint_backup'   => '',
			'rsa_public_key'            => (string) $client_public,
			'rsa_private_key'           => (string) $client_private,
			'path_rrdtool_default_font' => ''
		]);

		$pid = pcntl_fork();

		expect($pid)->not->toBe(-1);

		if ($pid === 0) {
			$peer = rrd_proxy_test_accept($listener);

			if ($peer === false) {
				socket_close($listener);
				exit(10);
			}

			rrd_proxy_test_read_sequence($peer);

			if ($reply !== null) {
				rrdtool_proxy_write($peer, $reply . RRD_PROXY_END_OF_SEQUENCE);
			}

			socket_close($peer);
			socket_close($listener);
			exit(0);
		}

		$result = __rrd_proxy_init('TEST');
		socket_close($listener);
		[$reaped, $status] = rrd_proxy_test_reap($pid);

		expect($reaped)->toBeTrue()
			->and(pcntl_wifexited($status))->toBeTrue()
			->and(pcntl_wexitstatus($status))->toBe(0);

		return $result;
	};

	expect($run_exchange(null, 'unused'))->toBeFalse()
		->and($run_exchange('not a public key', 'unused'))->toBeFalse()
		->and($run_exchange((string) $server_public, str_repeat('0', 47)))->toBeFalse();
});
