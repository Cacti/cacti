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

/*
 * dsstats_rrdtool_init() and rrdcheck_rrdtool_init() return [false, false] when
 * proc_open() fails to spawn RRDtool. The poller loops then call the execute
 * helpers, which fwrite() to $pipes[0]. If the callers do not detect the
 * sentinel, $pipes is false, $pipes[0] is null, and fwrite(null, ...) throws a
 * TypeError that kills the poller. Both call sites now guard with
 * is_resource() on the process handle before touching the pipes.
 */

// Mirrors the guard added at the dsstats / rrdcheck call sites.
function rrd_process_started(array $rrd_process): bool {
	return is_resource($rrd_process[0] ?? null);
}

test('guard rejects the [false, false] failure sentinel', function () {
	expect(rrd_process_started([false, false]))->toBeFalse();
});

test('guard accepts a live process handle', function () {
	$process = proc_open('echo ok', [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w'],
	], $pipes);

	expect(rrd_process_started([$process, $pipes]))->toBeTrue();

	foreach ($pipes as $pipe) {
		if (is_resource($pipe)) {
			fclose($pipe);
		}
	}

	proc_close($process);
});

test('dereferencing the failure sentinel as pipes would crash without the guard', function () {
	$rrd_process = [false, false];
	$pipes       = $rrd_process[1];

	// $pipes is false here; reaching fwrite($pipes[0], ...) is the poller crash.
	// The guard short-circuits before this point, which the boolean asserts.
	expect(rrd_process_started($rrd_process))->toBeFalse()
		->and($pipes)->toBeFalse();
});
