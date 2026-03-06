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
 |
 | Tests for PHP 8.5 deprecation removals: curl_close(), imagedestroy().
 | PR #6772 removes explicit calls; resources are freed when out of scope.
 +-------------------------------------------------------------------------+
*/

$basePath = dirname(__DIR__, 2);

// --- Static analysis: no deprecated calls in changed files ---

test('cli/add_site.php contains no curl_close calls', function () use ($basePath) {
	$path = $basePath . '/cli/add_site.php';
	$contents = file_get_contents($path);

	expect($contents)->not->toContain('curl_close(');
});

test('lib/auth.php contains no curl_close calls', function () use ($basePath) {
	$path = $basePath . '/lib/auth.php';
	$contents = file_get_contents($path);

	expect($contents)->not->toContain('curl_close(');
});

test('lib/plugins.php contains no curl_close calls', function () use ($basePath) {
	$path = $basePath . '/lib/plugins.php';
	$contents = file_get_contents($path);

	expect($contents)->not->toContain('curl_close(');
});

test('lib/reports.php contains no imagedestroy calls', function () use ($basePath) {
	$path = $basePath . '/lib/reports.php';
	$contents = file_get_contents($path);

	expect($contents)->not->toContain('imagedestroy(');
});

test('lib/rrd.php contains no imagedestroy calls', function () use ($basePath) {
	$path = $basePath . '/lib/rrd.php';
	$contents = file_get_contents($path);

	expect($contents)->not->toContain('imagedestroy(');
});
