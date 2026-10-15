<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function poller_output_probe(string $scenario) : array {
	return cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/PollerOutputProbe.php', [$scenario]);
}

test('completed timestamps do not delete incomplete timestamps for the same source', function () {
	$r = poller_output_probe('partial');
	expect($r['first'])->toBe(1)->and($r['remaining'])->toBe(1);
	expect($r['rows'][0]['unix_time'])->toBe(1700000060);
});
test('samples arriving after the fetch are not acknowledged by the previous update', function () {
	$r = poller_output_probe('arrival');
	expect($r['first'])->toBe(1)->and($r['remaining'])->toBe(1);
});
test('failed RRDtool writes retain their queue rows', function () {
	$r = poller_output_probe('failed');
	expect($r['first'])->toBe(0)->and($r['remaining'])->toBe(1)->and($r['delete_calls'])->toBe(0);
});
test('one failing file stops before later timestamps while other files can succeed', function () {
	$r = poller_output_probe('mixed');
	expect($r['first'])->toBe(1)->and($r['remaining'])->toBe(2)->and($r['rrd_calls'])->toBe(2);
});
test('template mappings expand into individual RRD fields', function () {
	$r = poller_output_probe('fanout');
	expect($r['first'])->toBe(1)->and($r['remaining'])->toBe(0);
	expect($r['commands'][0])->toContain('--template in:out')->toContain('1700000000:42:42');
});
test('mapping expansion applies orphan exclusions to each field', function () {
	$r = poller_output_probe('fanout-orphan');
	expect($r['first'])->toBe(1)->and($r['commands'][0])->toContain('--template in ')->not->toContain('in:out');
});
test('batch boundaries defer a whole timestamp group', function () {
	$r = poller_output_probe('boundary');
	expect($r['first'])->toBe(39999)->and($r['second'])->toBe(1)->and($r['remaining'])->toBe(0)->and($r['queries'])->toBe(2);
});
test('large incomplete backlogs return without recursion and advance past the first batch', function () {
	$r = poller_output_probe('backlog');
	expect($r['first'])->toBe(0)->and($r['second'])->toBe(0)->and($r['remaining'])->toBe(40001)->and($r['queries'])->toBe(2);
});
test('successful Boost staging acknowledges queue rows without inline RRD writes', function () {
	$r = poller_output_probe('boost-success');
	expect($r['remaining'])->toBe(0)->and($r['boost_calls'])->toBe(1)->and($r['rrd_calls'])->toBe(0);
});
test('redirected polling never re-inserts collector-staged samples into poller_output_boost', function () {
	$r = poller_output_probe('boost-redirect');
	expect($r['remaining'])->toBe(0)->and($r['boost_calls'])->toBe(0)->and($r['rrd_calls'])->toBe(0);
});
test('failed Boost staging and failed RRD fallback retain queue rows', function () {
	$r = poller_output_probe('boost-failure');
	expect($r['remaining'])->toBe(1)->and($r['boost_calls'])->toBe(1)->and($r['rrd_calls'])->toBe(1);
});
test('failed acknowledgement leaves stored samples available for a safe retry', function () {
	$r = poller_output_probe('delete-failure');
	expect($r['first'])->toBe(1)->and($r['remaining'])->toBe(1);
});
test('failed template metadata reads retain samples and retry on the next pass', function () {
	$r = poller_output_probe('metadata');
	expect($r['first'])->toBe(0)->and($r['second'])->toBe(1)->and($r['mapping_queries'])->toBe(2)->and($r['remaining'])->toBe(0);
});

test('later timestamps cannot advance an RRD past an incomplete earlier timestamp', function () {
	$r = poller_output_probe('early-partial');
	expect($r['first'])->toBe(0)->and($r['remaining'])->toBe(3)->and($r['rrd_calls'])->toBe(0);
});

test('incomplete timestamps block later timestamps across batch boundaries', function () {
	$r = poller_output_probe('boundary-partial');
	expect($r['first'])->toBe(39999)->and($r['second'])->toBe(0)->and($r['remaining'])->toBe(3);
});

test('failed timestamps block later timestamps across batch boundaries', function () {
	$r = poller_output_probe('boundary-failure');
	expect($r['first'])->toBe(39999)->and($r['second'])->toBe(0)->and($r['remaining'])->toBe(2);
});

test('a failed delete never leaves only part of a stored timestamp group queued', function () {
	$r = poller_output_probe('delete-group');
	expect($r['first'])->toBe(1001)->and($r['remaining'])->toBe(1);
	expect($r['rows'][0]['local_data_id'])->toBe(1001);
});

test('a failed sample succeeds on a later pass without being lost or duplicated', function () {
	$r = poller_output_probe('retry-failed');
	expect($r['first'])->toBe(0)->and($r['second'])->toBe(1)->and($r['remaining'])->toBe(0);
});

test('failed source-specific metadata reads retry without losing samples', function (string $scenario) {
	$r = poller_output_probe($scenario);
	expect($r['first'])->toBe(0)->and($r['second'])->toBe(1)->and($r['remaining'])->toBe(0)
		->and($r['source_queries'])->toBe(2);
})->with(['manual-query-failure', 'orphan-query-failure']);
