<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('bulk Boost drain stops writing a data source after its flush is rejected and parks it', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/BoostBulkDrainFailureProbe.php', ['middle']);

	// Data source 1 keeps its rejected sample and everything after it: no later
	// timestamp is written past the rejected one, and the data source is parked
	// for the parent to carry forward. Data source 2 is unaffected and the rest
	// of the shard is acknowledged.
	expect($r['result'])->toBe(8)
		->and($r['writes'])->toBe(['1@1700000000:42', '1@1700000060:42', '2@1700000000:42', '2@1700000060:42', '2@1700000120:42', '2@1700000180:42'])
		->and($r['parked'])->toBe([1])
		->and($r['deletes'])->toBe(1);
});

test('bulk Boost drain acknowledges the shard when every flush succeeds', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/BoostBulkDrainFailureProbe.php', ['none']);

	expect($r['result'])->toBe(8)->and(count($r['writes']))->toBe(8)->and($r['deletes'])->toBe(1);
});

test('bulk Boost drain parks a data source whose flush at the next data source or the last flush is rejected', function ($scenario, $writes, $parked) {
	$r = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/BoostBulkDrainFailureProbe.php', [$scenario]);

	expect($r['result'])->toBe(8)->and(count($r['writes']))->toBe($writes)->and($r['parked'])->toBe($parked)->and($r['deletes'])->toBe(1);
})->with([['boundary', 8, [1]], ['tail', 8, [2]]]);
