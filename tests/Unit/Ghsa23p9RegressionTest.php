<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-23p9-hfcc-w864.
 *
 * Fix: verify_peer=true; allow_self_signed=false; follow_location=0 in http context
 *
 * Source-scan invariants that would fail if the fix were reverted. Each
 * assertion targets a pattern introduced by the fix commit.
 */

test('lib/functions.php contains the 23p9 fix', function () {
	$src = file_get_contents(__DIR__ . '/../../lib/functions.php');
	expect($src)->not->toBeFalse();
	// Fix-specific assertion anchors below:
	expect($src)->toContain("'verify_peer'");
	expect($src)->toMatch('/verify_peer.+=>\s*true/');
	expect($src)->toContain("'follow_location'");
});
