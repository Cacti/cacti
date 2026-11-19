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

require_once CACTI_PATH_LIBRARY . '/rrd.php';

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

test('an embedded double quote is written the way RRDtool reads it', function () {
	// RRDtool has no backslash escape, so \" would end the token early.  It
	// joins adjacent quoted pieces, so the quote is closed, '"' added and the
	// quote reopened.
	expect(rrdtool_prepare_stdin_command('graph - COMMENT:' . escapeshellarg('say "hi"')))
		->toBe('graph - COMMENT:"say "\'"\'"hi"\'"\'""')
		->and(rrdtool_prepare_stdin_command('graph - COMMENT:' . escapeshellarg('x" -w 177 -t "')))
		->toBe('graph - COMMENT:"x"\'"\'" -w 177 -t "\'"\'""');
});

test('backslashes keep their literal form, including before a quote or at the end', function () {
	expect(rrdtool_prepare_stdin_command('graph - COMMENT:' . escapeshellarg('ends with\\')))
		->toBe('graph - COMMENT:"ends with\\"')
		->and(rrdtool_prepare_stdin_command('graph - COMMENT:' . escapeshellarg('path\\"x')))
		->toBe('graph - COMMENT:"path\\"\'"\'"x"');
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

test('a died process is relaunched so the rest of the run still writes', function () {
	$binary = trim((string) shell_exec('command -v rrdtool'));

	if ($binary === '') {
		test()->markTestSkipped('rrdtool is not installed');
	}

	set_config_option('path_rrdtool', $binary);

	$process = __rrd_init(false);

	expect(rrdtool_is_process($process))->toBeTrue();

	// Kill it the way a crash would, then confirm a command still succeeds.
	proc_terminate($process->process);
	$process->alive = false;

	$result = rrdtool_process_command($process, 'pwd');

	expect($process->alive)->toBeTrue()
		->and($process->restarts)->toBe(1)
		->and($result['success'])->toBeTrue();

	rrd_close($process);
});

test('process relaunches are bounded so a persistently failing binary cannot spin', function () {
	$process = (object) [
		'process'        => null,
		'stdin'          => null,
		'stdout'         => null,
		'stderr'         => null,
		'alive'          => false,
		'restarts'       => RRDTOOL_MAX_RESTARTS,
		'output_to_term' => false
	];

	expect(rrdtool_process_restart($process))->toBeFalse();
});

test('a handle that is not an RRDtool process is never relaunched', function () {
	expect(rrdtool_process_restart((object) ['alive' => false]))->toBeFalse();
});

test('RRDtool reads every converted value back as one unchanged argument', function () {
	$rrdtool = trim((string) shell_exec('command -v rrdtool 2>/dev/null'));

	if ($rrdtool === '') {
		$this->markTestSkipped('rrdtool is not installed');
	}

	$dir = sys_get_temp_dir() . '/cacti-rps-' . bin2hex(random_bytes(6));
	mkdir($dir, 0700);
	shell_exec('cd ' . escapeshellarg($dir) . ' && ' . escapeshellarg($rrdtool) . ' create a.rrd --step 300 DS:v:GAUGE:600:U:U RRA:AVERAGE:0.5:1:10');

	try {
		foreach (['plain', "it's", 'say "hi"', 'x" -w 177 -t "', "x' -w 177 -t '", "both ' and \"", 'ends with\\', 'path\\"x'] as $value) {
			// The value ends the line, so RRDtool names the argument it parsed in its error
			$command = rrdtool_prepare_stdin_command('graph ' . $dir . '/o.png DEF:x=' . $dir . '/a.rrd:v:AVERAGE LINE1:' . escapeshellarg($value));
			$output  = (string) shell_exec('printf "%s\n" ' . escapeshellarg((string) $command) . ' | ' . escapeshellarg($rrdtool) . ' - 2>&1');

			expect($output)->toContain('ERROR: ' . $value . ' is not a vname nor a number');
		}
	} finally {
		foreach (glob($dir . '/*') as $file) {
			unlink($file);
		}

		rmdir($dir);
	}
});

test('returned RRDtool output reports a rendered graph instead of its image bytes', function () {
	$ok = ['success' => true, 'error' => ''];

	expect(rrdtool_format_result($ok + ['output' => "\x89PNG\r\n..."], RRDTOOL_OUTPUT_RETURN_STDERR))->toBe('OK')
		->and(rrdtool_format_result($ok + ['output' => '<?xml version="1.0"?><svg/>'], RRDTOOL_OUTPUT_RETURN_STDERR))->toBe('SVG/XML Output OK')
		->and(rrdtool_format_result(['success' => true, 'error' => 'ERROR: bad', 'output' => ''], RRDTOOL_OUTPUT_RETURN_STDERR))->toBe('ERROR: bad')
		->and(rrdtool_format_result($ok + ['output' => 'tune text'], RRDTOOL_OUTPUT_RETURN_STDERR))->toBe('tune text');
});
