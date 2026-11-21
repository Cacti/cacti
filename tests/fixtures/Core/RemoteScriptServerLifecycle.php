<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiRemoteScriptServerLifecycle;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/include/global_constants.php';

const CACTI_CONNECTION = 'online';

$request   = [];
$events    = [];
$available = true;
$failed    = false;
$db        = new \PDO('sqlite::memory:');
$db->exec('CREATE TABLE poller_item (host_id INTEGER, local_data_id INTEGER, action INTEGER, arg1 TEXT, rrd_name TEXT)');
$insert = $db->prepare('INSERT INTO poller_item VALUES (?, ?, ?, ?, ?)');
$insert->execute([7, 11, POLLER_ACTION_SCRIPT_PHP, '/script.php sample', 'a']);
$insert->execute([7, 12, POLLER_ACTION_SCRIPT_PHP, '/script.php sample', 'b']);

function gnrv($name): mixed {
	global $request;

	return $request[$name] ?? null;
}

function gfrv($name): mixed {
	return gnrv($name);
}

function read_config_option($name, $force = false): mixed {
	return $name === 'script_timeout' ? '2.5' : '';
}

function input_validate_input_number($value, $name = 'value'): void {
	if (filter_var($value, FILTER_VALIDATE_INT) === false) {
		throw new \RuntimeException('The poller item id must be numeric.');
	}
}

function db_fetch_assoc_prepared($sql, $params = [], $cache = true, $db_conn = false): array {
	global $db;
	$statement = $db->prepare($sql);
	$statement->execute($params);

	return $statement->fetchAll(\PDO::FETCH_ASSOC);
}

function cacti_log($message, $output = false, $environ = 'SYSTEM', $level = ''): void {
	global $events;
	$events[] = ['log', $message];
}

function script_server_open(string $environment, int|string $poller_id, string $connection_mode = 'online', float $timeout = 0.0): array {
	global $events, $available;
	$events[] = ['open', $environment, $poller_id, $connection_mode, $timeout];

	return [false, $available ? [] : false, $available];
}

function exec_poll_php(string $command, bool $using_proc_function, array|false $pipes, mixed $proc_fd, float $timeout = 0.0, bool &$worker_healthy = true): string {
	global $events, $failed;
	$events[]       = ['execute', $command, $timeout];
	$worker_healthy = !$failed;

	return $failed ? 'U' : "42\n";
}

function script_server_replace(mixed &$process, array|false &$pipes, bool &$using_process, string $environment, int|string $poller_id, string $connection_mode = 'online', float $timeout = 0.0): bool {
	global $events, $failed;
	$events[] = ['replace', $environment, $poller_id, $connection_mode, $timeout];
	$failed   = false;

	return true;
}

function script_server_close(mixed &$process, array|false &$pipes, bool $send_quit = true): void {
	global $events;
	$events[] = ['close'];
}

function prepare_validate_result($value): bool {
	return is_numeric($value) || $value === 'U';
}

\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/functions.php', 'cacti_sizeof', __NAMESPACE__);
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/remote_agent.php', 'poll_for_data', __NAMESPACE__);

foreach ([['7', true, false, ['42', '42']], [str_repeat('a', 64), true, true, ['U', '42']], ['7', false, false, ['U', 'U']]] as [$poller_id, $available, $failed, $expected]) {
	$request = ['local_data_ids' => [11, 12], 'host_id' => 7, 'poller_id' => $poller_id];
	$events  = [];
	ob_start();
	$result                = poll_for_data();
	$data                  = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
	$open                  = array_values(array_filter($events, static fn ($event) => $event[0] === 'open'));
	$replace               = array_values(array_filter($events, static fn ($event) => $event[0] === 'replace'));
	$expected_replacements = $expected === ['U', '42'] ? 1 : 0;

	if ($result !== false || array_column($data, 'value') !== $expected || array_column($data, 'local_data_id') !== [11, 12]
		|| $open !== [['open', 'realtime', $poller_id, 'online', 2.5]] || count($replace) !== $expected_replacements || end($events) !== ['close']) {
		throw new \RuntimeException('Remote poller must reuse one start attempt, recover failed workers, and close the worker.');
	}
}

foreach ([[], '0', '../7'] as $invalid) {
	$request['poller_id'] = $invalid;
	$events               = [];

	if (poll_for_data() !== [] || $events) {
		throw new \RuntimeException('An invalid poller id must not start a worker.');
	}
}

print "PASS: 6 remote Script Server lifecycle contracts\n";
