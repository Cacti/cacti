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
 * Unit coverage for rrd_execute_pipe(): the no-shell execution primitive behind
 * the rrdtool argv contract. A shell-script test double stands in for rrdtool.
 */

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_LIBRARY . '/rrd.php';

function _fake_rrdtool(): string {
	return dirname(__DIR__) . '/integration/fixtures/fake_rrdtool.sh';
}

test('returns false when the rrdtool executable cannot be run', function () {
	// proc_open() warns on a missing binary; the function handles the false
	// return, so swallow the expected warning (the '@' operator is ignored by
	// the test runner's error handler).
	set_error_handler(static function () {
		return true;
	});

	try {
		$out = rrd_execute_pipe('/nonexistent/path/to/rrdtool', 'info /x.rrd', false);
	} finally {
		restore_error_handler();
	}

	expect($out)->toBeFalse();
});

test('returns stdout from the process', function () {
	$out = rrd_execute_pipe(_fake_rrdtool(), 'info /x.rrd', false);

	expect($out)->toContain('FAKERRD-OK');
});

test('control characters cannot inject a second stdin command', function () {
	$stdinFile = tempnam(sys_get_temp_dir(), 'rrdpipe');
	putenv('FAKE_RRD_STDIN_FILE=' . $stdinFile);

	try {
		rrd_execute_pipe(_fake_rrdtool(), "info /x.rrd\r\nfetch /other.rrd AVERAGE\0", false);
		$stdin = (string) file_get_contents($stdinFile);

		expect($stdin)->not->toContain("\nfetch")
			->and($stdin)->not->toContain("\0")
			->and($stdin)->toContain('info /x.rrdfetch /other.rrd AVERAGE')
			->and($stdin)->toEndWith("\r\nquit\r\n");
	} finally {
		putenv('FAKE_RRD_STDIN_FILE');

		if (is_string($stdinFile) && file_exists($stdinFile)) {
			unlink($stdinFile);
		}
	}
});

test('stderr is excluded unless requested', function () {
	putenv('FAKE_RRD_STDERR=a warning line');

	try {
		$without = rrd_execute_pipe(_fake_rrdtool(), 'info /x.rrd', false);
		$with    = rrd_execute_pipe(_fake_rrdtool(), 'info /x.rrd', true);

		expect($without)->not->toContain('a warning line');
		expect($with)->toContain('a warning line');
	} finally {
		putenv('FAKE_RRD_STDERR');
	}
});
