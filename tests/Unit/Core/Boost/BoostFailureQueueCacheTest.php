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

namespace BoostFailureQueueCacheFixture;

$root = dirname(__DIR__, 4);
require_once $root . '/tests/Helpers/SourceSlice.php';

function boost_ensure_failure_queue() {
	$GLOBALS['boost_failure_cache_ensure_calls']++;

	return $GLOBALS['boost_failure_cache_ensure_result'];
}

function db_fetch_assoc($sql) {
	$GLOBALS['boost_failure_cache_queries'][] = $sql;

	return $GLOBALS['boost_failure_cache_rows'];
}

function cacti_log($message) {
	$GLOBALS['boost_failure_cache_logs'][] = $message;
}

function boostFailureCacheLoadFunctions($root) {
	if (function_exists(__NAMESPACE__ . '\\boost_load_failure_queue_id_cache')) {
		return;
	}

	foreach(array(
		'boost_load_failure_queue_id_cache',
		'boost_failure_queue_id_may_exist',
		'boost_update_failure_queue_id_cache',
	) as $function) {
		\cacti_fixture_source_function($root . '/lib/boost.php', $function, __NAMESPACE__);
	}
}

beforeEach(function () use ($root) {
	boostFailureCacheLoadFunctions($root);
	unset($GLOBALS['boost_failure_queue_ids']);
	$GLOBALS['boost_failure_cache_ensure_calls']  = 0;
	$GLOBALS['boost_failure_cache_ensure_result'] = true;
	$GLOBALS['boost_failure_cache_queries']       = array();
	$GLOBALS['boost_failure_cache_rows']          = array(
		array('local_data_id' => 7),
		array('local_data_id' => 42),
	);
	$GLOBALS['boost_failure_cache_logs'] = array();
});

test('healthy writes use one bounded failure-ID preload instead of per-RRD queries', function () {
	expect(boost_failure_queue_id_may_exist(7))->toBeTrue();

	for ($local_data_id = 1; $local_data_id <= 10000; $local_data_id++) {
		boost_failure_queue_id_may_exist($local_data_id);
	}

	expect(boost_failure_queue_id_may_exist(42))->toBeTrue()
		->and(boost_failure_queue_id_may_exist(90001))->toBeFalse()
		->and($GLOBALS['boost_failure_cache_ensure_calls'])->toBe(1)
		->and($GLOBALS['boost_failure_cache_queries'])->toHaveCount(1)
		->and($GLOBALS['boost_failure_cache_queries'][0])->toContain('LIMIT 100001');
});

test('queue mutations keep a loaded ID cache synchronized', function () {
	expect(boost_failure_queue_id_may_exist(99))->toBeFalse();

	boost_update_failure_queue_id_cache(99, true);

	expect(boost_failure_queue_id_may_exist(99))->toBeTrue();

	boost_update_failure_queue_id_cache(99, false);

	expect(boost_failure_queue_id_may_exist(99))->toBeFalse()
		->and($GLOBALS['boost_failure_cache_queries'])->toHaveCount(1);
});

test('failure handling does not force a cache preload', function () {
	boost_update_failure_queue_id_cache(99, true);

	expect(isset($GLOBALS['boost_failure_queue_ids']))->toBeFalse()
		->and($GLOBALS['boost_failure_cache_ensure_calls'])->toBe(0)
		->and($GLOBALS['boost_failure_cache_queries'])->toBe(array());
});

test('a failed cache preload defers cleanup without repeated database reads', function () {
	$GLOBALS['boost_failure_cache_rows'] = false;

	expect(boost_failure_queue_id_may_exist(7))->toBeFalse()
		->and(boost_failure_queue_id_may_exist(7))->toBeFalse()
		->and($GLOBALS['boost_failure_cache_queries'])->toHaveCount(1)
		->and($GLOBALS['boost_failure_cache_logs'])->toHaveCount(1);
});
