<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-9mf9-v3mh-89cv.
 *
 * Fix: rawurlencode keys and values in remote agent URL
 *
 * Source-scan invariants that would fail if the fix were reverted. Each
 * assertion targets a pattern introduced by the fix commit.
 */

test('graph_image.php contains the 9mf9 fix', function () {
	$src = file_get_contents(__DIR__ . '/../../graph_image.php');
	expect($src)->not->toBeFalse();
	// Fix-specific assertion anchors below:
	expect($src)->toContain('rawurlencode((string)$variable)');
	expect($src)->toContain('rawurlencode((string)$value)');
});

test('graph_json.php contains the 9mf9 fix', function () {
	$src = file_get_contents(__DIR__ . '/../../graph_json.php');
	expect($src)->not->toBeFalse();
	// Fix-specific assertion anchors below:
	expect($src)->toContain('rawurlencode((string)$variable)');
	expect($src)->toContain('rawurlencode((string)$value)');
});
