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

function boostRecoveryTestReset(array $overrides = array()) {
	$GLOBALS['boost_recovery_test_state'] = array_merge(array(
		'calls'   => array(),
		'fail_at' => 0,
	), $overrides);
}

function boostRecoveryTestExecute($sql, $params, $log, $conn) {
	$state =& $GLOBALS['boost_recovery_test_state'];
	$state['calls'][] = array('sql' => $sql, 'params' => $params, 'conn' => $conn);

	return $state['fail_at'] !== count($state['calls']);
}

function boostRecoveryTestLoadFunction($root) {
	if (function_exists('boostRecoveryTestDelete')) {
		return;
	}

	$source = file_get_contents($root . '/poller_recovery.php');
	expect($source)->not->toBeFalse();

	$start = strpos($source, 'function recovery_delete_acknowledged_rows(');
	expect($start)->not->toBeFalse();

	$body  = strpos($source, '{', $start);
	$depth = 0;
	$end   = false;

	for ($offset = $body, $length = strlen($source); $offset < $length; $offset++) {
		if ($source[$offset] === '{') {
			$depth++;
		} elseif ($source[$offset] === '}') {
			$depth--;

			if ($depth === 0) {
				$end = $offset + 1;
				break;
			}
		}
	}

	expect($end)->not->toBeFalse();

	$function = substr($source, $start, $end - $start);
	$function = str_replace(array(
		'recovery_delete_acknowledged_rows',
		'db_execute_prepared',
	), array(
		'boostRecoveryTestDelete',
		'boostRecoveryTestExecute',
	), $function);

	eval($function);
}

function boostRecoveryTestRows($count) {
	$rows = array();

	for ($index = 1; $index <= $count; $index++) {
		$rows[] = array(
			'local_data_id' => $index,
			'rrd_name'      => 'ds_' . $index,
			'time'          => sprintf('2026-01-01 00:%02d:%02d', intdiv($index, 60) % 60, $index % 60),
		);
	}

	return $rows;
}

beforeEach(function () use ($root) {
	boostRecoveryTestLoadFunction($root);
	boostRecoveryTestReset();
});

test('an empty acknowledged set performs no delete', function () {
	expect(boostRecoveryTestDelete(array(), 'local'))->toBeTrue()
		->and($GLOBALS['boost_recovery_test_state']['calls'])->toBe(array());
});

test('recovery deletes exact primary-key triples with bound parameters', function () {
	$rows = boostRecoveryTestRows(2);

	expect(boostRecoveryTestDelete($rows, 'local'))->toBeTrue()
		->and($GLOBALS['boost_recovery_test_state']['calls'])->toHaveCount(1);

	$call = $GLOBALS['boost_recovery_test_state']['calls'][0];

	expect(substr_count($call['sql'], '(local_data_id = ? AND rrd_name = ? AND time = ?)'))->toBe(2)
		->and($call['params'])->toBe(array(
			1, 'ds_1', $rows[0]['time'],
			2, 'ds_2', $rows[1]['time'],
		))
		->and($call['conn'])->toBe('local');
});

test('recovery chunks exact deletes at five hundred rows', function () {
	expect(boostRecoveryTestDelete(boostRecoveryTestRows(1001), 'local'))->toBeTrue()
		->and($GLOBALS['boost_recovery_test_state']['calls'])->toHaveCount(3)
		->and($GLOBALS['boost_recovery_test_state']['calls'][0]['params'])->toHaveCount(1500)
		->and($GLOBALS['boost_recovery_test_state']['calls'][1]['params'])->toHaveCount(1500)
		->and($GLOBALS['boost_recovery_test_state']['calls'][2]['params'])->toHaveCount(3);
});

test('recovery stops deleting immediately after a failed chunk', function () {
	boostRecoveryTestReset(array('fail_at' => 2));

	expect(boostRecoveryTestDelete(boostRecoveryTestRows(1200), 'local'))->toBeFalse()
		->and($GLOBALS['boost_recovery_test_state']['calls'])->toHaveCount(2);
});
