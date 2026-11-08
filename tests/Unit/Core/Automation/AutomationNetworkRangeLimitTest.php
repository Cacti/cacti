<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('automation networks larger than the address limit are refused and never primed', function () {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/AutomationNetworkRangeLimit.php');

	expect($result['save_16'])->toBe(7)
		->and($result['save_15'])->toBeFalse()
		->and($result['save_message'])->toContain('131070')
		->and($result['save_message'])->toContain('65536')
		->and($result['save_sum'])->toBeFalse()
		->and($result['saved'])->toBe([65534])
		->and($result['prime_over'])->toBeFalse()
		->and($result['prime_over_rows'])->toBe(0);
});

test('automation priming skips ignored addresses, batches inserts and reports failures', function () {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/AutomationNetworkRangeLimit.php');

	expect($result['prime_ignore'])->toBeTrue()
		->and($result['prime_ignore_rows'])->toBe(['192.168.1.1', '192.168.1.3', '192.168.1.4', '192.168.1.6'])
		->and($result['prime_batches'])->toBeTrue()
		->and($result['prime_batch_rows'])->toBe(1022)
		->and($result['prime_batch_unique'])->toBe(1022)
		->and($result['prime_fail'])->toBeFalse()
		->and($result['logs'])->toBe(2);
});
