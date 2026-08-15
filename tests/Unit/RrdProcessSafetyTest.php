<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';

foreach ([
	'RRDTOOL_OUTPUT_NULL'          => 0,
	'RRDTOOL_OUTPUT_STDOUT'        => 1,
	'RRDTOOL_OUTPUT_STDERR'        => 2,
	'RRDTOOL_OUTPUT_GRAPH_DATA'    => 3,
	'RRDTOOL_OUTPUT_BOOLEAN'       => 4,
	'RRDTOOL_OUTPUT_RETURN_STDERR' => 5
] as $constant => $value) {
	if (!defined($constant)) {
		define($constant, $value);
	}
}

require_once dirname(__DIR__, 2) . '/lib/rrd.php';

test('stdin commands convert shell quoted arguments without losing apostrophes', function () {
	$path     = "/tmp/device's metrics.rrd";
	$prepared = rrdtool_prepare_stdin_command('info ' . escapeshellarg($path));

	expect($prepared)->toBe('info "/tmp/device\'s metrics.rrd"');
});

test('RRDtool control codes survive the conversion to a quoted token', function () {
	// Cacti escapes ':' in legend text because ':' separates RRDtool fields.
	// Escaping the backslash too left the legend ending in a dangling control
	// code and RRDtool refused every graph that carried one.
	$legend = '  IO Wait\\:';

	expect(rrdtool_prepare_stdin_command('graph - COMMENT:' . escapeshellarg($legend)))
		->toBe('graph - COMMENT:"  IO Wait\\:"')
		->and(rrdtool_prepare_stdin_command('graph - COMMENT:' . escapeshellarg('Average\\: %8.2lf\\n')))
		->toBe('graph - COMMENT:"Average\\: %8.2lf\\n"');
});

test('a quoted token still escapes an embedded double quote', function () {
	expect(rrdtool_prepare_stdin_command('graph - COMMENT:' . escapeshellarg('say "hi"')))
		->toBe('graph - COMMENT:"say \\"hi\\""');
});

test('values with no unambiguous quoted form are refused', function () {
	// Neither can be expressed inside a quoted RRDtool token: the trailing
	// backslash would escape the closing delimiter.
	expect(rrdtool_prepare_stdin_command('graph - COMMENT:' . escapeshellarg('ends with\\')))->toBeFalse()
		->and(rrdtool_prepare_stdin_command('graph - COMMENT:' . escapeshellarg('path\\"x')))->toBeFalse();
});

test('array commands leave the operation unquoted and quote only arguments', function () {
	expect(rrdtool_build_command(['info', "/tmp/device's metrics.rrd"]))
		->toBe("info '/tmp/device'\\''s metrics.rrd'")
		->and(rrdtool_build_command(['pwd']))->toBe('pwd');
});

test('array commands reject an absent or non-string operation', function () {
	expect(rrdtool_build_command([]))->toBeFalse()
		->and(rrdtool_build_command([42, 'file']))->toBeFalse()
		->and(__rrd_execute([], false))->toBeFalse();
});

test('machine-readable numeric commands launch RRDtool with an English locale', function () {
	expect(rrdtool_command_language('fetch file AVERAGE'))->toBe('en')
		->and(rrdtool_command_language(' INFO file'))->toBe('en')
		->and(rrdtool_command_language('xport DEF:value=file:value:AVERAGE'))->toBe('en')
		->and(rrdtool_command_language('graph -'))->toBeFalse();
});

test('stdin commands reject unapproved operations and malformed framing', function (string $command) {
	expect(rrdtool_prepare_stdin_command($command))->toBeFalse();
})->with([
	'empty'          => '',
	'unknown verb'   => 'execute something',
	'newline'        => "info /tmp/file\nquit",
	'carriage return'=> "info /tmp/file\rquit",
	'null byte'      => "info /tmp/file\0quit",
	'unclosed quote' => "info '/tmp/file",
	'oversize'       => 'info ' . str_repeat('a', RRDTOOL_MAX_COMMAND_BYTES)
]);

test('stdin command allowlist includes every local RRDtool operation used by Cacti', function (string $command) {
	expect(rrdtool_prepare_stdin_command($command))->toBe($command);
})->with([
	'create'      => 'create file',
	'update'      => 'update file N:1',
	'updatev'     => 'updatev file N:1',
	'graph'       => 'graph -',
	'graphv'      => 'graphv -',
	'dump'        => 'dump file',
	'restore'     => 'restore source target',
	'last'        => 'last file',
	'lastupdate'  => 'lastupdate file',
	'first'       => 'first file',
	'info'        => 'info file',
	'list'        => 'list .',
	'fetch'       => 'fetch file AVERAGE',
	'tune'        => 'tune file',
	'resize'      => 'resize file RRA GROW 1',
	'xport'       => 'xport DEF:value=file:value:AVERAGE',
	'flushcached' => 'flushcached file',
	'ls'          => 'ls',
	'cd'          => 'cd path',
	'mkdir'       => 'mkdir path',
	'pwd'         => 'pwd'
]);

test('process result formatting preserves success output and fails closed', function () {
	$success = ['success' => true, 'output' => 'payload', 'error' => ''];
	$failure = ['success' => false, 'output' => '', 'error' => 'rejected'];

	expect(rrdtool_format_result($success, RRDTOOL_OUTPUT_NULL))->toBeTrue()
		->and(rrdtool_format_result($success, RRDTOOL_OUTPUT_BOOLEAN))->toBeTrue()
		->and(rrdtool_format_result($success, RRDTOOL_OUTPUT_STDOUT))->toBe('payload')
		->and(rrdtool_format_result($success, RRDTOOL_OUTPUT_GRAPH_DATA))->toBe('payload')
		->and(rrdtool_format_result($success, RRDTOOL_OUTPUT_RETURN_STDERR))->toBe('payload')
		->and(rrdtool_format_result($success, 999))->toBeFalse()
		->and(rrdtool_format_result($failure, RRDTOOL_OUTPUT_NULL))->toBeFalse()
		->and(rrdtool_format_result($failure, RRDTOOL_OUTPUT_BOOLEAN))->toBeFalse()
		->and(rrdtool_format_result($failure, RRDTOOL_OUTPUT_STDOUT))->toBeFalse()
		->and(rrdtool_format_result($failure, RRDTOOL_OUTPUT_RETURN_STDERR))->toBe('rejected');
});

test('process stderr formatting identifies image output and prints diagnostics', function () {
	ob_start();
	$plain = rrdtool_format_result(
		['success' => true, 'output' => 'plain', 'error' => 'warning'],
		RRDTOOL_OUTPUT_STDERR
	);
	$printed = ob_get_clean();

	ob_start();
	$failed = rrdtool_format_result(
		['success' => false, 'output' => '', 'error' => 'rejected'],
		RRDTOOL_OUTPUT_STDERR
	);
	$failureOutput = ob_get_clean();

	expect(rrdtool_format_result(['success' => true, 'output' => "\x89PNGdata", 'error' => ''], RRDTOOL_OUTPUT_STDERR))->toBe('OK')
		->and(rrdtool_format_result(['success' => true, 'output' => '<?xml version="1.0"?>', 'error' => ''], RRDTOOL_OUTPUT_STDERR))->toBe('SVG/XML Output OK')
		->and($plain)->toBeTrue()
		->and($printed)->toBe('warning')
		->and($failed)->toBeFalse()
		->and($failureOutput)->toBe('rejected');
});

test('process handle and complete write helpers reject invalid streams', function () {
	$result = rrdtool_process_command((object) [], 'info file');

	expect(rrdtool_is_process(null))->toBeFalse()
		->and(rrdtool_is_process((object) []))->toBeFalse()
		->and(rrdtool_write_all(null, 'data'))->toBeFalse()
		->and($result['success'])->toBeFalse()
		->and($result['error'])->toContain('unavailable');
});
