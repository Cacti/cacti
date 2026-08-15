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
 * exit_code" warnings seen from poller_realtime.php). The exit code now comes
 * from proc_close(), which stays correct. These tests spawn a real PHP process.
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

test('large stdout is captured without truncation or deadlock', function () {
	$out = array();
	// 5000 lines forces the child to keep writing while the parent drains.
	$rc = cacti_exec(PHP_BINARY, array('-r', 'for ($i = 0; $i < 5000; $i++) echo "line$i\n";'), $out);

	expect($rc)->toBe(0);
	expect(count($out))->toBe(5000);
	expect($out[4999])->toBe('line4999');
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

test('a non-existent binary returns 255 and does not crash', function () {
	$out = array();

	expect(_exec_quietly(fn () => cacti_exec('/nonexistent/path/to/binary', array(), $out)))->toBe(255);
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
