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

require_once dirname(__DIR__, 3) . '/lib/functions.php';
require_once dirname(__DIR__, 3) . '/lib/CactiHttpPoller.php';
require_once dirname(__DIR__, 2) . '/Helpers/HttpTestServer.php';

/**
 * Build a poller item row the way update_poller_cache() stores it.
 *
 * @param array<string, mixed> $overrides
 *
 * @return array<string, mixed>
 */
function http_poller_row(array $overrides = []) : array {
	return $overrides + [
		'host_id'       => 1,
		'local_data_id' => 10,
		'rrd_name'      => 'value',
		'arg1'          => 'https://device.example/api/stats',
		'arg2'          => '/value',
		'arg3'          => '',
	];
}

test('pointer follows objects, lists and escaped tokens', function () {
	$document = ['a/b' => ['m~n' => 7], 'list' => [['v' => 1], ['v' => 2]], '' => 3];

	expect(CactiHttpPollItem::pointer($document, ''))->toBe($document)
		->and(CactiHttpPollItem::pointer($document, '/a~1b/m~0n'))->toBe(7)
		->and(CactiHttpPollItem::pointer($document, '/list/1/v'))->toBe(2)
		->and(CactiHttpPollItem::pointer($document, '/'))->toBe(3)
		->and(CactiHttpPollItem::pointer($document, '/list/01/v'))->toBeNull()
		->and(CactiHttpPollItem::pointer($document, '/list/-'))->toBeNull()
		->and(CactiHttpPollItem::pointer($document, '/list/2'))->toBeNull()
		->and(CactiHttpPollItem::pointer($document, '/missing'))->toBeNull()
		->and(CactiHttpPollItem::pointer($document, '/a~1b/m~0n/deeper'))->toBeNull();
});

test('resolve scales numbers and keeps unscaled counters exact', function () {
	$item    = new CactiHttpPollItem(1, 10, 'value', 'https://device.example/', '/v');
	$halved  = new CactiHttpPollItem(1, 10, 'value', 'https://device.example/', '/v', 0.5);

	expect($item->resolve(['v' => '18446744073709551615']))->toBe('18446744073709551615')
		->and($item->resolve(['v' => 42]))->toBe('42')
		->and($item->resolve(['v' => 0.1]))->toBe('0.1')
		->and($item->resolve(['v' => true]))->toBe('1')
		->and($halved->resolve(['v' => 3]))->toBe('1.5')
		->and($halved->resolve(['v' => '8']))->toBe('4.0')
		->and($item->resolve(['v' => 'up']))->toBeNull()
		->and($item->resolve(['v' => null]))->toBeNull()
		->and($item->resolve(['v' => [1]]))->toBeNull()
		->and($item->resolve([]))->toBeNull();

	$huge = new CactiHttpPollItem(1, 10, 'value', 'https://device.example/', '/v', 1e308);

	expect($huge->resolve(['v' => 1e308]))->toBeNull();
});

test('poller rows decode the item spec from the argument columns', function () {
	$item = CactiHttpPollItem::fromPollerItem(http_poller_row(['arg3' => '{"scale":0.001,"timeout":2.5}']));

	expect($item->url)->toBe('https://device.example/api/stats')
		->and($item->pointer)->toBe('/value')
		->and($item->scale)->toBe(0.001)
		->and($item->timeout)->toBe(2.5)
		->and($item->endpointKey)->toBe('1 https://device.example/api/stats');

	$defaults = CactiHttpPollItem::fromPollerItem(http_poller_row());

	expect($defaults->scale)->toBe(1.0)
		->and($defaults->timeout)->toBe(CactiHttpPollItem::DEFAULT_TIMEOUT);
});

test('broken item specs are rejected', function (array $row) {
	expect(fn () => CactiHttpPollItem::fromPollerItem(http_poller_row($row)))->toThrow(InvalidArgumentException::class);
})->with([
	'file scheme'      => [['arg1' => 'file:///etc/passwd']],
	'relative url'     => [['arg1' => '/api/stats']],
	'blank url'        => [['arg1' => '']],
	'bad pointer'      => [['arg2' => 'value']],
	'options not json' => [['arg3' => 'scale=2']],
	'options a list'   => [['arg3' => '[1]']],
	'text scale'       => [['arg3' => '{"scale":"x"}']],
	'zero timeout'     => [['arg3' => '{"timeout":0}']],
	'huge timeout'     => [['arg3' => '{"timeout":61}']],
]);

test('options encode numbers and keep bad input visible', function () {
	expect(CactiHttpPollItem::encodeOptions('', ''))->toBe('')
		->and(CactiHttpPollItem::encodeOptions('0.001', ' 3 '))->toBe('{"scale":0.001,"timeout":3}')
		->and(CactiHttpPollItem::encodeOptions('8', ''))->toBe('{"scale":8}')
		->and(CactiHttpPollItem::encodeOptions('abc', ''))->toBe('{"scale":"abc"}');

	expect(fn () => CactiHttpPollItem::fromPollerItem(http_poller_row(['arg3' => CactiHttpPollItem::encodeOptions('abc', '')])))
		->toThrow(InvalidArgumentException::class);
});

test('log urls drop credentials and query strings', function () {
	$item = new CactiHttpPollItem(1, 10, 'value', 'https://user:secret@device.example:8443/api/stats?apikey=secret');

	expect($item->redactedUrl())->toBe('https://device.example:8443/api/stats');
});

test('items sharing a device and endpoint share one request', function () {
	$base  = cacti_http_test_server()['url'];
	$seen  = count(cacti_http_test_requests());
	$items = [
		CactiHttpPollItem::fromPollerItem(http_poller_row(['local_data_id' => 1, 'arg1' => "$base/stats", 'arg2' => '/in'])),
		CactiHttpPollItem::fromPollerItem(http_poller_row(['local_data_id' => 2, 'arg1' => "$base/stats", 'arg2' => '/out'])),
		CactiHttpPollItem::fromPollerItem(http_poller_row(['local_data_id' => 3, 'arg1' => "$base/stats", 'arg2' => '/in', 'host_id' => 2])),
	];

	$errors    = [];
	$documents = (new CactiHttpPoller())->fetch($items, $errors);

	expect(array_slice(cacti_http_test_requests(), $seen))->toBe(['/stats', '/stats'])
		->and($errors)->toBe([])
		->and($items[0]->resolve($documents[$items[0]->endpointKey]))->toBe('5')
		->and($items[1]->resolve($documents[$items[1]->endpointKey]))->toBe('7')
		->and($items[2]->resolve($documents[$items[2]->endpointKey]))->toBe('5');
});

test('a shared endpoint gets the longest timeout of its items', function () {
	$base  = cacti_http_test_server()['url'];
	$items = [
		CactiHttpPollItem::fromPollerItem(http_poller_row(['local_data_id' => 1, 'arg1' => "$base/slow", 'arg2' => '/v', 'arg3' => '{"timeout":0.2}'])),
		CactiHttpPollItem::fromPollerItem(http_poller_row(['local_data_id' => 2, 'arg1' => "$base/slow", 'arg2' => '/v', 'arg3' => '{"timeout":5}'])),
	];

	$errors    = [];
	$documents = (new CactiHttpPoller())->fetch($items, $errors);

	expect($errors)->toBe([])
		->and($items[0]->resolve($documents[$items[0]->endpointKey]))->toBe('1');
});

test('no more than the configured number of requests are in flight', function () {
	$base  = cacti_http_test_server()['url'];
	$items = [];

	for ($i = 1; $i <= 6; $i++) {
		$items[] = new CactiHttpPollItem(1, $i, 'v', "$base/inflight/$i", '/v');
	}

	$errors    = [];
	$documents = (new CactiHttpPoller(2))->fetch($items, $errors);
	$peak      = json_decode(file_get_contents("$base/peak"), true)['peak'];

	expect($errors)->toBe([])
		->and($documents)->toHaveCount(6)
		->and($peak)->toBe(2);
});

test('failed endpoints are reported without stopping the others', function () {
	$base  = cacti_http_test_server()['url'];
	$items = [
		new CactiHttpPollItem(1, 1, 'v', "$base/stats", '/in'),
		new CactiHttpPollItem(1, 2, 'v', "$base/missing", '/v'),
		new CactiHttpPollItem(1, 3, 'v', "$base/html", '/v'),
		new CactiHttpPollItem(1, 4, 'v', "$base/slow", '/v', 1.0, 0.2),
		new CactiHttpPollItem(1, 5, 'v', "$base/big", '/v'),
		new CactiHttpPollItem(1, 6, 'v', 'http://127.0.0.1:1/down?token=secret', '/v'),
		new CactiHttpPollItem(1, 7, 'v', "$base/loop", '/v'),
	];

	$errors    = [];
	$documents = (new CactiHttpPoller(3, 32))->fetch($items, $errors);

	expect(array_keys($documents))->toBe(["1 $base/stats"])
		->and($errors["1 $base/missing"])->toBe('HTTP 404')
		->and($errors["1 $base/html"])->toStartWith('Invalid JSON')
		->and($errors["1 $base/slow"])->toBe('Timed out')
		->and($errors["1 $base/big"])->toBe('Response exceeds 32 bytes')
		->and($errors["1 $base/loop"])->toBe('Too many redirects')
		->and($errors['1 http://127.0.0.1:1/down?token=secret'])->not->toBe('')
		->and($errors['1 http://127.0.0.1:1/down?token=secret'])->not->toContain('secret');
});

test('concurrency outside the supported range is rejected or clamped', function () {
	expect(fn () => new CactiHttpPoller(0))->toThrow(InvalidArgumentException::class)
		->and(fn () => new CactiHttpPoller(CactiHttpPoller::MAX_CONCURRENCY + 1))->toThrow(InvalidArgumentException::class)
		->and(CactiHttpPoller::create(0))->toBeInstanceOf(CactiHttpPoller::class)
		->and(CactiHttpPoller::create(1000))->toBeInstanceOf(CactiHttpPoller::class);
});

test('collect returns one output per row with a reason for each failure', function () {
	$base = cacti_http_test_server()['url'];
	$seen = count(cacti_http_test_requests());

	$rows = [
		http_poller_row(['local_data_id' => 1, 'arg1' => "$base/stats", 'arg2' => '/in']),
		http_poller_row(['local_data_id' => 2, 'arg1' => "$base/stats", 'arg2' => '/label']),
		http_poller_row(['local_data_id' => 3, 'arg1' => "$base/down"]),
		http_poller_row(['local_data_id' => 4, 'arg1' => 'ftp://device.example/']),
	];

	$reasons = [];
	$outputs = (new CactiHttpPoller())->collect($rows, $reasons);

	$served = array_slice(cacti_http_test_requests(), $seen);

	sort($served);

	expect($served)->toBe(['/down', '/stats'])
		->and($outputs)->toBe(['4:value' => 'U', '1:value' => '5', '2:value' => 'U', '3:value' => 'U'])
		->and(array_keys($reasons))->toBe(['4:value', '2:value', '3:value'])
		->and($reasons['2:value'])->toBe('JSON Pointer did not select a number')
		->and($reasons['3:value'])->toBe('HTTP 503')
		->and($reasons['4:value'])->toContain('http or https');
});

test('link-local and metadata destinations are refused while loopback and private networks stay reachable', function () {
	$base     = cacti_http_test_server()['url'];
	$port     = parse_url($base, PHP_URL_PORT);
	$refused  = 'Target host resolves to a disallowed address';
	$items    = [
		new CactiHttpPollItem(1, 1, 'v', 'http://169.254.169.254/latest/meta-data', '/v'),
		new CactiHttpPollItem(1, 2, 'v', 'http://[fe80::1]/stats', '/v'),
		new CactiHttpPollItem(1, 3, 'v', 'http://[::ffff:169.254.169.254]/latest', '/v'),
		new CactiHttpPollItem(1, 4, 'v', 'http://[64:ff9b::a9fe:a9fe]/latest', '/v'),
		new CactiHttpPollItem(1, 5, 'v', 'http://100.100.100.200/latest', '/v'),
		new CactiHttpPollItem(1, 6, 'v', 'http://[::ffff:100.100.100.200]/latest', '/v'),
		new CactiHttpPollItem(1, 7, 'v', 'http://[64:ff9b::c000:c0]/latest', '/v'),
		new CactiHttpPollItem(1, 8, 'v', "$base/redirect?to=metadata", '/v'),
		new CactiHttpPollItem(1, 9, 'v', "http://localhost:$port/stats", '/in'),
		new CactiHttpPollItem(1, 10, 'v', "$base/stats", '/in'),
		new CactiHttpPollItem(1, 11, 'v', 'http://10.255.255.1/stats', '/v', 1.0, 0.2),
	];

	$errors    = [];
	$documents = (new CactiHttpPoller(16))->fetch($items, $errors);

	expect(array_keys($documents))->toBe(["1 http://localhost:$port/stats", "1 $base/stats"])
		->and(array_count_values($errors)[$refused])->toBe(8)
		->and($errors['1 http://10.255.255.1/stats'])->not->toBe($refused);
});

test('redirects are followed relative to the endpoint and decoded', function () {
	$base      = cacti_http_test_server()['url'];
	$errors    = [];
	$documents = (new CactiHttpPoller())->fetch([
		new CactiHttpPollItem(1, 1, 'v', "$base/relative", '/in'),
		new CactiHttpPollItem(1, 2, 'v', "$base/gzip", '/v'),
	], $errors);

	expect($errors)->toBe([])
		->and($documents["1 $base/relative"]['in'])->toBe(5)
		->and($documents["1 $base/gzip"]['v'])->toBe(3);
});

test('redact handles urls that do not parse', function () {
	expect(CactiHttpPollItem::redact(' https://u:p@device.example/x?k=v#f '))->toBe('https://device.example/x')
		->and(CactiHttpPollItem::redact('not a url'))->toBe('invalid URL')
		->and(CactiHttpPollItem::redact(''))->toBe('invalid URL');
});

test('options with invalid utf-8 still encode and are then rejected', function () {
	$encoded = CactiHttpPollItem::encodeOptions("\xff", '');

	expect($encoded)->toBeString()
		->and(fn () => CactiHttpPollItem::fromPollerItem(http_poller_row(['arg3' => $encoded])))->toThrow(InvalidArgumentException::class);
});

test('a scale that is not finite is rejected', function () {
	expect(fn () => new CactiHttpPollItem(1, 1, 'v', 'https://device.example/', '/v', INF))->toThrow(InvalidArgumentException::class);
});
