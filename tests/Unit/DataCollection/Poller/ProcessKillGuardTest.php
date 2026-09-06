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
 * Issue #7027 guarded the two kill sites inside lib/poller.php and left the
 * rest signalling a stored pid directly. cacti_process_kill() is the shared
 * bound those sites now go through: it refuses the reserved low range that
 * belongs to init and kernel threads, and the values above pid_t that
 * posix_kill() would narrow to -1, meaning every process the caller owns.
 */

require_once __DIR__ . '/../../../../lib/poller.php';

/**
 * Run a refusal path in a separate PHP process so its cacti_log() test double
 * cannot shadow the production function while Pest collects the unit suite.
 *
 * @param mixed $pid PID value passed to cacti_process_kill().
 *
 * @return array{result: bool, log: array<int, string>}
 */
function process_kill_guard_refusal($pid) {
	$library = dirname(__DIR__, 4) . '/lib/poller.php';
	$code    = '$log = array();'
		. 'function cacti_log($message, $output = false, $environ = "CMDPHP", $level = 0) { global $log; $log[] = $message; }'
		. 'require ' . var_export($library, true) . ';'
		. '$result = cacti_process_kill(' . var_export($pid, true) . ', SIGTERM);'
		. 'echo json_encode(array("result" => $result, "log" => $log));';
	$pipes   = array();
	$process = proc_open(array(PHP_BINARY, '-r', $code), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);

	expect($process)->not->toBeFalse();

	$output = stream_get_contents($pipes[1]);
	$error  = stream_get_contents($pipes[2]);

	fclose($pipes[1]);
	fclose($pipes[2]);

	expect(proc_close($process))->toBe(0, $error);

	return json_decode($output, true);
}

/**
 * Every file that reads a pid out of a process table and signals it.
 *
 * @return array<int, string> Repository-relative paths containing guarded
 *                            process-table kill sites.
 */
function process_kill_guard_sites() {
	return array(
		'cli/batchgapfix.php',
		'cli/float_rrdfiles.php',
		'cli/rebuild_poller_cache.php',
		'lib/dsstats.php',
		'lib/rrdcheck.php',
		'poller.php',
		'poller_automation.php',
		'poller_boost.php',
		'poller_commands.php',
	);
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
function process_kill_guard_code($src) {
	$code = '';

	foreach (token_get_all($src) as $token) {
		if (is_array($token)) {
			if (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE), true)) {
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
function process_kill_guard_signals_directly($src) {
	return strpos(process_kill_guard_code($src), 'posix_kill(') !== false;
}

test('a pid that cannot name a process is refused and recorded', function () {
	$zero     = process_kill_guard_refusal(0);
	$negative = process_kill_guard_refusal(-1);

	expect($zero['result'])->toBeFalse()
		->and($negative['result'])->toBeFalse()
		->and(implode("\n", array_merge($zero['log'], $negative['log'])))->toContain('Refusing to signal PID');
});

test('control characters in a refused pid cannot inject log lines', function () {
	$result = process_kill_guard_refusal("12\nforged");
	$log    = implode("\n", $result['log']);

	expect($result['result'])->toBeFalse()
		->and($log)->toContain('PID 12?forged')
		->and(substr_count($log, "\n"))->toBe(0);
});

test('the master poller sanitizes a stored pid before its status log', function () {
	$src = file_get_contents(dirname(__DIR__, 4) . '/poller.php');

	expect($src)->toContain("cacti_process_pid_for_log(\$process['pid'])")
		->and($src)->toContain("process with pid '\$logged_pid'");
});

test('the guard bounds the pid before calling the native signal function', function () {
	/* Validate before the native call so malformed, reserved, and out-of-range
	   values never reach the platform signal adapter. */
	$src   = file_get_contents(dirname(__DIR__, 4) . '/lib/poller.php');
	$start = strpos($src, 'function cacti_process_kill(');

	expect($start)->not->toBeFalse();

	/* Slice to the function's own closing brace. A fixed width would spill
	   into the next declaration and match text this function does not carry. */
	$body = substr($src, $start, strpos($src, "\n}\n", $start) - $start);

	expect($body)->toContain('cacti_process_pid_is_valid($pid)')
		->and($body)->toContain('is_system_pid($pid)')
		->and($body)->toContain("function_exists('posix_kill')")
		->and($body)->not->toContain('/proc/')
		->and($body)->not->toContain('$pid <= 100');

	/* The predicate it delegates to must keep refusing init and anything wider
	   than pid_t without rejecting container-safe low process IDs. */
	$start = strpos($src, 'function is_system_pid(');

	expect($start)->not->toBeFalse();

	$predicate = substr($src, $start, strpos($src, "\n}\n", $start) - $start);

	expect($predicate)->toContain('cacti_process_pid_is_valid($pid)')
		->and($predicate)->toContain('return $pid === 1;')
		->and($predicate)->not->toContain('getmypid()');
});

test('a pid wider than pid_t is refused', function () {
	if (PHP_INT_SIZE < 8) {
		test()->markTestSkipped('a 32 bit build cannot express a pid wider than pid_t');
	}

	/* posix_kill() narrows its argument to a 32 bit pid_t, so 4294967295, the
	   largest value an int(10) unsigned pid column holds, would arrive as -1
	   and signal every process the caller owns. */
	$wide = process_kill_guard_refusal(4294967295);
	$max  = process_kill_guard_refusal(PHP_INT_MAX);

	expect($wide['result'])->toBeFalse()
		->and($max['result'])->toBeFalse()
		->and(implode("\n", array_merge($wide['log'], $max['log'])))->toContain('Refusing to signal PID');
});

test('pid validation uses the platform signal adapter ceiling', function () {
	expect(cacti_process_pid_is_valid('2147483647'))->toBeTrue();

	if (PHP_OS_FAMILY === 'Windows') {
		expect(cacti_process_pid_is_valid('2147483648'))->toBeTrue()
			->and(cacti_process_pid_is_valid('4294967295'))->toBeTrue();
	} else {
		expect(cacti_process_pid_is_valid('2147483648'))->toBeFalse()
			->and(cacti_process_pid_is_valid('4294967295'))->toBeFalse();
	}
});

test('the unsigned Windows ceiling is only used by 64-bit PHP', function () {
	$src = file_get_contents(dirname(__DIR__, 4) . '/lib/poller.php');

	expect($src)->toContain("PHP_OS_FAMILY === 'Windows' && PHP_INT_SIZE >= 8");
});

test('pid validation accepts zero padding and rejects non-integer forms', function () {
	expect(cacti_process_pid_is_valid('0000000001'))->toBeTrue()
		->and(cacti_process_pid_is_valid('01'))->toBeTrue()
		->and(cacti_process_pid_is_valid('007'))->toBeTrue()
		->and(cacti_process_pid_is_valid(' 7'))->toBeFalse()
		->and(cacti_process_pid_is_valid('7.0'))->toBeFalse()
		->and(cacti_process_pid_is_valid("123\n"))->toBeFalse()
		->and(cacti_process_pid_is_valid("999999999\n"))->toBeFalse();
});

test('pid validation handles native scalar inputs', function () {
	expect(cacti_process_pid_is_valid(5))->toBeTrue()
		->and(cacti_process_pid_is_valid(0))->toBeFalse()
		->and(cacti_process_pid_is_valid(1))->toBeTrue()
		->and(cacti_process_pid_is_valid(-1))->toBeFalse()
		->and(cacti_process_pid_is_valid(null))->toBeFalse()
		->and(cacti_process_pid_is_valid(false))->toBeFalse()
		->and(cacti_process_pid_is_valid(7.0))->toBeTrue()
		->and(cacti_process_pid_is_valid(7.5))->toBeFalse()
		->and(cacti_process_pid_is_valid(array(7)))->toBeFalse()
		->and(cacti_process_pid_is_valid(new stdClass()))->toBeFalse();
});

test('untrusted pids are safe to include in one log line', function () {
	expect(cacti_process_pid_for_log("12\nforged"))->toBe('12?forged')
		->and(cacti_process_pid_for_log(array(7)))->toBe('array')
		->and(cacti_process_pid_for_log(new stdClass()))->toBe('object')
		->and(cacti_process_pid_for_log(false))->toBe('false')
		->and(cacti_process_pid_for_log(null))->toBe('null');
});

test('an ordinary pid is still signalled', function () {
	$pipes  = array();
	$script = dirname(__DIR__, 4) . '/tests/fixtures/process_sleep.php';
	$handle = proc_open(array(PHP_BINARY, $script), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);

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

test('a pid that does not exist reports the kernel refusal, not a guard refusal', function () {
	// 999999999 is above every platform's pid_max but inside pid_t, so it must
	// reach posix_kill() and come back ESRCH rather than be refused here.
	expect(cacti_process_kill(999999999, SIGTERM))->toBeFalse();
});

test('every process table kill site routes through the guard', function () {
	$missing  = array();
	$unguarded = array();

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

	expect($missing)->toBe(array())
		->and($unguarded)->toBe(array());
});

test('cleanup sites guard signals and still retire timed-out rows', function () {
	$sites = array(
		'cli/batchgapfix.php',
		'cli/float_rrdfiles.php',
		'cli/rebuild_poller_cache.php',
		'lib/dsstats.php',
		'lib/rrdcheck.php',
		'poller_boost.php',
		'poller_commands.php',
	);

	foreach ($sites as $file) {
		$src = file_get_contents(dirname(__DIR__, 4) . '/' . $file);

		expect($src)->not->toBeFalse($file . ' must be readable')
			->and($src)->toContain('cacti_process_still_running(')
			->and($src)->toContain('unregister_process(');
	}

	$poller = file_get_contents(dirname(__DIR__, 4) . '/lib/poller.php');

	expect($poller)->not->toContain('function cacti_process_can_unregister(')
		->and($poller)->toContain('unregister_process($tasktype, $taskname, $taskid)');
});

/**
 * Files that only probe a stored pid with signal 0. They never deliver a
 * signal, but an unbounded probe still narrows a wide pid to -1 and answers
 * "running" for a row that names nothing.
 *
 * cli/batchgapfix.php is left out on purpose: its probe keys off
 * posix_get_last_error() and reassigns the array it is iterating, so bounding
 * it is a behavioural change that belongs with a fix for those.
 *
 * @return array<int, string> Repository-relative paths.
 */
function process_kill_guard_probe_sites() {
	return array(
		'poller_automation.php',
		'poller_recovery.php',
	);
}

test('a stored pid is never probed with a raw signal 0 either', function () {
	$raw = array();

	foreach (process_kill_guard_probe_sites() as $file) {
		$src = file_get_contents(dirname(__DIR__, 4) . '/' . $file);

		expect($src)->not->toBeFalse($file . ' must be readable');

		if (preg_match('/posix_kill\([^)]*,\s*0\s*\)/', process_kill_guard_code($src))) {
			$raw[] = $file;
		}
	}

	expect($raw)->toBe(array());
});

test('the bounded probe answers false for a wide pid', function () {
	if (PHP_INT_SIZE < 8) {
		test()->markTestSkipped('a 32 bit build cannot express a pid wider than pid_t');
	}

	/* poller_recovery treats a true answer as "another recovery owns this pid"
	   and leaves the stale settings row in place, so a wide pid answered true
	   would wedge recovery permanently rather than merely misreport once. */
	expect(cacti_process_still_running(4294967295))->toBeFalse()
		->and(cacti_process_still_running(PHP_INT_MAX))->toBeFalse();
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
$interpolated = "posix_kill($pid, 0)";
GUARDED;

	expect(process_kill_guard_signals_directly($guarded))->toBeFalse();

	$shapes = array(
		'posix_kill($row[\'pid\'], SIGTERM);',
		'posix_kill($pid, SIGTERM);',
		'posix_kill((int) $r[\'pid\'], SIGTERM);',
		'posix_kill($pid, 0);',
	);

	foreach ($shapes as $shape) {
		expect(process_kill_guard_signals_directly("<?php\n" . $shape . "\n"))
			->toBeTrue($shape . ' must be reported');
	}
});

test('batchgapfix sanitizes stored pids before terminal output', function () {
	$src = file_get_contents(dirname(__DIR__, 4) . '/cli/batchgapfix.php');

	expect($src)->not->toBeFalse()
		->and($src)->toContain("cacti_process_pid_for_log(\$r['pid'])")
		->and($src)->toContain('PHP_EOL, $logged_pid)')
		->and($src)->toContain("db_fetch_assoc_prepared('SELECT *");
});

test('automation cancel reaches full network cleanup before exit', function () {
	$src    = file_get_contents(dirname(__DIR__, 4) . '/poller_automation.php');
	$cancel = strpos($src, "|| \$command == 'cancel'");

	expect($src)->not->toBeFalse()
		->and($cancel)->not->toBeFalse()
		->and(substr($src, 0, $cancel))->not->toContain("if (\$command == 'cancel')")
		->and(substr($src, $cancel))->toContain('cacti_process_kill($pid, SIGTERM')
		->and(strpos($src, 'cacti_process_kill($pid, SIGTERM', $cancel))->toBeLessThan(strpos($src, 'DELETE FROM automation_ips', $cancel))
		->and(substr($src, $cancel))->toContain('DELETE FROM automation_ips')
		->and(substr($src, $cancel))->toContain('clearAllTasks($network_id)')
		->and(substr($src, $cancel))->toContain('reportNetworkStatus($network_id, $preexisting_devices)');
});

test('a pid that exists but cannot be signalled remains live', function () {
	$eperm = defined('SOCKET_EPERM') ? SOCKET_EPERM : 1;

	if (posix_kill(1, 0) || posix_get_last_error() !== $eperm) {
		test()->markTestSkipped('pid 1 does not produce EPERM for this user');
	}

	/* EPERM proves the process exists. Treating it as stale would clear the
	   ownership row and permit a duplicate process to start. */
	expect(cacti_process_signalable(1))->toBeTrue();
	expect(cacti_process_signalable(1, false))->toBeFalse();

	$src = file_get_contents(dirname(__DIR__, 4) . '/poller_recovery.php');

	expect($src)->not->toBeFalse()
		->and(preg_match('/cacti_process_signalable\s*\(\s*\$recovery_pid\s*,\s*false\s*\)/', process_kill_guard_code($src)))->toBe(1)
		->and($src)->toContain('Another recovery process is still running')
		->and($src)->toContain('cacti_process_pid_for_log($recovery_pid)');
});

test('cacti_process_kill refuses init behaviourally, not just in source', function () {
	// Signal 0 delivers nothing, so this asserts the refusal without touching init.
	expect(process_kill_guard_refusal(1)['result'])->toBeFalse()
		->and(cacti_process_signalable(getmypid()))->toBeTrue();
});
