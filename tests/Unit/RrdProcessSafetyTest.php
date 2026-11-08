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

/**
 * Write a scripted RRDtool double that answers each command with a canned reply.
 *
 * @param string $body Shell case arms keyed on the command line read from stdin
 *
 * @return string Path of the executable script
 */
function rrd_reply_framing_binary(string $body) : string {
	$path = sys_get_temp_dir() . '/cacti-rrd-framing-' . bin2hex(random_bytes(6)) . '.sh';

	file_put_contents($path, "#!/bin/sh\nwhile IFS= read -r command; do\n\tcase \"\$command\" in\n" . $body . "\tesac\ndone\n");
	chmod($path, 0700);

	return $path;
}

test('a reply is not complete until its status line is newline terminated', function () {
	if (PHP_OS_FAMILY === 'Windows') {
		test()->markTestSkipped('The RRDtool double is a POSIX shell script.');
	}

	// The first reply pauses after PRINT text ending in "OK"; the second one
	// fails. Taking the pause as the end of the first reply would hand its
	// real OK line to the second command and acknowledge a rejected update.
	$binary = rrd_reply_framing_binary(
		"\t\tinfo*) printf 'PRINT:BOOK'; sleep 1; printf '\\nOK u:0.00 s:0.00 r:0.00\\n' ;;\n" .
		"\t\t*) printf 'ERROR: second\\n' ;;\n"
	);

	set_config_option('path_rrdtool', $binary);

	$process = __rrd_init(false);

	try {
		$first  = rrdtool_process_command($process, 'info first.rrd', 5.0);
		$second = rrdtool_process_command($process, 'last second.rrd', 5.0);

		expect($first['success'])->toBeTrue()
			->and($first['output'])->toBe("PRINT:BOOK\n")
			->and($second['success'])->toBeFalse()
			->and($second['error'])->toBe('ERROR: second');
	} finally {
		rrd_close($process);
		unlink($binary);
	}
});

test('an OK that ends a partial image is not taken as the reply status', function () {
	if (PHP_OS_FAMILY === 'Windows') {
		test()->markTestSkipped('The RRDtool double is a POSIX shell script.');
	}

	// Image bytes can spell "OK" at a read boundary; the real status follows
	// the image's last byte with no newline in between.
	$binary = rrd_reply_framing_binary(
		"\t\tgraph*) printf '\\211PNG\\001OK'; sleep 1; printf '\\nIEND\\256B`\\202OK u:0.01 s:0.00 r:0.02\\n' ;;\n" .
		"\t\t*) printf '1700000060\\nOK\\n' ;;\n"
	);

	set_config_option('path_rrdtool', $binary);

	$process = __rrd_init(false);

	try {
		$graph = rrdtool_process_command($process, 'graph -', 5.0);
		$last  = rrdtool_process_command($process, 'last file.rrd', 5.0);

		expect($graph['success'])->toBeTrue()
			->and($graph['output'])->toBe("\x89PNG\x01OK\nIEND\xaeB`\x82")
			->and($last['success'])->toBeTrue()
			->and($last['output'])->toBe("1700000060\n");
	} finally {
		rrd_close($process);
		unlink($binary);
	}
});

test('real RRDtool image and text replies stay in step on one pipe', function () {
	$binary = trim((string) shell_exec('command -v rrdtool'));

	if ($binary === '') {
		test()->markTestSkipped('rrdtool is not installed');
	}

	$rrd = sys_get_temp_dir() . '/cacti-rrd-framing-' . bin2hex(random_bytes(6)) . '.rrd';

	set_config_option('path_rrdtool', $binary);

	$process = __rrd_init(false);

	try {
		$create = rrdtool_process_command($process, 'create ' . $rrd . ' --start 1700000000 --step 60 DS:BOOK:GAUGE:120:U:U RRA:AVERAGE:0.5:1:10');
		$update = rrdtool_process_command($process, 'update ' . $rrd . ' 1700000060:1');
		$graph  = rrdtool_process_command($process, 'graph - --start 1700000000 --end 1700000300 DEF:a=' . $rrd . ':BOOK:AVERAGE LINE1:a#FF0000 PRINT:a:LAST:BOOK%lf');
		$last   = rrdtool_process_command($process, 'last ' . $rrd);
		$reject = rrdtool_process_command($process, 'update ' . $rrd . ' 1700000060:1');

		expect($create['success'])->toBeTrue()
			->and($update['success'])->toBeTrue()
			->and($graph['success'])->toBeTrue()
			->and($graph['output'])->toContain("\x89PNG")
			->and($last['output'])->toBe("1700000060\n")
			->and($reject['success'])->toBeFalse()
			->and($reject['error'])->toStartWith('ERROR:');
	} finally {
		rrd_close($process);

		if (is_file($rrd)) {
			unlink($rrd);
		}
	}
});
