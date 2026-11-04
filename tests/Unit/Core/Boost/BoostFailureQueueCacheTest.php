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

function boostFailureCacheEnsure() {
	$GLOBALS['boost_failure_cache_ensure_calls']++;

	return $GLOBALS['boost_failure_cache_ensure_result'];
}

function boostFailureCacheFetch($sql) {
	$GLOBALS['boost_failure_cache_queries'][] = $sql;

	return $GLOBALS['boost_failure_cache_rows'];
}

function boostFailureCacheLog($message) {
	$GLOBALS['boost_failure_cache_logs'][] = $message;
}

function boostFailureCacheLoadFunctions($root) {
	if (function_exists('boostFailureCacheLoad')) {
		return;
	}

	$source = file_get_contents($root . '/lib/boost.php');
	$start  = strpos($source, 'function boost_load_failure_queue_id_cache(');
	$end    = strpos($source, "\nfunction boost_update_failure_status(", $start);

	expect($start)->not->toBeFalse()
		->and($end)->not->toBeFalse();

	$functions = substr($source, $start, $end - $start);
	$functions = str_replace(array(
		'boost_load_failure_queue_id_cache',
		'boost_failure_queue_id_may_exist',
		'boost_update_failure_queue_id_cache',
		'boost_ensure_failure_queue',
		'db_fetch_assoc',
		'cacti_log',
	), array(
		'boostFailureCacheLoad',
		'boostFailureCacheMayExist',
		'boostFailureCacheUpdate',
		'boostFailureCacheEnsure',
		'boostFailureCacheFetch',
		'boostFailureCacheLog',
	), $functions);

	eval($functions);
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
	expect(boostFailureCacheMayExist(7))->toBeTrue();

	for ($local_data_id = 1; $local_data_id <= 10000; $local_data_id++) {
		boostFailureCacheMayExist($local_data_id);
	}

	expect(boostFailureCacheMayExist(42))->toBeTrue()
		->and(boostFailureCacheMayExist(90001))->toBeFalse()
		->and($GLOBALS['boost_failure_cache_ensure_calls'])->toBe(1)
		->and($GLOBALS['boost_failure_cache_queries'])->toHaveCount(1)
		->and($GLOBALS['boost_failure_cache_queries'][0])->toContain('LIMIT 100001');
});

test('queue mutations keep a loaded ID cache synchronized', function () {
	expect(boostFailureCacheMayExist(99))->toBeFalse();

	boostFailureCacheUpdate(99, true);

	expect(boostFailureCacheMayExist(99))->toBeTrue();

	boostFailureCacheUpdate(99, false);

	expect(boostFailureCacheMayExist(99))->toBeFalse()
		->and($GLOBALS['boost_failure_cache_queries'])->toHaveCount(1);
});

test('failure handling does not force a cache preload', function () {
	boostFailureCacheUpdate(99, true);

	expect(isset($GLOBALS['boost_failure_queue_ids']))->toBeFalse()
		->and($GLOBALS['boost_failure_cache_ensure_calls'])->toBe(0)
		->and($GLOBALS['boost_failure_cache_queries'])->toBe(array());
});

test('a failed cache preload defers cleanup without repeated database reads', function () {
	$GLOBALS['boost_failure_cache_rows'] = false;

	expect(boostFailureCacheMayExist(7))->toBeFalse()
		->and(boostFailureCacheMayExist(7))->toBeFalse()
		->and($GLOBALS['boost_failure_cache_queries'])->toHaveCount(1)
		->and($GLOBALS['boost_failure_cache_logs'])->toHaveCount(1);
});
