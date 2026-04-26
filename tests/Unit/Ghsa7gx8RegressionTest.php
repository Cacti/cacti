<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-7gx8-f5q4-86mv.
 *
 * Fix: html_escape description and notification in tooltip
 *
 * Source-scan invariants that would fail if the fix were reverted. Each
 * assertion targets a pattern introduced by the fix commit.
 */

test('managers.php contains the 7gx8 fix', function () {
	$src = file_get_contents(__DIR__ . '/../../managers.php');
	expect($src)->not->toBeFalse();
	// Fix-specific assertion anchors below:
	expect($src)->toContain("html_escape(\$item['name'])");
	expect($src)->toContain("html_escape(\$item['description'])");
});
