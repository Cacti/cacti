<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * A Remote Data Collector owns poller_item.rrd_next_step.  It must not push
 * every poller_item row back to the main database each polling cycle, and the
 * main server must not overwrite the remote's value when it replicates
 * poller_item out.  The production functions are loaded unchanged into a
 * fixture namespace with the database layer replaced.
 */

namespace CactiRemoteRrdNextStepFixture;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

if (!defined('POLLER_VERBOSITY_MEDIUM')) {
	define('POLLER_VERBOSITY_MEDIUM', 3);
}

function cacti_sizeof($array) {
	return (is_array($array) || $array instanceof \Countable) ? count($array) : 0;
}

function db_fetch_row($sql, $log = true, $conn = false) {
	return array('Value' => 16777216);
}

function db_fetch_cell($sql, $col = '', $log = true, $conn = false) {
	return $GLOBALS['rrd_next_step_fixture']['remote_rows'];
}

function db_column_exists($table, $column, $log = true, $conn = false) {
	return true;
}

function db_qstr($value) {
	return "'" . addslashes((string) $value) . "'";
}

function db_execute($sql, $log = true, $conn = false) {
	$GLOBALS['rrd_next_step_fixture']['sql'][] = $sql;

	return true;
}

function db_affected_rows($conn = false) {
	return 0;
}

function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') {
}

function db_fetch_assoc_prepared($sql, $params = array(), $log = true, $conn = false) {
	return array(array('id' => 1));
}

function poller_push_table($db_cnn, $records, $table, $ignore = false, $dupes = array()) {
	$GLOBALS['rrd_next_step_fixture']['pushed'][] = $table;
}

if (!function_exists(__NAMESPACE__ . '\\replicate_table_to_poller')) {
	foreach (array('replicate_table_to_poller', 'poller_push_data_to_main') as $function) {
		\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/poller.php', $function, __NAMESPACE__);
	}
}

function replicate_poller_item_sql($remote_rows, $exclude) {
	$GLOBALS['rrd_next_step_fixture'] = array('remote_rows' => $remote_rows, 'sql' => array());

	$data = array(
		array('local_data_id' => 10, 'rrd_name' => 'traffic_in', 'rrd_step' => 300, 'rrd_next_step' => 120),
	);

	replicate_table_to_poller(false, $data, 'poller_item', $exclude);

	expect($GLOBALS['rrd_next_step_fixture']['sql'])->toHaveCount(1);

	return $GLOBALS['rrd_next_step_fixture']['sql'][0];
}

test('an excluded column is inserted for new rows but never overwritten on existing rows', function () {
	$sql = replicate_poller_item_sql(5, array('rrd_next_step'));

	[$insert, $update] = explode(' ON DUPLICATE KEY UPDATE ', $sql);

	expect($insert)->toContain('rrd_next_step')
		->and($insert)->toContain("'120'")
		->and($update)->toContain('rrd_step=VALUES(rrd_step)')
		->and($update)->not->toContain('rrd_next_step');
});

test('excluding every column inserts new rows only', function () {
	$sql = replicate_poller_item_sql(5, array('local_data_id', 'rrd_name', 'rrd_step', 'rrd_next_step'));

	expect($sql)->toStartWith('INSERT IGNORE INTO poller_item (')
		->and($sql)->not->toContain('ON DUPLICATE KEY UPDATE');
});

test('a remote data collector pushes device status but not poller_item to main', function () {
	$saved = $GLOBALS['config'] ?? null;

	$GLOBALS['config']['poller_id'] = 2;
	$GLOBALS['rrd_next_step_fixture']['pushed'] = array();

	try {
		poller_push_data_to_main();
	} finally {
		$GLOBALS['config'] = $saved;
	}

	expect($GLOBALS['rrd_next_step_fixture']['pushed'])->toBe(array('host'));
});

test('full and per-device replication exclude rrd_next_step from poller_item updates', function () {
	$base = dirname(__DIR__, 4);

	expect(file_get_contents($base . '/lib/poller.php'))
		->toMatch("/replicate_out_table\\(\\\$rcnn_id, \\\$data, 'poller_item', [^;]*'rrd_next_step'/")
		->and(file_get_contents($base . '/lib/api_device.php'))
		->toMatch("/replicate_table_to_poller\\(\\\$rcnn_id, \\\$data, 'poller_item', array\\('rrd_next_step'\\)\\)/");
});
