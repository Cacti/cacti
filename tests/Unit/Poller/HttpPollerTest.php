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

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

require_once dirname(__DIR__, 3) . '/lib/CactiHttpPoller.php';

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
	$requests = [];

	$client = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
		$requests[] = [$url, $options['timeout']];

		return new MockResponse('{"in":5,"out":7}');
	});

	$items = [
		CactiHttpPollItem::fromPollerItem(http_poller_row(['local_data_id' => 1, 'arg2' => '/in', 'arg3' => '{"timeout":2}'])),
		CactiHttpPollItem::fromPollerItem(http_poller_row(['local_data_id' => 2, 'arg2' => '/out', 'arg3' => '{"timeout":4}'])),
		CactiHttpPollItem::fromPollerItem(http_poller_row(['local_data_id' => 3, 'host_id' => 2, 'arg2' => '/in'])),
	];

	$errors    = [];
	$documents = (new CactiHttpPoller($client))->fetch($items, $errors);

	expect($requests)->toHaveCount(2)
		->and($requests[0][1])->toBe(4.0)
		->and($errors)->toBe([])
		->and($items[0]->resolve($documents[$items[0]->endpointKey]))->toBe('5')
		->and($items[1]->resolve($documents[$items[1]->endpointKey]))->toBe('7')
		->and($items[2]->resolve($documents[$items[2]->endpointKey]))->toBe('5');
});

test('no more than the configured number of requests are in flight', function () {
	$events = [];

	$client = new MockHttpClient(function (string $method, string $url) use (&$events) {
		$events[] = ['start', $url];

		$body = (function () use (&$events, $url) {
			yield '{"v":';

			yield '1}';

			$events[] = ['end', $url];
		})();

		return new MockResponse($body);
	});

	$items = [];

	for ($i = 1; $i <= 7; $i++) {
		$items[] = new CactiHttpPollItem(1, $i, 'v', "https://device.example/$i", '/v');
	}

	$errors    = [];
	$documents = (new CactiHttpPoller($client, 2))->fetch($items, $errors);

	$inflight = 0;
	$peak     = 0;

	foreach ($events as [$event]) {
		$inflight += $event === 'start' ? 1 : -1;
		$peak      = max($peak, $inflight);
	}

	expect($errors)->toBe([])
		->and($documents)->toHaveCount(7)
		->and(array_filter($events, fn ($e) => $e[0] === 'start'))->toHaveCount(7)
		->and($peak)->toBe(2);
});

test('failed endpoints are reported without stopping the others', function () {
	$client = new MockHttpClient(function (string $method, string $url) {
		return match (parse_url($url, PHP_URL_PATH)) {
			'/ok'      => new MockResponse('{"v":1}'),
			'/missing' => new MockResponse('{}', ['http_code' => 404]),
			'/html'    => new MockResponse('<html>'),
			'/slow'    => new MockResponse((function () {
				yield '';
			})()),
			'/big'     => new MockResponse(str_repeat(' ', 64) . '{}'),
			'/down'    => new MockResponse('', ['error' => 'Could not resolve host: https://device.example/down?token=secret']),
		};
	});

	$paths = ['ok', 'missing', 'html', 'slow', 'big', 'down'];
	$items = [];

	foreach ($paths as $i => $path) {
		$query   = $path === 'down' ? '?token=secret' : '';
		$items[] = new CactiHttpPollItem(1, $i, 'v', "https://device.example/$path$query", '/v');
	}

	$errors    = [];
	$documents = (new CactiHttpPoller($client, 3, 32))->fetch($items, $errors);

	expect(array_keys($documents))->toBe(['1 https://device.example/ok'])
		->and($errors['1 https://device.example/missing'])->toBe('HTTP status 404')
		->and($errors['1 https://device.example/html'])->toStartWith('Invalid JSON')
		->and($errors['1 https://device.example/slow'])->toBe('Timed out')
		->and($errors['1 https://device.example/big'])->toBe('Response exceeds 32 bytes')
		->and($errors['1 https://device.example/down?token=secret'])->toContain('https://device.example/down')
		->and($errors['1 https://device.example/down?token=secret'])->not->toContain('secret');
});

test('concurrency outside the supported range is rejected or clamped', function () {
	expect(fn () => new CactiHttpPoller(new MockHttpClient(), 0))->toThrow(InvalidArgumentException::class)
		->and(fn () => new CactiHttpPoller(new MockHttpClient(), CactiHttpPoller::MAX_CONCURRENCY + 1))->toThrow(InvalidArgumentException::class)
		->and(CactiHttpPoller::create(0))->toBeInstanceOf(CactiHttpPoller::class)
		->and(CactiHttpPoller::create(1000))->toBeInstanceOf(CactiHttpPoller::class);
});

test('collect returns one output per row with a reason for each failure', function () {
	$requests = 0;

	$client = new MockHttpClient(function (string $method, string $url) use (&$requests) {
		$requests++;

		return str_ends_with($url, '/down') ? new MockResponse('', ['http_code' => 503]) : new MockResponse('{"in":5,"label":"eth0"}');
	});

	$rows = [
		http_poller_row(['local_data_id' => 1, 'arg2' => '/in']),
		http_poller_row(['local_data_id' => 2, 'arg2' => '/label']),
		http_poller_row(['local_data_id' => 3, 'arg1' => 'https://device.example/down']),
		http_poller_row(['local_data_id' => 4, 'arg1' => 'ftp://device.example/']),
	];

	$reasons = [];
	$outputs = (new CactiHttpPoller($client))->collect($rows, $reasons);

	expect($requests)->toBe(2)
		->and($outputs)->toBe(['4:value' => 'U', '1:value' => '5', '2:value' => 'U', '3:value' => 'U'])
		->and(array_keys($reasons))->toBe(['4:value', '2:value', '3:value'])
		->and($reasons['2:value'])->toBe('JSON Pointer did not select a number')
		->and($reasons['3:value'])->toBe('HTTP status 503')
		->and($reasons['4:value'])->toContain('http or https');
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

test('link-local and metadata destinations are refused while private networks stay reachable', function () {
	$client = new NoPrivateNetworkHttpClient(new MockHttpClient(fn () => new MockResponse('{"v":1}')), CactiHttpPoller::blockedSubnets());

	$items = [
		new CactiHttpPollItem(1, 1, 'v', 'https://169.254.169.254/latest/meta-data', '/v'),
		new CactiHttpPollItem(1, 2, 'v', 'https://[fe80::1]/stats', '/v'),
		new CactiHttpPollItem(1, 5, 'v', 'https://[::ffff:169.254.169.254]/latest', '/v'),
		new CactiHttpPollItem(1, 6, 'v', 'https://[64:ff9b::a9fe:a9fe]/latest', '/v'),
		new CactiHttpPollItem(1, 7, 'v', 'https://100.100.100.200/latest', '/v'),
		new CactiHttpPollItem(1, 8, 'v', 'https://[::ffff:100.100.100.200]/latest', '/v'),
		new CactiHttpPollItem(1, 9, 'v', 'https://[64:ff9b::c000:c0]/latest', '/v'),
		new CactiHttpPollItem(1, 3, 'v', 'https://10.0.0.5/stats', '/v'),
		new CactiHttpPollItem(1, 4, 'v', 'https://127.0.0.1:9100/stats', '/v'),
	];

	$errors    = [];
	$documents = (new CactiHttpPoller($client))->fetch($items, $errors);

	expect(array_keys($documents))->toBe(['1 https://10.0.0.5/stats', '1 https://127.0.0.1:9100/stats'])
		->and(array_keys($errors))->toBe([
			'1 https://169.254.169.254/latest/meta-data',
			'1 https://[fe80::1]/stats',
			'1 https://[::ffff:169.254.169.254]/latest',
			'1 https://[64:ff9b::a9fe:a9fe]/latest',
			'1 https://100.100.100.200/latest',
			'1 https://[::ffff:100.100.100.200]/latest',
			'1 https://[64:ff9b::c000:c0]/latest',
		]);
});

test('every url in a transport error is redacted, not only the configured one', function () {
	$client = new MockHttpClient(fn () => new MockResponse('', ['error' => 'Redirected to "https://login.example/cb?token=abc" from https://device.example/x?key=def failed']));

	$errors = [];

	(new CactiHttpPoller($client))->fetch([new CactiHttpPollItem(1, 1, 'v', 'https://device.example/x?key=def')], $errors);

	expect($errors['1 https://device.example/x?key=def'])->toBe('Redirected to "https://login.example/cb" from https://device.example/x failed');
});

test('configured credentials are redacted even when they would end a url match', function () {
	$url    = 'https://user:pa"ss word@device.example/x?key=def';
	$client = new MockHttpClient(fn () => new MockResponse('', ['error' => "Failed for \"$url\"."]));

	$errors = [];

	(new CactiHttpPoller($client))->fetch([new CactiHttpPollItem(1, 1, 'v', $url)], $errors);

	expect($errors["1 $url"])->toBe('Failed for "https://device.example/x".');
});

test('a scale that is not finite is rejected', function () {
	expect(fn () => new CactiHttpPollItem(1, 1, 'v', 'https://device.example/', '/v', INF))->toThrow(InvalidArgumentException::class);
});

test('requests that fail before they start are reported', function () {
	$client = new MockHttpClient(function () {
		throw new TransportException('Unsupported option for "https://device.example/x?key=def"');
	});

	$errors    = [];
	$documents = (new CactiHttpPoller($client))->fetch([new CactiHttpPollItem(1, 1, 'v', 'https://device.example/x?key=def')], $errors);

	expect($documents)->toBe([])
		->and($errors)->toBe(['1 https://device.example/x?key=def' => 'Unsupported option for "https://device.example/x"']);
});

test('a stream that ends without settling a response cannot spin', function () {
	$inner  = new MockHttpClient(fn () => new MockResponse('{"v":1}'));
	$client = new class ($inner) implements HttpClientInterface {
		public function __construct(private readonly HttpClientInterface $inner) {
		}

		public function request(string $method, string $url, array $options = []) : ResponseInterface {
			return $this->inner->request($method, $url, $options);
		}

		public function stream(ResponseInterface|iterable $responses, ?float $timeout = null) : ResponseStreamInterface {
			return new ResponseStream((static fn () : Generator => yield from [])());
		}

		public function withOptions(array $options) : static {
			return $this;
		}
	};

	$errors    = [];
	$documents = (new CactiHttpPoller($client))->fetch([new CactiHttpPollItem(1, 1, 'v', 'https://device.example/x')], $errors);

	expect($documents)->toBe([])
		->and($errors)->toBe(['1 https://device.example/x' => 'Response did not complete']);
});
