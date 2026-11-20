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

namespace BoostPollerOutputFixture;

$root = dirname(__DIR__, 4);
require_once $root . '/tests/Helpers/SourceSlice.php';

if (!defined('BOOST_TIMER_START')) {
	define('BOOST_TIMER_START', 0);
}

if (!defined('BOOST_TIMER_END')) {
	define('BOOST_TIMER_END', 1);
}

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

function boost_is_isolated_rrd_failure($error) {
	return str_contains((string) $error, 'isolated');
}

function boost_backend_failure_window_open($successes, $failures, $consecutive_failures) {
	return $failures >= 2 || $consecutive_failures >= 2;
}

function boost_clear_superseded_rrd_failure($local_data_id, $latest_sample) {
	return $GLOBALS['boost_poller_output']['superseded'];
}

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['boost_poller_output']['logs'][] = $message;
}

function read_config_option($name) {
	return $GLOBALS['boost_poller_output']['settings'][$name] ?? '';
}

function boost_timer($area, $type) {
	$GLOBALS['boost_poller_output']['timers'][] = array($area, $type);
}

function boost_acquire_rrd_path_lock($rrd_path, $timeout = 5) {
	return $GLOBALS['boost_poller_output']['lock'];
}

function boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_update_template, &$rrd_update_values, &$rrdtool_pipe) {
	$GLOBALS['boost_poller_output']['updates'][] = array($local_data_id, $rrd_path, $rrd_update_template, $rrd_update_values);

	return $GLOBALS['boost_poller_output']['update_result'];
}

function boost_release_rrd_path_lock($lock_name) {
	$GLOBALS['boost_poller_output']['released'][] = $lock_name;
}

function boost_queue_rrd_failure($local_data_id, $rrd_path, $rrd_template, $update_values, $latest_sample, $sample_count, $error) {
	$GLOBALS['boost_poller_output']['queued'][] = array(
		$local_data_id, $rrd_path, $rrd_template, $update_values, $latest_sample, $sample_count, $error,
	);

	return $GLOBALS['boost_poller_output']['disposition'];
}

function rrd_init($output_to_term = true, $read_response = false) {
	return $GLOBALS['boost_poller_output']['pipe'];
}

function rrd_close($rrdtool_pipe) {
	$GLOBALS['boost_poller_output']['closed']++;
}

function boost_is_valid_archive_table($table) {
	return $GLOBALS['boost_poller_output']['archive_valid'] &&
		is_string($table) && preg_match('/^poller_output_boost_arch_\d+$/D', $table) === 1;
}

function boost_dynamic_table_exists_uncached($table) {
	return $GLOBALS['boost_poller_output']['archive_exists'];
}

function boost_process_local_data_ids($child, $rrdtool_pipe) {
	return array_shift($GLOBALS['boost_poller_output']['process_results']);
}

function boost_debug($message) {
	$GLOBALS['boost_poller_output']['debug'][] = $message;
}

function set_config_option($name, $value) {
	$GLOBALS['boost_poller_output']['settings'][$name] = $value;
}

function db_fetch_assoc_prepared($sql, $params = array()) {
	$GLOBALS['boost_poller_output']['queries'][] = array($sql, $params);

	return $GLOBALS['boost_poller_output']['fetch_result'];
}

function boostPollerOutputReset() {
	$GLOBALS['boost_poller_output'] = array(
		'superseded'     => 0,
		'logs'           => array(),
		'settings'       => array(),
		'timers'         => array(),
		'lock'           => 'boost.rrd.lock',
		'update_result'  => 'OK',
		'updates'        => array(),
		'released'       => array(),
		'queued'         => array(),
		'disposition'    => array('handled' => true, 'queued_samples' => 1, 'discarded_samples' => 0),
		'pipe'           => array('pipe'),
		'closed'         => 0,
		'archive_valid'  => true,
		'archive_exists' => true,
		'process_results'=> array(),
		'debug'          => array(),
		'queries'        => array(),
		'fetch_result'   => array(),
	);
	$GLOBALS['boost_worker_accounting'] = array(
		'written_samples'   => 0,
		'failed_samples'    => 0,
		'queued_samples'    => 0,
		'discarded_samples' => 0,
	);
	$GLOBALS['boost_backend_circuit'] = array(
		'successes'            => 0,
		'failures'             => 0,
		'isolated_failures'    => 0,
		'consecutive_failures' => 0,
		'open'                 => false,
	);
}

function boostPollerOutputLoadFunctions($root) {
	if (function_exists(__NAMESPACE__ . '\\boost_process_output')) {
		return;
	}

	foreach(array(
		'boost_output_rrd_data',
		'boost_fetch_archive_range',
		'boost_measure_update_values',
		'boost_record_backend_result',
		'boost_backend_circuit_open',
		'boost_process_output',
	) as $function) {
		\cacti_fixture_source_function($root . '/poller_boost.php', $function, __NAMESPACE__);
	}
}

beforeEach(function () use ($root) {
	boostPollerOutputLoadFunctions($root);
	boostPollerOutputReset();
	$GLOBALS['start'] = time();
	$GLOBALS['archive_table'] = 'poller_output_boost_arch_1';
	$GLOBALS['range_start'] = 1;
	$GLOBALS['range_end'] = 10;
	$GLOBALS['max_run_duration'] = 1200;
	$GLOBALS['config'] = array();
	$GLOBALS['run_id'] = str_repeat('a', 32);
});

test('sample accounting handles empty and multi-update commands', function () {
	expect(boost_measure_update_values(''))->toBe(array('count' => 1, 'latest' => 0))
		->and(boost_measure_update_values('10:1 30:2 20:3'))->toBe(array('count' => 3, 'latest' => 30));
});

test('backend accounting separates success isolated failure and outage pressure', function () {
	expect(boost_record_backend_result(true))->toBeFalse();
	expect(boost_record_backend_result(false, 'isolated destination'))->toBeFalse();
	expect(boost_record_backend_result(false, 'backend down'))->toBeFalse();
	expect(boost_record_backend_result(false, 'backend down'))->toBeTrue()
		->and(boost_backend_circuit_open())->toBeTrue()
		->and($GLOBALS['boost_backend_circuit']['successes'])->toBe(1)
		->and($GLOBALS['boost_backend_circuit']['isolated_failures'])->toBe(1);
});

test('deleted destinations are explicit discards and clear stale retries', function () {
	$GLOBALS['boost_poller_output']['superseded'] = 2;

	expect(boost_process_output(7, array(array(100, 1)), '', array('value' => 1), array()))->toBeTrue()
		->and($GLOBALS['boost_worker_accounting']['discarded_samples'])->toBe(3)
		->and($GLOBALS['boost_poller_output']['logs'])->toHaveCount(1);
});

test('write lock and persistence failures retain the archive', function () {
	$GLOBALS['boost_poller_output']['lock'] = false;
	$GLOBALS['boost_poller_output']['disposition'] = array(
		'handled' => false, 'queued_samples' => 0, 'discarded_samples' => 0,
	);

	expect(boost_process_output(7, array(array(100, 1)), '/rra/a.rrd', array('value' => 1), array()))->toBeFalse()
		->and($GLOBALS['boost_backend_circuit']['open'])->toBeTrue()
		->and($GLOBALS['boost_worker_accounting']['failed_samples'])->toBe(1);
});

test('isolated failures queue only their destination and healthy writes advance accounting', function () {
	$GLOBALS['boost_poller_output']['update_result'] = 'isolated destination';
	expect(boost_process_output(7, array(array(100, 1)), '/rra/a.rrd', array('value' => 1), array()))->toBeFalse()
		->and($GLOBALS['boost_worker_accounting']['queued_samples'])->toBe(1)
		->and($GLOBALS['boost_backend_circuit']['open'])->toBeFalse();

	boostPollerOutputReset();
	$GLOBALS['boost_poller_output']['superseded'] = 2;
	expect(boost_process_output(7, array(array(100, 1)), '/rra/a.rrd', array('value' => 1), array()))->toBeTrue()
		->and($GLOBALS['boost_worker_accounting']['written_samples'])->toBe(1)
		->and($GLOBALS['boost_worker_accounting']['discarded_samples'])->toBe(2);
});

test('RRD worker startup validates archive range and acknowledged pipe state', function () {
	$GLOBALS['boost_poller_output']['archive_valid'] = false;
	expect(boost_output_rrd_data(1))->toBe(-1);

	boostPollerOutputReset();
	$GLOBALS['range_start'] = 0;
	expect(boost_output_rrd_data(1))->toBe(0)
		->and($GLOBALS['boost_poller_output']['closed'])->toBe(1);

	boostPollerOutputReset();
	$GLOBALS['range_start'] = 1;
	$GLOBALS['boost_poller_output']['pipe'] = false;
	expect(boost_output_rrd_data(1))->toBe(-1);
});

test('RRD worker returns fatal processing errors and counts completed range rows', function () {
	$GLOBALS['boost_poller_output']['process_results'] = array(-1);
	expect(boost_output_rrd_data(1))->toBe(-1);

	boostPollerOutputReset();
	$GLOBALS['boost_poller_output']['process_results'] = array(2, 0);
	expect(boost_output_rrd_data(1))->toBe(2)
		->and($GLOBALS['boost_poller_output']['closed'])->toBe(1);
});

test('archive range reader rejects invalid ownership and uses one ordered range query', function () {
	expect(boost_fetch_archive_range('invalid', 1, 2))->toBeFalse()
		->and(boost_fetch_archive_range('poller_output_boost_arch_1', 2, 1))->toBeFalse();

	$GLOBALS['boost_poller_output']['fetch_result'] = array(array('local_data_id' => 1));
	expect(boost_fetch_archive_range('poller_output_boost_arch_1', 1, 2))->toBe(array(array('local_data_id' => 1)))
		->and($GLOBALS['boost_poller_output']['queries'][0][0])->toContain('BETWEEN ? AND ?')
		->and($GLOBALS['boost_poller_output']['queries'][0][0])->not->toContain('LIMIT');
});
