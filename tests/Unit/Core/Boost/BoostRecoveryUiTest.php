<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

$root = dirname(__DIR__, 4);

test('failed Boost shards return only unacknowledged rows to the live queue', function () use ($root) {
	$result = cacti_test_isolated_probe($root . '/tests/fixtures/Core/BoostArchiveRequeue.php');

	expect($result['success'])->toBeTrue()
		->and($result['calls'])->toHaveCount(2)
		->and($result['calls'][0]['sql'])->toContain('INSERT IGNORE INTO poller_output_boost')
		->and($result['calls'][0]['sql'])->toContain('progress.cursor_time IS NULL')
		->and($result['calls'][0]['sql'])->toContain('archived.rrd_name > progress.cursor_rrd_name')
		->and($result['calls'][0]['params'])->toBe(array(str_repeat('a', 32)))
		->and($result['calls'][0]['log'])->toBeFalse()
		->and($result['invalid_run'])->toBeFalse()
		->and($result['invalid_table'])->toBeFalse()
		->and($result['failed_copy'])->toBeFalse()
		->and($result['failed_calls'])->toHaveCount(2);
});

test('Boost retry refuses active work and schedules retained data without deleting it', function () use ($root) {
	$result = cacti_test_isolated_probe($root . '/tests/fixtures/Core/BoostRetry.php');

	expect($result['running']['settings'])->toBe(array())
		->and($result['running']['messages'][0][0])->toBe('boost_retry_running')
		->and($result['empty']['settings'])->toBe(array())
		->and($result['empty']['messages'][0][0])->toBe('boost_retry_empty')
		->and($result['scheduled']['settings']['boost_poller_started'])->toBe(0)
		->and($result['scheduled']['settings']['boost_last_run_time'])->toBe(1799999100)
		->and($result['scheduled']['settings']['boost_next_run_time'])->toBe(1800000000)
		->and($result['scheduled']['settings']['boost_poller_status'])->toContain('retry scheduled')
		->and($result['scheduled']['messages'][0][0])->toBe('boost_retry_scheduled');
});

test('Boost retry is POST protected and stale shard rows are not displayed as running', function () use ($root) {
	$source = file_get_contents($root . '/utilities.php');
	$start  = strpos($source, "case 'retry_boost':");
	$end    = strpos($source, 'break;', $start);
	$action = substr($source, $start, $end - $start);

	expect($action)->toContain('csrf_require_post()')
		->and($source)->toContain("name='action' value='retry_boost'")
		->and($source)->toContain('Retry Queued Data')
		->and($source)->toContain('if ($rows_to_process > 0 && is_numeric($runtime))')
		->and($source)->toContain('<b>Interrupted</b>');
});
