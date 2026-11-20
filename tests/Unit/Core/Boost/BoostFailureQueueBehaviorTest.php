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

namespace BoostFailureQueueBehaviorFixture;

$root = dirname(__DIR__, 4);
require_once $root . '/tests/Helpers/SourceSlice.php';

function boost_failure_table_exists_uncached() {
	return array_shift($GLOBALS['boost_failure_queue_behavior']['table_exists']);
}

function db_execute($sql) {
	$GLOBALS['boost_failure_queue_behavior']['queries'][] = $sql;

	return array_shift($GLOBALS['boost_failure_queue_behavior']['execute_results']);
}

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['boost_failure_queue_behavior']['logs'][] = $message;
}

function read_config_option($name) {
	return $GLOBALS['boost_failure_queue_behavior']['settings'][$name] ?? '';
}

function db_fetch_row_prepared($sql, $params = array()) {
	$GLOBALS['boost_failure_queue_behavior']['queries'][] = array($sql, $params);

	return array_shift($GLOBALS['boost_failure_queue_behavior']['row_results']);
}

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : false;
}

function db_fetch_cell($sql) {
	$GLOBALS['boost_failure_queue_behavior']['queries'][] = $sql;

	return array_shift($GLOBALS['boost_failure_queue_behavior']['cell_results']);
}

function boost_alert_failure_queue($message, $immediate = false) {
	$GLOBALS['boost_failure_queue_behavior']['alerts'][] = array($message, $immediate);
}

function db_execute_prepared($sql, $params = array(), $log = true, $db_conn = false, $execute_name = 'Exec', $default_value = true, $return_func = 'no_return_function', $return_params = array()) {
	$GLOBALS['boost_failure_queue_behavior']['queries'][] = array($sql, $params, $log);

	return array_shift($GLOBALS['boost_failure_queue_behavior']['prepared_results']);
}

function boost_update_failure_queue_id_cache($local_data_id, $present) {
	$GLOBALS['boost_failure_queue_behavior']['cache_updates'][] = array($local_data_id, $present);
}

function boost_update_failure_status() {
	$GLOBALS['boost_failure_queue_behavior']['status_updates']++;
}

function boost_failure_queue_id_may_exist($local_data_id) {
	return $GLOBALS['boost_failure_queue_behavior']['may_exist'];
}

function db_affected_rows() {
	return $GLOBALS['boost_failure_queue_behavior']['affected_rows'];
}

function boost_acquire_failure_queue_lock() {
	return $GLOBALS['boost_failure_queue_behavior']['lock'];
}

function boost_release_failure_queue_lock($lock_name) {
	$GLOBALS['boost_failure_queue_behavior']['released'][] = $lock_name;
}

function __($message) {
	return $message;
}

function boostFailureQueueBehaviorLoadFunctions($root) {
	if (function_exists(__NAMESPACE__ . '\\boost_queue_rrd_failure')) {
		return;
	}

	foreach(array(
		'boost_ensure_failure_queue',
		'boost_failure_limit',
		'boost_queue_rrd_failure',
		'boost_queue_rrd_failure_locked',
		'boost_clear_superseded_rrd_failure',
	) as $function) {
		\cacti_fixture_source_function($root . '/lib/boost.php', $function, __NAMESPACE__);
	}
}

beforeEach(function () use ($root) {
	boostFailureQueueBehaviorLoadFunctions($root);
	$GLOBALS['boost_failure_queue_behavior'] = array(
		'table_exists'    => array(true),
		'execute_results' => array(true),
		'row_results'     => array(),
		'cell_results'    => array(),
		'prepared_results'=> array(),
		'settings'        => array(),
		'queries'         => array(),
		'logs'            => array(),
		'alerts'          => array(),
		'cache_updates'   => array(),
		'status_updates'  => 0,
		'may_exist'       => true,
		'affected_rows'   => 1,
		'lock'            => 'boost.failure.queue',
		'released'        => array(),
	);
});

test('failure queue creation distinguishes existing created and unavailable schemas', function () {
	expect(boost_ensure_failure_queue())->toBeTrue()
		->and($GLOBALS['boost_failure_queue_behavior']['queries'])->toBe(array());

	$GLOBALS['boost_failure_queue_behavior']['table_exists'] = array(false);
	$GLOBALS['boost_failure_queue_behavior']['execute_results'] = array(true);

	expect(boost_ensure_failure_queue())->toBeTrue()
		->and($GLOBALS['boost_failure_queue_behavior']['queries'][0])->toContain('CREATE TABLE IF NOT EXISTS');

	$GLOBALS['boost_failure_queue_behavior']['queries'] = array();
	$GLOBALS['boost_failure_queue_behavior']['table_exists'] = array(false, false);
	$GLOBALS['boost_failure_queue_behavior']['execute_results'] = array(false);

	expect(boost_ensure_failure_queue())->toBeFalse()
		->and($GLOBALS['boost_failure_queue_behavior']['logs'])->toHaveCount(1);
});

test('failure queue settings use defaults and hard ceilings', function () {
	expect(boost_failure_limit('budget', 100, 1000))->toBe(100);

	$GLOBALS['boost_failure_queue_behavior']['settings']['budget'] = 40;
	expect(boost_failure_limit('budget', 100, 1000))->toBe(40);

	$GLOBALS['boost_failure_queue_behavior']['settings']['budget'] = 4000;
	expect(boost_failure_limit('budget', 100, 1000))->toBe(1000);
});

test('failure queue wrapper retains an archive when admission is unavailable', function () {
	$GLOBALS['boost_failure_queue_behavior']['table_exists'] = array(false, false);
	$GLOBALS['boost_failure_queue_behavior']['execute_results'] = array(false);

	expect(boost_queue_rrd_failure(7, '/rrd/a.rrd', 'value', '1:1', 1, 1, 'error'))
		->toBe(array('handled' => false, 'queued_samples' => 0, 'discarded_samples' => 0));

	$GLOBALS['boost_failure_queue_behavior']['table_exists'] = array(true);
	$GLOBALS['boost_failure_queue_behavior']['lock'] = false;

	expect(boost_queue_rrd_failure(7, '/rrd/a.rrd', 'value', '1:1', 1, 1, 'error'))
		->toBe(array('handled' => false, 'queued_samples' => 0, 'discarded_samples' => 0))
		->and($GLOBALS['boost_failure_queue_behavior']['logs'])->toHaveCount(2);
});

test('failure queue rejects unreadable state and enforces its destination limit', function () {
	$GLOBALS['boost_failure_queue_behavior']['row_results'] = array(false);

	expect(boost_queue_rrd_failure_locked(7, '/rrd/a.rrd', 'value', '1:1', 1, 2, 'error'))
		->toBe(array('handled' => false, 'queued_samples' => 0, 'discarded_samples' => 0));

	$GLOBALS['boost_failure_queue_behavior']['row_results'] = array(array());
	$GLOBALS['boost_failure_queue_behavior']['cell_results'] = array(false);

	expect(boost_queue_rrd_failure_locked(7, '/rrd/a.rrd', 'value', '1:1', 1, 2, 'error'))
		->toBe(array('handled' => false, 'queued_samples' => 0, 'discarded_samples' => 0));

	$GLOBALS['boost_failure_queue_behavior']['settings']['boost_failure_queue_max_records'] = 1;
	$GLOBALS['boost_failure_queue_behavior']['row_results'] = array(array());
	$GLOBALS['boost_failure_queue_behavior']['cell_results'] = array(1);

	expect(boost_queue_rrd_failure_locked(7, '/rrd/a.rrd', 'value', '1:1', 1, 2, 'error'))
		->toBe(array('handled' => true, 'queued_samples' => 0, 'discarded_samples' => 2))
		->and($GLOBALS['boost_failure_queue_behavior']['alerts'])->toHaveCount(1);
});

test('failure queue discards stale payloads and reports insert failures', function () {
	$GLOBALS['boost_failure_queue_behavior']['row_results'] = array(
		array('latest_sample' => 20, 'sample_count' => 3),
	);

	expect(boost_queue_rrd_failure_locked(7, '/rrd/a.rrd', 'value', '10:1', 10, 2, 'error'))
		->toBe(array('handled' => true, 'queued_samples' => 0, 'discarded_samples' => 2));

	$GLOBALS['boost_failure_queue_behavior']['row_results'] = array(array());
	$GLOBALS['boost_failure_queue_behavior']['cell_results'] = array(0);
	$GLOBALS['boost_failure_queue_behavior']['prepared_results'] = array(false);

	expect(boost_queue_rrd_failure_locked(7, '/rrd/a.rrd', 'value', '30:1', 30, 2, 'error'))
		->toBe(array('handled' => false, 'queued_samples' => 0, 'discarded_samples' => 0));
});

test('failure queue coalesces only the latest complete payload', function () {
	$GLOBALS['boost_failure_queue_behavior']['settings']['boost_failure_queue_depth'] = 1;
	$GLOBALS['boost_failure_queue_behavior']['row_results'] = array(
		array('latest_sample' => 20, 'sample_count' => 3),
	);
	$GLOBALS['boost_failure_queue_behavior']['prepared_results'] = array(true);

	$result = boost_queue_rrd_failure_locked(7, '/rrd/a.rrd', 'value', '30:1', 30, 2, 'error');

	expect($result)->toBe(array('handled' => true, 'queued_samples' => 2, 'discarded_samples' => 3))
		->and($GLOBALS['boost_failure_queue_behavior']['cache_updates'])->toBe(array(array(7, true)))
		->and($GLOBALS['boost_failure_queue_behavior']['status_updates'])->toBe(1)
		->and($GLOBALS['boost_failure_queue_behavior']['alerts'])->toHaveCount(2);

	$GLOBALS['boost_failure_queue_behavior']['table_exists'] = array(true);
	$GLOBALS['boost_failure_queue_behavior']['row_results'] = array(array());
	$GLOBALS['boost_failure_queue_behavior']['cell_results'] = array(0);
	$GLOBALS['boost_failure_queue_behavior']['prepared_results'] = array(true);

	expect(boost_queue_rrd_failure(8, '/rrd/b.rrd', 'value', '40:1', 40, 1, 'error'))
		->toBe(array('handled' => true, 'queued_samples' => 1, 'discarded_samples' => 0))
		->and($GLOBALS['boost_failure_queue_behavior']['released'])->toBe(array('boost.failure.queue'));
});

test('acknowledged writes clear only generation-matched superseded retries', function () {
	expect(boost_clear_superseded_rrd_failure(7, 0))->toBe(0);

	$GLOBALS['boost_failure_queue_behavior']['may_exist'] = false;
	expect(boost_clear_superseded_rrd_failure(7, 30))->toBe(0);

	$GLOBALS['boost_failure_queue_behavior']['may_exist'] = true;
	$GLOBALS['boost_failure_queue_behavior']['row_results'] = array(array());
	expect(boost_clear_superseded_rrd_failure(7, 30))->toBe(0);

	$GLOBALS['boost_failure_queue_behavior']['row_results'] = array(
		array('generation' => 'generation', 'sample_count' => 4),
	);
	$GLOBALS['boost_failure_queue_behavior']['prepared_results'] = array(false);
	expect(boost_clear_superseded_rrd_failure(7, 30))->toBe(0);

	$GLOBALS['boost_failure_queue_behavior']['row_results'] = array(
		array('generation' => 'generation', 'sample_count' => 4),
	);
	$GLOBALS['boost_failure_queue_behavior']['prepared_results'] = array(true);
	expect(boost_clear_superseded_rrd_failure(7, 30))->toBe(4)
		->and($GLOBALS['boost_failure_queue_behavior']['cache_updates'])->toContain(array(7, false))
		->and($GLOBALS['boost_failure_queue_behavior']['status_updates'])->toBe(1);
});
