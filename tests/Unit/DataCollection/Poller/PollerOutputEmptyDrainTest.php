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
 * cli/poller_output_empty.php drains poller_output in a
 * `while (count(*) > 0)` loop, deleting each sample only after
 * process_poller_output() reads rrdtool's per-command acknowledgement.
 *
 * Acknowledgement is only readable when the pipe is opened with
 * rrd_init(true, true) (the response-carrying pipe). With the default
 * write-only rrd_init(), no sample is ever acknowledged, nothing is
 * deleted, and the loop never terminates. poller.php and lib/boost.php
 * open the drain pipe the same way; this CLI must match them.
 */

function getPollerOutputEmptySource(): string {
	$source = file_get_contents(__DIR__ . '/../../../../cli/poller_output_empty.php');
	expect($source)->not->toBeFalse('Failed to read cli/poller_output_empty.php');

	return $source;
}

test('the drain CLI opens the acknowledged rrdtool pipe', function () {
	$source = getPollerOutputEmptySource();

	expect($source)->toContain('rrd_init(true, true)');

	// the write-only pipe would make the drain loop spin forever
	expect($source)->toMatch('/\$rrdtool_pipe\s*=\s*rrd_init\(true,\s*true\)\s*;/');
	expect($source)->not->toMatch('/\$rrdtool_pipe\s*=\s*rrd_init\(\s*\)\s*;/');
});

test('the drain CLI acknowledges real RRD writes before deleting queued samples', function () {
	$output = [];
	$status = 0;
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 3) . '/fixtures/Core/Rrd/PollerOutputRetention.php') . ' real-drain 2>&1', $output, $status);
	expect($status)->toBe(0, implode("\n", $output));
	expect(json_decode(implode("\n", $output), true))->toBe(['scenario' => 'real-drain', 'written' => 6, 'retained' => 0, 'ok' => true]);
})->skip(!getenv('RRDTOOL_BINARY') || !function_exists('pcntl_alarm'), 'Set RRDTOOL_BINARY and enable pcntl for the real drain test');
