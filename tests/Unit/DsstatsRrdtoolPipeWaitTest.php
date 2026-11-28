<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * dsstats_rrdtool_execute() waits on the non-blocking RRDtool pipe.  It must
 * sleep while RRDtool works, and give up on an RRDtool that never answers.
 */

require_once dirname(__DIR__) . '/Helpers/DsstatsTestDatabase.php';

/**
 * Start a stand-in for 'rrdtool -' with its stdout set up as dsstats_rrdtool_init() does.
 *
 * @return array{0: resource, 1: array<int, resource>}
 */
function dsstats_slow_rrdtool(string $script) : array {
	$process = proc_open(['/bin/sh', '-c', $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'a']], $pipes);

	stream_set_blocking($pipes[0], false);
	stream_set_blocking($pipes[1], false);

	return [$process, $pipes];
}

function dsstats_cpu_seconds() : float {
	$usage = getrusage();

	return $usage['ru_utime.tv_sec'] + $usage['ru_utime.tv_usec'] / 1e6 + $usage['ru_stime.tv_sec'] + $usage['ru_stime.tv_usec'] / 1e6;
}

beforeEach(function () {
	if (CACTI_SERVER_OS != 'unix') {
		$this->markTestSkipped('The RRDtool stand-in is a shell script');
	}

	dsstats_test_connect();
});

afterEach(function () {
	dsstats_test_restore();
});

test('waiting for a slow answer does not spin the CPU', function () {
	$rrd_process = dsstats_slow_rrdtool('sleep 1; printf "OK u:0.00 s:0.00 r:0.00\n"; sleep 5');

	$cpu    = dsstats_cpu_seconds();
	$output = dsstats_rrdtool_execute('info slow.rrd', $rrd_process);
	$cpu    = dsstats_cpu_seconds() - $cpu;

	dsstats_rrdtool_close($rrd_process);

	expect($output)->toBe("OK u:0.00 s:0.00 r:0.00\n")
		->and($cpu)->toBeLessThan(0.3);
});

test('an RRDtool that never answers is abandoned after the timeout', function () {
	$rrd_process = dsstats_slow_rrdtool('sleep 8');

	$start  = microtime(true);
	$output = dsstats_rrdtool_execute('info hung.rrd', $rrd_process, 1);
	$again  = dsstats_rrdtool_execute('info next.rrd', $rrd_process, 1);
	$wait   = microtime(true) - $start;

	dsstats_rrdtool_close($rrd_process);

	expect($output)->toBe('')
		->and($again)->toBe('')
		->and($wait)->toBeLessThan(4.0);
});
