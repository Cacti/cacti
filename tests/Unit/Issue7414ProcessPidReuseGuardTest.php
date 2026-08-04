<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * register_process_start(), timeout_kill_registered_processes(), and
 * dsstats_kill_running_processes() used to trust a registered pid with a
 * bare `posix_kill($pid, 0)`. If the registered process died without
 * unregistering and the OS recycled its pid for an unrelated program, the
 * bare check couldn't tell the difference: it would refuse to start a
 * legitimate new task, or SIGTERM a process that never had anything to do
 * with Cacti. The fix adds cacti_process_still_running(), which layers a
 * /proc/<pid>/comm identity check on Linux, and routes all three call
 * sites through it instead of the bare posix_kill($pid, 0).
 */

require_once dirname(__DIR__, 2) . '/lib/poller.php';

/*
 * On hosts without /proc (macOS/BSD test runners), cacti_process_still_running()
 * hits its own @file_get_contents() fallback path, which is intentionally
 * suppressed in the fix itself. PHPUnit's error handler intercepts E_WARNING
 * regardless of the "@" operator, so calls that can reach that path are
 * wrapped here to keep the suppression the fix already declared.
 */
$stillRunning = function ($pid) {
	set_error_handler(function () {
		return true;
	});

	try {
		return cacti_process_still_running($pid);
	} finally {
		restore_error_handler();
	}
};

test('rejects non-positive pids outright', function () use ($stillRunning) {
	expect($stillRunning(0))->toBeFalse();
	expect($stillRunning(-1))->toBeFalse();
});

test('returns false for a pid that is not running', function () use ($stillRunning) {
	// PID_MAX_LIMIT on Linux is 4194304; on macOS/BSD pids are 16-bit.
	// 999999999 is not an assignable pid on any of these, so posix_kill
	// with signal 0 must fail with ESRCH.
	expect($stillRunning(999999999))->toBeFalse();
});

test('returns true for the currently running process (self)', function () use ($stillRunning) {
	// Comparing /proc/self/comm to /proc/<pid>/comm (or falling back to
	// the bare existence check when /proc is unavailable) must agree
	// that our own pid is both alive and not a reused identity.
	expect($stillRunning(getmypid()))->toBeTrue();
});

test('tracks a real child process through its start/exit lifecycle', function () use ($stillRunning) {
	$descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
	$proc        = proc_open('sleep 5', $descriptors, $pipes);

	expect($proc)->not->toBeFalse();

	$status = proc_get_status($proc);
	$pid    = $status['pid'];

	expect($stillRunning($pid))->toBeTrue();

	posix_kill($pid, SIGKILL);

	foreach ($pipes as $pipe) {
		fclose($pipe);
	}

	proc_close($proc);

	// Give the OS a moment to reap the pid before asserting it is gone.
	$deadline = microtime(true) + 2;

	while (posix_kill($pid, 0) && microtime(true) < $deadline) {
		usleep(50000);
	}

	expect($stillRunning($pid))->toBeFalse();
});

test('falls back to the bare existence check when /proc is unavailable', function () use ($stillRunning) {
	if (is_dir('/proc')) {
		test()->markTestSkipped('This host has /proc; the Linux comm-comparison path is exercised instead.');
	}

	// On non-Linux hosts (e.g. macOS/BSD) file_get_contents() on
	// /proc/<pid>/comm always fails, so the function must fall back to
	// treating a live pid as "still running" rather than defaulting to
	// false and starving legitimate registrations.
	expect($stillRunning(getmypid()))->toBeTrue();
});

test('register_process_start() and timeout_kill_registered_processes() route through the guard, not a bare posix_kill(pid, 0)', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/lib/poller.php');

	expect(substr_count($src, 'cacti_process_still_running($r[\'pid\'])'))->toBe(3);
	expect($src)->not->toContain('$r[\'pid\'] > 0 && posix_kill($r[\'pid\'], 0)');
});

test('dsstats_kill_running_processes() guards its SIGTERM with the same check', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/lib/dsstats.php');

	$pos = strpos($src, 'function dsstats_kill_running_processes()');
	expect($pos)->not->toBeFalse();

	$fragment = substr($src, $pos, 700);

	expect($fragment)->toContain('if (cacti_process_still_running($p[\'pid\'])) {');
});
