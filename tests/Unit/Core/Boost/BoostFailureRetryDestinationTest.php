<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace BoostFailureRetryDestinationFixture;

$root = dirname(__DIR__, 4);
require_once $root . '/tests/Helpers/SourceSlice.php';

function db_fetch_cell_prepared($sql, $params = array()) {
	$GLOBALS['boost_failure_destination_queries'][] = array($sql, $params);

	return array_shift($GLOBALS['boost_failure_destination_results']);
}

function boostFailureDestinationLoadFunction($root) {
	if (function_exists(__NAMESPACE__ . '\\boost_failure_retry_destination_is_current')) {
		return;
	}

	\cacti_fixture_source_function(
		$root . '/lib/boost.php',
		'boost_failure_retry_destination_is_current',
		__NAMESPACE__
	);
}

beforeEach(function () use ($root) {
	boostFailureDestinationLoadFunction($root);
	$GLOBALS['boost_failure_destination_queries'] = array();
	$GLOBALS['boost_failure_destination_results'] = array();
});

test('retry destination verification fails closed on database errors', function () {
	$GLOBALS['boost_failure_destination_results'] = array(false);

	expect(boost_failure_retry_destination_is_current(7, '/rrd/current.rrd'))->toBeNull()
		->and($GLOBALS['boost_failure_destination_queries'])->toHaveCount(1);

	$GLOBALS['boost_failure_destination_queries'] = array();
	$GLOBALS['boost_failure_destination_results'] = array(1, false);

	expect(boost_failure_retry_destination_is_current(7, '/rrd/current.rrd'))->toBeNull()
		->and($GLOBALS['boost_failure_destination_queries'])->toHaveCount(2);
});

test('removed or repointed destinations are stale but an unchanged path is current', function () {
	$GLOBALS['boost_failure_destination_results'] = array(0);

	expect(boost_failure_retry_destination_is_current(7, '/rrd/old.rrd'))->toBeFalse()
		->and($GLOBALS['boost_failure_destination_queries'])->toHaveCount(1);

	$GLOBALS['boost_failure_destination_queries'] = array();
	$GLOBALS['boost_failure_destination_results'] = array(1, '/rrd/new.rrd');

	expect(boost_failure_retry_destination_is_current(7, '/rrd/old.rrd'))->toBeFalse();

	$GLOBALS['boost_failure_destination_results'] = array(1, '/rrd/new.rrd');

	expect(boost_failure_retry_destination_is_current(7, '/rrd/new.rrd'))->toBeTrue();
});
