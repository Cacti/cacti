<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

namespace GraphPollTimeTest;

/** Run the real graph renderer in an isolated process without a database or RRDtool. */
function results() {
	static $results = null;
	if ($results === null) {
		$fixture = dirname(__DIR__, 3) . '/fixtures/Core/Rrd/GraphPollTime.php';
		exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr ' . escapeshellarg($fixture), $output, $status);
		expect($status)->toBe(0);
		$results = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
	}
	return $results;
}

/** Allow the actual clock to advance while the renderer loads its dependencies. */
function expectPollEnd(array $case) {
	expect($case['reads'])->toBe(array(true))
		->and($case['times']['graph_end'])->toBeGreaterThanOrEqual((int) $case['database'] - $case['after'])
		->and($case['times']['graph_end'])->toBeLessThanOrEqual((int) $case['database'] - $case['before']);
}

test('automatic graph end uses the latest poll instead of a stale session value', function () {
	$case = results()['automatic'];
	expectPollEnd($case);
	expect($case['times']['graph_start'])->toBe(-86400);
});

test('successive standalone graph requests follow later completed polls', function () {
	$cases = results();
	expectPollEnd($cases['earlier']);
	expectPollEnd($cases['automatic']);
	expect($cases['automatic']['times']['graph_end'])->toBeGreaterThan($cases['earlier']['times']['graph_end']);
});

test('an explicit historical graph range remains unchanged', function () {
	$case = results()['historical'];
	expect($case['times'])->toBe(array('graph_start' => 1700000000, 'graph_end' => 1700003600))
		->and($case['reads'])->toBe(array());
});

test('missing and future poll timestamps retain the poll-interval fallback', function () {
	$cases = results();
	foreach (array('missing', 'future') as $name) {
		expect($cases[$name]['times']['graph_end'])->toBe(-300)
			->and($cases[$name]['reads'])->toBe(array(true));
	}
});

test('a custom relative start is preserved when selecting the latest poll', function () {
	$case = results()['custom_start'];
	expectPollEnd($case);
	expect($case['times']['graph_start'])->toBe(-3600);
});
