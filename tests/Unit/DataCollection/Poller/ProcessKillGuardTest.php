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
 * The files below each read a pid out of a process table and signalled it
 * directly. lib/poller.php's own two sites are not in this list because it
 * legitimately calls posix_kill() inside the guard itself; they are asserted
 * separately.
 * A row holding 1 would reach init, and one holding 4294967295, the maximum an
 * int(10) unsigned column takes, narrows inside posix_kill() to -1, which
 * kill(2) reads as every process the caller owns. cacti_process_kill() is the
 * shared bound those sites now go through.
 */

require_once dirname(__DIR__, 4) . '/include/global_constants.php';
require_once dirname(__DIR__, 4) . '/lib/poller.php';

/**
 * Every file that reads a pid out of a process table and signals it.
 *
 * @return array<int, string> Repository-relative paths whose signal calls
 *                            the guard replaced.
 */
function process_kill_guard_sites() : array {
	return [
		'cli/batchgapfix.php',
		'cli/float_rrdfiles.php',
		'cli/poller_reindex_hosts.php',
		'cli/rebuild_poller_cache.php',
		'lib/dsstats.php',
		'lib/rrdcheck.php',
		'poller.php',
		'poller_automation.php',
		'poller_boost.php',
		'poller_commands.php',
	];
}

/**
 * The file's code with comments and literal strings removed, so the scan reads
 * calls rather than prose. Matching raw text tripped on a docblock naming the
 * function it forbids, and matching one argument shape missed the scalar form
 * these files actually use.
 *
 * @param  string $src The file contents.
 *
 * @return string The same file with comments and string literals dropped.
 */
function process_kill_guard_code(string $src) : string {
	$code = '';

	foreach (token_get_all($src) as $token) {
		if (is_array($token)) {
			if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING], true)) {
				continue;
			}

			$code .= $token[1];
		} else {
			$code .= $token;
		}
	}

	return $code;
}

/**
 * Whether the file still reaches posix_kill() itself. These files have no
 * business calling it at all now, whatever the argument looks like.
 *
 * @param  string $src The file contents.
 *
 * @return bool True when a raw signal call survives in the code.
 */
function process_kill_guard_signals_directly(string $src) : bool {
	return strpos(process_kill_guard_code($src), 'posix_kill(') !== false;
}

test('a pid that cannot name a process is refused', function () {
	expect(cacti_process_kill(0, SIGTERM))->toBeFalse()
		->and(cacti_process_kill(-1, SIGTERM))->toBeFalse();
});

test('the guard refuses init and carries no wider low pid floor', function () {
	/* A floor at the reserved low range would also exclude Cacti's own
	   children inside a pid namespace, where they hold single and double digit
	   pids, and every caller unregisters the row whether or not the signal
	   lands. Refusing there would strand a live collector with no registry row
	   and let a second one start against the same RRDs. Asserting on the
	   source keeps that decision from being quietly reversed; the behaviour
	   itself cannot be tested without signalling a real low pid. */
	$src   = file_get_contents(dirname(__DIR__, 4) . '/lib/poller.php');
	$start = strpos($src, 'function cacti_process_kill(');

	expect($start)->not->toBeFalse();

	/* Slice to the function's own closing brace; a fixed width truncates as
	   soon as the body grows. */
	$body = substr($src, $start, strpos($src, "\n}\n", $start) - $start);

	expect($body)->toContain('$pid <= 1')
		->and($body)->toContain('$pid > cacti_process_pid_max()')
		->and($body)->not->toContain('<= 100');
});

test('a pid wider than pid_t is refused', function () {
	if (PHP_INT_SIZE < 8) {
		test()->markTestSkipped('a 32 bit build cannot express a pid wider than pid_t');
	}

	/* Through PHP 8.4 posix_kill() narrows 4294967295 to -1 and signals every
	   process the caller owns; from PHP 8.5 the same call raises a ValueError
	   that ends the poller. Refusing ahead of the call covers both. */
	expect(cacti_process_kill(4294967295, SIGTERM))->toBeFalse()
		->and(cacti_process_kill(PHP_INT_MAX, SIGTERM))->toBeFalse();
});

test('the process id ceiling follows the platform signal adapter', function () {
	expect(cacti_process_pid_max())->toBe(PHP_OS_FAMILY === 'Windows' ? 4294967295 : 2147483647);
});

test('a pid inside pid_t that does not exist is left to the kernel', function () {
	// Above every platform's pid_max but a real pid_t, so it must reach
	// posix_kill() and come back ESRCH rather than be refused here.
	expect(cacti_process_kill(999999999, SIGTERM))->toBeFalse();
});

test('an ordinary pid is still signalled', function () {
	$pipes  = [];
	$handle = proc_open([PHP_BINARY, '-r', 'sleep(30);'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

	/* proc_open() returns false when process creation is disabled, and
	   proc_get_status(false) then fails somewhere less obvious than here. */
	expect($handle)->toBeResource();

	$pid = (int) proc_get_status($handle)['pid'];

	foreach ($pipes as $pipe) {
		fclose($pipe);
	}

	expect(cacti_process_kill($pid, SIGTERM))->toBeTrue();

	/* Wait on the child's own status rather than probing the pid. Until the
	   parent reaps it the pid still exists as a zombie, so posix_kill($pid, 0)
	   would report it alive well after the signal landed. */
	$deadline = microtime(true) + 5;
	$status   = proc_get_status($handle);

	while ($status['running'] && microtime(true) < $deadline) {
		usleep(10000);
		$status = proc_get_status($handle);
	}

	expect($status['running'])->toBeFalse()
		->and($status['signaled'])->toBeTrue()
		->and($status['termsig'])->toBe(SIGTERM);

	proc_close($handle);
});

test('the guard logs the row it refused', function () {
	$src = file_get_contents(dirname(__DIR__, 4) . '/lib/poller.php');

	expect($src)->not->toBeFalse()
		->and($src)->toContain('Refusing to signal PID');

	/* ext-posix is optional outside Windows, so the guard must not fatal there.
	   Slice to the function's own closing brace rather than a fixed width,
	   which drifts as the body changes. */
	$start = strpos($src, 'function cacti_process_kill(');

	expect($start)->not->toBeFalse();

	expect(substr($src, $start, strpos($src, "\n}\n", $start) - $start))
		->toContain("function_exists('posix_kill')");
});

test('every process table kill site routes through the guard', function () {
	$missing   = [];
	$unguarded = [];

	foreach (process_kill_guard_sites() as $file) {
		$src = file_get_contents(dirname(__DIR__, 4) . '/' . $file);

		expect($src)->not->toBeFalse($file . ' must be readable');

		if (strpos($src, 'cacti_process_kill(') === false) {
			$missing[] = $file;
		}

		if (process_kill_guard_signals_directly($src)) {
			$unguarded[] = $file;
		}
	}

	expect($missing)->toBe([])
		->and($unguarded)->toBe([]);
});

/**
 * Files that only probe a stored pid with signal 0. They never deliver a
 * signal, but an unbounded probe still narrows a wide pid to -1 and answers
 * "running" for a row that names nothing, and on PHP 8.5 it throws instead.
 *
 * @return array<int, string> Repository-relative paths.
 */
function process_kill_guard_probe_sites() : array {
	return [
		'poller_automation.php',
		'poller_recovery.php',
	];
}

test('a stored pid is never probed with a raw signal 0 either', function () {
	$raw = [];

	foreach (process_kill_guard_probe_sites() as $file) {
		$src = file_get_contents(dirname(__DIR__, 4) . '/' . $file);

		expect($src)->not->toBeFalse($file . ' must be readable');

		if (preg_match('/posix_kill\([^)]*,\s*0\s*\)/', process_kill_guard_code($src))) {
			$raw[] = $file;
		}
	}

	expect($raw)->toBe([]);
});

test('the bounded probe answers false for a wide pid instead of throwing', function () {
	if (PHP_INT_SIZE < 8) {
		test()->markTestSkipped('a 32 bit build cannot express a pid wider than pid_t');
	}

	/* poller_recovery treats a true answer as "another recovery owns this pid"
	   and leaves the stale settings row in place, so a wide pid answered true
	   would wedge recovery permanently rather than merely misreport once. */
	expect(cacti_process_pid_exists(4294967295))->toBeFalse()
		->and(cacti_process_pid_exists(PHP_INT_MAX))->toBeFalse();
});

test('the scan catches every shape of raw signal and ignores prose', function () {
	/* Without this the ratchet's own coverage is unverified. The scalar form is
	   the one half these files use, and it is the shape an argument-matching
	   regex missed. */
	$guarded = <<<'GUARDED'
<?php
/* posix_kill($pid, 0) is what this replaced. */
cacti_process_kill($p['pid'], SIGTERM, 'BOOST');
$note = 'posix_kill(';
GUARDED;

	expect(process_kill_guard_signals_directly($guarded))->toBeFalse();

	$shapes = [
		'posix_kill($row[\'pid\'], SIGTERM);',
		'posix_kill($pid, SIGTERM);',
		'posix_kill((int) $r[\'pid\'], SIGTERM);',
		'posix_kill($pid, 0);',
	];

	foreach ($shapes as $shape) {
		expect(process_kill_guard_signals_directly("<?php\n" . $shape . "\n"))
			->toBeTrue($shape . ' must be reported');
	}
});

test('a pid that exists but cannot be signalled counts as stale, not live', function () {
	$eperm = defined('SOCKET_EPERM') ? SOCKET_EPERM : 1;

	if (posix_kill(1, 0) || posix_get_last_error() !== $eperm) {
		test()->markTestSkipped('pid 1 does not produce EPERM for this user');
	}

	/* The two contracts must stay apart. cacti_process_pid_exists() answers
	   "is it there", and EPERM means yes. poller_recovery asks "is the row
	   still mine", where EPERM means the pid was recycled by someone else and
	   the row is stale; reading it as live leaves settings.recovery_pid in
	   place and retires recovery for good. */
	expect(cacti_process_pid_exists(1))->toBeTrue()
		->and(cacti_process_signalable(1))->toBeFalse();

	$src = file_get_contents(dirname(__DIR__, 4) . '/poller_recovery.php');

	expect($src)->not->toBeFalse()
		->and(process_kill_guard_code($src))->toContain('cacti_process_signalable(');
});

test('cacti_process_kill refuses init behaviourally, not just in source', function () {
	// Signal 0 delivers nothing, so this asserts the refusal without touching init.
	expect(cacti_process_kill(1, 0))->toBeFalse()
		->and(cacti_process_signalable(getmypid()))->toBeTrue();
});
