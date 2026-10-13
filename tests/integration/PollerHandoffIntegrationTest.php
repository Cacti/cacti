<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
require_once dirname(__DIR__) . '/Helpers/IsolatedProbe.php';

test('production polling creates a real RRD and acknowledges the stored sample', function (string $scenario) {
	if (!getenv('BOOST_DB_HOST') || !is_executable(getenv('RRDTOOL_TEST_BINARY') ?: '')) {
		$this->markTestSkipped('Set BOOST_DB_HOST and RRDTOOL_TEST_BINARY for the MariaDB/RRDtool integration.');
	}
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/PollerOutputProbe.php', [$scenario]);
	expect($r['remaining'])->toBe(0)->and($r['handoff']['rrd_exists'])->toBeTrue()
		->and($r['handoff']['lastupdate'])->toContain(': 42')->and($r['handoff']['staged'])->toBe([]);
	if ($scenario === 'integration-manual-retry') {
		expect($r['first'])->toBe(0)->and($r['manual_queries'])->toBe(2);
	}
})->with(['integration-create', 'integration-manual-retry']);

test('remote handoffs persist Boost samples before removing queued rows', function (string $scenario) {
	if (!getenv('BOOST_DB_HOST')) $this->markTestSkipped('Set BOOST_DB_HOST for the MariaDB handoff integration.');
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/PollerOutputProbe.php', [$scenario]);
	expect($r['remaining'])->toBe(0)->and($r['handoff']['staged'])->toHaveCount(1)
		->and($r['handoff']['staged'][0]['output'])->toBe('42')->and($r['handoff']['rrd_exists'])->toBeFalse();
	if ($scenario === 'integration-owner-change') expect($r['first'])->toBe(0);
	if ($scenario === 'integration-stage-retry') expect($r['first'])->toBe(0)->and($r['boost_calls'])->toBe(2);
	if ($scenario === 'integration-delete-retry') expect($r['boost_calls'])->toBe(2)->and($r['delete_calls'])->toBe(2);
})->with(['integration-handoff', 'integration-stage-retry', 'integration-delete-retry', 'integration-owner-change']);

test('ownership failures retain database handoffs without RRD or Boost writes', function (string $scenario) {
	if (!getenv('BOOST_DB_HOST')) $this->markTestSkipped('Set BOOST_DB_HOST for the MariaDB handoff integration.');
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/PollerOutputProbe.php', [$scenario]);
	expect($r['remaining'])->toBe(1)->and($r['handoff']['staged'])->toBe([])
		->and($r['handoff']['rrd_exists'])->toBeFalse()->and($r['delete_calls'])->toBe(0);
})->with(['integration-owner-failure', 'integration-owner-mismatch']);
