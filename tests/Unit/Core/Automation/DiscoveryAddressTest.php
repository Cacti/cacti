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

test('discovery enumerates admitted addresses and terminates with exclusions', function (string $range, string $ignore, array $expected) : void {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/discovery_address_probe.php', [json_encode(['range' => $range, 'ignore' => $ignore], JSON_THROW_ON_ERROR)]);

	expect($result['completed'])->toBeTrue()
		->and($result['addresses'])->toBe($expected);
})->with([
	'no exclusions'           => ['192.0.2.0/29', '', ['192.0.2.1', '192.0.2.2', '192.0.2.3', '192.0.2.4', '192.0.2.5', '192.0.2.6']],
	'first address'           => ['192.0.2.0/30', '192.0.2.1', ['192.0.2.2']],
	'middle address'          => ['192.0.2.0/29', '192.0.2.2', ['192.0.2.1', '192.0.2.3', '192.0.2.4', '192.0.2.5', '192.0.2.6']],
	'last address'            => ['192.0.2.0/30', '192.0.2.2', ['192.0.2.1']],
	'all addresses'           => ['192.0.2.0/30', '192.0.2.1,192.0.2.2', []],
	'whitespace'              => ['192.0.2.0/30', ' 192.0.2.2 ', ['192.0.2.1']],
	'single address'          => ['192.0.2.9', '', ['192.0.2.9']],
	'single excluded address' => ['192.0.2.9', '192.0.2.9', []],
	'multiple subnets'        => ['192.0.2.0/30,192.0.2.8/30', '192.0.2.2,192.0.2.9', ['192.0.2.1', '192.0.2.10']],
]);

test('discovery skips fully excluded batches without empty inserts', function () : void {
	$excluded = [];

	for ($address = ip2long('192.0.0.1'); $address <= ip2long('192.0.3.254'); $address++) {
		$excluded[] = long2ip($address);
	}
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/discovery_address_probe.php', [json_encode(['range' => '192.0.0.0/22', 'ignore' => implode(',', $excluded)], JSON_THROW_ON_ERROR)]);

	expect($result)->toBe(['completed' => true, 'addresses' => [], 'writes' => 0, 'logs' => []]);
});

test('discovery batches a subnet without per-address writes or duplicate indices', function () : void {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/discovery_address_probe.php', [json_encode(['range' => '192.0.0.0/22', 'ignore' => ''], JSON_THROW_ON_ERROR)]);

	expect($result['completed'])->toBeTrue()
		->and($result['addresses'])->toHaveCount(1022)
		->and(array_unique($result['addresses']))->toHaveCount(1022)
		->and($result['addresses'][0])->toBe('192.0.0.1')
		->and($result['addresses'][1021])->toBe('192.0.3.254')
		->and($result['addresses'])->toContain('192.0.0.255', '192.0.1.0')
		->and($result['writes'])->toBe(2);
});

test('discovery reports addresses another network already queued', function (?int $owned, array $logs) : void {
	$scenario = ['range' => '192.0.2.0/29', 'ignore' => ''];

	if ($owned !== null) {
		$scenario['owned'] = $owned;
	}
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/discovery_address_probe.php', [json_encode($scenario, JSON_THROW_ON_ERROR)]);

	expect($result['completed'])->toBeTrue()
		->and($result['addresses'])->toHaveCount(6)
		->and($result['logs'])->toBe($logs);
})->with([
	'every address owned'  => [null, []],
	'two owned elsewhere'  => [4, ['NOTE: Discovery Network 7 queued 4 of 6 addresses; the rest repeat an overlapping range of this network or are already queued by another network']],
]);
