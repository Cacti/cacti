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

namespace BoostOnDemandOwnershipFixture;

$root = dirname(__DIR__, 4);
require_once $root . '/tests/Helpers/SourceSlice.php';

function boost_acquire_archive_lifecycle_lock($timeout = 0) {
	return $GLOBALS['boost_on_demand_ownership']['archive_lock'];
}

function read_config_option($name) {
	return $GLOBALS['boost_on_demand_ownership']['settings'][$name] ?? '';
}

function boost_release_archive_lifecycle_lock($lock_name) {
	$GLOBALS['boost_on_demand_ownership']['released_archives'][] = $lock_name;
}

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['boost_on_demand_ownership']['logs'][] = $message;
}

function boost_reset_rrd_metadata_page_cache() {
	$GLOBALS['boost_on_demand_ownership']['cache_resets']++;
}

function boost_get_rrd_filename_and_template($local_data_id) {
	return $GLOBALS['boost_on_demand_ownership']['metadata'];
}

function boost_acquire_rrd_path_lock($rrd_path, $timeout = 5) {
	return $GLOBALS['boost_on_demand_ownership']['path_lock'];
}

function boost_process_poller_output_unlocked($local_data_id, $rrdtool_pipe = '') {
	$GLOBALS['boost_on_demand_ownership']['processed'][] = array($local_data_id, $rrdtool_pipe);

	return $GLOBALS['boost_on_demand_ownership']['result'];
}

function boost_release_rrd_path_lock($lock_name) {
	$GLOBALS['boost_on_demand_ownership']['released_paths'][] = $lock_name;
}

function boostOnDemandOwnershipLoadFunction($root) {
	if (function_exists(__NAMESPACE__ . '\\boost_process_poller_output')) {
		return;
	}

	\cacti_fixture_source_function($root . '/lib/boost.php', 'boost_process_poller_output', __NAMESPACE__);
}

beforeEach(function () use ($root) {
	boostOnDemandOwnershipLoadFunction($root);
	$GLOBALS['boost_on_demand_ownership'] = array(
		'archive_lock'      => 'boost.archive.lifecycle',
		'settings'          => array('boost_poller_started' => 0),
		'released_archives' => array(),
		'logs'              => array(),
		'cache_resets'      => 0,
		'metadata'          => array('rrd_path' => '', 'rrd_template' => ''),
		'path_lock'         => 'boost.rrd.path',
		'processed'         => array(),
		'result'            => 3,
		'released_paths'    => array(),
	);
});

test('on-demand processing rejects invalid IDs and defers behind archive ownership', function () {
	expect(boost_process_poller_output(0))->toBe(-1);

	$GLOBALS['boost_on_demand_ownership']['archive_lock'] = false;
	expect(boost_process_poller_output(7))->toBe(-1)
		->and($GLOBALS['boost_on_demand_ownership']['logs'])->toHaveCount(1);
});

test('on-demand processing defers while a scheduled owner lease remains active', function () {
	$GLOBALS['boost_on_demand_ownership']['settings']['boost_poller_started'] = 1;

	expect(boost_process_poller_output(7))->toBe(-1)
		->and($GLOBALS['boost_on_demand_ownership']['released_archives'])->toBe(array('boost.archive.lifecycle'));
});

test('on-demand processing defers behind an active writer for the same RRD', function () {
	$GLOBALS['boost_on_demand_ownership']['metadata'] = array(
		'rrd_path' => '/rra/a.rrd', 'rrd_template' => 'value',
	);
	$GLOBALS['boost_on_demand_ownership']['path_lock'] = false;

	expect(boost_process_poller_output(7))->toBe(-1)
		->and($GLOBALS['boost_on_demand_ownership']['released_archives'])->toBe(array('boost.archive.lifecycle'));
});

test('on-demand processing releases both locks after success', function () {
	$GLOBALS['boost_on_demand_ownership']['metadata'] = array(
		'rrd_path' => '/rra/a.rrd', 'rrd_template' => 'value',
	);

	expect(boost_process_poller_output(7, array('pipe')))->toBe(3)
		->and($GLOBALS['boost_on_demand_ownership']['cache_resets'])->toBe(1)
		->and($GLOBALS['boost_on_demand_ownership']['released_paths'])->toBe(array('boost.rrd.path'))
		->and($GLOBALS['boost_on_demand_ownership']['released_archives'])->toBe(array('boost.archive.lifecycle'));
});
