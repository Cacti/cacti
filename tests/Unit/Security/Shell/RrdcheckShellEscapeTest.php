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
 * Tests for RRDtool argument escaping in lib/rrdcheck.php.
 *
 * do_rrdcheck() interpolated $file (data_template_data.data_source_path, a
 * free form textbox that is also settable through XML template import) and the
 * fetch window straight into the RRDtool command strings:
 *
 *   rrdtool_execute("file_exists $file", ...)
 *   rrdcheck_rrdtool_execute("fetch $file LAST -s $pstart -e $pend", $pipes)
 *
 * The proxy calls now quote the path with cacti_escapeshellarg() and the pipe
 * calls pass an argument array, which rrdcheck_rrdtool_execute() escapes one
 * argument at a time.
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 4) . '/include/global.php';
require_once dirname(__DIR__, 4) . '/lib/rrdcheck.php';

$rrdcheckSource = file_get_contents(__DIR__ . '/../../../../lib/rrdcheck.php');

// --- proxy calls quote the RRDfile path ---

test('rrdcheck file_exists escapes the RRDfile path', function () use ($rrdcheckSource) {
	expect($rrdcheckSource)->toContain("rrdtool_execute('file_exists ' . cacti_escapeshellarg(\$file)");
	expect($rrdcheckSource)->not->toContain('rrdtool_execute("file_exists $file"');
});

test('rrdcheck proxy info escapes the RRDfile path', function () use ($rrdcheckSource) {
	expect($rrdcheckSource)->toContain("rrdtool_execute('info ' . cacti_escapeshellarg(\$file)");
	expect($rrdcheckSource)->not->toContain('rrdtool_execute("info $file"');
});

test('rrdcheck proxy fetch escapes the RRDfile path', function () use ($rrdcheckSource) {
	expect($rrdcheckSource)->toContain("rrdtool_execute('fetch ' . cacti_escapeshellarg(\$file)");
	expect($rrdcheckSource)->not->toMatch('/rrdtool_execute\("fetch \$file/');
});

// --- pipe calls hand RRDtool an argument array ---

test('rrdcheck pipe info uses the argument array form', function () use ($rrdcheckSource) {
	expect($rrdcheckSource)->toContain("rrdcheck_rrdtool_execute(['info', \$file], \$pipes)");
	expect($rrdcheckSource)->not->toContain('rrdcheck_rrdtool_execute("info $file"');
});

test('rrdcheck pipe fetch uses the argument array form', function () use ($rrdcheckSource) {
	expect($rrdcheckSource)->toContain("rrdcheck_rrdtool_execute(['fetch', \$file, 'LAST', '-s', \$pstart, '-e', \$pend], \$pipes)");
	expect($rrdcheckSource)->not->toMatch('/rrdcheck_rrdtool_execute\("fetch \$file/');
});

// --- no RRDtool command in this file interpolates the path ---

test('no rrdcheck RRDtool command interpolates $file', function () use ($rrdcheckSource) {
	expect(preg_match('/rrd(check_)?(rrd)?tool_execute\(\s*"[^"]*\$file/', $rrdcheckSource))->toBe(0,
		'RRDtool commands must not interpolate $file into a double quoted string'
	);
});

// --- the array form reaches RRDtool with every argument quoted ---

test('rrdcheck_rrdtool_execute quotes each argument after the sub-command', function () {
	/*
	 * Drive the real function with a pair of in-memory streams.  The 'OK'
	 * already sitting in the read pipe ends the response loop, so what lands
	 * in the write pipe is the exact command line RRDtool would receive.
	 */
	$stdin  = fopen('php://temp', 'r+');
	$stdout = fopen('php://temp', 'r+');

	fwrite($stdout, "OK\n");
	rewind($stdout);

	$pipes = [$stdin, $stdout];

	rrdcheck_rrdtool_execute(['fetch', '/rra/x.rrd; touch /tmp/pwned', 'LAST', '-s', 100, '-e', 200], $pipes);

	rewind($stdin);
	$written = stream_get_contents($stdin);

	fclose($stdin);
	fclose($stdout);

	expect($written)->toBe("fetch '/rra/x.rrd; touch /tmp/pwned' 'LAST' '-s' '100' '-e' '200'\r\n");
});
