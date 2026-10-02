<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-x5pw-cg7j-mj4r / GHSA-2p3x-f5jm-rg6p: scripts/webhits.pl ran wc through
 * a two-argument piped open(), re-opening a shell with the log path embedded.
 * It now uses a list-form open with no shell.
 */

$root = dirname(__DIR__, 4);

test('webhits.pl runs wc through a list-form open with no shell', function () use ($root) {
	$source = file_get_contents($root . '/scripts/webhits.pl');

	expect($source)->toContain("open(PROCESS, '-|', 'wc', '-l', '--', \$log_path)")
		->and($source)->not->toContain('open(PROCESS,"wc -l $log_path |")');
});
