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

/*
 * Source-scan and logic tests for the import_package() path-traversal guard
 * in lib/import.php (GHSA-vp35-4h28-r883).
 *
 * The guard resolves the target directory with realpath() and verifies it
 * falls within CACTI_PATH_BASE/scripts or CACTI_PATH_BASE/resource before
 * allowing any file write.  These tests verify that pattern is present and
 * exercise the boundary logic in isolation.
 */

// --- source-scan helpers ---

function getImportPackageSource(): string {
	$src = file_get_contents(__DIR__ . '/../../lib/import.php');
	expect($src)->not->toBeFalse('Failed to read lib/import.php');
	return $src;
}

// --- source-scan: guard structure is present ---

test('import.php boundary guard resolves target dir with realpath', function () {
	$src = getImportPackageSource();
	expect($src)->toContain('$resolved_dir = realpath(dirname($filename))');
});

test('import.php boundary guard resolves both scripts and resource bases', function () {
	$src = getImportPackageSource();
	expect($src)->toContain("realpath(CACTI_PATH_BASE . '/scripts')");
	expect($src)->toContain("realpath(CACTI_PATH_BASE . '/resource')");
});

test('import.php guard blocks when resolved_dir is false', function () {
	$src = getImportPackageSource();
	expect($src)->toContain('$resolved_dir === false');
});

test('import.php guard uses str_starts_with with DIRECTORY_SEPARATOR', function () {
	$src = getImportPackageSource();
	expect($src)->toContain('str_starts_with($resolved_dir . DIRECTORY_SEPARATOR, $allowed_base_scripts  . DIRECTORY_SEPARATOR)');
	expect($src)->toContain('str_starts_with($resolved_dir . DIRECTORY_SEPARATOR, $allowed_base_resource . DIRECTORY_SEPARATOR)');
});

test('import.php guard logs FATAL on boundary violation', function () {
	$src = getImportPackageSource();
	expect($src)->toContain("cacti_log('FATAL: Package file destination outside allowed boundaries:");
});

test('import.php no longer uses blocklist approach for traversal detection', function () {
	$src = getImportPackageSource();
	// The old blocklist (str_contains($name, '..')) was replaced by realpath().
	// Verify the blocklist guard is gone from the file-write loop.
	expect($src)->not->toContain("str_contains(\$name, '..')");
});

// --- inline boundary logic with real temp filesystem ---

/*
 * Replicates the guard logic from import_package() so it can be exercised
 * in isolation without loading the full Cacti bootstrap.
 */
function importBoundaryAllowed(string $base, string $name): bool {
	$filename              = $base . "/$name";
	$allowed_base_scripts  = realpath($base . '/scripts');
	$allowed_base_resource = realpath($base . '/resource');
	$resolved_dir          = realpath(dirname($filename));

	if ($resolved_dir === false) {
		return false;
	}

	$in_scripts  = $allowed_base_scripts  !== false && str_starts_with($resolved_dir . DIRECTORY_SEPARATOR, $allowed_base_scripts  . DIRECTORY_SEPARATOR);
	$in_resource = $allowed_base_resource !== false && str_starts_with($resolved_dir . DIRECTORY_SEPARATOR, $allowed_base_resource . DIRECTORY_SEPARATOR);

	return $in_scripts || $in_resource;
}

/*
 * Create a temporary directory tree that mirrors CACTI_PATH_BASE:
 *   $tmp/scripts/
 *   $tmp/resource/
 *   $tmp/webroot/   (must not be reachable by the guard)
 */
function makeTempBase(): string {
	$tmp = sys_get_temp_dir() . '/cacti_import_test_' . getmypid();
	mkdir($tmp . '/scripts',  0755, true);
	mkdir($tmp . '/resource', 0755, true);
	mkdir($tmp . '/webroot',  0755, true);
	return $tmp;
}

function removeTempBase(string $base): void {
	foreach (['scripts', 'resource', 'webroot'] as $dir) {
		$full = $base . "/$dir";

		if (is_dir($full)) {
			rmdir($full);
		}
	}

	if (is_dir($base)) {
		rmdir($base);
	}
}

// --- happy paths ---

test('valid scripts/ path within boundary is allowed', function () {
	$base = makeTempBase();
	expect(importBoundaryAllowed($base, 'scripts/myscript.sh'))->toBeTrue();
	removeTempBase($base);
});

test('valid resource/ path within boundary is allowed', function () {
	$base = makeTempBase();
	expect(importBoundaryAllowed($base, 'resource/host.xml'))->toBeTrue();
	removeTempBase($base);
});

test('trailing slash on scripts/ resolves to directory itself which is within boundary', function () {
	$base = makeTempBase();
	// dirname('scripts/') resolves to $base/scripts — inside the boundary.
	// The actual write would fail (fopen on a directory) but the guard permits it.
	expect(importBoundaryAllowed($base, 'scripts/'))->toBeTrue();
	removeTempBase($base);
});

// --- path traversal attacks ---

test('traversal from scripts/ via ../ to webroot is blocked', function () {
	$base = makeTempBase();
	// scripts/../../webroot/evil.php — dirname resolves to $base/webroot, not scripts/.
	expect(importBoundaryAllowed($base, 'scripts/../../webroot/evil.php'))->toBeFalse();
	removeTempBase($base);
});

test('traversal from resource/ via ../ to webroot is blocked', function () {
	$base = makeTempBase();
	expect(importBoundaryAllowed($base, 'resource/../../webroot/evil.php'))->toBeFalse();
	removeTempBase($base);
});

test('deep traversal escaping base directory is blocked', function () {
	$base = makeTempBase();
	expect(importBoundaryAllowed($base, 'scripts/../../../etc/passwd'))->toBeFalse();
	removeTempBase($base);
});

test('excessive traversal segments are blocked', function () {
	$base = makeTempBase();
	$name = 'scripts/' . str_repeat('../', 20) . 'evil.php';
	expect(importBoundaryAllowed($base, $name))->toBeFalse();
	removeTempBase($base);
});

// --- edge cases ---

test('null byte in path is blocked', function () {
	$base = makeTempBase();
	// realpath() returns false for paths containing null bytes.
	$name = "scripts/\x00../evil";
	expect(importBoundaryAllowed($base, $name))->toBeFalse();
	removeTempBase($base);
});

test('empty name is blocked', function () {
	$base = makeTempBase();
	// dirname('') == '.'; realpath('.') resolves to cwd, not under scripts/ or resource/.
	expect(importBoundaryAllowed($base, ''))->toBeFalse();
	removeTempBase($base);
});

test('absolute path embedding scripts/ substring is blocked', function () {
	$base = makeTempBase();
	// $base . "//tmp/scripts/evil.php" — dirname resolves outside the base.
	expect(importBoundaryAllowed($base, '/tmp/scripts/evil.php'))->toBeFalse();
	removeTempBase($base);
});

// --- failure paths ---

test('non-existent subdirectory under scripts/ is blocked', function () {
	$base = makeTempBase();
	// realpath() returns false for paths that do not exist on disk.
	expect(importBoundaryAllowed($base, 'scripts/does/not/exist/file.php'))->toBeFalse();
	removeTempBase($base);
});

test('non-existent subdirectory under resource/ is blocked', function () {
	$base = makeTempBase();
	expect(importBoundaryAllowed($base, 'resource/deep/missing/file.xml'))->toBeFalse();
	removeTempBase($base);
});

test('missing scripts/ directory in base causes scripts/ writes to be blocked', function () {
	$base = sys_get_temp_dir() . '/cacti_import_noscripts_' . getmypid();
	mkdir($base . '/resource', 0755, true);
	// No scripts/ dir — realpath($base/scripts) returns false; $in_scripts always false.
	expect(importBoundaryAllowed($base, 'scripts/file.sh'))->toBeFalse();
	rmdir($base . '/resource');
	rmdir($base);
});
