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

function discoverySnmpVerdict(string $scenario): array {
	return cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/discovery_snmp_probe.php', [$scenario]);
}

test('discovery stops after first working credential and closes its session', function () {
	$result = discoverySnmpVerdict('success');
	expect($result['up'])->toBeTrue()
		->and($result['attempts'])->toHaveCount(1)
		->and($result['closed'])->toBe([true])
		->and($result['device']['snmp_sysUptime'])->toBe('0')
		->and($result['device']['snmp_sysName'])->toBe('fixture-device')
		->and($result['device']['snmp_status'])->toBe(3)
		->and($result['queries'][0][0])->toContain('ORDER BY sequence ASC')
		->and($result['queries'][0][1])->toBe([7]);
});

test('discovery recovers from unavailable and rejected credentials in sequence', function () {
	$result = discoverySnmpVerdict('recovery');
	expect($result['up'])->toBeTrue()->and($result['attempts'])->toHaveCount(3)
		->and(array_column($result['attempts'], 2))->toBe(['1', '2', '3'])
		->and($result['device']['snmp_version'])->toBe('3')
		->and($result['device']['snmp_sysObjectID'])->toBe('.1.3.6.1.4.1.9.1.1');

	foreach ($result['attempts'] as $index => $args) {
		expect($args)->toBe(['192.0.2.8', 'fixture-community-' . $index, (string) ($index + 1),
			'fixture-user', 'fixture-auth', 'SHA256', 'fixture-privacy', 'AES',
			'fixture-context', '8000000001', 1161, 125, 1, 4]);
	}
});

test('discovery rejects missing and error object identifiers', function (string $scenario) {
	$result = discoverySnmpVerdict($scenario);
	expect($result['up'])->toBeFalse()->and($result['device']['snmp_status'])->toBe(1)
		->and($result['device'])->not->toHaveKey('snmp_sysObjectID');
})->with(['unknown', 'empty', 'no-object', 'no-instance', 'error', 'outage', 'no-response', 'no-credentials']);

test('discovery preserves accessible fields when optional metadata is unavailable', function () {
	$result = discoverySnmpVerdict('partial');
	expect($result['up'])->toBeTrue()->and($result['device']['snmp_sysName'])->toBe('fixture-device')
		->and($result['device']['snmp_sysDescr'])->toBe('')
		->and($result['device']['snmp_sysLocation'])->toBe('')
		->and($result['device']['snmp_sysContact'])->toBe('')
		->and($result['closed'])->toBe([true]);
});

test('timed out probes exhaust credentials without reading device metadata', function () {
	$result = discoverySnmpVerdict('no-response');
	expect($result['up'])->toBeFalse()->and($result['attempts'])->toHaveCount(3)
		->and($result['gets'])->toBe(array_fill(0, 3, '.1.3.6.1.2.1.1.2.0'))
		->and($result['device']['snmp_sysUptime'])->toBe('previous');
});
