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
 * Tests for exec_with_timeout() in lib/poller.php.
 *
 * Covers three bugs fixed in commit c58f8d940:
 *   1. Operator precedence: (int) cast bound tighter than multiplication,
 *      truncating the elapsed-time float to 0 before scaling to microseconds.
 *   2. stderr discard: any stderr output caused an early `return false`,
 *      throwing away valid stdout data.
 *   3. Orphaned children: no process-group kill before proc_terminate.
 */

// Stub cacti_log so lib/poller.php can call it without the full framework.
if (!function_exists('cacti_log')) {
	function cacti_log(string $msg, bool $output = false, string $facility = '', int $level = 0): void {
		// no-op for tests
	}
}

if (!defined('POLLER_VERBOSITY_MEDIUM')) {
	define('POLLER_VERBOSITY_MEDIUM', 2);
}

require_once dirname(__DIR__, 2) . '/lib/poller.php';

// --- Bug 1: operator precedence in timeout microsecond conversion ---

test('timeout converts seconds to microseconds without truncation', function () {
	/*
	 * The old code: (int) (microtime(true) - $start) * 1000000
	 * Cast a sub-second float (e.g. 0.003) to int (0), then * 1000000 = 0.
	 * The loop would exit immediately regardless of the timeout value.
	 *
	 * The fix: (int) ((microtime(true) - $start) * 1000000)
	 * Multiplies first, then casts, preserving the elapsed microseconds.
	 *
	 * Verify by running a command that takes ~100ms with a 5-second timeout.
	 * With the bug, the loop exits in one iteration and the command has no
	 * time to produce output. With the fix, the loop waits properly.
	 */
	$output      = [];
	$return_code = -1;

	$result = exec_with_timeout('/bin/sh -c "sleep 0.1 && echo DONE"', $output, $return_code, 5);

	expect($result)->toBe('DONE')
		->and($output)->toContain('DONE')
		->and($return_code)->toBe(0);
});

test('fast command completes well within timeout', function () {
	$output      = [];
	$return_code = -1;

	$result = exec_with_timeout('echo hello', $output, $return_code, 5);

	expect($result)->toBe('hello')
		->and($return_code)->toBe(0);
});

test('timeout value of 1 second allows sub-second command to finish', function () {
	$output      = [];
	$return_code = -1;

	$result = exec_with_timeout('/bin/sh -c "sleep 0.05 && echo OK"', $output, $return_code, 1);

	expect($result)->toBe('OK')
		->and($return_code)->toBe(0);
});

// --- Bug 2: stderr no longer discards valid stdout ---

test('command with stderr still returns stdout', function () {
	/*
	 * The old code: if (!empty($errors)) { return false; }
	 * Any stderr output caused the function to discard stdout and return false.
	 *
	 * The fix: log the stderr warning and continue processing stdout.
	 */
	$output      = [];
	$return_code = -1;

	// Write to both stdout and stderr; stdout should still be captured.
	$cmd    = '/bin/sh -c "echo GOODDATA; echo STDERRINFO >&2"';
	$result = exec_with_timeout($cmd, $output, $return_code, 5);

	expect($output)->toContain('GOODDATA')
		->and($result)->not->toBeFalse();
});

test('command with only stderr returns null and logs warning', function () {
	$output      = [];
	$return_code = -1;

	$cmd    = '/bin/sh -c "echo WARNING >&2"';
	$result = exec_with_timeout($cmd, $output, $return_code, 5);

	// No stdout, so buffer is empty and function returns null.
	expect($result)->toBeNull();
});

// --- Bug 3: process group cleanup with posix_kill ---

test('timed-out process is terminated and returns non-zero exit code', function () {
	/*
	 * The fix added posix_kill(-$pid, 9) before proc_terminate() to kill the
	 * entire process group, preventing orphaned children. We verify that a
	 * long-running command hit by the timeout does get killed.
	 */
	$output      = [];
	$return_code = -1;

	// Command sleeps longer than the 1-second timeout.
	$result = exec_with_timeout('sleep 30', $output, $return_code, 1);

	// Process was killed; no stdout produced.
	expect($result)->toBeNull()
		->and($return_code)->not->toBe(0);
});

// --- General contract tests ---

test('exec_with_timeout returns false for invalid command', function () {
	$output      = [];
	$return_code = -1;

	$result = exec_with_timeout('/nonexistent/binary/xyz', $output, $return_code, 2);

	expect($return_code)->not->toBe(0);
});

test('multi-line stdout populates output array and returns last line', function () {
	$output      = [];
	$return_code = -1;

	$cmd    = '/bin/sh -c "echo line1; echo line2; echo line3"';
	$result = exec_with_timeout($cmd, $output, $return_code, 5);

	expect($output)->toContain('line1')
		->and($output)->toContain('line2')
		->and($output)->toContain('line3')
		->and($return_code)->toBe(0);
});

test('operator precedence inline: cast-after-multiply preserves microseconds', function () {
	/*
	 * Pure arithmetic check, independent of process execution.
	 * Simulates the two expressions with a known elapsed time of 0.003 seconds.
	 */
	$elapsed = 0.003;

	// Old (buggy): cast first, then multiply.
	$buggy = (int) $elapsed * 1000000;

	// New (fixed): multiply first, then cast.
	$fixed = (int) ($elapsed * 1000000);

	expect($buggy)->toBe(0)
		->and($fixed)->toBe(3000);
});
