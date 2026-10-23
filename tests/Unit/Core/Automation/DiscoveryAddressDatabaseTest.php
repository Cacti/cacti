<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

declare(strict_types = 1);

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('overlapping discovery ranges enqueue every unique target without resetting claimed jobs', function () {
	if (!getenv('CACTI_MATCH_DB_DSN')) {
		$this->markTestSkipped('Set CACTI_MATCH_DB_DSN for the native discovery address regression.');
	}
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/discovery_address_database_probe.php');
	expect($result['completed'])->toBeTrue();
	expect(array_column($result['first'], 'ip_address'))->toBe(['192.0.2.1', '192.0.2.2', '192.0.2.3', '192.0.2.4', '192.0.2.5', '192.0.2.6'])
		->and($result['repeated'])->toBe($result['before'])
		->and($result['other'])->toBe($result['before'][1])
		->and($result['other']['hostname'])->toBe('claimed-fixture')
		->and((int) $result['other']['pid'])->toBe(123)
		->and((int) $result['other']['status'])->toBe(1)
		->and((int) $result['other']['thread'])->toBe(3)
		->and((int) $result['other']['network_id'])->toBe(7)
		->and((int) $result['new']['network_id'])->toBe(8)
		->and($result['new']['ip_address'])->toBe('192.0.2.9')
		->and($result['writes'])->toBe(6);
});
