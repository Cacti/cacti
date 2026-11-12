<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
// Drives the bulk Boost drain over two data sources with four timestamps each.
// The update string length of 1 forces a flush on every timestamp change, so a
// rejection on the scenario's write lands in the middle of data source 1.
$root     = getenv('CACTI_BOOST_SOURCE_ROOT') ?: dirname(__DIR__, 2);
$scenario = $argv[1] ?? 'none';
$writes   = [];
$deletes  = 0;
$parked   = [];
require dirname(__DIR__) . '/Helpers/FixtureCoverage.php';
require __DIR__ . '/ProbeLoader.php';

/**
 * Return the archive rows in the drain's local_data_id, time order.
 *
 * @param string $sql the archive select
 *
 * @return array<int, array<string, int|string>> the fetched rows
 */
function db_fetch_assoc($sql) {
	$rows = [];

	foreach ([1, 2] as $id) {
		for ($i = 0; $i < 4; $i++) {
			$rows[] = ['local_data_id'=>$id, 'data_template_id'=>0, 'timestamp'=>(string) (1700000000 + $i * 60), 'rrd_name'=>'in', 'output'=>'42'];
		}
	}

	return $rows;
}

/**
 * Record each RRD update and reject the one the scenario names.
 *
 * @param int    $id     the data source being written
 * @param string $path   the RRD path
 * @param string $tmpl   the update template
 * @param string $values the update values
 * @param mixed  $pipe   the RRDtool pipe, unused
 *
 * @return string OK, or an error for the rejected write
 */
function boost_rrdtool_function_update($id, $path, $tmpl, &$values, $pipe = null) {
	$GLOBALS['writes'][] = $id . '@' . $values;

	return count($GLOBALS['writes']) === (['middle' => 2, 'boundary' => 4, 'tail' => 8][$GLOBALS['scenario']] ?? 0) ? 'ERROR: rejected' : 'OK';
}

/**
 * Count the shard acknowledgement.
 *
 * @param string $sql the statement
 *
 * @return bool always true
 */
function db_execute_prepared($sql, ...$args) {
	if (str_starts_with($sql, 'DELETE')) {
		$GLOBALS['deletes']++;
	}

	if (preg_match('/SET process_handler = 0.*local_data_id IN \(([0-9,]+)\)/s', $sql, $m)) {
		$GLOBALS['parked'] = array_merge($GLOBALS['parked'], array_map('intval', explode(',', $m[1])));
	}

	return true;
}

/** @return array<int, string> one archive table */
function boost_get_arch_table_names(...$args) {
	return ['poller_output_boost_arch_1'];
}

/** @return string a placeholder select */
function boost_archive_select_sql(...$args) {
	return 'SELECT 1';
}

/** @return array<int, mixed> no field mappings */
function db_fetch_assoc_prepared(...$args) {
	return [];
}

/** @return array<string, string> no unused data source names */
function poller_get_unused_data_source_names(...$args) {
	return [];
}

/** @return array<string, string> the single field name */
function poller_get_nt_rrd_field_names(...$args) {
	return ['in'=>'in'];
}

/** @return array{rrd_path: string, rrd_template: string} the RRD for any data source */
function boost_get_rrd_filename_and_template(...$args) {
	return ['rrd_path'=>'source.rrd', 'rrd_template'=>'in'];
}

/** @return string a release above the 1.5 floor */
function get_rrdtool_version(...$args) {
	return '1.8.0';
}

/** @return string the same release as installed */
function get_installed_rrdtool_version(...$args) {
	return '1.8.0';
}

/** @return bool a version comparison */
function cacti_version_compare($a, $b, $op) {
	return version_compare($a, $b, $op);
}

/** @return string a tiny update string length so every timestamp flushes */
function read_config_option($key) {
	return $key === 'boost_rrd_update_string_length' ? '1' : '100';
}

/** @return bool the error handler result */
function boost_error_handler(...$args) {
	return false;
}

/** No-op stand-in for a Cacti runtime function the drain calls. */
function boost_timer(...$args) {
}

/** No-op stand-in for a Cacti runtime function the drain calls. */
function boost_debug(...$args) {
}

/** No-op stand-in for a Cacti runtime function the drain calls. */
function cacti_log(...$args) {
}

/** No-op stand-in for a Cacti runtime function the drain calls. */
function set_config_option(...$args) {
}

foreach (['BOOST_TIMER_START'=>0, 'BOOST_TIMER_END'=>1, 'POLLER_VERBOSITY_HIGH'=>4, 'SQL_NO_CACHE'=>''] as $name=>$value) {
	define($name, $value);
}
define('CACTI_PATH_LIBRARY', __DIR__ . '/../performance/fixtures');

foreach (['cacti_sizeof', 'cacti_count', 'array_rekey'] as $name) {
	probe_load($root . '/lib/functions.php', $name);
}

foreach (['boost_process_local_data_ids', 'boost_defer_failed_data_sources', 'boost_process_output'] as $name) {
	probe_load($root . '/poller_boost.php', $name);
}

$archive_table = 'poller_output_boost_arch_1';
$get_memory    = false;
$result        = boost_process_local_data_ids(2, 0, null);
print json_encode(['result'=>$result, 'writes'=>$writes, 'deletes'=>$deletes, 'parked'=>$parked], JSON_THROW_ON_ERROR);
