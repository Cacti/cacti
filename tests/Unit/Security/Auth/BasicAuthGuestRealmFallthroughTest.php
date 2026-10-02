<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-4rmr-wvjq-qxc2: include/auth.php returned true from the Basic Auth and
 * guest branches before the per-page realm check. Both branches now fall
 * through to the realm check.
 */

$root = dirname(__DIR__, 4);

test('the Basic Auth and guest branches fall through to the per-page realm check', function () use ($root) {
	$source = file_get_contents($root . '/include/auth.php');

	expect($source)->not->toContain("\$_SESSION[SESS_CLIENT_ADDR]]);\n\n\t\t\treturn true;")
		->and($source)->not->toContain("[\$_SESSION[SESS_USER_ID]]);\n\n\t\treturn true;")
		->and(substr_count($source, 'GHSA-4rmr-wvjq-qxc2'))->toBe(2);
});
