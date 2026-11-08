<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

beforeAll(function () {
	$GLOBALS['boost_isolation_verdict'] = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/Boost/FailedDataSourceIsolation.php');
});

test('a rejected data source is parked and the rest of its shard is acknowledged', function () {
	$r = $GLOBALS['boost_isolation_verdict']['isolate'];

	// Data source 2 is written in full, and the shard still returns its row
	// count so the child goes on to its remaining passes.
	expect($r['result'])->toBe(6)
		->and($r['writes'])->toContain('2@1700000000:42', '2@1700000060:42', '2@1700000120:42')
		->and($r['statements'][0])->toBe([
			'UPDATE poller_output_boost_local_data_ids SET process_handler = 0 WHERE process_handler = ? AND local_data_id IN (1)',
			[3]
		])
		->and($r['statements'][1])->toBe([
			'DELETE FROM poller_output_boost_local_data_ids WHERE local_data_id <= ? AND process_handler = ?',
			[2, 3]
		]);
});

test('a rejected final flush parks the shard\'s last data source', function () {
	$r = $GLOBALS['boost_isolation_verdict']['isolate_last'];

	expect($r['result'])->toBe(6)
		->and($r['statements'][0][0])->toEndWith('AND local_data_id IN (2)');
});

test('a shard with no rejected update parks nothing', function () {
	$r = $GLOBALS['boost_isolation_verdict']['clean'];

	expect($r['result'])->toBe(6)
		->and($r['statements'])->toHaveCount(1)
		->and($r['statements'][0][0])->toStartWith('DELETE FROM poller_output_boost_local_data_ids');
});

test('a shard whose failed data source cannot be parked is retained', function () {
	$r = $GLOBALS['boost_isolation_verdict']['park_fails'];

	expect($r['result'])->toBe(-1)
		->and(array_filter($r['statements'], fn ($s) => str_starts_with($s[0], 'DELETE')))->toBeEmpty();
});

test('parked data sources are carried into the live table within the age cap', function () {
	$r = $GLOBALS['boost_isolation_verdict']['carry_some'];

	// The table that is not a Boost archive is never read.
	expect($r['result'])->toBeTrue()
		->and($r['statements'])->toHaveCount(1)
		->and($r['statements'][0][0])->toStartWith('INSERT IGNORE INTO poller_output_boost (local_data_id, rrd_name, time, output)')
		->and($r['statements'][0][0])->toContain('FROM `poller_output_boost_arch_1` AS at')
		->and($r['statements'][0][0])->toContain('bpt.process_handler = 0')
		->and($r['statements'][0][1][0])->toBeGreaterThanOrEqual($r['before'] - 86400)
		->and($r['statements'][0][1][0])->toBeLessThanOrEqual($r['before'] - 86400 + 5)
		->and($r['logs'])->toContain('WARNING: Boost discarded 3 sample(s) older than 86400 seconds from data sources whose RRD update keeps failing.');
});

test('nothing is copied when no data source failed', function () {
	$r = $GLOBALS['boost_isolation_verdict']['carry_none'];

	expect($r['result'])->toBeTrue()->and($r['statements'])->toBeEmpty();
});

test('a failed carry-forward read or copy keeps the archive tables', function (string $scenario) {
	expect($GLOBALS['boost_isolation_verdict'][$scenario]['result'])->toBeFalse();
})->with(['carry_count_fails', 'carry_expiry_fails', 'carry_insert_fails']);

test('the parent drops archive tables only after the failed data sources are carried forward', function () {
	$v = $GLOBALS['boost_isolation_verdict'];

	expect($v['parent_drops']['dropped'])->toBe(['DROP TABLE IF EXISTS `poller_output_boost_arch_1`'])
		->and($v['parent_keeps']['dropped'])->toBeEmpty()
		->and($v['parent_keeps']['logs'])->toContain('WARNING: Boost could not carry failed data sources forward; leaving archive tables in place for the next run to pick up.')
		->and($v['parent_incomplete']['dropped'])->toBeEmpty();
});
