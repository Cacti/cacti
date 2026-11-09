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
 * Coverage probe for the HTTP transport behind the HTTP poller action:
 * cacti_http(), cacti_http_multi() and its helpers in lib/functions.php, and
 * the CactiHttpPoller and CactiHttpPollItem classes.
 *
 * Every request goes to the loopback test router through real curl.  Two
 * states curl cannot be made to reach on demand, a multi handle that stops
 * without reporting its transfers and a failure while transfers are still in
 * flight, run cacti_http_multi() compiled into the Stalled and Throwing
 * namespaces below, where one curl or settle function is replaced.
 */

namespace CactiHttpTransportFixture\Stalled {
	/**
	 * A multi handle that reports nothing running and never finished a transfer.
	 *
	 * @param \CurlMultiHandle $multi
	 * @param int|null         $running
	 *
	 * @return int
	 */
	function curl_multi_exec($multi, &$running) {
		$running = 0;

		return CURLM_OK;
	}

	/**
	 * No transfer ever reports completion.
	 *
	 * @param \CurlMultiHandle $multi
	 *
	 * @return false
	 */
	function curl_multi_info_read($multi) {
		return false;
	}
}

namespace CactiHttpTransportFixture\Throwing {
	/**
	 * Fail while settling the first finished transfer, with others still running.
	 *
	 * @param \CurlMultiHandle $multi
	 * @param \stdClass        $transfer
	 * @param int              $code
	 *
	 * @return never
	 */
	function cacti_http_finish($multi, $transfer, $code) {
		throw new \RuntimeException('settle failed for ' . $transfer->key);
	}
}

namespace CactiHttpTransportFixture {
	use CactiHttpPoller;
	use CactiHttpPollItem;
	use InvalidArgumentException;
	use RuntimeException;
	use stdClass;

	$root = dirname(__DIR__, 3);

	require $root . '/tests/Helpers/FixtureCoverage.php';
	require $root . '/tests/Helpers/SourceSlice.php';
	require $root . '/include/vendor/autoload.php';
	require $root . '/lib/functions.php';
	require $root . '/lib/CactiHttpPoller.php';
	require $root . '/tests/Helpers/HttpTestServer.php';

	// classes do not fall back to the global namespace the way functions do
	foreach (['Stalled', 'Throwing'] as $variant) {
		class_alias(stdClass::class, __NAMESPACE__ . '\\' . $variant . '\stdClass');
		cacti_fixture_source_function($root . '/lib/functions.php', 'cacti_http_multi', __NAMESPACE__ . '\\' . $variant);
	}

	// the loopback router is reachable the way the poller reaches private devices
	const METADATA = ['169.254.0.0/16', '::ffff:169.254.0.0/112'];
	const REFUSED  = 'Target host resolves to a disallowed address';

	/**
	 * Stop the probe, and with it the coverage upload, when a scenario fails.
	 *
	 * @param bool   $condition
	 * @param string $message
	 *
	 * @return void
	 */
	function check(bool $condition, string $message) : void {
		if (!$condition) {
			throw new RuntimeException($message);
		}
	}

	/**
	 * Expect $callback to reject an item spec.
	 *
	 * @param callable $callback
	 * @param string   $case
	 *
	 * @return void
	 */
	function rejects(callable $callback, string $case) : void {
		try {
			$callback();
		} catch (InvalidArgumentException $e) {
			return;
		}

		throw new RuntimeException('accepted ' . $case);
	}

	/**
	 * A poller_item row the way update_poller_cache() stores it.
	 *
	 * @param array<string, mixed> $overrides
	 *
	 * @return array<string, mixed>
	 */
	function row(array $overrides = []) : array {
		return $overrides + [
			'host_id'       => 1,
			'local_data_id' => 10,
			'rrd_name'      => 'value',
			'arg1'          => 'https://device.example/api/stats',
			'arg2'          => '/value',
			'arg3'          => '',
		];
	}

	$base = cacti_http_test_server()['url'];
	$held = cacti_http_test_server()['inflight'];
	$port = parse_url($base, PHP_URL_PORT);
	$opts = ['blocked_subnets' => METADATA];

	// the default policy still refuses loopback and does not follow redirects
	check(cacti_http('GET', "$base/stats")['error'] === REFUSED, 'default policy reached loopback');
	check(cacti_http('GET', '')['error'] === 'Invalid URL', 'empty url accepted');
	check(cacti_http('GET', 'http://')['error'] === 'Invalid URL', 'hostless url accepted');
	check(cacti_http('GET', 'ftp://example.com/')['error'] === 'Unsupported URL scheme', 'ftp accepted');
	check(cacti_http('GET', 'https://nonexistent.invalid/')['error'] === REFUSED, 'unresolvable host accepted');

	$unfollowed = cacti_http('GET', "$base/relative", $opts);

	check($unfollowed['status'] === 302 && $unfollowed['error'] === 'HTTP 302', 'redirect followed by default');

	// redirects are followed only when asked, each hop is checked again and the redirect body is dropped
	$follow = $opts + ['max_redirects' => 3];

	check(cacti_http('GET', "$base/relative", $follow)['body'] === '{"in":5,"out":7,"label":"eth0"}', 'relative redirect not followed');
	check(cacti_http('HEAD', "$base/relative", $follow)['status'] === 200, 'HEAD redirect not followed');
	check(cacti_http('GET', "$base/loop", $follow)['error'] === 'Too many redirects', 'redirect loop not stopped');
	check(cacti_http('GET', "$base/redirect?to=metadata", $follow)['error'] === REFUSED, 'redirect into metadata followed');
	check(cacti_http('GET', "$base/redirect?to=ftp", $follow)['error'] === 'Unsupported URL scheme', 'redirect to ftp followed');
	check(cacti_http('GET', "$base/relative", $follow + ['allowed_hosts' => ['localhost']])['error'] === 'Host not allowed', 'allowed_hosts ignored');
	check(cacti_http('POST', "$base/relative", $follow + ['body' => 'x'])['error'] === 'HTTP 302', 'POST redirect followed');

	// credentials and authorization headers stay on the original scheme, host and port
	$auth  = 'http://user:' . rawurlencode('p@ss:word') . "@127.0.0.1:$port";
	$once  = $opts + ['max_redirects' => 1];
	$moved = $once + ['headers' => ['Authorization: Bearer abc', 'Accept: application/json', 'X-Api-Key: secret']];

	check(cacti_http('GET', "$auth/redirect?to=auth", $once)['success'], 'credentials lost on a same-origin redirect');
	check(cacti_http('GET', "$auth/redirect?to=other-auth", $once)['status'] === 401, 'credentials sent to another origin');
	check(json_decode(cacti_http('GET', "$base/redirect?to=headers", $moved)['body'], true) === ['authorization' => 'Bearer abc', 'accept' => 'application/json', 'x-api-key' => 'secret', 'proxy-authorization' => ''], 'headers lost on a same-origin redirect');
	check(json_decode(cacti_http('GET', "$base/redirect?to=other-headers", $moved)['body'], true) === ['authorization' => '', 'accept' => 'application/json', 'x-api-key' => '', 'proxy-authorization' => ''], 'caller headers sent to another origin');

	// bodies over max_bytes are abandoned; compression is accepted only under a cap that counts decoded bytes
	check(cacti_http('GET', "$base/big", $opts + ['max_bytes' => 32])['error'] === 'Response exceeds 32 bytes', 'oversized body accepted');
	check(cacti_http('GET', "$base/big", $opts + ['max_bytes' => 66])['success'], 'body at the cap refused');
	check(cacti_http('GET', "$base/gzip", $opts + ['max_bytes' => 32])['error'] === 'Response exceeds 32 bytes', 'decoded size not capped');
	check(json_decode(cacti_http('GET', "$base/gzip", $opts + ['max_bytes' => 1024])['body'], true) === ['v' => 3], 'gzip body not decoded');
	check(json_decode(cacti_http('GET', "$base/headers", $opts)['body'], true)['accept'] === '*/*', 'compression offered without a cap');

	// the timeout covers the whole request, and a hop that starts after the budget is refused
	check(cacti_http('GET', "$base/slow", $opts + ['timeout' => 0.3])['error'] === 'Timed out', 'slow body not timed out');
	check(cacti_http('GET', 'http://127.0.0.1:1/', $opts)['error'] !== '', 'refused connection without an error');

	$late = new stdClass();

	$late->url      = "$base/stats";
	$late->method   = 'GET';
	$late->options  = $opts;
	$late->deadline = microtime(true) - 1;

	check(cacti_http_start(curl_multi_init(), $late) === 'Timed out', 'hop started after its deadline');

	// multi keeps request keys and isolates failures; HttpPollerTest asserts concurrency
	$results = cacti_http_multi([
		'a'  => ['method' => 'GET', 'url' => "$held/inflight/a", 'options' => $opts],
		'b'  => ['method' => 'GET', 'url' => "$held/inflight/b", 'options' => $opts],
		7    => ['method' => 'GET', 'url' => 'https://169.254.169.254/', 'options' => $opts],
		'ok' => ['method' => 'GET', 'url' => "$base/stats", 'options' => $opts],
	], 4);

	check(array_keys($results) === ['a', 'b', 7, 'ok'], 'multi reordered its results');
	check(array_column($results, 'success') === [true, true, false, true], 'multi results were ' . json_encode($results));
	check(cacti_http_multi([['method' => 'GET', 'url' => '']])[0]['error'] === 'Invalid URL', 'multi with only refused requests');

	// a group at its limit waits while another group's transfer starts
	$grouped = cacti_http_multi([
		'a1' => ['method' => 'GET', 'url' => "$held/inflight/a1", 'group' => 'a', 'options' => $opts],
		'a2' => ['method' => 'GET', 'url' => "$held/inflight/a2", 'group' => 'a', 'options' => $opts],
		'b1' => ['method' => 'GET', 'url' => "$held/inflight/b1", 'group' => 'b', 'options' => $opts],
	], 3, 1);

	check(array_column($grouped, 'success') === [true, true, true], 'grouped results were ' . json_encode($grouped));

	// names go through the system resolver as well as DNS
	check(cacti_http_resolve_safe_ips('localhost') === [], 'localhost passed the default policy');
	check(in_array('127.0.0.1', cacti_http_resolve_safe_ips('localhost', METADATA), true), 'localhost not resolved');
	check(cacti_http_resolve_safe_ips('[::ffff:169.254.169.254]', METADATA) === [], 'mapped metadata address allowed');

	// a multi handle that stops without reporting its transfers fails them instead of spinning
	$stalled = Stalled\cacti_http_multi([['method' => 'GET', 'url' => "$base/stats", 'options' => $opts]]);

	check($stalled[0]['error'] === 'Request did not complete', 'stalled transfer was ' . json_encode($stalled));

	// a failure while settling still detaches the transfers that are in flight
	try {
		Throwing\cacti_http_multi([
			'slow' => ['method' => 'GET', 'url' => "$base/slow", 'options' => $opts],
			'fast' => ['method' => 'GET', 'url' => "$base/stats", 'options' => $opts],
		], 2);

		throw new RuntimeException('settle failure was swallowed');
	} catch (RuntimeException $e) {
		// whichever transfer settles first, the other is still attached when the failure unwinds
		check(in_array($e->getMessage(), ['settle failed for slow', 'settle failed for fast'], true), 'unexpected failure: ' . $e->getMessage());
	}

	// item specs
	$document = ['a/b' => ['m~n' => 7], 'list' => [['v' => 1], ['v' => 2]], '' => 3];

	check(CactiHttpPollItem::pointer($document, '') === $document, 'empty pointer');
	check(CactiHttpPollItem::pointer($document, '/a~1b/m~0n') === 7, 'escaped pointer');
	check(CactiHttpPollItem::pointer($document, '/list/1/v') === 2, 'list pointer');
	check(CactiHttpPollItem::pointer($document, '/list/01/v') === null, 'leading zero index');
	check(CactiHttpPollItem::pointer($document, '/missing') === null, 'missing key');
	check(CactiHttpPollItem::pointer($document, '/a~1b/m~0n/deeper') === null, 'pointer below a scalar');

	$item   = new CactiHttpPollItem(1, 10, 'value', 'https://device.example/', '/v');
	$halved = new CactiHttpPollItem(1, 10, 'value', 'https://device.example/', '/v', 0.5);
	$huge   = new CactiHttpPollItem(1, 10, 'value', 'https://device.example/', '/v', 1e308);

	check($item->resolve(['v' => '18446744073709551615']) === '18446744073709551615', 'counter lost digits');
	check($item->resolve(['v' => 0.1]) === '0.1', 'float output');
	check($item->resolve(['v' => true]) === '1', 'boolean output');
	check($halved->resolve(['v' => 3]) === '1.5', 'scaled output');
	check($item->resolve(['v' => 'up']) === null, 'text accepted');
	check($huge->resolve(['v' => 1e308]) === null, 'infinite output accepted');
	check($item->resolve(json_decode('{"v":1e400}', true)) === null, 'unscaled infinite value accepted');

	$spec = CactiHttpPollItem::fromPollerItem(row(['arg3' => '{"scale":0.001,"timeout":2.5}']));

	check($spec->scale === 0.001 && $spec->timeout === 2.5 && $spec->endpointKey === '1 https://device.example/api/stats', 'decoded spec');

	foreach ([
		'file scheme'      => ['arg1' => 'file:///etc/passwd'],
		'bad pointer'      => ['arg2' => 'value'],
		'options not json' => ['arg3' => 'scale=2'],
		'options a list'   => ['arg3' => '[1]'],
		'text scale'       => ['arg3' => CactiHttpPollItem::encodeOptions('abc', '')],
		'zero timeout'     => ['arg3' => '{"timeout":0}'],
	] as $case => $overrides) {
		rejects(fn () => CactiHttpPollItem::fromPollerItem(row($overrides)), $case);
	}

	rejects(fn () => new CactiHttpPollItem(1, 1, 'v', 'https://device.example/', '/v', INF), 'infinite scale');

	check(CactiHttpPollItem::encodeOptions('', '') === '', 'blank options');
	check(CactiHttpPollItem::encodeOptions('0.001', ' 3 ') === '{"scale":0.001,"timeout":3}', 'numeric options');
	check((new CactiHttpPollItem(1, 1, 'v', 'https://u:p@device.example:8443/x?k=v'))->redactedUrl() === 'https://device.example:8443/x', 'redacted url');
	check(CactiHttpPollItem::redact('not a url') === 'invalid URL', 'unparseable url');

	// the poller: concurrency bounds, shared endpoints, longest timeout and per-endpoint failures
	rejects(fn () => new CactiHttpPoller(0), 'zero concurrency');
	check(CactiHttpPoller::create(0) instanceof CactiHttpPoller && CactiHttpPoller::create(1000) instanceof CactiHttpPoller, 'create did not clamp');
	check(in_array('64:ff9b::169.254.0.0/112', CactiHttpPoller::blockedSubnets(), true), 'NAT64 metadata range missing');

	$seen  = count(cacti_http_test_requests());
	$items = [
		new CactiHttpPollItem(1, 1, 'v', "$base/stats", '/in'),
		new CactiHttpPollItem(1, 2, 'v', "$base/stats", '/out'),
		new CactiHttpPollItem(1, 3, 'v', "$base/slow", '/v', 1.0, 0.2),
		new CactiHttpPollItem(1, 4, 'v', "$base/slow", '/v', 1.0, 5.0),
		new CactiHttpPollItem(1, 5, 'v', "$base/html", '/v'),
		new CactiHttpPollItem(1, 6, 'v', 'http://127.0.0.1:1/down?token=secret', '/v'),
		new CactiHttpPollItem(1, 7, 'v', 'https://169.254.169.254/latest', '/v'),
	];

	$errors    = [];
	$documents = (new CactiHttpPoller(4))->fetch($items, $errors);

	check(array_keys($documents) === ["1 $base/stats", "1 $base/slow"], 'documents were ' . json_encode(array_keys($documents)));
	check(count(array_keys(array_slice(cacti_http_test_requests(), $seen), '/stats', true)) === 1, 'shared endpoint fetched twice');
	check(str_starts_with($errors["1 $base/html"], 'Invalid JSON'), 'html accepted as JSON');
	check($errors['1 http://127.0.0.1:1/down?token=secret'] !== '' && !str_contains($errors['1 http://127.0.0.1:1/down?token=secret'], 'secret'), 'error kept the token');
	check($errors['1 https://169.254.169.254/latest'] === REFUSED, 'metadata service reached');

	$reasons = [];
	$outputs = (new CactiHttpPoller())->collect([
		row(['local_data_id' => 1, 'arg1' => "$base/stats", 'arg2' => '/in']),
		row(['local_data_id' => 2, 'arg1' => "$base/stats", 'arg2' => '/label']),
		row(['local_data_id' => 3, 'arg1' => "$base/down"]),
		row(['local_data_id' => 4, 'arg1' => 'ftp://device.example/']),
	], $reasons);

	check($outputs === ['4:value' => 'U', '1:value' => '5', '2:value' => 'U', '3:value' => 'U'], 'collect outputs were ' . json_encode($outputs));
	check($reasons['2:value'] === 'JSON Pointer did not select a number' && $reasons['3:value'] === 'HTTP 503', 'collect reasons were ' . json_encode($reasons));

	print json_encode(['http_transport_scenarios' => 12], JSON_THROW_ON_ERROR) . "\n";
}
