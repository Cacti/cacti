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

$root = dirname(__DIR__, 4);

function boostFailureDestinationFetch($sql, $params = array()) {
	$GLOBALS['boost_failure_destination_queries'][] = array($sql, $params);

	return array_shift($GLOBALS['boost_failure_destination_results']);
}

function boostFailureDestinationLoadFunction($root) {
	if (function_exists('boostFailureDestinationIsCurrent')) {
		return;
	}

	$source = file_get_contents($root . '/lib/boost.php');
	$start  = strpos($source, 'function boost_failure_retry_destination_is_current(');
	$end    = strpos($source, "\n}\n\n/**", $start);

	expect($start)->not->toBeFalse()
		->and($end)->not->toBeFalse();

	$function = substr($source, $start, $end - $start + 2);
	$function = str_replace(array(
		'boost_failure_retry_destination_is_current',
		'db_fetch_cell_prepared',
	), array(
		'boostFailureDestinationIsCurrent',
		'boostFailureDestinationFetch',
	), $function);

	eval($function);
}

beforeEach(function () use ($root) {
	boostFailureDestinationLoadFunction($root);
	$GLOBALS['boost_failure_destination_queries'] = array();
	$GLOBALS['boost_failure_destination_results'] = array();
});

test('retry destination verification fails closed on database errors', function () {
	$GLOBALS['boost_failure_destination_results'] = array(false);

	expect(boostFailureDestinationIsCurrent(7, '/rrd/current.rrd'))->toBeNull()
		->and($GLOBALS['boost_failure_destination_queries'])->toHaveCount(1);

	$GLOBALS['boost_failure_destination_queries'] = array();
	$GLOBALS['boost_failure_destination_results'] = array(1, false);

	expect(boostFailureDestinationIsCurrent(7, '/rrd/current.rrd'))->toBeNull()
		->and($GLOBALS['boost_failure_destination_queries'])->toHaveCount(2);
});

test('removed or repointed destinations are stale but an unchanged path is current', function () {
	$GLOBALS['boost_failure_destination_results'] = array(0);

	expect(boostFailureDestinationIsCurrent(7, '/rrd/old.rrd'))->toBeFalse()
		->and($GLOBALS['boost_failure_destination_queries'])->toHaveCount(1);

	$GLOBALS['boost_failure_destination_queries'] = array();
	$GLOBALS['boost_failure_destination_results'] = array(1, '/rrd/new.rrd');

	expect(boostFailureDestinationIsCurrent(7, '/rrd/old.rrd'))->toBeFalse();

	$GLOBALS['boost_failure_destination_results'] = array(1, '/rrd/new.rrd');

	expect(boostFailureDestinationIsCurrent(7, '/rrd/new.rrd'))->toBeTrue();
});
