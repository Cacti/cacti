<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

declare(strict_types = 1);

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('discovery worker probes admitted hosts regardless of the last octet', function (string $address): void {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/discovery_worker_address_probe.php', [$address]);
	expect($result['running'])->toBe([$address])
		->and($result['snmp'])->toBe([$address]);
})->with(['192.0.2.254', '192.0.2.255']);

test('discovery worker skips managed and deleted hosts before new probing', function (string $scenario, int $count, array $reruns): void {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/discovery_worker_address_probe.php', ['192.0.2.255', $scenario, 'live']);
	expect($result['claims'])->toBe(1)
		->and($result['snmp'])->toBe([])
		->and($result['running'])->toBe([])
		->and($result['host_adds'])->toBe([])
		->and($result['matches'])->toBe(0)
		->and($result['reruns'])->toBe($reruns)
		->and($result['up_counts'])->toBe($count)
		->and($result['snmp_counts'])->toBe($count);
})->with([
	'existing IP'       => ['managed-ip', 1, [41]],
	'existing DNS name' => ['managed-dns', 1, [41]],
	'deleted host'      => ['deleted', 0, []],
]);

test('discovery worker cancellation stops before claiming an address', function (string $scenario): void {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/discovery_worker_address_probe.php', ['192.0.2.255', $scenario]);
	expect($result['claims'])->toBe(0)
		->and($result['writes'])->toBe([])
		->and($result['snmp'])->toBe([])
		->and($result['host_adds'])->toBe([])
		->and($result['removed'])->toBe([7]);
})->with(['cancel', 'missing-master']);

test('discovery worker obeys the duplicate sysName policy', function (string $scenario, int $matches): void {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/discovery_worker_address_probe.php', ['192.0.2.255', $scenario]);
	expect($result['claims'])->toBe(1)
		->and($result['snmp'])->toBe(['192.0.2.255'])
		->and($result['matches'])->toBe($matches)
		->and($result['host_adds'])->toBe([]);
})->with([
	'reject same sysName on different discovered IP' => ['duplicate-rejected', 0],
	'allow same sysName when explicitly enabled'     => ['duplicate-allowed', 1],
]);

test('discovery dry run leaves existing devices unindexed', function (string $scenario, array $snmp): void {
	$probe = dirname(__DIR__, 3) . '/fixtures/discovery_worker_address_probe.php';
	$live  = cacti_test_isolated_probe($probe, ['192.0.2.255', $scenario, 'live']);
	$dry   = cacti_test_isolated_probe($probe, ['192.0.2.255', $scenario, 'dryrun']);

	expect($live['reruns'])->toBe([41])
		->and($dry['reruns'])->toBe([])
		->and($dry['snmp'])->toBe($snmp)
		->and($dry['host_adds'])->toBe([])
		->and($dry['up_counts'])->toBe(1);
})->with([
	'known by IP before SNMP'   => ['managed-ip', []],
	'known by sysName via SNMP' => ['managed-sysname', ['192.0.2.255']],
]);
