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

/*
 * Loopback server that counts how many requests a client keeps in flight,
 * started by cacti_http_test_server().
 *
 * PHP's built-in server cannot measure this: each worker serves one request
 * at a time, and an idle worker may accept a second connection before it
 * runs the first, so two requests the client sent together are served one
 * after the other.  This server is a single stream_select() loop that
 * accepts every connection as soon as it arrives, so the count depends only
 * on what the client sends.
 *
 * Routes:
 *   /inflight/<any>?wait=N  held for at least 300 ms, and longer while fewer
 *                           than N requests have been in flight together
 *                           since the last reset, but never past 2 s
 *   /peak                   {"peak": the highest in-flight count since the last reset}
 *   /reset-peak             start a new measurement
 *
 * The listening address is printed on stdout.  The server exits when its
 * stdin closes, so it cannot outlive the test process.
 */

const HOLD_MIN = 0.3;
const HOLD_MAX = 2.0;

$listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

if ($listener === false) {
	fwrite(STDERR, "listen failed: $errstr\n");

	exit(1);
}

stream_set_blocking($listener, false);

fwrite(STDOUT, stream_socket_get_name($listener, false) . "\n");
fflush(STDOUT);

$clients  = [];
$inflight = 0;
$peak     = 0;

/**
 * Write a JSON response and close the connection.
 *
 * @param resource $socket the client connection
 * @param string   $body   the JSON body
 *
 * @return void
 */
function inflight_respond($socket, string $body) : void {
	stream_set_blocking($socket, true);
	@fwrite($socket, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body);
	fclose($socket);
}

while (true) {
	$read = [$listener, STDIN];

	foreach ($clients as $client) {
		if ($client['held'] === null) {
			$read[] = $client['socket'];
		}
	}

	$write  = null;
	$except = null;

	if (stream_select($read, $write, $except, 0, 10000) === false) {
		exit(1);
	}

	foreach ($read as $socket) {
		if ($socket === STDIN) {
			if (fread(STDIN, 1024) === '' && feof(STDIN)) {
				exit(0);
			}
		} elseif ($socket === $listener) {
			while (($accepted = @stream_socket_accept($listener, 0)) !== false) {
				stream_set_blocking($accepted, false);

				$clients[(int) $accepted] = ['socket' => $accepted, 'buffer' => '', 'held' => null, 'wait' => 0];
			}
		} else {
			$id   = (int) $socket;
			$data = fread($socket, 8192);

			if ($data === '' || $data === false) {
				if (feof($socket)) {
					fclose($socket);
					unset($clients[$id]);
				}

				continue;
			}

			$clients[$id]['buffer'] .= $data;

			if (!str_contains($clients[$id]['buffer'], "\r\n\r\n")) {
				continue;
			}

			$target = explode(' ', strtok($clients[$id]['buffer'], "\r\n"))[1] ?? '/';
			$path   = (string) parse_url($target, PHP_URL_PATH);

			parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

			if (str_starts_with($path, '/inflight/')) {
				$inflight++;
				$peak = max($peak, $inflight);

				$clients[$id]['held'] = microtime(true);
				$clients[$id]['wait'] = (int) ($query['wait'] ?? 0);

				continue;
			}

			if ($path === '/reset-peak') {
				$peak = $inflight;
			}

			inflight_respond($socket, $path === '/peak' ? json_encode(['peak' => $peak]) : '{}');
			unset($clients[$id]);
		}
	}

	$now = microtime(true);

	foreach ($clients as $id => $client) {
		if ($client['held'] === null) {
			continue;
		}

		$elapsed = $now - $client['held'];

		if (($elapsed >= HOLD_MIN && $peak >= $client['wait']) || $elapsed >= HOLD_MAX) {
			inflight_respond($client['socket'], '{"v":1}');
			unset($clients[$id]);

			$inflight--;
		}
	}
}
