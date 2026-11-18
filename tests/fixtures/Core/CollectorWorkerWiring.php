<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiCollectorWorkerWiring;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/include/global_constants.php';

const CACTI_CONNECTION = 'online';

$events        = [];
$workers       = [];
$available     = true;
$workerHealthy = true;
$reply         = '42';

function cacti_log(mixed $string, bool $output = false, string $environ = 'CMDPHP', mixed $level = ''): bool {
	global $events;
	$events[] = ['log', $string];

	return true;
}

function dsv_log(string $message, mixed $data = null, int $level = POLLER_VERBOSITY_LOW): void {
	// Per-source debug telemetry is outside this worker lifecycle contract.
}

function script_server_open(string $environment, int|string $poller_id, string $connection_mode = 'online', float $timeout = 0.0): array {
	global $events, $workers, $available;
	$events[] = ['open', $environment, $poller_id, $connection_mode, $timeout];

	if (!$available) {
		return [false, false, false];
	}
	$worker = tmpfile();

	if ($worker === false) {
		throw new \RuntimeException('Could not allocate the worker boundary handle.');
	}
	$workers[] = $worker;

	return [$worker, [], true];
}

function script_server_close(mixed &$process, array|false &$pipes, bool $graceful = true): void {
	global $events;
	$events[] = ['close'];

	if (is_resource($process)) {
		fclose($process);
	}
	$process = false;
	$pipes   = false;
}

function exec_poll_php(string $command, bool $using_proc_function, array|false $pipes, mixed $proc_fd, float $timeout = 0.0, bool &$worker_healthy = true): string {
	global $events, $workerHealthy, $reply;
	$events[]       = ['execute', $command, $timeout];
	$worker_healthy = $workerHealthy;

	return $reply;
}

function script_server_replace(mixed &$process, array|false &$pipes, bool &$using_process, string $environment, int|string $poller_id, string $connection_mode = 'online', float $timeout = 0.0): bool {
	global $events;
	$events[] = ['replace', $environment, $poller_id, $connection_mode, $timeout];

	return true;
}

/** Execute a complete unchanged top-level block with its original line numbers. */
function source_range(string $file, string $start, string $end, array $variables, string $prefix = ''): array {
	$file   = dirname(__DIR__, 3) . '/' . $file;
	$source = file_get_contents($file);
	$begin  = strpos($source, $start);
	$finish = $begin === false ? false : strpos($source, $end, $begin + strlen($start));

	if ($begin === false || $finish === false) {
		throw new \RuntimeException('The collector lifecycle block cannot be located.');
	}
	$line      = substr_count(substr($source, 0, $begin), "\n") + 1;
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-collector-block-');
	file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . $prefix
		. str_repeat("\n", $line - 1) . substr($source, $begin, $finish - $begin));
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);
	extract($variables, EXTR_SKIP);

	try {
		require $temporary;

		return get_defined_vars();
	} finally {
		unlink($temporary);
	}
}

foreach (['prepare_validate_result', 'is_hexadecimal', 'strip_alpha'] as $function) {
	\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/functions.php', $function, __NAMESPACE__);
}

$checks = 0;

try {
	foreach (['cmd', 'realtime'] as $environment) {
		foreach ([[0, true], [1, false], [3, true]] as [$calls, $available]) {
			$events    = [];
			$id        = $environment === 'cmd' ? '7' : str_repeat('a', 64);
			$variables = ['script_server_calls' => $calls, 'poller_id' => $id, 'script_timeout' => 2.5,
				'print_data_to_stdout'             => false, 'cactiphp' => false, 'pipes' => false, 'using_proc_function' => false];
			$ready = $calls > 0 && $available;

			if ($environment === 'cmd') {
				$result = source_range('cmd.php', 'if ($script_server_calls > 0) {', 'foreach ($poller_items as $item)', $variables);
			} else {
				$result = source_range('cmd_realtime.php', '$cactiphp = false;', '// all polled items need the same insert time', $variables);
			}
			$open         = array_values(array_filter($events, static fn ($event) => $event[0] === 'open'));
			$expectedOpen = $calls > 0 ? [['open', $environment, $environment === 'cmd' ? 7 : $id, 'online', 2.5]] : [];
			$warnings     = array_filter($events, static fn ($event) => $event[0] === 'log' && str_contains($event[1], 'WARNING: Unable to start PHP Script Server'));

			if ($result['using_proc_function'] !== $ready || is_resource($result['cactiphp']) !== $ready
				|| is_array($result['pipes']) !== $ready || $open !== $expectedOpen || ($calls > 0 && !$available && count($warnings) !== 1)) {
				throw new \RuntimeException('A collector must start at most one worker, retain its timeout and owner, and refuse a failed launch.');
			}
			$checks++;
			$events = [];

			if ($environment === 'cmd') {
				$result = source_range('cmd.php', 'if ($using_proc_function) {', '// take time and log performance data', $result);
			} else {
				$result = source_range('cmd_realtime.php', 'if (($using_proc_function == true) && ($script_server_calls > 0)) {', "\n}\n", $result);
			}

			if ($result['cactiphp'] !== false || $result['pipes'] !== false || $events !== ($ready ? [['close']] : [])) {
				throw new \RuntimeException('Collector completion must close exactly the worker that started.');
			}
			$checks++;
		}
	}

	foreach ([[true, true, "42\n", '42'], [true, true, 'not a number', 'U'], [true, false, 'U', 'U'], [false, true, '42', 'U']] as [$ready, $workerHealthy, $reply, $expected]) {
		$available        = true;
		[$worker, $pipes] = script_server_open('realtime', str_repeat('a', 64));
		$events           = [];
		$variables        = ['item'   => ['action' => POLLER_ACTION_SCRIPT_PHP, 'arg1' => '/scripts/example.php sample'],
			'using_proc_function'        => $ready, 'pipes' => $pipes, 'cactiphp' => $worker,
			'script_timeout'             => 2.5, 'poller_id' => str_repeat('a', 64)];
		$result              = source_range('cmd_realtime.php', 'case POLLER_ACTION_SCRIPT_PHP:', 'if (isset($output))', $variables, 'switch ($item["action"]) {');
		$executed            = array_values(array_filter($events, static fn ($event) => $event[0] === 'execute'));
		$replaced            = array_values(array_filter($events, static fn ($event) => $event[0] === 'replace'));
		$expectedReplacement = $ready && !$workerHealthy ? [['replace', 'realtime', str_repeat('a', 64), 'online', 2.5]] : [];

		if ($result['output'] !== $expected || count($executed) !== (int) $ready || $replaced !== $expectedReplacement) {
			throw new \RuntimeException('Realtime collection must validate replies and replace only a failed worker under the same owner and timeout.');
		}
		$checks++;
	}
	printf("PASS: %d collector worker wiring contracts\n", $checks);
} finally {
	foreach ($workers as $worker) {
		if (is_resource($worker)) {
			fclose($worker);
		}
	}
}
