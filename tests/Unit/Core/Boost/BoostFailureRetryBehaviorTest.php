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

namespace BoostFailureRetryBehaviorFixture;

$root = dirname(__DIR__, 4);
require_once $root . '/tests/Helpers/SourceSlice.php';

function boost_ensure_failure_queue() {
	return $GLOBALS['boost_failure_retry_behavior']['queue_available'];
}

function boost_failure_limit($name, $default, $maximum) {
	return $GLOBALS['boost_failure_retry_behavior']['limits'][$name] ?? $default;
}

function db_fetch_assoc_prepared($sql, $params = array()) {
	$GLOBALS['boost_failure_retry_behavior']['queries'][] = array($sql, $params);

	return $GLOBALS['boost_failure_retry_behavior']['expired'];
}

function db_execute_prepared($sql, $params = array(), $log = true, $db_conn = false, $execute_name = 'Exec', $default_value = true, $return_func = 'no_return_function', $return_params = array()) {
	$GLOBALS['boost_failure_retry_behavior']['queries'][] = array($sql, $params, $log);

	return $GLOBALS['boost_failure_retry_behavior']['execute_result'];
}

function db_affected_rows() {
	return $GLOBALS['boost_failure_retry_behavior']['affected_rows'];
}

function boost_update_failure_queue_id_cache($local_data_id, $present) {
	$GLOBALS['boost_failure_retry_behavior']['cache_updates'][] = array($local_data_id, $present);
}

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['boost_failure_retry_behavior']['logs'][] = $message;
}

function boost_alert_failure_queue($message, $immediate = false) {
	$GLOBALS['boost_failure_retry_behavior']['alerts'][] = array($message, $immediate);
}

function db_fetch_assoc($sql) {
	$GLOBALS['boost_failure_retry_behavior']['queries'][] = $sql;

	return $GLOBALS['boost_failure_retry_behavior']['due'];
}

function boost_acquire_rrd_path_lock($rrd_path, $timeout = 5) {
	return $GLOBALS['boost_failure_retry_behavior']['locks'][$rrd_path] ?? 'lock-' . $rrd_path;
}

function boost_failure_retry_destination_is_current($local_data_id, $rrd_path) {
	if (array_key_exists($local_data_id, $GLOBALS['boost_failure_retry_behavior']['destinations'])) {
		return $GLOBALS['boost_failure_retry_behavior']['destinations'][$local_data_id];
	}

	return true;
}

function boost_release_rrd_path_lock($lock_name) {
	$GLOBALS['boost_failure_retry_behavior']['released'][] = $lock_name;
}

function boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_template, &$values, &$rrdtool_pipe) {
	$GLOBALS['boost_failure_retry_behavior']['updates'][] = array($local_data_id, $rrd_path, $rrd_template, $values);

	return $GLOBALS['boost_failure_retry_behavior']['update_results'][$local_data_id] ?? 'OK';
}

function boost_is_isolated_rrd_failure($error) {
	return str_contains((string) $error, 'isolated');
}

function boost_backend_failure_window_open($successes, $failures, $consecutive_failures) {
	return $failures >= 2 || $consecutive_failures >= 2;
}

function boost_update_failure_status() {
	$GLOBALS['boost_failure_retry_behavior']['status_updates']++;
}

function read_config_option($name) {
	return $GLOBALS['boost_failure_retry_behavior']['settings'][$name] ?? '';
}

function boostFailureRetryBehaviorLoadFunction($root) {
	if (function_exists(__NAMESPACE__ . '\\boost_retry_rrd_failures')) {
		return;
	}

	\cacti_fixture_source_function($root . '/lib/boost.php', 'boost_retry_rrd_failures', __NAMESPACE__);
}

function boostFailureRetryRow($local_data_id, $sample_count = 1) {
	return array(
		'local_data_id' => $local_data_id,
		'generation'    => 'generation-' . $local_data_id,
		'sample_count'  => $sample_count,
		'rrd_path'      => '/rrd/' . $local_data_id . '.rrd',
		'rrd_template'  => 'value',
		'update_values' => '1700000000:' . $local_data_id,
		'retry_count'   => 0,
	);
}

beforeEach(function () use ($root) {
	boostFailureRetryBehaviorLoadFunction($root);
	$GLOBALS['boost_failure_retry_behavior'] = array(
		'queue_available' => true,
		'limits'          => array(),
		'expired'         => array(),
		'due'             => array(),
		'execute_result'  => true,
		'affected_rows'   => 1,
		'cache_updates'   => array(),
		'logs'            => array(),
		'alerts'          => array(),
		'queries'         => array(),
		'locks'           => array(),
		'destinations'    => array(),
		'updates'         => array(),
		'update_results'  => array(),
		'released'        => array(),
		'status_updates'  => 0,
		'settings'        => array('boost_failure_queue_depth' => 0),
	);
});

test('retry pass does no work when its bounded queue is unavailable', function () {
	$GLOBALS['boost_failure_retry_behavior']['queue_available'] = false;
	$pipe = false;

	expect(boost_retry_rrd_failures($pipe))->toBe(array(
		'attempted' => 0,
		'recovered' => 0,
		'pending'   => 0,
		'discarded' => 0,
	));
});

test('retry pass expires bounded rows and publishes pending depth', function () {
	$GLOBALS['boost_failure_retry_behavior']['expired'] = array(
		boostFailureRetryRow(1, 3),
	);
	$GLOBALS['boost_failure_retry_behavior']['settings']['boost_failure_queue_depth'] = 4;
	$pipe = false;

	$result = boost_retry_rrd_failures($pipe);

	expect($result)->toBe(array(
		'attempted' => 0,
		'recovered' => 0,
		'pending'   => 4,
		'discarded' => 3,
	))
		->and($GLOBALS['boost_failure_retry_behavior']['cache_updates'])->toBe(array(array(1, false)))
		->and($GLOBALS['boost_failure_retry_behavior']['logs'])->toHaveCount(1)
		->and($GLOBALS['boost_failure_retry_behavior']['alerts'])->toHaveCount(1)
		->and($GLOBALS['boost_failure_retry_behavior']['status_updates'])->toBe(1);
});

test('retry pass isolates lock stale destination and write outcomes', function () {
	$GLOBALS['boost_failure_retry_behavior']['due'] = array(
		boostFailureRetryRow(1),
		boostFailureRetryRow(2),
		boostFailureRetryRow(3, 3),
		boostFailureRetryRow(4, 4),
		boostFailureRetryRow(5),
		boostFailureRetryRow(6),
		boostFailureRetryRow(7),
		boostFailureRetryRow(8),
	);
	$GLOBALS['boost_failure_retry_behavior']['locks']['/rrd/1.rrd'] = false;
	$GLOBALS['boost_failure_retry_behavior']['destinations'] = array(
		2 => null,
		3 => false,
	);
	$GLOBALS['boost_failure_retry_behavior']['update_results'] = array(
		4 => 'OK',
		5 => 'isolated destination error',
		6 => 'backend unavailable',
		7 => 'backend unavailable',
		8 => 'OK',
	);
	$GLOBALS['boost_failure_retry_behavior']['settings']['boost_failure_queue_depth'] = 5;
	$pipe = false;

	$result = boost_retry_rrd_failures($pipe);

	expect($result)->toBe(array(
		'attempted' => 7,
		'recovered' => 4,
		'pending'   => 5,
		'discarded' => 3,
	))
		->and(array_column($GLOBALS['boost_failure_retry_behavior']['updates'], 0))->toBe(array(4, 5, 6, 7))
		->and($GLOBALS['boost_failure_retry_behavior']['released'])->toHaveCount(6)
		->and($GLOBALS['boost_failure_retry_behavior']['logs'])->toHaveCount(2)
		->and($GLOBALS['boost_failure_retry_behavior']['alerts'])->toHaveCount(2)
		->and($GLOBALS['boost_failure_retry_behavior']['status_updates'])->toBe(1);
});
