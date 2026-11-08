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
 * Start the HTTP test router on a free loopback port, once per process.
 *
 * The router is PHP's built-in server with several workers so requests can
 * overlap.  Its state directory records every request path and the peak
 * number of requests that were in flight together.  The server is stopped
 * when the process exits.
 *
 * @return array{url: string, state: string} base URL without a trailing slash and the state directory
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

	$owner = getmypid();

	// suites fork children that run shutdown functions too; only the starting process may stop the server
	register_shutdown_function(static function () use ($process, $state, $owner) : void {
		if (getmypid() !== $owner) {
			return;
		}

		proc_terminate($process);
		proc_close($process);

		array_map('unlink', glob($state . '/*') ?: []);
		rmdir($state);
	});

	for ($i = 0; $i < 100; $i++) {
		$socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $errstr, 0.1);

		if ($socket !== false) {
			fclose($socket);

			return $server = ['url' => 'http://127.0.0.1:' . $port, 'state' => $state];
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
