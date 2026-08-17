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
	expect($src)->toMatch('/\$resolved_dir\s*=\s*false\s*;/');
	expect($src)->toMatch('/\$target_dir\s*=\s*dirname\(\$filename\)\s*;/');
	expect($src)->toMatch('/\$resolved_dir\s*=\s*realpath\(\$target_dir\)/');
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

test('import.php guard verifies resolved dir is inside each allowed base', function () {
	$src = getImportPackageSource();
	expect($src)->toMatch('/strpos\(\s*\$normalized_resolved,\s*\$normalized_scripts\s*\.\s*\'\/\'\s*\)\s*===\s*0/');
	expect($src)->toMatch('/strpos\(\s*\$normalized_resolved,\s*\$normalized_resource\s*\.\s*\'\/\'\s*\)\s*===\s*0/');
});

test('import.php guard logs FATAL on boundary violation', function () {
	$src = getImportPackageSource();
	expect($src)->toContain("cacti_log('FATAL: Package file destination outside allowed boundaries:");
});

test('import.php uses a segment-aware fast reject before the ancestor boundary check', function () {
	$src = getImportPackageSource();
	expect($src)->toMatch('/preg_match\(\s*[\'"]#\(\^\|\/\)\\\\\.\\\\\.\(\/\|\$\)#[\'"]\s*,\s*\$normalized_name\s*\)/');
	expect($src)->toMatch('/strpos\(\s*\$name\s*,\s*chr\(0\)\s*\)\s*!==\s*false/');
});

// --- inline boundary logic with real temp filesystem ---

/*
 * Replicates the guard logic from import_package() so it can be exercised
 * in isolation without loading the full Cacti bootstrap.
 */
function importBoundaryAllowed(string $base, string $name): bool {
	if (strpos($name, chr(0)) !== false) {
		return false;
	}

	$filename              = $base . "/$name";
	$allowed_base_scripts  = realpath($base . '/scripts');
	$allowed_base_resource = realpath($base . '/resource');
	$target_dir            = dirname($filename);
	$resolved_dir          = false;

	while ($target_dir !== dirname($target_dir)) {
		$resolved_dir = realpath($target_dir);

		if ($resolved_dir !== false) {
			break;
		}

		$target_dir = dirname($target_dir);
	}

	if ($resolved_dir === false) {
		return false;
	}

	$normalized_resolved = rtrim(str_replace('\\', '/', $resolved_dir), '/');
	$normalized_scripts  = ($allowed_base_scripts === false ? false : rtrim(str_replace('\\', '/', $allowed_base_scripts), '/'));
	$normalized_resource = ($allowed_base_resource === false ? false : rtrim(str_replace('\\', '/', $allowed_base_resource), '/'));
	$in_scripts          = $normalized_scripts !== false
		&& ($normalized_resolved === $normalized_scripts || strpos($normalized_resolved, $normalized_scripts . '/') === 0);
	$in_resource         = $normalized_resource !== false
		&& ($normalized_resolved === $normalized_resource || strpos($normalized_resolved, $normalized_resource . '/') === 0);

	return $in_scripts || $in_resource;
}

/*
 * Create a temporary directory tree that mirrors CACTI_PATH_BASE:
 *   $tmp/scripts/
 *   $tmp/resource/
 *   $tmp/webroot/   (must not be reachable by the guard)
 */
function makeTempBase(): string {
	$tmp = sys_get_temp_dir() . '/cacti_import_test_' . bin2hex(random_bytes(4));
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

test('new nested scripts/ subdirectory is allowed when its first existing ancestor is in bounds', function () {
	$base = makeTempBase();
	expect(importBoundaryAllowed($base, 'scripts/vendor/foo/bar.sh'))->toBeTrue();
	removeTempBase($base);
});

test('new nested resource/ subdirectory is allowed when its first existing ancestor is in bounds', function () {
	$base = makeTempBase();
	expect(importBoundaryAllowed($base, 'resource/snmp/vendor/query.xml'))->toBeTrue();
	removeTempBase($base);
});

test('trailing slash on scripts/ resolves to directory itself which is within boundary', function () {
	$base = makeTempBase();
	// dirname("$base/scripts/") resolves to $base, so the boundary guard rejects
	// it before any write attempt.
	expect(importBoundaryAllowed($base, 'scripts/'))->toBeFalse();
	removeTempBase($base);
});

// --- path traversal attacks ---

test('traversal from scripts/ via ../ to webroot is blocked', function () {
	$base = makeTempBase();
	// scripts/../../webroot/evil.php; dirname resolves to $base/webroot, not scripts/.
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
	// Import hardening rejects NUL before attempting any path resolution.
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
	// $base . "//tmp/scripts/evil.php"; dirname resolves outside the base.
	expect(importBoundaryAllowed($base, '/tmp/scripts/evil.php'))->toBeFalse();
	removeTempBase($base);
});

// --- failure paths ---

test('non-existent nested subdirectory under scripts/ is allowed when it stays within scripts/', function () {
	$base = makeTempBase();
	expect(importBoundaryAllowed($base, 'scripts/does/not/exist/file.php'))->toBeTrue();
	removeTempBase($base);
});

test('non-existent nested subdirectory under resource/ is allowed when it stays within resource/', function () {
	$base = makeTempBase();
	expect(importBoundaryAllowed($base, 'resource/deep/missing/file.xml'))->toBeTrue();
	removeTempBase($base);
});

test('missing scripts/ directory in base causes scripts/ writes to be blocked', function () {
	// Unique random suffix per test run; avoids collisions on parallel/failed cleanup.
	$base = sys_get_temp_dir() . '/cacti_import_noscripts_' . bin2hex(random_bytes(4));
	mkdir($base . '/resource', 0755, true);
	// No scripts/ dir; realpath($base/scripts) returns false; $in_scripts always false.
	expect(importBoundaryAllowed($base, 'scripts/file.sh'))->toBeFalse();
	rmdir($base . '/resource');
	rmdir($base);
});
