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

// GHSA-mjvw-mhj5-9jcj: local file inclusion in reports format loader.
// The POST-supplied 'format_file' was written verbatim into reports.format_file
// and later concatenated onto CACTI_PATH_FORMATS in reports_load_format_file
// without a directory component check, letting a traversal sequence escape
// the formats directory and read arbitrary files the web user could access.
//
// The fix calls basename() at both save and load sites.

test('basename strips directory traversal from format_file', function () {
	expect(basename('../../../etc/passwd'))->toBe('passwd');
	expect(basename('../lib/auth.php'))->toBe('auth.php');
	expect(basename('/absolute/path/cacti_group.format'))->toBe('cacti_group.format');
});

test('basename preserves a legitimate format file name', function () {
	expect(basename('cacti_group.format'))->toBe('cacti_group.format');
	expect(basename('my_report.format'))->toBe('my_report.format');
});

test('html_reports.php sanitizes format_file before save', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/html_reports.php');

	expect($source)->toContain("basename((string) \$post['format_file'])");
	expect($source)->not->toMatch('/\$save\[[\'"]format_file[\'"]\]\s*=\s*\$post\[[\'"]format_file[\'"]\];/');
});

test('reports_load_format_file applies basename before concatenation', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/reports.php');

	$openPos = strpos($source, 'function reports_load_format_file');
	expect($openPos)->not->toBeFalse();

	$slice = substr($source, $openPos, 1200);

	$basenamePos = strpos($slice, 'basename($format_file)');
	$concatPos   = strpos($slice, 'CACTI_PATH_FORMATS');

	expect($basenamePos)->not->toBeFalse();
	expect($concatPos)->not->toBeFalse();
	expect($basenamePos)->toBeLessThan($concatPos);
});
