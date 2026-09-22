<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-hcj6-pmvx-fwgr / GHSA-967c-6qj7-q7rh.
 * escape_command() was a no-op, so a CR/LF in interpolated data (e.g. an
 * SNMP-substituted rrd_maximum) survived into the newline-delimited rrdtool
 * remote protocol and injected a second rrdtool command. escape_command() now
 * strips CR/LF; RRD_NL line-continuations are already collapsed before it runs.
 */
require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 4) . '/include/global.php';
require_once dirname(__DIR__, 4) . '/lib/rrd.php';

test('escape_command strips a raw newline (rrdtool-protocol injection)', function () {
	$payload = "100\ncreate /tmp/x.rrd DS:a:GAUGE:1:0:1";

	expect(escape_command($payload))->not->toContain("\n")
		->and(escape_command($payload))->not->toContain("\r");
});

test('escape_command leaves a normal single-line command intact', function () {
	$cmd = 'create /x.rrd --step 300 DS:x:GAUGE:600:0:100 RRA:AVERAGE:0.5:1:10';

	expect(escape_command($cmd))->toBe($cmd);
});
