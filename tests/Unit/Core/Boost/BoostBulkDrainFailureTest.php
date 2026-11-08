<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('bulk Boost drain stops writing a data source after its flush is rejected', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/BoostBulkDrainFailureProbe.php', ['middle']);

	// Data source 1 keeps its rejected sample and everything after it for the
	// retry; data source 2 is unaffected, and the shard is not acknowledged.
	expect($r['result'])->toBe(-1)
		->and($r['writes'])->toBe(['1@1700000000:42', '1@1700000060:42', '2@1700000000:42', '2@1700000060:42', '2@1700000120:42', '2@1700000180:42'])
		->and($r['deletes'])->toBe(0);
});

test('bulk Boost drain acknowledges the shard when every flush succeeds', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/BoostBulkDrainFailureProbe.php', ['none']);

	expect($r['result'])->toBe(8)->and(count($r['writes']))->toBe(8)->and($r['deletes'])->toBe(1);
});
