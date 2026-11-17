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
$db = class_exists('Pdo\\Sqlite') ? new \Pdo\Sqlite('sqlite::memory:') : new \PDO('sqlite::memory:');
$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$create_function = method_exists($db, 'createFunction') ? 'createFunction' : 'sqliteCreateFunction';
$db->$create_function('UNIX_TIMESTAMP', static fn($value) => (int) $value);
$db->$create_function('CONCAT', static fn(...$values) => implode('', $values));
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
	return $GLOBALS['scenario'] === 'real-drain' ? \file_exists($path) : true;
}
function get_rrdtool_version() {
	return '1.8.0';
}
function rrdtool_execute($command, $log_to_stdout, $output_flag, $rrdtool_pipe, $logopt) {
	global $db, $scenario, $attempts, $fail, $inserted;
	if ($scenario === 'real-drain') {
		return \rrdtool_execute($command, $log_to_stdout, $output_flag, $rrdtool_pipe, $logopt);
	}
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

if ($scenario === 'real-drain') {
	// Exercise the CLI's actual initialization and drain loop with real RRDtool
	// acknowledgements. SQLite supplies only the queue, not simulated responses.
	require $root . '/lib/rrd.php';
	if (!defined('CACTI_LOCALE')) {
		define('CACTI_LOCALE', 'en-US');
	}
	$config['cacti_server_os'] = 'unix';
	$config['config_options_array']['path_rrdtool'] = getenv('RRDTOOL_BINARY');
	$directory = sys_get_temp_dir() . '/cacti-drain-' . bin2hex(random_bytes(8));
	mkdir($directory);
	$setup = \rrd_init(true, true);
	check(is_resource($setup), 'Cannot start RRDtool');
	try {
		for ($id = 1; $id <= $sources; $id++) {
			$file = $directory . '/fixture-' . $id . '.rrd';
			$response = \rrdtool_execute('create ' . escapeshellarg($file) . ' --start 1699999999 --step 1 DS:value:GAUGE:120:U:U RRA:AVERAGE:0.5:1:10', false, RRDTOOL_OUTPUT_RETURN_STDERR, $setup, 'TEST');
			check(str_starts_with($response, 'OK'), 'RRD setup failed');
			$db->prepare('UPDATE poller_item SET rrd_path = ? WHERE local_data_id = ?')->execute([$file, $id]);
		}
		\rrd_close($setup);
		$source = file_get_contents($root . '/cli/poller_output_empty.php');
		check((bool) preg_match('/\$start = microtime\(true\);.*?rrd_close\(\$rrdtool_pipe\);/s', $source, $match), 'Cannot find CLI drain loop');
		$temporary = tempnam(sys_get_temp_dir(), 'cacti-drain-source-');
		$line = substr_count(substr($source, 0, strpos($source, $match[0])), "\n");
		file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", $line) . $match[0]);
		$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($root . '/cli/poller_output_empty.php');
		// Bound a regression to the old write-only pipe rather than hanging CI.
		pcntl_async_signals(true);
		pcntl_signal(SIGALRM, static function () {
			throw new \RuntimeException('CLI failed to acknowledge and drain queued samples');
		});
		pcntl_alarm(10);
		ob_start();
		try {
			require $temporary;
			$output = ob_get_contents();
		} finally {
			ob_end_clean();
			pcntl_alarm(0);
			unlink($temporary);
		}
		check($rrds_processed === 6 && db_fetch_cell('SELECT COUNT(*) FROM poller_output') === 0, 'CLI failed to drain the queue');
		check(str_contains($output, 'There were 6 RRD updates'), 'CLI reported the wrong update count');
		foreach (glob($directory . '/*.rrd') as $file) {
			$command = [getenv('RRDTOOL_BINARY'), 'lastupdate', $file];
			$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
			$result = stream_get_contents($pipes[1]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			check(proc_close($process) === 0 && str_contains($result, '1700000003: 42'), 'Queued sample was deleted without reaching its RRD');
		}
	} finally {
		foreach (glob($directory . '/*') as $file) {
			unlink($file);
		}
		rmdir($directory);
	}
	echo json_encode(['scenario' => $scenario, 'written' => $rrds_processed, 'retained' => 0, 'ok' => true]), "\n";
	exit;
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
