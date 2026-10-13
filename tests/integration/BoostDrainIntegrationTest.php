<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
require_once dirname(__DIR__) . '/Helpers/IsolatedProbe.php';

test('Boost retains staged samples after a crashed writer and stores them on retry', function (string $scenario) {
	if (!getenv('BOOST_DB_HOST') || !is_executable(getenv('RRDTOOL_TEST_BINARY') ?: '')) {
		$this->markTestSkipped('Set BOOST_DB_HOST and RRDTOOL_TEST_BINARY for storage integration.');
	}
	$r     = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/BoostDrainProbe.php', [$scenario]);
	$count = $scenario === 'integration-drain-middle-failure' ? 3 : 1;
	expect($r['first'])->toBe(-1)->and($r['retained'])->toBe($count)
		->and($r['first_last'])->toContain($count === 3 ? ': 42' : ': U')->and($r['second'])->toBe($count)
		->and($r['handoff']['staged'])->toBe([])
		->and($r['handoff']['lastupdate'])->toContain($count === 3 ? ': 44' : ': 42');
})->with(['integration-drain-crash', 'integration-drain-middle-failure']);

test('Boost retries failed field metadata without acknowledging unstored values', function (string $scenario) {
	if (!getenv('BOOST_DB_HOST') || !is_executable(getenv('RRDTOOL_TEST_BINARY') ?: '')) {
		$this->markTestSkipped('Requires MariaDB and RRDtool.');
	}
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/BoostDrainProbe.php', [$scenario]);
	expect($r['first'])->toBe(-1)->and($r['retained'])->toBe(1)
		->and($r['first_last'])->toContain(': U')->and($r['second'])->toBe(1)
		->and($r['handoff']['staged'])->toBe([])->and($r['handoff']['lastupdate'])->toContain(': 42');
})->with(['integration-drain-manual-retry']);

test('Boost safely retries a committed RRD write after queue deletion fails', function () {
	if (!getenv('BOOST_DB_HOST') || !is_executable(getenv('RRDTOOL_TEST_BINARY') ?: '')) {
		$this->markTestSkipped('Requires MariaDB and RRDtool.');
	}
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/BoostDrainProbe.php', ['integration-drain-delete-retry']);
	expect($r['first'])->toBe(-1)->and($r['retained'])->toBe(1)->and($r['first_last'])->toContain(': 42')
		->and($r['second'])->toBe(1)->and($r['handoff']['staged'])->toBe([])->and($r['handoff']['lastupdate'])->toBe($r['first_last']);
});

test('scheduled Boost advances persistent cursors only after acknowledged storage', function (string $scenario) {
	if (!getenv('BOOST_DB_HOST') || !is_executable(getenv('RRDTOOL_TEST_BINARY') ?: '')) {
		$this->markTestSkipped('Requires MariaDB and RRDtool.');
	}
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/BoostDrainProbe.php', [$scenario]);
	expect($r['first'])->toBe(-1)->and($r['first_cursor'])->toBeNull()->and($r['first_last'])->toContain(': U')
		->and($r['second'])->toBe(1)->and($r['second_cursor'])->not->toBeNull()->and($r['handoff']['lastupdate'])->toContain(': 42');

	if ($scenario === 'integration-scheduled-mapping-retry') {
		expect($r['mapping_queries'])->toBe(2);
	}
})->with(['integration-scheduled-crash', 'integration-scheduled-mapping-retry']);

test('an incomplete multi-field timestamp cannot discard itself or advance past later samples', function () {
	if (!getenv('BOOST_DB_HOST') || !is_executable(getenv('RRDTOOL_TEST_BINARY') ?: '')) {
		$this->markTestSkipped('Requires MariaDB and RRDtool.');
	}
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/BoostDrainProbe.php', ['integration-drain-multi-field']);
	expect($r['first'])->toBe(-1)->and($r['retained'])->toBe(3)->and($r['first_last'])->toContain(': U U')
		->and($r['second'])->toBe(4)->and($r['handoff']['staged'])->toBe([])->and($r['handoff']['lastupdate'])->toContain(': 43 44');
});

test('a failed archive copy retains closed-round samples for storage on retry', function (string $scenario) {
	if (!getenv('BOOST_DB_HOST') || !is_executable(getenv('RRDTOOL_TEST_BINARY') ?: '')) {
		$this->markTestSkipped('Requires MariaDB and RRDtool.');
	}
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/BoostDrainProbe.php', [$scenario]);
	expect($r['first'])->toBe(-1)->and($r['archive_retained'])->toBe(1)->and($r['first_last'])->toContain(': U')
		->and($r['second'])->toBe(1)->and($r['handoff']['lastupdate'])->toContain(': 42');
})->with(['integration-archive-copy-retry', 'integration-archive-active-copy-retry']);

test('Boost preserves late arrivals that were not fetched by the acknowledged update', function () {
	if (!getenv('BOOST_DB_HOST') || !is_executable(getenv('RRDTOOL_TEST_BINARY') ?: '')) {
		$this->markTestSkipped('Requires MariaDB and RRDtool.');
	}
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/BoostDrainProbe.php', ['integration-drain-arrival']);
	expect($r['first'])->toBe(1)->and($r['retained'])->toBe(1)->and($r['first_last'])->toContain(': 42')
		->and($r['second'])->toBe(1)->and($r['handoff']['staged'])->toBe([])->and($r['handoff']['lastupdate'])->toContain(': 43');
});
