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
 * Guard for issue #7027: timeout_kill_registered_processes() signalled any pid
 * from the processes table. A tampered pid column could then target init or a
 * value that narrows to a process group. is_system_pid() rejects those values
 * and callers retire a refused stale row without sending a signal.
 *
 * lib/poller.php only defines functions at file scope, so it is safe to load
 * here; require_once dedupes by path across the suite.
 */

require_once __DIR__ . '/../../../../lib/poller.php';

test('is_system_pid rejects init and anything that cannot name a process', function () {
	expect(is_system_pid(1))->toBeTrue();
	expect(is_system_pid(0))->toBeTrue();
	expect(is_system_pid(-1))->toBeTrue();
});

test('ordinary low pids remain valid inside pid namespaces', function () {
	foreach (array(2, 7, 42, 100) as $pid) {
		expect(is_system_pid($pid))->toBeFalse((string) $pid);
	}
});

test('is_system_pid allows a normal registered pid', function () {
	expect(is_system_pid(101))->toBeFalse();
	expect(is_system_pid(12345))->toBeFalse();
});

test('is_system_pid coerces numeric strings from the processes table', function () {
	expect(is_system_pid('1'))->toBeTrue();
	expect(is_system_pid('12345'))->toBeFalse();
});

test('timeout_kill_registered_processes gates posix_kill behind is_system_pid', function () {
	$source = file_get_contents(__DIR__ . '/../../../../lib/poller.php');
	expect($source)->not->toBeFalse();

	$start = strpos($source, 'function timeout_kill_registered_processes');
	expect($start)->not->toBeFalse();

	$body = substr($source, $start, 2200);

	// The SIGTERM kill must sit on the elseif branch, reachable only after the
	// is_system_pid() check has excluded the reserved range, and it goes out
	// through the shared guard rather than a bare posix_kill().
	expect(strpos($body, 'if (is_system_pid($pid))'))->not->toBeFalse();
	expect(preg_match('/}\s*elseif\s*\(\s*cacti_process_still_running\s*\(\s*\$pid\s*\)\s*\)\s*{/', $body))->toBe(1);
	expect(strpos($body, 'cacti_process_kill($pid, SIGTERM)'))->not->toBeFalse();
});
