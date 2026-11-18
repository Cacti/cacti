<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiScriptServerLaunchFailures;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/include/global_constants.php';

const CACTI_SERVER_OS = 'win32';
const CACTI_PATH_BASE = __DIR__;

$mode     = '';
$closed   = false;
$launches = [];
$messages = [];
$stderr   = null;
$deferred = false;

function cacti_log(mixed $string, bool $output = false, string $environ = 'CMDPHP', mixed $level = ''): bool {
	global $messages;
	$messages[] = $string;

	return true;
}

function function_exists(string $function): bool {
	global $mode;

	return $function === 'proc_open' && $mode === 'disabled' ? false : \function_exists($function);
}

function read_config_option($name, $force = false): mixed {
	return $name === 'path_php_binary' ? PHP_BINARY : '';
}

function proc_open(array|string $command, array $descriptor_spec, ?array &$pipes, ?string $cwd = null, ?array $env_vars = null, ?array $options = null): mixed {
	global $mode, $launches, $stderr;
	$launches[] = $command;
	$pipes      = [];

	if ($mode === 'tail') {
		$process = \proc_open($command, $descriptor_spec, $pipes, $cwd, $env_vars, $options);

		if (!is_resource($process)) {
			throw new \RuntimeException('Could not launch the real stderr boundary fixture.');
		}
		$stderr   = $pipes[2];
		$deadline = hrtime(true) + 3000000000;

		do {
			$status = \proc_get_status($process);

			if (!$status['running']) {
				return $process;
			}
			usleep(1000);
		} while (hrtime(true) < $deadline);

		\proc_terminate($process, 9);
		\proc_close($process);

		throw new \RuntimeException('The stderr boundary fixture did not exit.');
	}

	return $mode === 'bad-banner' ? fopen('php://memory', 'w+') : false;
}

function stream_get_contents($stream, ?int $length = null, int $offset = -1): string|false {
	global $mode, $stderr, $deferred;

	// Model a non-blocking read that sees no bytes immediately before the
	// process exits; the final read must still retain and redact its stderr.
	if ($mode === 'tail' && $stream === $stderr && !$deferred) {
		$deferred = true;

		return '';
	}

	return \stream_get_contents($stream, $length, $offset);
}

function script_server_read_response(array $pipes, mixed $process, bool &$worker_healthy, float $timeout = 0.0): string|false {
	return "unexpected banner\n";
}

function script_server_close(mixed &$process, array|false &$pipes, bool $graceful = true): void {
	global $closed;
	$closed = !$graceful;
	fclose($process);
	$process = false;
	$pipes   = false;
}

foreach (['script_server_process_descriptors', 'script_server_open', 'script_server_terminate', 'poller_php_binary', 'exec_with_timeout'] as $function) {
	\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/poller.php', $function, __NAMESPACE__);
}

foreach (['disabled', 'failed', 'bad-banner'] as $mode) {
	$closed   = false;
	$launches = [];

	if (script_server_open('cmd', 7, 'online', 0.5) !== [false, false, false] || ($mode === 'disabled' && $launches)
		|| ($mode === 'bad-banner' && !$closed)) {
		throw new \RuntimeException('A worker launch failure must return no usable process or pipes and close an invalid worker.');
	}
}

foreach (['disabled', 'failed'] as $mode) {
	$output = [];
	$code   = 0;

	if (exec_with_timeout([PHP_BINARY, '-v'], $output, $code) !== false || $code !== 1 || $output) {
		throw new \RuntimeException('A disabled or failed process launch must fail without output.');
	}
}

// An unavailable process must be harmless during replacement cleanup.
script_server_terminate(false);
$mode   = 'tail';
$output = [];
$code   = 0;
$result = exec_with_timeout([PHP_BINARY, '-r', 'fwrite(STDERR, "private marker"); exit(3);'], $output, $code, 5, 0,
	static fn (string $message): string => str_replace('private marker', '<redacted>', $message));

if ($result !== null || $output || $code !== 3 || count($messages) !== 1 || !str_contains($messages[0], '<redacted>')
	|| str_contains($messages[0], 'private marker')) {
	throw new \RuntimeException('Final stderr must be retained and redacted after the child exits.');
}

print "PASS: 7 Script Server launch failure contracts\n";
