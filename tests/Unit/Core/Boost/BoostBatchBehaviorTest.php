<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$root = dirname(__DIR__, 4);

function boostBatchTestReset(array $overrides = array()) {
	$GLOBALS['boost_batch_test_state'] = array_merge(array(
		'packet_limit'  => 1048576,
		'queries'       => array(),
		'affected_rows' => 1000,
		'fail_at'       => 0,
		'throw_at'      => 0,
		'logs'          => array(),
	), $overrides);
}

function boostBatchTestSizeof($value) {
	return is_countable($value) ? count($value) : 0;
}

function boostBatchTestFetchRow($sql, $assoc, $conn) {
	return array('Value' => $GLOBALS['boost_batch_test_state']['packet_limit']);
}

function boostBatchTestExecute($sql, $log, $conn) {
	$state =& $GLOBALS['boost_batch_test_state'];
	$state['queries'][] = $sql;
	$call = count($state['queries']);

	if ($state['throw_at'] === $call) {
		throw new RuntimeException('injected database failure');
	}

	return $state['fail_at'] !== $call;
}

function boostBatchTestAffectedRows($conn) {
	return $GLOBALS['boost_batch_test_state']['affected_rows'];
}

function boostBatchTestLog($message, $output = false, $facility = '') {
	$GLOBALS['boost_batch_test_state']['logs'][] = array($message, $output, $facility);
}

function boostBatchTestLoadFunction($root) {
	if (function_exists('boostBatchTestFlush')) {
		return;
	}

	$source = file_get_contents($root . '/lib/boost.php');
	expect($source)->not->toBeFalse();

	$start = strpos($source, 'function boost_flush_output_batch(');
	$end   = strpos($source, "\nfunction ", $start + 1);
	expect($start)->not->toBeFalse()
		->and($end)->not->toBeFalse();

	$function = substr($source, $start, $end - $start);
	$function = str_replace(array(
		'boost_flush_output_batch',
		'cacti_sizeof',
		'db_fetch_row',
		'db_execute',
		'db_affected_rows',
		'cacti_log',
	), array(
		'boostBatchTestFlush',
		'boostBatchTestSizeof',
		'boostBatchTestFetchRow',
		'boostBatchTestExecute',
		'boostBatchTestAffectedRows',
		'boostBatchTestLog',
	), $function);

	eval($function);
}

beforeEach(function () use ($root) {
	boostBatchTestLoadFunction($root);
	boostBatchTestReset();
});

test('an empty handoff succeeds without querying the packet limit or writing SQL', function () {
	expect(boostBatchTestFlush(array(), new stdClass()))->toBeTrue()
		->and($GLOBALS['boost_batch_test_state']['queries'])->toBe(array());
});

test('a handoff below the packet limit is emitted as one insert', function () {
	$conn   = new stdClass();
	$tuples = array("(1,'a','2026-01-01 00:00:00','1')", "(2,'b','2026-01-01 00:00:00','2')");

	expect(boostBatchTestFlush($tuples, $conn))->toBeTrue()
		->and($GLOBALS['boost_batch_test_state']['queries'])->toHaveCount(1)
		->and($GLOBALS['boost_batch_test_state']['queries'][0])->toContain(implode(',', $tuples));
});

test('packet-aware handoffs preserve tuple order across multiple inserts', function () {
	boostBatchTestReset(array('packet_limit' => 120));
	$conn   = new stdClass();
	$tuples = array(
		"(1,'alpha','2026-01-01 00:00:00','1111111111')",
		"(2,'beta','2026-01-01 00:00:01','2222222222')",
		"(3,'gamma','2026-01-01 00:00:02','3333333333')",
	);

	expect(boostBatchTestFlush($tuples, $conn))->toBeTrue()
		->and($GLOBALS['boost_batch_test_state']['queries'])->toHaveCount(3);

	foreach ($tuples as $index => $tuple) {
		expect($GLOBALS['boost_batch_test_state']['queries'][$index])->toEndWith($tuple);
	}
});

test('a false database acknowledgement fails the handoff', function () {
	boostBatchTestReset(array('fail_at' => 1));

	expect(boostBatchTestFlush(array("(1,'a','2026-01-01 00:00:00','1')"), new stdClass()))->toBeFalse();
});

test('a failed chunk stops the handoff before later chunks can be partially staged', function () {
	boostBatchTestReset(array('packet_limit' => 120, 'fail_at' => 2));
	$tuples = array(
		"(1,'alpha','2026-01-01 00:00:00','1111111111')",
		"(2,'beta','2026-01-01 00:00:01','2222222222')",
		"(3,'gamma','2026-01-01 00:00:02','3333333333')",
	);

	expect(boostBatchTestFlush($tuples, new stdClass()))->toBeFalse()
		->and($GLOBALS['boost_batch_test_state']['queries'])->toHaveCount(2);
});

test('a thrown database exception fails the handoff instead of escaping', function () {
	boostBatchTestReset(array('throw_at' => 1));

	expect(boostBatchTestFlush(array("(1,'a','2026-01-01 00:00:00','1')"), new stdClass()))->toBeFalse();
});

test('ignored duplicate keys are acknowledged but logged', function () {
	boostBatchTestReset(array('affected_rows' => 0));

	expect(boostBatchTestFlush(array("(1,'a','2026-01-01 00:00:00','1')"), new stdClass()))->toBeTrue()
		->and($GLOBALS['boost_batch_test_state']['logs'])->toHaveCount(1)
		->and($GLOBALS['boost_batch_test_state']['logs'][0][0])->toContain('duplicate sample keys');
});
