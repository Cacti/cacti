<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function discoveryNetworkVerdict(string $range, array $offsets = []): array {
	return cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/discovery_network_probe.php',
		[json_encode(['range' => $range, 'offsets' => $offsets], JSON_THROW_ON_ERROR)]);
}

test('discovery parses supported IPv4 range forms with explicit usable boundaries', function (string $range, array $expected) {
	$result = discoveryNetworkVerdict($range);
	expect($result['detail'])->toBe($expected);
})->with([
	['192.0.2.8', ['network' => '192.0.2.8', 'broadcast' => '192.0.2.8', 'cidr' => 32, 'type' => 'single', 'count' => 1, 'start' => '192.0.2.8', 'end' => '192.0.2.8']],
	[' 192.0.2.8/32 ', ['network' => '192.0.2.8', 'broadcast' => '192.0.2.8', 'cidr' => 32, 'type' => 'single', 'count' => 1, 'start' => '192.0.2.8', 'end' => '192.0.2.8']],
	['192.0.2.93/24', ['network' => '192.0.2.0', 'broadcast' => '192.0.2.255', 'cidr' => '24', 'type' => 'subnet', 'count' => 254, 'start' => '192.0.2.1', 'end' => '192.0.2.254']],
	['192.0.2.93/255.255.255.0', ['network' => '192.0.2.0', 'broadcast' => '192.0.2.255', 'cidr' => 24, 'type' => 'subnet', 'count' => 254, 'start' => '192.0.2.1', 'end' => '192.0.2.254']],
	['192.0.2.*', ['network' => '192.0.2.0', 'broadcast' => '192.0.2.255', 'cidr' => 24, 'type' => 'subnet', 'count' => 254, 'start' => '192.0.2.1', 'end' => '192.0.2.254']],
	['192.168.*.*', ['network' => '192.168.0.0', 'broadcast' => '192.168.255.255', 'cidr' => 16, 'type' => 'subnet', 'count' => 65534, 'start' => '192.168.0.1', 'end' => '192.168.255.254']],
]);

test('discovery rejects invalid and unsupported IPv6 ranges before enumeration', function (string $range) {
	$result = discoveryNetworkVerdict($range);
	expect($result['detail'])->toBeFalse()->and($result['addresses'])->toBeNull();
})->with(['', 'not-an-address', '192.0.2.256', '192.0.2.0/33', '192.0.2.0/0',
	'192.0.2.0/255.0.255.0', '192.0.2.0/24/garbage', '192.0.2.0/24.5',
	'192.0.2.0/2.4e1', '10.1.*.1', '10.*.1.*', '192.0.2.0/', '/24', '192.0.2.0-192.0.2.10', '2001:db8::1', '2001:db8::/64', '2001:db8::/24', '2001:db8::1/32']);

test('discovery enumerates each usable address exactly once including interior addresses ending in .255', function () {
	$result    = discoveryNetworkVerdict('192.0.0.0/22');
	$addresses = $result['addresses'];
	expect($addresses)->toHaveCount(1022)->and(array_unique($addresses))->toHaveCount(1022)
		->and($addresses[0])->toBe('192.0.0.1')->and($addresses[1021])->toBe('192.0.3.254')
		->and($addresses)->toContain('192.0.0.255', '192.0.1.0', '192.0.1.255', '192.0.2.0');

	foreach ($addresses as $offset => $address) {
		expect($address)->toBe(long2ip(ip2long('192.0.0.1') + $offset));
	}
});

test('wide discovery ranges carry correctly across second and third octets and terminate', function () {
	$result = discoveryNetworkVerdict('10.0.0.0/15', [0, 254, 255, 65534, 65535, 131069, 131070]);
	expect($result['detail']['count'])->toBe(131070)
		->and($result['detail']['end'])->toBe('10.1.255.254')
		->and($result['samples'])->toBe(['10.0.0.1', '10.0.0.255', '10.0.1.0', '10.0.255.255', '10.1.0.0', '10.1.255.254', false]);
});

test('single address and 32-bit host route produce one address then stop', function (string $range) {
	$result = discoveryNetworkVerdict($range, [0, 1]);
	expect($result['addresses'])->toBe(['192.0.2.8'])
		->and($result['samples'])->toBe(['192.0.2.8', false]);
})->with(['192.0.2.8', '192.0.2.8/32']);

test('legacy /31 address helper returns no next host', function () {
	$result = discoveryNetworkVerdict('192.0.2.0/31', [0, 1]);
	expect($result['samples'])->toBe([false, false]);
});
