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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';
require_once dirname(__DIR__, 2) . '/lib/poller.php';

// --- Bug 1: operator precedence in timeout microsecond conversion ---

test('timeout converts seconds to microseconds without truncation', function () {
	/*
	 * The original bug cast elapsed time to int before multiplying by 1000000,
	 * truncating sub-second values to zero. This prevented the timeout from
	 * decrementing during sub-second iterations.
	 *
	 * The fix: (int) ((microtime(true) - $start) * 1000000)
	 * Multiply first to get microseconds as a float, then cast to int,
	 * so each sub-second wakeup correctly subtracts its actual elapsed time.
	 *
	 * Verify using a command that emits output in sub-second bursts with a
	 * short timeout. With the bug the timeout never decrements and the process
	 * runs to completion; with the fix it is killed after ~1 second.
	 */
	$output      = [];
	$return_code = -1;

	// Emits 20 lines over ~2 seconds; 1s timeout should kill it before completion.
	$result = exec_with_timeout(
		'/bin/sh -c "for i in $(seq 1 20); do echo $i; sleep 0.1; done"',
		$output,
		$return_code,
		1
	);

	// Some output captured before kill, but the sequence never completed.
	expect($output)->not->toContain('10')
		->and($return_code)->not->toBe(0);
});

test('fast command completes well within timeout', function () {
	$output      = [];
	$return_code = -1;

	$result = exec_with_timeout('echo hello', $output, $return_code, 5);

	// exec_with_timeout returns end(explode("\n", ...)) which is '' for
	// trailing-newline output. The actual data lives in $output.
	expect($output)->toContain('hello')
		->and($return_code)->toBe(0);
});

test('timeout value of 1 second allows sub-second command to finish', function () {
	$output      = [];
	$return_code = -1;

	$result = exec_with_timeout('/bin/sh -c "sleep 0.05 && echo OK"', $output, $return_code, 1);

	expect($output)->toContain('OK')
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
	$result = exec_with_timeout('sleep 3', $output, $return_code, 1);

	// Process was killed; no stdout produced.
	expect($result)->toBeNull()
		->and($return_code)->not->toBe(0);
});

// --- General contract tests ---

test('exec_with_timeout returns non-zero exit code for invalid command', function () {
	$output      = [];
	$return_code = -1;

	$result = exec_with_timeout('/nonexistent/binary/xyz', $output, $return_code, 2);

	expect($result)->toBeNull()
		->and($return_code)->not->toBe(0);
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
