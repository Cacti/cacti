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
 * Router for PHP's built-in server, started by cacti_http_test_server().
 *
 * Each route stands in for one device behaviour the HTTP client and poller
 * must handle: plain JSON, error statuses, slow and oversized bodies,
 * redirects (relative, looping, into a blocked range) and Basic auth.
 */

$state = getenv('CACTI_HTTP_TEST_STATE');
$path  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

file_put_contents($state . '/requests', $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND | LOCK_EX);

/**
 * Add $delta to the in-flight counter and keep the highest value seen.
 */
function http_test_inflight(string $state, int $delta) : void {
	$fh = fopen($state . '/inflight', 'c+');

	flock($fh, LOCK_EX);

	[$now, $peak] = array_map('intval', explode(' ', stream_get_contents($fh) ?: '0 0') + [0, 0]);

	$now += $delta;

	ftruncate($fh, 0);
	rewind($fh);
	fwrite($fh, $now . ' ' . max($peak, $now));
	flock($fh, LOCK_UN);
	fclose($fh);
}

/**
 * Hold a request for at least 300 ms, and longer while fewer than $wait
 * requests have been in flight together since the last reset.
 *
 * A fixed sleep only shows overlap when the client and the server happen to
 * line up; holding until the peak reaches $wait makes overlap certain whenever
 * both sides can run that many requests at once.  The 2 s deadline turns a
 * client or server that cannot into a peak below $wait instead of a hang.
 *
 * @param string $state the router state directory
 * @param int    $wait  in-flight requests to wait for; 0 only applies the minimum hold
 *
 * @return void
 */
function http_test_barrier(string $state, int $wait) : void {
	$start = microtime(true);

	while (true) {
		$elapsed = microtime(true) - $start;
		$peak    = (int) (explode(' ', (string) @file_get_contents($state . '/inflight') . ' 0')[1] ?? 0);

		if (($elapsed >= 0.3 && $peak >= $wait) || $elapsed >= 2) {
			return;
		}

		usleep(10000);
	}
}

header('Content-Type: application/json');

switch (true) {
	case $path === '/stats':
		print '{"in":5,"out":7,"label":"eth0"}';

		break;
	case $path === '/missing':
		http_response_code(404);
		print '{}';

		break;
	case $path === '/down':
		http_response_code(503);

		break;
	case $path === '/html':
		header('Content-Type: text/html');
		print '<html>';

		break;
	case $path === '/slow':
		sleep(1);
		print '{"v":1}';

		break;
	case $path === '/big':
		print str_repeat(' ', 64) . '{}';

		break;
	case $path === '/gzip':
		header('Content-Encoding: gzip');
		print gzencode('{"v":' . str_repeat(' ', 64) . '3}');

		break;
	case $path === '/relative':
		// a body the client must drop when it follows the redirect
		header('Location: /stats', true, 302);
		print '{"moved":true}';

		break;
	case $path === '/loop':
		header('Location: /loop', true, 302);

		break;
	case $path === '/redirect':
		// fixed targets only, so the router is not an open redirect
		$other = 'http://localhost:' . (int) $_SERVER['SERVER_PORT'];

		switch ($_GET['to'] ?? '') {
			case 'auth':
				$location = '/auth';

				break;
			case 'headers':
				$location = '/headers';

				break;
			case 'other-auth':
				$location = $other . '/auth';

				break;
			case 'other-headers':
				$location = $other . '/headers';

				break;
			case 'metadata':
				$location = 'http://169.254.169.254/latest';

				break;
			case 'ftp':
				$location = 'ftp://example.com/x';

				break;
			default:
				$location = '';
		}

		if ($location !== '') {
			header('Location: ' . $location, true, 302);
		} else {
			http_response_code(400);
		}

		break;
	case $path === '/auth':
		if (($_SERVER['PHP_AUTH_USER'] ?? '') === 'user' && ($_SERVER['PHP_AUTH_PW'] ?? '') === 'p@ss:word') {
			print '{"v":1}';
		} else {
			http_response_code(401);
		}

		break;
	case $path === '/headers':
		$received = array_change_key_case(getallheaders());

		print json_encode([
			'authorization'       => $received['authorization'] ?? '',
			'accept'              => $received['accept'] ?? '',
			'x-api-key'           => $received['x-api-key'] ?? '',
			'proxy-authorization' => $received['proxy-authorization'] ?? '',
		]);

		break;
	case str_starts_with($path, '/inflight/'):
		http_test_inflight($state, 1);
		http_test_barrier($state, (int) ($_GET['wait'] ?? 0));
		http_test_inflight($state, -1);
		print '{"v":1}';

		break;
	case $path === '/reset-peak':
		file_put_contents($state . '/inflight', '0 0', LOCK_EX);
		print '{}';

		break;
	case $path === '/peak':
		print json_encode(['peak' => (int) (explode(' ', (string) @file_get_contents($state . '/inflight'))[1] ?? 0)]);

		break;
	default:
		http_response_code(404);
}
