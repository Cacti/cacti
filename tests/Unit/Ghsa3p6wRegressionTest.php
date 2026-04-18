<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-3p6w-h4wv-6x7g.
 *
 * Fix: cacti_validate_sort_column() allowlist in utilities.php sort_column sites
 *
 * Source-scan invariants that would fail if the fix were reverted. Each
 * assertion targets a pattern introduced by the fix commit.
 */

test('utilities.php contains the 3p6w fix', function () {
	$src = file_get_contents(__DIR__ . '/../../utilities.php');
	expect($src)->not->toBeFalse();
	// Fix-specific assertion anchors below:
	expect($src)->toContain('cacti_validate_sort_column');
});
