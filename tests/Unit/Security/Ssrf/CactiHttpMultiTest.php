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
 * cacti_http() and cacti_http_multi() against a loopback test server.
 *
 * The server is loopback, which the default policy refuses, so most tests
 * opt in with blocked_subnets the way the HTTP poller does.  The first test
 * pins the default: existing callers keep refusing loopback and redirects.
 */

require_once dirname(__DIR__, 4) . '/lib/functions.php';
require_once dirname(__DIR__, 3) . '/Helpers/HttpTestServer.php';

const CACTI_HTTP_TEST_METADATA = ['169.254.0.0/16', '::ffff:169.254.0.0/112'];

test('the default policy still refuses loopback and does not follow redirects', function () {
	$base = cacti_http_test_server()['url'];

	expect(cacti_http('GET', "$base/stats"))->toBe(['success' => false, 'status' => 0, 'body' => '', 'error' => 'Target host resolves to a disallowed address'])
		->and(cacti_http('GET', 'https://[::ffff:127.0.0.1]/'))->toMatchArray(['success' => false, 'error' => 'Target host resolves to a disallowed address'])
		->and(cacti_http('GET', 'ftp://example.com/'))->toMatchArray(['error' => 'Unsupported URL scheme'])
		->and(cacti_http('GET', ''))->toMatchArray(['error' => 'Invalid URL']);

	$result = cacti_http('GET', "$base/relative", ['blocked_subnets' => CACTI_HTTP_TEST_METADATA]);

	expect($result['success'])->toBeFalse()
		->and($result['status'])->toBe(302)
		->and($result['error'])->toBe('HTTP 302');
});

test('redirects are followed only when asked and each hop is checked again', function () {
	$base = cacti_http_test_server()['url'];
	$opts = ['blocked_subnets' => CACTI_HTTP_TEST_METADATA, 'max_redirects' => 3];

	expect(cacti_http('GET', "$base/relative", $opts))->toMatchArray(['success' => true, 'status' => 200, 'body' => '{"in":5,"out":7,"label":"eth0"}'])
		->and(cacti_http('GET', "$base/loop", $opts)['error'])->toBe('Too many redirects')
		->and(cacti_http('GET', "$base/redirect?to=metadata", $opts)['error'])->toBe('Target host resolves to a disallowed address')
		->and(cacti_http('GET', "$base/redirect?to=ftp", $opts)['error'])->toBe('Unsupported URL scheme')
		->and(cacti_http('GET', "$base/relative", $opts + ['allowed_hosts' => ['localhost']])['error'])->toBe('Host not allowed')
		->and(cacti_http('POST', "$base/relative", $opts + ['body' => 'x'])['error'])->toBe('HTTP 302');
});

test('credentials and authorization headers stay on the original origin', function () {
	$base = cacti_http_test_server()['url'];
	$port = parse_url($base, PHP_URL_PORT);
	$auth = 'http://user:' . rawurlencode('p@ss:word') . "@127.0.0.1:$port";
	$opts = ['blocked_subnets' => CACTI_HTTP_TEST_METADATA, 'max_redirects' => 1];

	$same  = cacti_http('GET', "$auth/redirect?to=auth", $opts);
	$cross = cacti_http('GET', "$auth/redirect?to=other-auth", $opts);

	// a secret can travel under any header name, so only Accept survives a new origin
	$header = ['headers' => ['Authorization: Bearer abc', 'Accept: application/json', 'X-Api-Key: secret', 'Proxy-Authorization: Basic eDp5']] + $opts;
	$kept   = json_decode(cacti_http('GET', "$base/redirect?to=headers", $header)['body'], true);
	$moved  = json_decode(cacti_http('GET', "$base/redirect?to=other-headers", $header)['body'], true);

	expect($same['success'])->toBeTrue()
		->and($cross['status'])->toBe(401)
		->and($kept)->toBe(['authorization' => 'Bearer abc', 'accept' => 'application/json', 'x-api-key' => 'secret', 'proxy-authorization' => 'Basic eDp5'])
		->and($moved)->toBe(['authorization' => '', 'accept' => 'application/json', 'x-api-key' => '', 'proxy-authorization' => '']);
});

test('bodies over max_bytes are abandoned, and compression is only accepted under a cap that counts decoded bytes', function () {
	$base = cacti_http_test_server()['url'];
	$opts = ['blocked_subnets' => CACTI_HTTP_TEST_METADATA];

	expect(cacti_http('GET', "$base/big", $opts + ['max_bytes' => 32]))->toMatchArray(['success' => false, 'error' => 'Response exceeds 32 bytes'])
		->and(cacti_http('GET', "$base/big", $opts + ['max_bytes' => 66])['success'])->toBeTrue()
		->and(cacti_http('GET', "$base/gzip", $opts + ['max_bytes' => 32])['error'])->toBe('Response exceeds 32 bytes')
		->and(json_decode(cacti_http('GET', "$base/gzip", $opts + ['max_bytes' => 1024])['body'], true))->toBe(['v' => 3])
		->and(json_decode(cacti_http('GET', "$base/headers", $opts)['body'], true)['accept'])->toBe('*/*');
});

test('the timeout covers the whole request and fractions are honoured', function () {
	$base  = cacti_http_test_server()['url'];
	$start = microtime(true);

	$result = cacti_http('GET', "$base/slow", ['blocked_subnets' => CACTI_HTTP_TEST_METADATA, 'timeout' => 0.3]);

	expect($result['error'])->toBe('Timed out')
		->and(microtime(true) - $start)->toBeLessThan(0.9);
});

test('multi keeps request keys and isolates failures', function () {
	$base = cacti_http_test_server()['url'];
	$opts = ['blocked_subnets' => CACTI_HTTP_TEST_METADATA];

	$results = cacti_http_multi([
		'a'  => ['method' => 'GET', 'url' => "$base/inflight/a", 'options' => $opts],
		'b'  => ['method' => 'GET', 'url' => "$base/inflight/b", 'options' => $opts],
		'c'  => ['method' => 'GET', 'url' => "$base/inflight/c", 'options' => $opts],
		7    => ['method' => 'GET', 'url' => 'https://169.254.169.254/', 'options' => $opts],
		'ok' => ['method' => 'GET', 'url' => "$base/stats", 'options' => $opts],
	], 4);

	expect(array_keys($results))->toBe(['a', 'b', 'c', 7, 'ok'])
		->and(array_column($results, 'success'))->toBe([true, true, true, false, true])
		->and($results[7]['error'])->toBe('Target host resolves to a disallowed address');
});

test('multi runs requests together up to the concurrency limit', function () {
	$base = cacti_http_test_server()['url'];
	$opts = ['blocked_subnets' => CACTI_HTTP_TEST_METADATA];

	// measured first with plain curl_multi, so a server that serves requests one
	// at a time skips this test instead of failing it as a client fault
	$overlap = cacti_http_test_server_overlap(3);

	if ($overlap < 3) {
		$this->markTestSkipped("the test server held $overlap of 3 plain curl requests together, so client overlap cannot be measured");
	}

	$requests = [];

	// each request is held until $wait are in flight: a client below the limit
	// reaches only 1, and one above the cap of 2 lets a third in beside the pair
	foreach ([3 => 'together', 2 => 'capped'] as $wait => $batch) {
		foreach (['a', 'b', 'c'] as $key) {
			$requests[$batch][$key] = ['method' => 'GET', 'url' => "$base/inflight/$key?wait=$wait", 'options' => $opts];
		}
	}

	$together = cacti_http_multi($requests['together'], 4);
	$peak     = cacti_http_test_peak();

	$capped   = cacti_http_multi($requests['capped'], 2);
	$cappedAt = cacti_http_test_peak();

	expect(array_column($together, 'success'))->toBe([true, true, true])
		->and($peak)->toBe(3)
		->and(array_column($capped, 'success'))->toBe([true, true, true])
		->and($cappedAt)->toBe(2);
});

test('names are checked through the system resolver as well as DNS', function () {
	expect(cacti_http_resolve_safe_ips('localhost'))->toBe([])
		->and(cacti_http_resolve_safe_ips('localhost', CACTI_HTTP_TEST_METADATA))->toContain('127.0.0.1')
		->and(cacti_http_resolve_safe_ips('169.254.169.254', CACTI_HTTP_TEST_METADATA))->toBe([])
		->and(cacti_http_resolve_safe_ips('[::ffff:169.254.169.254]', CACTI_HTTP_TEST_METADATA))->toBe([]);
});
