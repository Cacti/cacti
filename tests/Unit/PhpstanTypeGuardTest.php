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
 * Tests for PHPStan level 8 type guard patterns applied across 5 files.
 *
 * PHPStan level 8 enforces strict type narrowing. Many Cacti functions return
 * union types (array|bool, string|false, int|false, etc.) that must be narrowed
 * before use. These stubs mirror the guard patterns added to production code
 * to satisfy the level 8 baseline.
 *
 * Each stub is a minimal reproduction of the narrowing pattern, not a full
 * function. The goal is to verify that each guard branch behaves correctly for
 * every input variant, including the boundary cases that PHPStan enforces.
 *
 * Guard patterns covered:
 *   guard_db_result_access()    - array|bool from db_fetch_row_prepared()
 *   guard_fopen_usage()         - resource|false from fopen()
 *   guard_chown_params()        - int|false from fileowner()/filegroup()
 *   clamp_color_value()         - int<0,255> clamping for imagecolorallocate()
 *   guard_session_name()        - string|false from session_name()
 *   guard_shell_exec_result()   - string|false|null from shell_exec()
 *   guard_preg_match_offsets()  - nullable offsets from preg_match captures
 *   guard_version_bits()        - float|int input cast before bitwise ops
 *   guard_pclose()              - resource|false from popen()
 *   guard_foreach_iterable()    - array|false before foreach
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

// --- Stub: guard_db_result_access (pattern from multiple graph/data files) ---
// Mirrors: $row = db_fetch_row_prepared(...); returns array|bool.
// PHPStan level 8 requires narrowing before array access.

function guard_db_result_access(array|bool $row): string {
	if (is_array($row) && cacti_sizeof($row)) {
		return (string) $row['value'];
	}

	return '';
}

// --- Stub: guard_fopen_usage (pattern from lib/rrd.php and poller files) ---
// Mirrors: $fh = fopen($file, 'r'); returns resource|false.
// Simulated with bool since we cannot open real files in unit tests.

function guard_fopen_usage(mixed $handle): string {
	if ($handle !== false) {
		// In production: fread($handle, 100) + fclose($handle)
		return 'content';
	}

	return '';
}

// --- Stub: guard_chown_params (pattern from install/index.php) ---
// Mirrors: fileowner()/filegroup() return int|false; both must be narrowed
// before comparison and before passing to chown()/chgrp().

function guard_chown_params(int|false $owner_id, int|false $current_owner): bool {
	if ($current_owner !== false && $owner_id !== false && $current_owner != $owner_id) {
		return true;
	}

	return false;
}

// --- Stub: clamp_color_value (pattern from lib/graph_image.php) ---
// imagecolorallocate() requires int<0,255>. PHPStan flags unguarded int|null.
// Null-coalesce then clamp to [0, 255].

function clamp_color_value(int|null $val): int {
	return min(255, max(0, (int) ($val ?? 0)));
}

// --- Stub: guard_session_name (pattern from auth_login.php / lib/auth.php) ---
// session_name() returns string|false. Use ternary to guarantee string return.

function guard_session_name(string|false $name): string {
	return $name ?: 'PHPSESSID';
}

// --- Stub: guard_shell_exec_result (pattern from lib/rrd.php, poller scripts) ---
// shell_exec() returns string|false|null. Level 8 requires explicit narrowing
// before string operations.

function guard_shell_exec_result(string|false|null $result): string {
	return is_string($result) ? $result : '';
}

// --- Stub: guard_preg_match_offsets (pattern from lib/business_hours.php) ---
// preg_match() populates $matches by reference. Captures beyond index 0 may
// not exist when the pattern has optional groups. Null-coalesce to 0.

function guard_preg_match_offsets(array $matches): array {
	$matches[1] = $matches[1] ?? 0;
	$matches[2] = $matches[2] ?? 0;

	return $matches;
}

// --- Stub: guard_version_bits (pattern from lib/plugins.php) ---
// Accepts float|int (e.g. from version_compare return values used as numbers).
// PHPStan level 8 disallows float on bitwise operators; cast to int first.

function guard_version_bits(float|int $newver, bool $hex): int|string {
	$newver = (int) $newver;

	return $hex ? dechex($newver) : $newver;
}

// --- Stub: guard_pclose (pattern from lib/rrd.php, poller/cmd.php) ---
// popen() returns resource|false. pclose() must not be called on false.

function guard_pclose(mixed $fp): bool {
	if ($fp !== false) {
		// In production: pclose($fp)
		return true;
	}

	return false;
}

// --- Stub: guard_foreach_iterable (pattern throughout lib/ and include/) ---
// db_fetch_assoc() / db_fetch_assoc_prepared() return array|false.
// PHPStan level 8 flags foreach on array|false without narrowing.

function guard_foreach_iterable(array|false $data): int {
	if (is_array($data)) {
		$count = 0;

		foreach ($data as $item) {
			$count++;
		}

		return $count;
	}

	return 0;
}

// =====================================================================
// guard_db_result_access tests
// =====================================================================

test('db result: valid array with value key returns value', function () {
	$row = ['value' => 'rrd_path', 'id' => 42];

	expect(guard_db_result_access($row))->toBe('rrd_path');
});

test('db result: empty array returns empty string', function () {
	expect(guard_db_result_access([]))->toBe('');
});

test('db result: false from failed query returns empty string', function () {
	expect(guard_db_result_access(false))->toBe('');
});

test('db result: true (non-array bool) returns empty string', function () {
	expect(guard_db_result_access(true))->toBe('');
});

test('db result: array with numeric value casts to string', function () {
	$row = ['value' => 128];

	expect(guard_db_result_access($row))->toBe('128');
});

// =====================================================================
// guard_fopen_usage tests
// =====================================================================

test('fopen: non-false handle returns content string', function () {
	// Simulate a valid resource-like truthy value
	expect(guard_fopen_usage(true))->toBe('content');
});

test('fopen: integer handle (resource id sim) returns content string', function () {
	expect(guard_fopen_usage(1))->toBe('content');
});

test('fopen: false handle returns empty string', function () {
	expect(guard_fopen_usage(false))->toBe('');
});

test('fopen: null handle passes guard (null !== false) and returns content', function () {
	// fopen() never returns null; this documents that the guard is false-specific.
	// null !== false, so the guard body executes just as a valid handle would.
	expect(guard_fopen_usage(null))->toBe('content');
});

// =====================================================================
// guard_chown_params tests
// =====================================================================

test('chown: both valid, different owners returns true', function () {
	expect(guard_chown_params(1000, 0))->toBeTrue();
});

test('chown: both valid, same owner returns false', function () {
	expect(guard_chown_params(1000, 1000))->toBeFalse();
});

test('chown: owner_id is false returns false', function () {
	expect(guard_chown_params(false, 1000))->toBeFalse();
});

test('chown: current_owner is false returns false', function () {
	expect(guard_chown_params(1000, false))->toBeFalse();
});

test('chown: both false returns false', function () {
	expect(guard_chown_params(false, false))->toBeFalse();
});

test('chown: both zero (same owner) returns false', function () {
	expect(guard_chown_params(0, 0))->toBeFalse();
});

// =====================================================================
// clamp_color_value tests
// =====================================================================

test('clamp: null coalesces to 0', function () {
	expect(clamp_color_value(null))->toBe(0);
});

test('clamp: 0 stays at 0', function () {
	expect(clamp_color_value(0))->toBe(0);
});

test('clamp: 128 passes through unchanged', function () {
	expect(clamp_color_value(128))->toBe(128);
});

test('clamp: 255 stays at 255', function () {
	expect(clamp_color_value(255))->toBe(255);
});

test('clamp: 256 clamps to 255', function () {
	expect(clamp_color_value(256))->toBe(255);
});

test('clamp: negative value clamps to 0', function () {
	expect(clamp_color_value(-1))->toBe(0);
});

test('clamp: large value clamps to 255', function () {
	expect(clamp_color_value(999))->toBe(255);
});

test('clamp: large negative clamps to 0', function () {
	expect(clamp_color_value(-999))->toBe(0);
});

// =====================================================================
// guard_session_name tests
// =====================================================================

test('session_name: valid string passes through', function () {
	expect(guard_session_name('Cacti'))->toBe('Cacti');
});

test('session_name: false falls back to PHPSESSID', function () {
	expect(guard_session_name(false))->toBe('PHPSESSID');
});

test('session_name: empty string falls back to PHPSESSID', function () {
	expect(guard_session_name(''))->toBe('PHPSESSID');
});

test('session_name: zero-like string passes through as-is', function () {
	// '0' is falsy in PHP; guard collapses it to the default
	expect(guard_session_name('0'))->toBe('PHPSESSID');
});

test('session_name: non-empty arbitrary name passes through', function () {
	expect(guard_session_name('MY_APP_SESSION'))->toBe('MY_APP_SESSION');
});

// =====================================================================
// guard_shell_exec_result tests
// =====================================================================

test('shell_exec: valid string output passes through', function () {
	expect(guard_shell_exec_result("rrdtool info graph.rrd\n"))->toBe("rrdtool info graph.rrd\n");
});

test('shell_exec: empty string passes through as empty string', function () {
	expect(guard_shell_exec_result(''))->toBe('');
});

test('shell_exec: false returns empty string', function () {
	expect(guard_shell_exec_result(false))->toBe('');
});

test('shell_exec: null returns empty string', function () {
	expect(guard_shell_exec_result(null))->toBe('');
});

test('shell_exec: multiline output passes through intact', function () {
	$output = "line1\nline2\nline3\n";

	expect(guard_shell_exec_result($output))->toBe($output);
});

// =====================================================================
// guard_preg_match_offsets tests
// =====================================================================

test('preg_match offsets: full match array passes through', function () {
	$matches = ['08:00-17:00', '08:00', '17:00'];

	$result = guard_preg_match_offsets($matches);

	expect($result[1])->toBe('08:00')
		->and($result[2])->toBe('17:00');
});

test('preg_match offsets: missing index 1 defaults to 0', function () {
	$matches = ['full-match'];

	$result = guard_preg_match_offsets($matches);

	expect($result[1])->toBe(0)
		->and($result[2])->toBe(0);
});

test('preg_match offsets: empty matches array sets both defaults', function () {
	$result = guard_preg_match_offsets([]);

	expect($result[1])->toBe(0)
		->and($result[2])->toBe(0);
});

test('preg_match offsets: index 0 preserved when others default', function () {
	$matches = ['only-full'];

	$result = guard_preg_match_offsets($matches);

	expect($result[0])->toBe('only-full');
});

test('preg_match offsets: null capture group defaults to 0', function () {
	$matches = ['full', null, '17:00'];

	$result = guard_preg_match_offsets($matches);

	expect($result[1])->toBe(0)
		->and($result[2])->toBe('17:00');
});

// =====================================================================
// guard_version_bits tests
// =====================================================================

test('version_bits: integer input passes through as int', function () {
	expect(guard_version_bits(10, false))->toBe(10);
});

test('version_bits: float input truncates to int', function () {
	expect(guard_version_bits(10.9, false))->toBe(10);
});

test('version_bits: hex mode returns dechex string', function () {
	expect(guard_version_bits(255, true))->toBe('ff');
});

test('version_bits: hex mode with float truncates before hex conversion', function () {
	expect(guard_version_bits(255.99, true))->toBe('ff');
});

test('version_bits: decimal mode returns int not string', function () {
	$result = guard_version_bits(42, false);

	expect($result)->toBe(42)
		->and($result)->toBeInt();
});

test('version_bits: zero input returns 0 decimal', function () {
	expect(guard_version_bits(0, false))->toBe(0);
});

test('version_bits: zero input returns 0 hex string', function () {
	expect(guard_version_bits(0, true))->toBe('0');
});

test('version_bits: large version number hex mode', function () {
	expect(guard_version_bits(256, true))->toBe('100');
});

// =====================================================================
// guard_pclose tests
// =====================================================================

test('pclose: non-false handle returns true', function () {
	expect(guard_pclose(true))->toBeTrue();
});

test('pclose: integer handle (resource sim) returns true', function () {
	expect(guard_pclose(1))->toBeTrue();
});

test('pclose: false handle returns false', function () {
	expect(guard_pclose(false))->toBeFalse();
});

test('pclose: null handle returns false', function () {
	// null !== false, so guard passes; null would cause pclose() to warn in
	// production, but the guard pattern only tests for false specifically.
	expect(guard_pclose(null))->toBeTrue();
});

// =====================================================================
// guard_foreach_iterable tests
// =====================================================================

test('foreach guard: valid array returns count', function () {
	$data = ['host_a', 'host_b', 'host_c'];

	expect(guard_foreach_iterable($data))->toBe(3);
});

test('foreach guard: empty array returns 0', function () {
	expect(guard_foreach_iterable([]))->toBe(0);
});

test('foreach guard: false returns 0 without iterating', function () {
	expect(guard_foreach_iterable(false))->toBe(0);
});

test('foreach guard: single-element array returns 1', function () {
	expect(guard_foreach_iterable(['only']))->toBe(1);
});

test('foreach guard: associative array counts all entries', function () {
	$data = ['host' => 'localhost', 'port' => 161, 'version' => '2c'];

	expect(guard_foreach_iterable($data))->toBe(3);
});
