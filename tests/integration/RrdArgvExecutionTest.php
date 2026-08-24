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
 * End-to-end coverage for the rrdtool argv contract. rrd_execute_pipe() runs a
 * real subprocess (a shell-script rrdtool double) via proc_open() with an
 * argument array. The double records the argv it received and the command it
 * read on stdin, and never interprets the payload, so these tests prove that
 * shell metacharacters in an rrdtool command cannot spawn a subprocess.
 */

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_LIBRARY . '/rrd.php';

function _rrd_argv_fake(): string {
	return __DIR__ . '/fixtures/fake_rrdtool.sh';
}

/* Run $fn with PHP warnings swallowed. proc_open() warns on a missing binary
 * and the test runner's error handler ignores the '@' operator, so this keeps
 * the expected-warning cases from being flagged as risky. */
function _rrd_silence(callable $fn) {
	set_error_handler(static function () {
		return true;
	});

	try {
		return $fn();
	} finally {
		restore_error_handler();
	}
}

function _rrd_unlink_if_exists(string $path): void {
	if (file_exists($path)) {
		unlink($path);
	}
}

test('rrdtool is invoked with argv "-" only, never the command as arguments', function () {
	$argvFile = tempnam(sys_get_temp_dir(), 'rrdargv');
	putenv('FAKE_RRD_ARGV_FILE=' . $argvFile);

	try {
		rrd_execute_pipe(_rrd_argv_fake(), 'graph - --imgformat=PNG DEF:a=/x.rrd:d:AVERAGE', false);
		$argv = array_values(array_filter(explode("\n", (string) file_get_contents($argvFile)), fn ($l) => $l !== ''));

		expect($argv)->toBe(['-']);
	} finally {
		putenv('FAKE_RRD_ARGV_FILE');
		_rrd_unlink_if_exists($argvFile);
	}
});

test('the full command is delivered to rrdtool on stdin', function () {
	$stdinFile = tempnam(sys_get_temp_dir(), 'rrdin');
	putenv('FAKE_RRD_STDIN_FILE=' . $stdinFile);

	try {
		rrd_execute_pipe(_rrd_argv_fake(), 'info /rra/x.rrd', false);
		$stdin = (string) file_get_contents($stdinFile);

		expect($stdin)->toContain('info /rra/x.rrd');
		expect($stdin)->toContain('quit');
	} finally {
		putenv('FAKE_RRD_STDIN_FILE');
		_rrd_unlink_if_exists($stdinFile);
	}
});

test('shell metacharacters in the command cannot execute (POC 08/14)', function () {
	$marker = sys_get_temp_dir() . '/rrd_argv_marker_' . getmypid();
	_rrd_unlink_if_exists($marker);

	// If any shell interpreted the command, one of these would create $marker.
	$payload = "graph -; touch $marker ; \$(touch $marker) ; `touch $marker`";
	rrd_execute_pipe(_rrd_argv_fake(), $payload, false);

	expect(file_exists($marker))->toBeFalse();
	_rrd_unlink_if_exists($marker);
});

test('a newline-split payload cannot inject a second argv command', function () {
	$marker = sys_get_temp_dir() . '/rrd_argv_nl_' . getmypid();
	_rrd_unlink_if_exists($marker);

	$payload = "info /rra/x.rrd\ntouch $marker";
	rrd_execute_pipe(_rrd_argv_fake(), $payload, false);

	// The double reads the whole payload on stdin and never runs it as a command.
	expect(file_exists($marker))->toBeFalse();
	_rrd_unlink_if_exists($marker);
});

test('large stdout and stderr are both captured without deadlock', function () {
	// The double writes ~200 KB to each of stdout and stderr, well past a pipe
	// buffer. Reading one stream to EOF before the other would hang here.
	putenv('FAKE_RRD_STDOUT_BYTES=200000');
	putenv('FAKE_RRD_STDERR_BYTES=200000');

	try {
		$out = rrd_execute_pipe(_rrd_argv_fake(), 'graph -', true);

		expect(substr_count($out, 'A'))->toBeGreaterThanOrEqual(200000);
		expect(substr_count($out, 'E'))->toBeGreaterThanOrEqual(200000);
	} finally {
		putenv('FAKE_RRD_STDOUT_BYTES');
		putenv('FAKE_RRD_STDERR_BYTES');
	}
});

test('stderr is folded into output only when requested', function () {
	putenv('FAKE_RRD_STDERR=a diagnostic line');

	try {
		expect(rrd_execute_pipe(_rrd_argv_fake(), 'info /x.rrd', false))->not->toContain('a diagnostic line');
		expect(rrd_execute_pipe(_rrd_argv_fake(), 'info /x.rrd', true))->toContain('a diagnostic line');
	} finally {
		putenv('FAKE_RRD_STDERR');
	}
});

test('a non-existent rrdtool binary returns false', function () {
	$rc = _rrd_silence(fn () => rrd_execute_pipe('/nonexistent/rrdtool', 'info /x.rrd', false));

	expect($rc)->toBeFalse();
});
