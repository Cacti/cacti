<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiScriptServerStreamFailures;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/include/global_constants.php';

// Source slices retain unqualified catch types from the global source scope.
class_alias(\Throwable::class, __NAMESPACE__ . '\\Throwable');

// The stream boundary supplies deterministic interrupted/unselectable pipe
// results. The unchanged production framing loop must still preserve requests,
// stop at its deadline, and restore the original blocking mode.
$state = [];

function stream_get_meta_data($stream): array {
	return ['blocked' => true];
}

function stream_set_blocking($stream, bool $enable): bool {
	global $state;
	$state['blocking'][] = $enable;

	return $state['can_block'];
}

function fgets($stream, ?int $length = null): string|false {
	global $state;

	if (!$state['chunks']) {
		throw new \RuntimeException('The reader exhausted the expected stream operations.');
	}

	return array_shift($state['chunks']);
}

function feof($stream): bool {
	return false;
}

function hrtime(bool $as_number = false): array|int|float|false {
	global $state;
	$state['clock'] += $state['tick'];

	return $state['clock'];
}

function stream_select(?array &$read, ?array &$write, ?array &$except, ?int $seconds, ?int $microseconds = null): int|false {
	global $state;
	$result = array_shift($state['ready']);

	if ($result instanceof \Throwable) {
		throw $result;
	}

	return $result;
}

function usleep(int $microseconds): void {
	global $state;

	if ($microseconds < 1 || $microseconds > 10000) {
		throw new \RuntimeException('Pipe retry sleep must remain bounded.');
	}
	$state['sleeps']++;
}

foreach (['script_server_read_request', 'script_server_read_request_blocking', 'script_server_drain_line', 'script_server_split_request'] as $function) {
	\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/script_server.php', $function, __NAMESPACE__);
}

$cases = [
	'blocking fallback'        => [false, ["ok\n"], [], 1, ["ok\n", false, true], 0],
	'unselectable idle pipe'   => [true, [false, "ok\n"], [false], 1, ["ok\n", false, true], 1],
	'idle timeout retries'     => [true, [false, "ok\n"], [0], 1, ["ok\n", false, true], 1],
	'interrupted partial read' => [true, ['part', false, "ial\n"], [new \ValueError('unselectable pipe')], 1, ["partial\n", false, true], 1],
	'partial read deadline'    => [true, ['part', false], [], 1000000000, ['part', true, false], 0],
];

foreach ($cases as $name => [$can_block, $chunks, $ready, $tick, $expected, $sleeps]) {
	$state  = compact('can_block', 'chunks', 'ready', 'tick') + ['clock' => 0, 'sleeps' => 0, 'blocking' => []];
	$actual = script_server_read_request(null, SCRIPT_SERVER_MAX_REQUEST_LENGTH, 0.5);

	if ($actual !== $expected || $state['sleeps'] !== $sleeps || ($can_block && end($state['blocking']) !== true)) {
		throw new \RuntimeException('Script Server stream failure: ' . $name);
	}
}

$state = ['can_block' => true, 'clock' => 0, 'tick' => 1000000000, 'blocking' => [], 'chunks' => [], 'ready' => []];

if (script_server_drain_line(null, 0.5) !== false || end($state['blocking']) !== true) {
	throw new \RuntimeException('Expired draining must stop before another read and restore blocking.');
}

if (script_server_split_request('/script.php ') !== ['/script.php', '', '']) {
	throw new \RuntimeException('A missing function must remain an empty token.');
}

printf("PASS: %d Script Server stream failure contracts\n", count($cases) + 2);
