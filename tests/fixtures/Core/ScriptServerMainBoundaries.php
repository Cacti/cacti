<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiScriptServerMainBoundaries;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);
$mode = $argv[1] ?? 'parent';

if ($mode === 'parent') {
	foreach (['startup', 'load', 'dispatch', 'framing', 'debug'] as $case) {
		$process = proc_open([PHP_BINARY, __FILE__, $case], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

		if (!is_resource($process)) {
			throw new \RuntimeException('Could not start the isolated Script Server boundary contract.');
		}
		fclose($pipes[0]);
		$output = stream_get_contents($pipes[1]);
		$error  = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$status = proc_close($process);

		if ($status !== ($case === 'debug' ? 0 : 1) || $output !== ($case === 'debug' ? "42\n" : '') || $error !== 'PASS: ' . $case . "\n") {
			throw new \RuntimeException('Script Server boundary failed: ' . $case . ': ' . $error);
		}
	}
	print "PASS: 5 Script Server main boundary contracts\n";

	return;
}

$events      = [];
$temporary   = null;
$includeFile = tempnam(sys_get_temp_dir(), 'cacti-main-include-');
file_put_contents($includeFile, '<?php /* Loading this fixture deliberately defines no callable. */');
$GLOBALS['cacti_fixture_contract'] = ['name' => 'script-server-main-' . $mode, 'complete' => false, 'assertions' => 0,
	'producers'                                 => ['script_server.php']];

// Validate terminal branches before the collector is allowed to publish them.
register_shutdown_function(static function () use ($mode, &$events, &$temporary, $includeFile): void {
	try {
		$expected = match ($mode) {
			'startup'  => ['error', 'close'],
			'framing'  => ['error', 'error', 'close'],
			'load'     => ['request', 'reset', 'error', 'close'],
			'dispatch' => ['request', 'run', 'reset', 'error', 'close'],
			'debug'    => ['request', 'run', 'reset', 'response'],
			default    => throw new \RuntimeException('Unknown main boundary mode.'),
		};

		if ($events !== $expected) {
			throw new \RuntimeException('The Script Server must close only on guard failure and log a healthy response only after framing it.');
		}
		$GLOBALS['cacti_fixture_contract']['assertions'] = 1;
		$GLOBALS['cacti_fixture_contract']['complete']   = true;
		fwrite(STDERR, 'PASS: ' . $mode . "\n");
	} finally {
		if (is_string($temporary) && is_file($temporary)) {
			unlink($temporary);
		}
		unlink($includeFile);
	}
});
require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once $root . '/include/global_constants.php';

function cacti_log(mixed $string, bool $output = false, string $environ = 'CMDPHP', mixed $level = ''): bool {
	global $events;
	$events[] = 'error';

	if (!str_contains($string, 'output guard') && !str_contains($string, 'output buffering') && !str_contains($string, 'request exceeds') && !str_contains($string, 'request framing')) {
		throw new \RuntimeException('Only the expected output guard failure may be logged.');
	}

	return true;
}
function db_close(mixed &$db_conn = false): bool {
	global $events;
	$events[] = 'close';

	return true;
}
function script_server_validate_request(string $request, array $roots): array|false {
	global $includeFile;

	return [$includeFile, 'strval', ['argument']];
}
function function_exists(string $function): bool {
	global $mode;

	return $mode === 'load' ? false : \function_exists($function);
}
function script_server_reset_output_guard(int $guard_level): bool {
	global $events, $mode;
	$events[] = 'reset';

	return $mode === 'debug';
}
function script_server_run(string $function, array $parameters, string $include_file, bool &$response_healthy = true, bool $canonical_include = false): string {
	global $events, $includeFile;

	if ($function !== 'strval' || $parameters !== ['argument'] || $include_file !== $includeFile || !$canonical_include) {
		throw new \RuntimeException('Dispatch must use the validated function, arguments, and canonical path.');
	}
	$events[] = 'run';

	return '42';
}
function script_server_log_request(int $pid, int $ctr, string $include_file, string $function, array $parameter_array, ?bool $enabled = null): void {
	global $events;

	if ($pid !== 17 || $ctr !== 0 || $enabled !== true) {
		throw new \RuntimeException('Request debug context was not preserved.');
	}
	$events[] = 'request';
}
function script_server_log_response(int $pid, int $ctr, mixed $result, ?bool $enabled = null): void {
	global $events;

	if ($pid !== 17 || $ctr !== 0 || $result !== '42' || $enabled !== true) {
		throw new \RuntimeException('Response debug context was not preserved.');
	}
	$events[] = 'response';
}

// Select an entire unchanged if block. Tokenization ignores braces in strings
// and comments, preserving the worker's original control flow and source lines.
$source = file_get_contents($root . '/script_server.php');
$marker = match ($mode) {
	'startup' => 'if ($output_guard_level === 0) {',
	'framing' => 'if ($overlong) {',
	default   => 'if ($input_string != \'\') {'
};
$begin  = strpos($source, $marker);

if ($begin === false) {
	throw new \RuntimeException('Could not locate the main boundary.');
}
$tokens  = token_get_all('<?php ' . substr($source, $begin));
$offset  = -6;
$depth   = 0;
$started = false;

foreach ($tokens as $token) {
	$text = is_array($token) ? $token[1] : $token;
	$offset += strlen($text);

	if ($token === '{') {
		$depth++;
		$started = true;
	}

	if ($token === '}') {
		$depth--;

		if ($started && $depth === 0) {
			break;
		}
	}
}
$line      = substr_count(substr($source, 0, $begin), "\n") + 1;
$temporary = tempnam(sys_get_temp_dir(), 'cacti-main-boundary-');
file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . '; while (true) {' . str_repeat("\n", $line - 1)
	. substr($source, $begin, $offset) . ' break; }');
$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($root . '/script_server.php');
$output_guard_level                              = $mode === 'startup' ? 0 : 1;
$input_string                                    = 'fixture request';
$allowed_roots                                   = [];
$debug_logging                                   = true;
$failed_includes                                 = [];
$pid                                             = 17;
$ctr                                             = 0;
$overlong                                        = true;
$framing_healthy                                 = false;
require $temporary;
