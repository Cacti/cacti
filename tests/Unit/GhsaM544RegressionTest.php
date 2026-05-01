<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-m544-32jr-54xw.
 *
 * Fix: json_encode session referer in JS context
 *
 * Source-scan invariants that would fail if the fix were reverted. Each
 * assertion targets a pattern introduced by the fix commit.
 */

test('auth_profile.php contains the m544 fix', function () {
	$src = file_get_contents(__DIR__ . '/../../auth_profile.php');
	expect($src)->not->toBeFalse();
	// Fix-specific assertion anchors below:
	expect($src)->toContain('json_encode(');
});
