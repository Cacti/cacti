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

// --- Static analysis: no deprecated calls in changed files ---

$basePath = CACTI_PATH_BASE;

test('cli/add_site.php contains no curl_close calls', function () use ($basePath) {
	$contents = file_get_contents(CACTI_PATH_BASE . '/cli/add_site.php');

	expect($contents)->not->toContain('curl_close(');
});

test('lib/auth.php contains no curl_close calls', function () use ($basePath) {
	$contents = file_get_contents(CACTI_PATH_LIBRARY . '/auth.php');

	expect($contents)->not->toContain('curl_close(');
});

test('lib/plugins.php contains no curl_close calls', function () use ($basePath) {
	$contents = file_get_contents(CACTI_PATH_LIBRARY . '/plugins.php');

	expect($contents)->not->toContain('curl_close(');
});

test('lib/reports.php contains no imagedestroy calls', function () use ($basePath) {
	$contents = file_get_contents(CACTI_PATH_LIBRARY . '/reports.php');

	expect($contents)->not->toContain('imagedestroy(');
});

test('lib/rrd.php contains no imagedestroy calls', function () use ($basePath) {
	$contents = file_get_contents(CACTI_PATH_LIBRARY . '/rrd.php');

	expect($contents)->not->toContain('imagedestroy(');
});
