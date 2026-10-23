<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
declare(strict_types = 1);

require_once dirname(__DIR__) . '/Helpers/IsolatedProbe.php';

test('discovery records reachable non-SNMP hosts without adding managed devices', function (): void {
	$result = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/discovery_worker_ping_coverage.php', ['alive']);
	expect($result)->toBe(['finished' => true, 'snmp_calls' => 1, 'up' => 1,
		'down'                           => [], 'completed' => ['192.0.2.255'], 'ping_only' => true]);
});

test('discovery records ping outages without probing SNMP or inventing found devices', function (): void {
	$result = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/discovery_worker_ping_coverage.php', ['down']);
	expect($result)->toBe(['finished' => true, 'snmp_calls' => 0, 'up' => 0,
		'down'                           => ['192.0.2.255'], 'completed' => ['192.0.2.255'], 'ping_only' => false]);
});
