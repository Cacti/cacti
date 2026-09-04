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
*/

$root = dirname(__DIR__, 3);

require_once $root . '/tests/tools/check_vendor_policy.php';

test('Composer output is rejected while legacy paths remain allowed', function () {
	$tracked = [
		'include/vendor/composer/index.php',
		'include/vendor/composer/installed.json',
		'include/vendor/csrf/README.md',
	];

	expect(cacti_vendor_policy_unexpected_files($tracked))
		->toBe(['include/vendor/composer/installed.json']);
});

test('every legacy allowlist entry still owns a tracked file', function () use ($root) {
	$tracked = cacti_vendor_policy_tracked_files($root);

	foreach (cacti_vendor_policy_legacy_paths() as $legacy_path) {
		$matches = array_filter($tracked, fn (string $file) : bool => $file === $legacy_path || str_starts_with($file, $legacy_path));

		expect($matches)->not->toBeEmpty($legacy_path . ' is a stale allowlist entry');
	}
});

test('the Composer directory keeps its release directory-listing guard', function () use ($root) {
	$guard = 'include/vendor/composer/index.php';

	expect(cacti_vendor_policy_tracked_files($root))->toContain($guard)
		->and(file_get_contents($root . '/' . $guard))->toContain('Location:../index.php');
});
