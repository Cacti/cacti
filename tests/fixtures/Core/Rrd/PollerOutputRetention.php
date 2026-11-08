<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiPollerRetentionFixture;

require dirname(__DIR__, 3) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 4);
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';
$config = ['library_path' => $root . '/lib', 'is_web' => false, 'config_options_array' => ['storage_location' => 0]];
$debug = false;
$db = new \PDO('sqlite::memory:');
$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('UNIX_TIMESTAMP', static fn($value) => (int) $value);
$db->sqliteCreateFunction('CONCAT', static fn(...$values) => implode('', $values));
$scenario = $argv[1];
$attempts = [];
$fail = true;
$inserted = false;
$deletes = 0;

function db_fetch_assoc_prepared($sql, $params = [], $log = true, $db_conn = false) {
	global $db;
	// SQLite's DISTINCT aggregate accepts one expression; retain the two-column grouping.
	$sql = str_replace('COUNT(DISTINCT po.local_data_id, po.rrd_name)', "COUNT(DISTINCT po.local_data_id || ':' || po.rrd_name)", $sql);
	$query = $db->prepare($sql);
	$query->execute($params);

	return $query->fetchAll(\PDO::FETCH_ASSOC);
}
function db_fetch_assoc($sql, $log = true, $db_conn = false) {
	return db_fetch_assoc_prepared($sql, [], $log, $db_conn);
}
function db_fetch_cell($sql, $col_name = '', $log = true, $db_conn = false) {
	$rows = db_fetch_assoc($sql, $log, $db_conn);

	return $rows ? reset($rows[0]) : false;
}
function db_execute_prepared($sql, $params = [], $log = true, $db_conn = false) {
	global $db, $deletes, $scenario, $fail;
	$deletes++;

	return in_array($scenario, ['delete-failure', 'batch-delete-failure'], true) && $fail ? false : $db->prepare($sql)->execute($params);
}
function db_execute($sql, $log = true, $db_conn = false) {
	return db_execute_prepared($sql, [], $log, $db_conn);
}
function cacti_log($message, $echo = false, $subsystem = 'SYSTEM', $verbosity = 2) {
}
function dsstats_poller_output(&$data) {
}
function dsdebug_poller_output(&$data) {
}
function api_plugin_hook_function($name, $data) {
	return $data;
}
function boost_poller_on_demand(&$results) {
	return $GLOBALS['scenario'] !== 'boost';
}
function file_exists($path) {
	return true;
}
function get_rrdtool_version() {
	return '1.8.0';
}
function rrdtool_execute($command, $log_to_stdout, $output_flag, $rrdtool_pipe, $logopt) {
	global $db, $scenario, $attempts, $fail, $inserted;
	$attempts[] = $command;
	if ($scenario === 'concurrent' && !$inserted) {
		$db->exec("INSERT INTO poller_output VALUES (1, 'value', 1700000004, '44')");
		$inserted = true;
	}
	if ($fail && str_contains($command, 'fixture-1.rrd')) {
		if ($scenario === 'write-failure') {
			return 'ERROR: fixture write failure';
		} elseif ($scenario === 'empty-response') {
			return '';
		} elseif ($scenario === 'false-response') {
			return false;
		} elseif ($scenario === 'mixed-response') {
			return "ERROR: fixture write failure\nOK";
		}
	}

	return "OK u:0.00 s:0.00 r:0.00\n";
}
function check($condition, $message) {
	if (!$condition) {
		throw new \RuntimeException($message);
	}
}

cacti_fixture_source_function($root . '/lib/poller.php', 'process_poller_output', __NAMESPACE__);
cacti_fixture_source_function($root . '/lib/rrd.php', 'rrdtool_function_update', __NAMESPACE__);
$db->exec('CREATE TABLE poller_output (local_data_id INTEGER, rrd_name TEXT, time INTEGER, output TEXT, PRIMARY KEY (local_data_id, rrd_name, time))');
$db->exec('CREATE TABLE poller_item (local_data_id INTEGER, rrd_name TEXT, rrd_path TEXT, rrd_num INTEGER)');
$db->exec('CREATE TABLE data_local (id INTEGER PRIMARY KEY, data_template_id INTEGER, host_id INTEGER DEFAULT 7)');
$db->exec('CREATE TABLE data_template (id INTEGER PRIMARY KEY, name TEXT)');
$db->exec("INSERT INTO data_template VALUES (0, 'Fixture')");
$db->exec('CREATE TABLE poller_data_template_field_mappings (data_template_id INTEGER, data_name TEXT, data_source_names TEXT)');
$db->exec('CREATE TABLE poller_time (end_time TEXT)');
$db->prepare('INSERT INTO poller_time VALUES (?)')->execute([$scenario === 'housekeeping' ? '2026-11-07' : '0000-00-00']);
$sources = in_array($scenario, ['batch', 'drain'], true) ? 13334 : ($scenario === 'batch-delete-failure' ? 334 : 2);
$insert = $db->prepare("INSERT INTO poller_output VALUES (?, 'value', ?, '42')");
$item = $db->prepare("INSERT INTO poller_item VALUES (?, 'value', ?, ?)");
$local = $db->prepare('INSERT INTO data_local (id, data_template_id) VALUES (?, 0)');
for ($id = 1; $id <= $sources; $id++) {
	$item->execute([$id, 'fixture-' . $id . '.rrd', in_array($scenario, ['incomplete', 'housekeeping'], true) ? 2 : 1]);
	$local->execute([$id]);
	for ($time = 1; $time <= 3; $time++) {
		$insert->execute([$id, 1700000000 + $time]);
	}
}

$pipe = false;
// A single batch lets the test inspect exactly what remains across the LIMIT boundary.
$written = process_poller_output($pipe, in_array($scenario, ['batch', 'concurrent'], true) ? 1 : 0);
$remaining = (int) db_fetch_cell('SELECT COUNT(*) FROM poller_output');
$failed_response = in_array($scenario, ['write-failure', 'empty-response', 'false-response', 'mixed-response'], true);
if ($scenario === 'batch') {
	check($written === 40000 && $remaining === 2 && $deletes === 40, 'Batch cleanup removed unread samples or was not bounded');
} elseif ($scenario === 'drain') {
	check($written === 40002 && $remaining === 0 && $deletes === 41, 'Backlog draining lost samples or stopped making progress');
} elseif ($failed_response) {
	check($written === 3 && $remaining === 3, 'An unacknowledged RRD lost samples');
	check(count($attempts) === 4, 'The writer advanced an RRD past its failed timestamp');
} elseif ($scenario === 'concurrent') {
	check($written === 6 && $remaining === 1, 'Cleanup removed a concurrent sample');
} elseif ($scenario === 'incomplete') {
	check($written === 0 && $remaining === 6 && !$attempts, 'Incomplete data was written or removed');
} elseif ($scenario === 'housekeeping') {
	check($written === 0 && $remaining === 0 && !$attempts, 'Idle housekeeping failed to remove mismatched sources');
} elseif ($scenario === 'boost') {
	check($written === 0 && $remaining === 0 && !$attempts, 'Boost handoff performed a direct write or retained completed rows');
} elseif ($scenario === 'delete-failure' || $scenario === 'batch-delete-failure') {
	check($written === $sources * 3 && $remaining === $sources * 3, 'A failed queue delete lost records');
}

$first_remaining = $remaining;
$fail = false;
if ($scenario !== 'incomplete') {
	$retried = process_poller_output($pipe, 1);
	check($retried === $remaining && db_fetch_cell('SELECT COUNT(*) FROM poller_output') == 0, 'Retained samples did not drain on retry');
}
echo json_encode(['scenario' => $scenario, 'written' => $written, 'retained' => $first_remaining, 'ok' => true], JSON_THROW_ON_ERROR), "\n";
