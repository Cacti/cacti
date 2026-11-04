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

namespace BoostArchiveDeletionOwnershipFixture;

$root = dirname(__DIR__, 4);
require_once $root . '/tests/Helpers/SourceSlice.php';

function api_plugin_hook_function($name, $arguments) {
}

function read_config_option($name) {
	return $GLOBALS['boost_archive_deletion']['settings'][$name] ?? '';
}

function db_fetch_row_prepared($sql, $params = array()) {
	return array();
}

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

function db_fetch_cell_prepared($sql, $params = array()) {
	return array_shift($GLOBALS['boost_archive_deletion']['cells']);
}

function db_execute_prepared($sql, $params = array(), $log = true, $db_conn = false, $execute_name = 'Exec', $default_value = true, $return_func = 'no_return_function', $return_params = array()) {
	$GLOBALS['boost_archive_deletion']['prepared'][] = array($sql, $params, $db_conn);

	return true;
}

function poller_push_to_remote_db_connect($poller_id, $force = false) {
	return $GLOBALS['boost_archive_deletion']['remote_connection'];
}

function api_data_source_cache_crc_update($poller_id) {
	$GLOBALS['boost_archive_deletion']['cache_updates'][] = $poller_id;
}

function get_remote_poller_ids_from_data_sources($local_data_ids) {
	return $GLOBALS['boost_archive_deletion']['poller_ids'];
}

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = '') {
}

function db_fetch_assoc($sql) {
	return array();
}

function db_execute($sql, $log = true, $connection = false) {
	$GLOBALS['boost_archive_deletion']['plain'][] = array($sql, $connection);

	return true;
}

function boostArchiveDeletionLoadFunctions($root) {
	if (function_exists(__NAMESPACE__ . '\\data_query_remove_disabled_items')) {
		return;
	}

	foreach(array('api_data_source_remove', 'api_data_source_remove_multi') as $function) {
		\cacti_fixture_source_function($root . '/lib/api_data_source.php', $function, __NAMESPACE__);
	}

	\cacti_fixture_source_function(
		$root . '/lib/data_query.php',
		'data_query_remove_disabled_items',
		__NAMESPACE__
	);
}

beforeEach(function () use ($root) {
	boostArchiveDeletionLoadFunctions($root);
	$GLOBALS['boost_archive_deletion'] = array(
		'settings'          => array(
			'rrd_autoclean'           => '',
			'rrd_autoclean_method'    => '',
			'boost_rrd_update_enable' => 'on',
		),
		'cells'             => array(0, 5),
		'remote_connection' => 99,
		'poller_ids'        => array(5),
		'prepared'          => array(),
		'plain'             => array(),
		'cache_updates'     => array(),
	);
});

test('enabled Boost retains live and sealed rows during single-source deletion', function () {
	api_data_source_remove(7);

	$queries = array_column($GLOBALS['boost_archive_deletion']['prepared'], 0);
	expect(implode("\n", $queries))->not->toContain('DELETE FROM poller_output_boost')
		->and($GLOBALS['boost_archive_deletion']['cache_updates'])->toBe(array(5));
});

test('disabled Boost preserves legacy local and remote live-queue deletion', function () {
	$GLOBALS['boost_archive_deletion']['settings']['boost_rrd_update_enable'] = '';
	api_data_source_remove(7);

	$queries = array_column($GLOBALS['boost_archive_deletion']['prepared'], 0);
	$boost_deletes = array_filter($queries, static fn($sql) => str_contains($sql, 'DELETE FROM poller_output_boost'));
	expect($boost_deletes)->toHaveCount(2);
});

test('multi-source deletion leaves enabled Boost queues to the scheduled owner', function () {
	api_data_source_remove_multi(array(7, 8));

	$queries = array_column($GLOBALS['boost_archive_deletion']['plain'], 0);
	expect(implode("\n", $queries))->not->toContain('DELETE FROM poller_output_boost')
		->and($GLOBALS['boost_archive_deletion']['cache_updates'])->toBe(array(5));
});

test('disabled Boost removes multi-source local and remote live rows', function () {
	$GLOBALS['boost_archive_deletion']['settings']['boost_rrd_update_enable'] = '';
	api_data_source_remove_multi(array(7, 8));

	$queries = array_column($GLOBALS['boost_archive_deletion']['plain'], 0);
	$boost_deletes = array_filter($queries, static fn($sql) => str_contains($sql, 'DELETE FROM poller_output_boost'));
	expect($boost_deletes)->toHaveCount(2);
});

test('orphan cleanup follows the same enabled and disabled Boost ownership rule', function () {
	data_query_remove_disabled_items(array(7, 8));
	$queries = array_column($GLOBALS['boost_archive_deletion']['prepared'], 0);
	expect(implode("\n", $queries))->not->toContain('DELETE FROM poller_output_boost');

	$GLOBALS['boost_archive_deletion']['settings']['boost_rrd_update_enable'] = '';
	data_query_remove_disabled_items(array(7, 8));
	$queries = array_column($GLOBALS['boost_archive_deletion']['prepared'], 0);
	expect(implode("\n", $queries))->toContain('DELETE FROM poller_output_boost');
});
