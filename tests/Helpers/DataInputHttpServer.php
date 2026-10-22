<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Starts the PHP built-in web server on a free loopback port with the
 * fixtures/DataInput router, for tests of the HTTP data input scripts.
 */

/**
 * @return array{process: resource, address: string}|null
 */
function data_input_http_server_start() : ?array {
	$probe = stream_socket_server('tcp://127.0.0.1:0');

	if ($probe === false) {
		return null;
	}

	$address = (string) stream_socket_get_name($probe, false);

	fclose($probe);

	$router  = dirname(__DIR__) . '/fixtures/DataInput/http_router.php';
	$process = proc_open([PHP_BINARY, '-S', $address, $router], [
		0 => ['file', '/dev/null', 'r'],
		1 => ['file', '/dev/null', 'w'],
		2 => ['file', '/dev/null', 'w']
	], $pipes);

	if (!is_resource($process)) {
		return null;
	}

	for ($i = 0; $i < 100; $i++) {
		$socket = @stream_socket_client('tcp://' . $address, $errno, $errstr, 0.1);

		if ($socket !== false) {
			fclose($socket);

			return ['process' => $process, 'address' => $address];
		}

		usleep(50000);
	}

	proc_terminate($process);
	proc_close($process);

	return null;
}

/**
 * @param array{process: resource, address: string}|null $server
 */
function data_input_http_server_stop(?array $server) : void {
	if ($server !== null) {
		proc_terminate($server['process']);
		proc_close($server['process']);
	}
}
