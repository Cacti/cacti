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
 */

/**
 * Start the HTTP test servers on free loopback ports, once per process.
 *
 * The router is PHP's built-in server with several workers, for the device
 * behaviours under 'url'.  Its state directory records every request path.
 * Requests that measure client concurrency go to 'inflight', a single-process
 * server that holds /inflight requests and counts how many are in flight
 * together (see HttpInflightServer.php).  Both stop when the process exits.
 *
 * @return array{url: string, state: string, inflight: string} base URLs without a trailing slash and the state directory
 */
function cacti_http_test_server() : array {
	static $server = null;

	if ($server !== null) {
		return $server;
	}

	$probe = stream_socket_server('tcp://127.0.0.1:0');
	$port  = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);

	fclose($probe);

	$state = sys_get_temp_dir() . '/cacti-http-test-' . getmypid() . '-' . $port;

	mkdir($state);

	// -n keeps php.ini out of the server, so coverage drivers and auto_prepend_file
	// hooks configured for the test run do not slow or serialize its requests
	$process = proc_open([PHP_BINARY, '-n', '-S', '127.0.0.1:' . $port, dirname(__DIR__) . '/fixtures/Core/HttpTestRouter.php'],
		[0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
		$pipes, null, ['PHP_CLI_SERVER_WORKERS' => '8', 'CACTI_HTTP_TEST_STATE' => $state]);

	// the inflight server prints its address once it listens, and exits when its stdin closes
	$counter = proc_open([PHP_BINARY, '-n', dirname(__DIR__) . '/fixtures/Core/HttpInflightServer.php'],
		[0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']],
		$counterPipes);

	$inflight = trim((string) fgets($counterPipes[1]));

	// the inflight server binds loopback only; keep the host literal in the URL
	if (!preg_match('/^127\.0\.0\.1:(\d+)$/', $inflight, $match)) {
		throw new RuntimeException('HTTP inflight test server did not start');
	}

	$inflight_port = (int) $match[1];

	$owner = getmypid();

	// suites fork children that run shutdown functions too; only the starting process may stop the servers
	register_shutdown_function(static function () use ($process, $counter, $state, $owner) : void {
		if (getmypid() !== $owner) {
			return;
		}

		foreach ([$process, $counter] as $child) {
			proc_terminate($child);
			proc_close($child);
		}

		array_map('unlink', glob($state . '/*') ?: []);
		rmdir($state);
	});

	for ($i = 0; $i < 100; $i++) {
		$socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $errstr, 0.1);

		if ($socket !== false) {
			fclose($socket);

			return $server = ['url' => 'http://127.0.0.1:' . $port, 'state' => $state, 'inflight' => 'http://127.0.0.1:' . $inflight_port];
		}

		usleep(50000);
	}

	throw new RuntimeException('HTTP test server did not start on port ' . $port);
}

/**
 * The request paths the test router has served, oldest first.
 *
 * @return list<string>
 */
function cacti_http_test_requests() : array {
	$log = cacti_http_test_server()['state'] . '/requests';

	return is_file($log) ? file($log, FILE_IGNORE_NEW_LINES) : [];
}

/**
 * Start a new in-flight measurement and return the peak reached since the previous one.
 *
 * @return int the highest number of /inflight requests the inflight server held together
 */
function cacti_http_test_peak() : int {
	$base = cacti_http_test_server()['inflight'];
	$peak = json_decode(file_get_contents("$base/peak"), true)['peak'];

	file_get_contents("$base/reset-peak");

	return $peak;
}
