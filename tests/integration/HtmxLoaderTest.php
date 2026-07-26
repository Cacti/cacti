<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

/*
 * Integration tests for the htmx loader. Each test spins up a `php -S`
 * instance that serves tests/integration/fixtures/htmx_fixture.php with the
 * htmx_enabled toggle driven via environment variable, then asserts on the
 * actual HTTP response. The server is torn down in finally{} so a failing
 * assertion never leaks a bound port.
 */

/**
 * Starts a PHP built-in server on a free port and returns [proc, port].
 *
 * @param array<string,string> $env
 * @return array{0: resource, 1: int}
 */
function htmx_loader_start_server(array $env = []): array {
	$port   = htmx_loader_pick_port();
	$fixture = dirname(__DIR__) . '/integration/fixtures/htmx_fixture.php';

	// stdout/stderr go to /dev/null: the built-in server logs every request,
	// and an unread pipe buffer would eventually fill and block it mid-test.
	$descriptors = [
		0 => ['file', '/dev/null', 'r'],
		1 => ['file', '/dev/null', 'w'],
		2 => ['file', '/dev/null', 'w'],
	];

	$env = array_merge($_ENV ?: [], $env);

	$cmd = sprintf(
		'exec %s -S 127.0.0.1:%d %s',
		escapeshellarg(PHP_BINARY),
		$port,
		escapeshellarg($fixture)
	);

	$proc = proc_open($cmd, $descriptors, $pipes, dirname(__DIR__, 2), $env);

	if (!is_resource($proc)) {
		throw new RuntimeException('failed to start php -S');
	}

	/* wait for the listener to bind before issuing the first curl */
	set_error_handler(static function (): bool { return true; });

	try {
		for ($i = 0; $i < 50; $i++) {
			$probe = fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);

			if ($probe) {
				fclose($probe);

				return [$proc, $port];
			}

			usleep(50_000);
		}
	} finally {
		restore_error_handler();
	}

	htmx_loader_stop_server($proc);

	throw new RuntimeException('php -S never accepted connections on port ' . $port);
}

function htmx_loader_pick_port(): int {
	$sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

	if ($sock === false) {
		throw new RuntimeException('cannot allocate a local port: ' . $errstr);
	}

	$name = stream_socket_get_name($sock, false);

	fclose($sock);

	return (int) substr($name, strrpos($name, ':') + 1);
}

function htmx_loader_stop_server($proc): void {
	if (is_resource($proc)) {
		proc_terminate($proc);
		proc_close($proc);
	}
}

/**
 * @param array<string,string> $headers
 */
function htmx_loader_http_get(int $port, array $headers = []): string {
	$ch = curl_init('http://127.0.0.1:' . $port . '/');

	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 5,
		CURLOPT_HTTPHEADER     => array_map(
			static fn ($k, $v) => "$k: $v",
			array_keys($headers),
			array_values($headers)
		),
	]);

	$body   = curl_exec($ch);
	$err    = curl_error($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

	curl_close($ch);

	if ($body === false) {
		throw new RuntimeException('curl failed: ' . $err);
	}

	// A 4xx/5xx error page can still contain the substrings the tests look for,
	// so reject it here rather than assert against an error body.
	if ($status >= 400) {
		throw new RuntimeException('fixture returned HTTP ' . $status);
	}

	return (string) $body;
}

// The HTTP probe relies on ext-curl; without it curl_init() is a fatal, not a
// catchable error, so skip the suite rather than let it crash on lean runners.
beforeEach(function () {
	if (!function_exists('curl_init')) {
		test()->markTestSkipped('ext-curl not available');
	}
});

test('GET / emits an htmx script tag when enabled', function () {
	[$proc, $port] = htmx_loader_start_server(['FIXTURE_HTMX_ENABLED' => 'on']);

	try {
		$body = htmx_loader_http_get($port);

		expect($body)->toContain('<script')
			->and($body)->toMatch('/src=[\'"][^\'"]*htmx\.min\.js/');
	} finally {
		htmx_loader_stop_server($proc);
	}
});

test('script tag carries a sha384 integrity attribute', function () {
	[$proc, $port] = htmx_loader_start_server(['FIXTURE_HTMX_ENABLED' => 'on']);

	try {
		$body = htmx_loader_http_get($port);

		expect($body)->toMatch('/integrity=[\'"]sha384-[A-Za-z0-9+\/=]+/');
	} finally {
		htmx_loader_stop_server($proc);
	}
});

test('script tag declares crossorigin="anonymous"', function () {
	[$proc, $port] = htmx_loader_start_server(['FIXTURE_HTMX_ENABLED' => 'on']);

	try {
		$body = htmx_loader_http_get($port);

		expect($body)->toMatch('/crossorigin=[\'"]anonymous/');
	} finally {
		htmx_loader_stop_server($proc);
	}
});

test('no htmx reference in body when the setting is off', function () {
	[$proc, $port] = htmx_loader_start_server(['FIXTURE_HTMX_ENABLED' => 'off']);

	try {
		$body = htmx_loader_http_get($port);

		expect($body)->not->toContain('htmx.min.js');
	} finally {
		htmx_loader_stop_server($proc);
	}
});

test('htmx_is_fragment_request returns true when HX-Request header is sent', function () {
	[$proc, $port] = htmx_loader_start_server([
		'FIXTURE_HTMX_ENABLED'       => 'on',
		'FIXTURE_EMIT_FRAGMENT_FLAG' => '1',
	]);

	try {
		$body = htmx_loader_http_get($port, ['HX-Request' => 'true']);

		expect($body)->toBe('FRAGMENT=true');
	} finally {
		htmx_loader_stop_server($proc);
	}
});

test('htmx_is_fragment_request returns false without the header', function () {
	[$proc, $port] = htmx_loader_start_server([
		'FIXTURE_HTMX_ENABLED'       => 'on',
		'FIXTURE_EMIT_FRAGMENT_FLAG' => '1',
	]);

	try {
		$body = htmx_loader_http_get($port);

		expect($body)->toBe('FRAGMENT=false');
	} finally {
		htmx_loader_stop_server($proc);
	}
});
