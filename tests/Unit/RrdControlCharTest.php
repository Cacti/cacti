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
 * rrd_strip_control_chars() runs on the assembled rrdtool command line before
 * it reaches the pipe or the shell. The persistent rrdtool process reads one
 * command per line, so a CR/LF in any field value would inject a second
 * command; these cases mirror the reported pipe/newline injection vectors.
 */

require_once CACTI_PATH_TESTS . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_LIBRARY . '/rrd.php';

test('CRLF in a data source path cannot inject a second rrdtool command', function () {
	// data_source_path carrying a CRLF + a second command over the pipe
	$payload = "/rra/legit.rrd\r\nfetch /rra/other.rrd AVERAGE";
	$clean   = rrd_strip_control_chars($payload);

	expect($clean)->not->toContain("\r");
	expect($clean)->not->toContain("\n");
	expect($clean)->toBe('/rra/legit.rrdfetch /rra/other.rrd AVERAGE');
});

test('newline in a DS field cannot split the shell command line', function () {
	// rrd_maximum-style value with an embedded newline separator
	$payload = "DS:ds:GAUGE:600:0:|query_ifSpeed|\n;id";
	$clean   = rrd_strip_control_chars($payload);

	expect($clean)->not->toContain("\n");
});

test('all C0 controls and DEL are removed, printable bytes kept', function () {
	$controls = '';
	for ($i = 0; $i <= 0x1f; $i++) {
		$controls .= chr($i);
	}
	$controls .= chr(0x7f);

	expect(rrd_strip_control_chars("a{$controls}b"))->toBe('ab');
	// NUL specifically
	expect(rrd_strip_control_chars("path\x00.rrd"))->toBe('path.rrd');
	// legitimate command text is untouched
	$cmd = "graph - --imgformat=PNG DEF:a=/rra/x.rrd:ds:AVERAGE LINE1:a#00FF00:\"Traffic\"";
	expect(rrd_strip_control_chars($cmd))->toBe($cmd);
});

test('always returns a string, even on empty and multibyte input', function () {
	expect(rrd_strip_control_chars(''))->toBe('');
	expect(rrd_strip_control_chars('graph'))->toBeString();
	// UTF-8 multibyte content is preserved (bytes above 0x7f are not stripped)
	expect(rrd_strip_control_chars("t\xC3\xA9st\ndata"))->toBe("t\xC3\xA9stdata");
});

test('both the local and proxy execute paths sanitize the command line', function () {
	$src = file_get_contents(CACTI_PATH_LIBRARY . '/rrd.php');

	// __rrd_execute() (local pipe/shell) and __rrd_proxy_execute() (rrdp proxy)
	// must both pass the assembled command through the stripper before it is sent.
	expect(substr_count($src, '$command_line = rrd_strip_control_chars($command_line);'))->toBeGreaterThanOrEqual(2);
});
