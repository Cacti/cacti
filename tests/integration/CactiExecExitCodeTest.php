<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * End-to-end coverage for cacti_exec() exit-status handling.
 *
 * Reading the process pipes to EOF can reap the child before proc_get_status()
 * runs, which left exit_code as -1 or a missing key (the "Undefined array key
 * exit_code" warnings seen from poller_realtime.php). The implementation now
 * preserves a valid observed exitcode and uses proc_close() only as fallback.
 * These tests spawn a real PHP process.
 */

beforeAll(function () {
	require_once dirname(__DIR__, 2) . '/include/global_constants.php';
	require_once dirname(__DIR__, 2) . '/lib/functions.php';
});

/* Run something that logs (the reject paths call cacti_log) without a full
 * Cacti bootstrap by swallowing the incidental log-origin notices, so the test
 * asserts the return value rather than cacti_log's environment needs. */
function _exec_quietly(callable $fn) {
	set_error_handler(function () { return true; });

	try {
		return $fn();
	} finally {
		restore_error_handler();
	}
}

test('cacti_exec returns the real process exit code', function () {
	$out = array();

	expect(cacti_exec(PHP_BINARY, array('-r', 'exit(0);'), $out))->toBe(0);
	expect(cacti_exec(PHP_BINARY, array('-r', 'exit(1);'), $out))->toBe(1);
	expect(cacti_exec(PHP_BINARY, array('-r', 'exit(3);'), $out))->toBe(3);
	expect(cacti_exec(PHP_BINARY, array('-r', 'exit(42);'), $out))->toBe(42);
	expect(cacti_exec(PHP_BINARY, array('-r', 'exit(127);'), $out))->toBe(127);
	expect(cacti_exec(PHP_BINARY, array('-r', 'exit(255);'), $out))->toBe(255);
});

test('a non-zero exit still returns the exit code and captures output', function () {
	$out = array();
	$rc  = cacti_exec(PHP_BINARY, array('-r', 'fwrite(STDOUT, "partial\n"); exit(2);'), $out);

	expect($rc)->toBe(2);
	expect($out)->toBe(array('partial'));
});

test('cacti_exec captures multi-line stdout into the output array', function () {
	$out = array();
	$rc  = cacti_exec(PHP_BINARY, array('-r', 'echo "hello\nworld";'), $out);

	expect($rc)->toBe(0);
	expect($out)->toBe(array('hello', 'world'));
});

test('empty stdout yields an empty output array, not a one-element array', function () {
	$out = array('stale');
	$rc  = cacti_exec(PHP_BINARY, array('-r', 'exit(0);'), $out);

	expect($rc)->toBe(0);
	expect($out)->toBe(array());
});

test('large stdout and stderr are drained without truncation or losing the exit code', function () {
	$out = array();
	// Both streams exceed typical pipe capacity and must be drained concurrently.
	$rc = _exec_quietly(function () use (&$out) {
		return cacti_exec(PHP_BINARY, array('-r', 'for ($i = 0; $i < 20000; $i++) { echo "line$i\n"; fwrite(STDERR, "warning$i\n"); } exit(23);'), $out);
	});

	expect($rc)->toBe(23);
	expect(count($out))->toBe(20000);
	expect($out[19999])->toBe('line19999');
});

test('cacti_exec rejects an empty, whitespace, or dash-led binary with 255', function () {
	$out = array();

	// empty returns before any logging
	expect(cacti_exec('', array(), $out))->toBe(255);
	expect(cacti_exec('   ', array(), $out))->toBe(255);

	// the dash guard logs; assert the return value without the bootstrap noise
	expect(_exec_quietly(fn () => cacti_exec('-x', array(), $out)))->toBe(255);
	expect(_exec_quietly(fn () => cacti_exec('--version', array(), $out)))->toBe(255);
});

test('a non-existent binary matches this PHP builds spawn behavior', function () {
	$out = array();
	$path = '/nonexistent/path/to/binary';
	$proc = @proc_open(array($path), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);

	if (is_resource($proc)) {
		fclose($pipes[0]);
		stream_get_contents($pipes[1]);
		stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$expected = proc_close($proc);
	} else {
		$expected = 255;
	}

	$exit = _exec_quietly(fn () => cacti_exec($path, array(), $out));
	expect($exit)->toBe($expected);
});

test('timeout zero fails closed without entering the process wait loop', function () {
	$out = array();

	expect(_exec_quietly(fn () => cacti_exec(PHP_BINARY, array('-r', 'usleep(200000);'), $out, 0)))->toBe(1);
});

test('a signal-terminated child does not fabricate a successful exit code', function () {
	if (!function_exists('posix_kill')) {
		$this->markTestSkipped('posix extension is required for the signal termination proof');
	}

	$out  = array();
	$exit = cacti_exec(PHP_BINARY, array('-r', 'posix_kill(getmypid(), 9);'), $out);

	expect($exit)->not->toBe(0);
});

test('cacti_exec raises no exit_code warning while reading status', function () {
	$out      = array();
	$warnings = array();

	set_error_handler(function ($n, $s) use (&$warnings) {
		$warnings[] = $s;
		return true;
	});

	try {
		cacti_exec(PHP_BINARY, array('-r', 'exit(0);'), $out);
		cacti_exec(PHP_BINARY, array('-r', 'usleep(120000); exit(7);'), $out);
	} finally {
		restore_error_handler();
	}

	$exit_code_warnings = array_values(array_filter($warnings, function ($w) {
		return stripos($w, 'exit_code') !== false;
	}));

	expect($exit_code_warnings)->toBe(array());
});

test('a fractional timeout is honored and reaps a silent child near its deadline', function () {
	$out   = array();
	$start = microtime(true);
	// 0.5s idle budget against a 5s silent sleep: the child must be reaped well
	// before its own sleep elapses, proving sub-second timeouts take effect.
	$exit    = _exec_quietly(fn () => cacti_exec(PHP_BINARY, array('-r', 'usleep(5000000);'), $out, 0.5));
	$elapsed = microtime(true) - $start;

	expect($exit)->toBe(1);
	expect($elapsed)->toBeLessThan(3.0);
});

test('cacti_exec has no fixed per-read sleep floor', function () {
	/* Deterministic regression guard: the old unconditional 50ms-per-pass sleep
	 * is gone, so a fast command cannot inherit an N*50ms floor from the loop. */
	$src = file_get_contents(dirname(__DIR__, 2) . '/lib/functions.php');
	expect($src)->not->toContain('usleep(50000)');

	$out   = array();
	$start = microtime(true);
	for ($i = 0; $i < 20; $i++) {
		expect(cacti_exec(PHP_BINARY, array('-r', 'exit(0);'), $out))->toBe(0);
	}
	// Under the old floor, 20 spawns needed >=1s of pure sleep on top of spawn
	// cost; this ceiling still catches a regression to any large fixed floor.
	expect(microtime(true) - $start)->toBeLessThan(5.0);
});

test('streaming output refills the idle budget so a long but active child is not killed', function () {
	$out = array();
	// Total runtime (~2s) exceeds the 1s idle budget, but each 200ms gap stays
	// well under it, so the idle timer resets on every line and the child runs
	// to completion instead of being treated as a stall.
	$script = 'for ($i = 0; $i < 10; $i++) { echo "tick$i\n"; usleep(200000); } exit(0);';
	$exit   = cacti_exec(PHP_BINARY, array('-r', $script), $out, 1);

	expect($exit)->toBe(0);
	expect(count($out))->toBe(10);
	expect($out[9])->toBe('tick9');
});

test('a silent child is terminated once the idle budget elapses', function () {
	$out   = array();
	$start = microtime(true);
	// No output for longer than the 1s idle budget: must be reaped as a stall.
	$exit    = _exec_quietly(fn () => cacti_exec(PHP_BINARY, array('-r', 'usleep(4000000);'), $out, 1));
	$elapsed = microtime(true) - $start;

	expect($exit)->toBe(1);
	expect($elapsed)->toBeLessThan(3.5);
});
