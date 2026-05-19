<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Coverage backfill for PR #7148. The dash-prefix guard in
 * cli/push_out_hosts.php originally referenced an undefined $binary;
 * the fix renamed it to $php_binary and switched the wrong return 1
 * to exit(1). Pin both so a future merge cannot quietly regress.
 */

$source = file_get_contents(__DIR__ . '/../../cli/push_out_hosts.php');

test('dash-prefix guard reads $php_binary, not undefined $binary', function () use ($source) {
	expect($source)->toContain("strpos(trim(\$php_binary), '-')");
	expect(substr_count($source, '$php_binary'))->toBeGreaterThanOrEqual(4);
	expect(preg_match('/\\$binary\\b/', $source))->toBe(0);
});

test('dash-prefix branch exits non-zero (not return 1)', function () use ($source) {
	$marker = strpos($source, 'Rejected PHP binary starting with dash');
	expect($marker)->not->toBeFalse();

	$slice = substr($source, $marker, 200);
	expect($slice)->toContain('exit(1);');
	expect($slice)->not->toContain('return 1;');
});
