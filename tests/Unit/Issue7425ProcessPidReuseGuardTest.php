<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The process registry stored a pid and nothing else, and every caller tested
 * it with a bare posix_kill($pid, 0). That only proves some process owns the
 * pid. Once a registered process dies without unregistering, the system is free
 * to hand its pid to an unrelated program, and Cacti would then either refuse
 * to start a legitimate task or send SIGTERM to a stranger.
 *
 * cacti_process_still_running() adds an identity check on top of the existence
 * check, using the command name under /proc where that exists.
 */

require_once dirname(__DIR__, 2) . '/include/global_constants.php';
require_once dirname(__DIR__, 2) . '/lib/poller.php';

/**
 * Starts a process running a different program and returns its pid.
 *
 * @param resource|null $handle Receives the process handle, to close later.
 *
 * @return int The pid of the started process.
 */
function pid_guard_spawn_foreign(&$handle) : int {
	$pipes  = [];
	// array form, so the child is sleep itself rather than a wrapping shell
	$handle = proc_open(['sleep', '30'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

	$status = proc_get_status($handle);

	foreach ($pipes as $pipe) {
		fclose($pipe);
	}

	return (int) $status['pid'];
}

test('a pid that cannot be valid is never reported running', function () {
	expect(cacti_process_still_running(0))->toBeFalse()
		->and(cacti_process_still_running(-1))->toBeFalse()
		->and(cacti_process_still_running(-99999))->toBeFalse();
});

test('the running process recognises itself', function () {
	expect(cacti_process_still_running(getmypid()))->toBeTrue();
});

test('a pid that has already exited is not reported running', function () {
	$pipes  = [];
	$handle = proc_open(['true'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$pid    = (int) proc_get_status($handle)['pid'];

	foreach ($pipes as $pipe) {
		fclose($pipe);
	}

	proc_close($handle);

	expect(cacti_process_still_running($pid))->toBeFalse();
});

/**
 * The identity half of the check, which is the whole point of the fix. It needs
 * the command names under /proc, so it only means anything on Linux.
 */
test('a live pid belonging to a different program is not reported running', function () {
	if (!is_dir('/proc')) {
		test()->markTestSkipped('no /proc, so command names cannot be compared');
	}

	if (PHP_SAPI !== 'cli') {
		test()->markTestSkipped('the identity check only applies to CLI callers');
	}

	$handle = null;
	$pid    = pid_guard_spawn_foreign($handle);

	// the pid is alive, so the bare existence test the fix replaced said yes
	expect(posix_kill($pid, 0))->toBeTrue()
		->and(cacti_process_still_running($pid))->toBeFalse();

	proc_terminate($handle);
	proc_close($handle);
});

/**
 * Where /proc is absent the function is documented to fall back to the bare
 * existence test, so this pins that it stays no worse than before rather than
 * silently reporting healthy processes dead.
 */
test('without /proc the check falls back to plain existence', function () {
	if (is_dir('/proc')) {
		test()->markTestSkipped('/proc is present, so the fallback does not apply');
	}

	$handle = null;
	$pid    = pid_guard_spawn_foreign($handle);

	expect(cacti_process_still_running($pid))->toBeTrue();

	proc_terminate($handle);
	proc_close($handle);
});

test('every registry kill site checks the pid before signalling it', function () {
	$poller  = file_get_contents(dirname(__DIR__, 2) . '/lib/poller.php');
	$dsstats = file_get_contents(dirname(__DIR__, 2) . '/lib/dsstats.php');

	// the bare existence test is what the fix removed; a reappearance means a
	// call site went back to trusting a recycled pid
	expect($poller)->not->toContain("posix_kill(\$r['pid'], 0)")
		->and($dsstats)->not->toContain("posix_kill(\$p['pid'], 0)");
});

/**
 * The registry holds CLI tasks. A web request asking whether one is alive has a
 * different command name by definition, so comparing identities there would
 * report a running poller dead and let a second copy start.
 */
test('the identity check is limited to CLI callers', function () {
	$body = file_get_contents(dirname(__DIR__, 2) . '/lib/poller.php');

	$start = strpos($body, 'function cacti_process_still_running(');
	expect($start)->not->toBeFalse();

	$function = substr($body, $start, strpos($body, "\nfunction ", $start + 1) - $start);

	$sapi_guard = strpos($function, "PHP_SAPI !== 'cli'");
	$proc_read  = strpos($function, '/proc/');

	expect($sapi_guard)->not->toBeFalse()
		->and($sapi_guard)->toBeLessThan($proc_read);
});
