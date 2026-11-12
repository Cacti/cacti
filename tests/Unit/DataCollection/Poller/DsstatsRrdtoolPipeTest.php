<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression: dsstats_rrdtool_execute() stopped reading at the first output
 * containing 'OK', so 'rrdtool info' for a data source named LOOKUPS ended
 * early and every later command read the previous command's output.  It also
 * spun on the non-blocking pipe while RRDtool worked, and waited forever for
 * an RRDtool that stopped answering.
 */

namespace DsstatsRrdtoolPipeTest;

function cacti_log($message, $output = false, $environ = 'CMDPHP') {
}

$source = file_get_contents(dirname(__DIR__, 4) . '/lib/dsstats.php');

if ($source === false || preg_match('/^function dsstats_rrdtool_execute\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract dsstats_rrdtool_execute() for the pipe test.');
}

// the code is lib/dsstats.php from this checkout
eval('namespace DsstatsRrdtoolPipeTest;' . $matches[0]);

test('the pipe reader stops at the status line, not at OK inside a data source name', function () {
	$info   = "filename = \"ok.rrd\"\nds[LOOKUPS].index = 0\nds[LOOKUPS].type = \"GAUGE\"\nOK u:0.00 s:0.00 r:0.00\n";
	$second = "ERROR: opening 'missing.rrd': No such file or directory\n";

	// a socket, so that stream_select() can wait on it as on the RRDtool pipe
	$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
	fwrite($pair[0], $info . $second);
	fclose($pair[0]);
	stream_set_blocking($pair[1], false);

	$pipes = array(fopen('php://memory', 'w+'), $pair[1]);

	expect(dsstats_rrdtool_execute('info ok.rrd', $pipes))->toBe($info)
		->and(dsstats_rrdtool_execute('info missing.rrd', $pipes))->toBe($second);
});

test('the pipe reader accepts the bare OK status line of an RRDtool built without getrusage', function () {
	$info   = "filename = \"ok.rrd\"\nds[LOOKUPS].index = 0\nOK\n";
	$second = "0x0\ntraffic-avg_avg=1.000000\nOK\r\n";

	$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
	fwrite($pair[0], $info . $second);
	fclose($pair[0]);
	stream_set_blocking($pair[1], false);

	$pipes = array(fopen('php://memory', 'w+'), $pair[1]);

	expect(dsstats_rrdtool_execute('info ok.rrd', $pipes))->toBe($info)
		->and(dsstats_rrdtool_execute('graph -', $pipes))->toBe($second);
});

/**
 * Start a stand-in for 'rrdtool -' with its pipes set up as dsstats_rrdtool_init() does.
 */
function slow_rrdtool($script) {
	$process = proc_open(array('/bin/sh', '-c', $script), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('file', '/dev/null', 'a')), $pipes);

	stream_set_blocking($pipes[0], 0);
	stream_set_blocking($pipes[1], 0);

	return array($process, $pipes);
}

function cpu_seconds() {
	$usage = getrusage();

	return $usage['ru_utime.tv_sec'] + $usage['ru_utime.tv_usec'] / 1e6 + $usage['ru_stime.tv_sec'] + $usage['ru_stime.tv_usec'] / 1e6;
}

test('waiting for a slow answer does not spin the CPU', function () {
	if (DIRECTORY_SEPARATOR != '/') {
		$this->markTestSkipped('The RRDtool stand-in is a shell script');
	}

	list($process, $pipes) = slow_rrdtool('sleep 1; printf "OK u:0.00 s:0.00 r:0.00\n"; sleep 5');

	$cpu    = cpu_seconds();
	$output = dsstats_rrdtool_execute('info slow.rrd', $pipes);
	$cpu    = cpu_seconds() - $cpu;

	proc_terminate($process);
	proc_close($process);

	expect($output)->toBe("OK u:0.00 s:0.00 r:0.00\n")
		->and($cpu)->toBeLessThan(0.3);
});

test('an RRDtool that never answers is abandoned after the timeout', function () {
	if (DIRECTORY_SEPARATOR != '/') {
		$this->markTestSkipped('The RRDtool stand-in is a shell script');
	}

	list($process, $pipes) = slow_rrdtool('sleep 8');

	$start  = microtime(true);
	$output = dsstats_rrdtool_execute('info hung.rrd', $pipes, 1);
	$again  = dsstats_rrdtool_execute('info next.rrd', $pipes, 1);
	$wait   = microtime(true) - $start;

	proc_terminate($process);
	proc_close($process);

	expect($output)->toBeEmpty()
		->and($again)->toBeEmpty()
		->and($wait)->toBeLessThan(4.0);
});
