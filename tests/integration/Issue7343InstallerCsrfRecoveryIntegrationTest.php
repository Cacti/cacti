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

function issue7343ReservePort() : int {
	$socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);

	if ($socket === false) {
		throw new RuntimeException("Unable to reserve an HTTP port: $errorMessage ($errorNumber)");
	}

	$address = stream_socket_get_name($socket, false);
	fclose($socket);

	if ($address === false || !preg_match('/:(\d+)$/', $address, $matches)) {
		throw new RuntimeException('Unable to determine the reserved HTTP port');
	}

	return (int) $matches[1];
}

function issue7343StartServer(string $root, string $sessionPath, int $port) : array {
	$command = [
		PHP_BINARY,
		'-d',
		'session.save_path=' . $sessionPath,
		'-d',
		'display_errors=1',
		'-S',
		'127.0.0.1:' . $port,
		$root . '/tests/fixtures/Issue7343InstallerCsrfEndpoint.php',
	];

	$descriptors = [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w'],
	];

	$process = proc_open($command, $descriptors, $pipes, $root);

	if (!is_resource($process)) {
		throw new RuntimeException('Unable to start the PHP integration-test server');
	}

	fclose($pipes[0]);
	stream_set_blocking($pipes[1], false);
	stream_set_blocking($pipes[2], false);

	$deadline = microtime(true) + 5;

	do {
		set_error_handler(static function () {
			return true;
		});

		try {
			$connection = stream_socket_client(
				'tcp://127.0.0.1:' . $port,
				$errorNumber,
				$errorMessage,
				0.1
			);
		} finally {
			restore_error_handler();
		}

		if ($connection !== false) {
			fclose($connection);

			return [$process, $pipes];
		}

		$status = proc_get_status($process);

		if (!$status['running']) {
			break;
		}

		usleep(25000);
	} while (microtime(true) < $deadline);

	$error = stream_get_contents($pipes[2]);
	issue7343StopServer($process, $pipes);

	throw new RuntimeException('PHP integration-test server did not start: ' . trim($error));
}

function issue7343StopServer($process, array $pipes) : void {
	$status = proc_get_status($process);

	if ($status['running']) {
		proc_terminate($process);

		$deadline = microtime(true) + 2;

		do {
			usleep(25000);
			$status = proc_get_status($process);
		} while ($status['running'] && microtime(true) < $deadline);

		if ($status['running']) {
			proc_terminate($process, 9);
		}
	}

	foreach ($pipes as $pipe) {
		if (is_resource($pipe)) {
			fclose($pipe);
		}
	}

	proc_close($process);
}

function issue7343Post(string $url, array $data, string $cookie = '') : array {
	$headers = [
		'Content-Type: application/x-www-form-urlencoded',
		'X-Requested-With: XMLHttpRequest',
	];

	if ($cookie !== '') {
		$headers[] = 'Cookie: ' . $cookie;
	}

	$context = stream_context_create(
		[
			'http' => [
				'method'          => 'POST',
				'header'          => implode("\r\n", $headers),
				'content'         => http_build_query($data),
				'ignore_errors'   => true,
				'follow_location' => 0,
				'timeout'         => 5,
			],
		]
	);

	$body            = file_get_contents($url, false, $context);
	$responseHeaders = $http_response_header ?? [];

	if ($body === false || !$responseHeaders) {
		throw new RuntimeException('Installer CSRF integration request failed');
	}

	if (!preg_match('/\s(\d{3})(?:\s|$)/', $responseHeaders[0], $statusMatch)) {
		throw new RuntimeException('Unable to parse HTTP response status');
	}

	$parsedHeaders = [];

	foreach (array_slice($responseHeaders, 1) as $header) {
		$separator = strpos($header, ':');

		if ($separator === false) {
			continue;
		}

		$name                   = strtolower(substr($header, 0, $separator));
		$parsedHeaders[$name][] = trim(substr($header, $separator + 1));
	}

	return [
		'status'  => (int) $statusMatch[1],
		'headers' => $parsedHeaders,
		'body'    => $body,
	];
}

function issue7343SessionCookie(array $headers) : string {
	foreach ($headers['set-cookie'] ?? [] as $header) {
		$cookie = explode(';', $header, 2)[0];

		if (str_starts_with($cookie, session_name() . '=')) {
			return $cookie;
		}
	}

	return '';
}

test('installer csrf timeout returns a replacement token accepted by the next request', function () {
	$root        = dirname(__DIR__, 2);
	$sessionPath = sys_get_temp_dir() . '/cacti-issue7343-' . bin2hex(random_bytes(8));
	$port        = issue7343ReservePort();

	if (!mkdir($sessionPath, 0700) && !is_dir($sessionPath)) {
		throw new RuntimeException('Unable to create the isolated PHP session directory');
	}

	$server = null;
	$pipes  = [];

	try {
		[$server, $pipes] = issue7343StartServer($root, $sessionPath, $port);

		$url      = 'http://127.0.0.1:' . $port . '/install/step_json.php';
		$rejected = issue7343Post($url, ['__csrf_magic' => 'invalid']);
		$payload  = json_decode($rejected['body'], true);
		$cookie   = issue7343SessionCookie($rejected['headers']);

		expect($rejected['status'])->toBe(403)
			->and($rejected['headers']['content-type'][0] ?? '')->toStartWith('application/json')
			->and($rejected['headers']['cache-control'] ?? [])->toContain('no-store')
			->and($rejected['headers']['x-content-type-options'] ?? [])->toContain('nosniff')
			->and($payload)->toBeArray()
			->and($payload['error'] ?? null)->toBe('csrf_timeout')
			->and($payload['csrfMagicToken'] ?? '')->toStartWith('sid:')
			->and($cookie)->not->toBe('');

		$accepted = issue7343Post(
			$url,
			['__csrf_magic' => $payload['csrfMagicToken']],
			$cookie
		);
		$acceptedPayload = json_decode($accepted['body'], true);

		expect($accepted['status'])->toBe(200)
			->and($acceptedPayload)->toBeArray()
			->and($acceptedPayload['csrfValidated'] ?? false)->toBeTrue()
			->and($acceptedPayload['csrfMagicToken'] ?? '')->toStartWith('sid:');
	} finally {
		if (is_resource($server)) {
			issue7343StopServer($server, $pipes);
		}

		foreach (glob($sessionPath . '/*') ?: [] as $sessionFile) {
			unlink($sessionFile);
		}

		rmdir($sessionPath);
	}
});
