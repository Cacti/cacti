<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-gp82-qhrg-crv7 / CVE-2026-39955.
 *
 * Pre-Authentication SQL Injection via unanchored FILTER_VALIDATE_REGEXP
 * in graph_view.php. The 'thumbnails' parameter regex was '(true|false)'
 * without ^ / $ anchors, so "true' UNION SELECT..." would pass validation.
 * Fix anchors the regex so the entire input must match.
 */

test('graph_view.php thumbnails regex is anchored', function () {
	$src = file_get_contents(__DIR__ . '/../../graph_view.php');
	expect($src)->not->toBeFalse();
	expect($src)->toContain("'regexp' => '^(true|false)\$'");
	expect($src)->not->toMatch("/'regexp' => '\\(true\\|false\\)'/");
});

test('anchored regex behavior', function () {
	$pattern = '/^(true|false)$/';
	expect(preg_match($pattern, 'true'))->toBe(1);
	expect(preg_match($pattern, 'false'))->toBe(1);
	expect(preg_match($pattern, "true' OR 1=1--"))->toBe(0);
	expect(preg_match($pattern, "fasetrue"))->toBe(0);
});
