<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
$root     = getenv('CACTI_BOOST_SOURCE_ROOT') ?: dirname(__DIR__, 2);
$scenario = $argv[1] ?? 'first';
$writes   = $deletes = 0;
require dirname(__DIR__) . '/Helpers/FixtureCoverage.php';
require __DIR__ . '/ProbeLoader.php';

function db_fetch_assoc_prepared($sql, $params = []) {
	if (!str_contains($sql, 'AS po')) {
		return [];
	}
	$rows = [];

	for ($i = 0; $i < 4; $i++) {
		$rows[] = ['local_data_id'=>1, 'data_template_id'=>1, 'timestamp'=>1700000000 + $i * 60, 'rrd_name'=>'in', 'output'=>'42'];
	}

	return $rows;
}
function db_fetch_cell_prepared(...$args) {
	return 0;
}
function db_affected_rows() {
	return 0;
}
function db_fetch_cell(...$args) {
	return 0;
}
function db_execute($sql, ...$args) {
	if ($GLOBALS['scenario'] === 'ack-create' && str_starts_with($sql, 'CREATE TEMPORARY TABLE `poller_output_boost_acked_')) {
		return false;
	}

	return !($GLOBALS['scenario'] === 'temp-create' && str_starts_with($sql, 'CREATE TEMPORARY TABLE'));
}
function db_execute_prepared(...$args) {
	if ($GLOBALS['scenario'] === 'archive-copy' && str_starts_with($args[0], 'INSERT IGNORE INTO')) {
		return false;
	}

	if ($GLOBALS['scenario'] === 'ack-insert' && str_contains($args[0], 'poller_output_boost_acked_') && str_starts_with($args[0], 'INSERT IGNORE INTO')) {
		return false;
	}

	if ($GLOBALS['scenario'] === 'ack-delete' && str_starts_with($args[0], 'DELETE')) {
		return false;
	}

	if (str_starts_with($args[0], 'DELETE')) {
		$GLOBALS['deletes']++;
	}

	return true;
}
function boost_get_arch_table_names(...$args) {
	return in_array($GLOBALS['scenario'], ['archive-copy', 'temp-create', 'archive-ok'], true) ? ['poller_output_boost_arch_1'] : [];
}
function boost_get_unused_data_source_names(...$args) {
	return [];
}
function boost_get_rrd_filename_and_template(...$args) {
	return ['rrd_path'=>'source.rrd', 'rrd_template'=>'in'];
}
function boost_rrdtool_function_update(...$args) {
	$call = ++$GLOBALS['writes'];

	// An error that merely contains OK (here in the RRD file name) must still
	// count as a rejection, so the acknowledgement test is an exact match.
	if ($GLOBALS['scenario'] === 'ok-substring') {
		return $call === 1 ? "ERROR: opening '/var/rra/BOOK_7.rrd': Permission denied" : 'OK';
	}

	// The final flush after the last timestamp is the fourth write.
	return $call === (['first' => 1, 'middle' => 2, 'tail' => 4][$GLOBALS['scenario']] ?? 0) ? 'ERROR: rejected' : 'OK';
}
function read_config_option($key) {
	return $key === 'boost_rrd_update_string_length' ? '1' : '100';
}
function boost_error_handler(...$args) {
	return false;
}
function cacti_system_zone_set() {
}
function cacti_log(...$args) {
}
function boost_timer(...$args) {
}
function rrd_init(...$args) {
	return [];
}
function rrd_close(...$args) {
}
function get_rrdtool_version(...$args) {
	return '1.8';
}
function cacti_version_compare($a,$b,$op) {
	return version_compare($a,$b,$op);
}

foreach (['BOOST_TIMER_START'=>0, 'BOOST_TIMER_END'=>1, 'POLLER_VERBOSITY_MEDIUM'=>3, 'POLLER_VERBOSITY_HIGH'=>4] as $name=>$value) {
	define($name,$value);
}
define('CACTI_PATH_LIBRARY',__DIR__ . '/../performance/fixtures');

foreach (['cacti_sizeof', 'cacti_count', 'array_rekey'] as $name) {
	probe_load($root . '/lib/functions.php',$name);
}
probe_load($root . '/lib/boost.php','boost_process_poller_output');
probe_load($root . '/lib/boost.php','boost_delete_output_keys');
$config     = ['library_path'=>CACTI_PATH_LIBRARY];
$get_memory = false;
// Acknowledging nothing succeeds without touching the database; the drain
// itself never calls it with an empty result.
$result = $scenario === 'ack-empty' ? (boost_delete_output_keys(1, []) ? 1 : -2) : boost_process_poller_output(1);
print json_encode(['result'=>$result, 'writes'=>$writes, 'deletes'=>$deletes],JSON_THROW_ON_ERROR);
