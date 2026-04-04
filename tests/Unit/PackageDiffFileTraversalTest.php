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

/**
 * Tests for the path traversal guard in package_diff_file().
 *
 * The guard calls realpath() on both CACTI_PATH_BASE and the caller-supplied
 * filename, then uses strncmp() to verify the resolved file path starts with
 * the resolved base followed by DIRECTORY_SEPARATOR.  If either realpath()
 * call returns false (non-existent path, null byte) or the prefix check fails,
 * the function returns immediately without touching the filesystem.
 *
 * Two suites:
 *   1. Source-scan: verifies the guard structure is textually present.
 *   2. Runtime boundary: exercises the guard predicate in isolation.
 */

// ---------------------------------------------------------------------------
// Source-scan suite
// ---------------------------------------------------------------------------

$src = file_get_contents(__DIR__ . '/../../package_import.php');

test('package_diff_file calls realpath on CACTI_PATH_BASE', function () use ($src) {
	expect($src)->toContain('realpath(CACTI_PATH_BASE)');
});

test('package_diff_file calls realpath on the composed filename path', function () use ($src) {
	// Guard resolves CACTI_PATH_BASE . '/' . $filename before comparison.
	expect($src)->toContain('realpath(CACTI_PATH_BASE . \'/\' . $filename)');
});

test('package_diff_file uses strncmp for boundary prefix check', function () use ($src) {
	expect($src)->toContain('strncmp($real_file, $real_base . DIRECTORY_SEPARATOR, strlen($real_base) + 1)');
});

test('package_diff_file returns early when realpath returns false', function () use ($src) {
	// Guard must short-circuit on $real_base === false or $real_file === false.
	expect($src)->toContain('$real_base === false || $real_file === false');
});

test('package_diff_file uses $real_file not raw $filename for file_get_contents', function () use ($src) {
	// I/O after the guard must use the validated canonical path.
	$guard_pos   = strpos($src, 'strncmp($real_file, $real_base . DIRECTORY_SEPARATOR');
	$read_pos    = strpos($src, 'file_get_contents($real_file)');
	$raw_read    = strpos($src, 'file_get_contents($filename)');

	expect($guard_pos)->not->toBeFalse()
		->and($read_pos)->not->toBeFalse()
		->and($guard_pos)->toBeLessThan($read_pos)
		->and($raw_read)->toBeFalse();
});

test('guard check precedes any file_get_contents call', function () use ($src) {
	$guard_pos = strpos($src, 'strncmp($real_file,');
	$read_pos  = strpos($src, 'file_get_contents(');

	expect($guard_pos)->not->toBeFalse()
		->and($read_pos)->not->toBeFalse()
		->and($guard_pos)->toBeLessThan($read_pos);
});

// ---------------------------------------------------------------------------
// Runtime boundary suite
// ---------------------------------------------------------------------------

/*
 * Replicates the guard predicate from package_diff_file() for isolated testing
 * without the Cacti bootstrap (DB, constants, session).
 *
 * Returns true when $file_path resolves within $base_dir.
 */
function packageDiffBoundaryAllowed(string $base_dir, string $file_path): bool {
	$real_base = realpath($base_dir);
	$real_file = realpath($file_path);

	if ($real_base === false || $real_file === false) {
		return false;
	}

	return strncmp($real_file, $real_base . DIRECTORY_SEPARATOR, strlen($real_base) + 1) === 0;
}

/*
 * Minimal temp tree:
 *   $tmp/cacti/         <- Cacti base
 *     formats/          <- typical location for package files
 *   $tmp/outside/       <- sibling, must not be reachable via traversal
 */
function makePackageTempBase(): array {
	$tmp  = sys_get_temp_dir() . '/cacti_pkg_test_' . getmypid();
	$base = $tmp . '/cacti';
	mkdir($base . '/formats', 0755, true);
	mkdir($tmp . '/outside',  0755, true);

	return [$tmp, $base];
}

function removePackageTempBase(string $tmp, string $base): void {
	foreach ([
		$base . '/formats',
		$base,
		$tmp . '/outside',
		$tmp,
	] as $path) {
		if (is_dir($path)) {
			@rmdir($path);
		}
	}
}

// --- happy path ---

test('file inside Cacti base is allowed', function () {
	[$tmp, $base] = makePackageTempBase();
	$file = $base . '/formats/package.xml';
	file_put_contents($file, '<package/>');

	$result = packageDiffBoundaryAllowed($base, $file);

	unlink($file);
	removePackageTempBase($tmp, $base);

	expect($result)->toBeTrue();
});

// --- traversal: ../../etc/passwd style ---

test('traversal via ../ to sibling directory is blocked', function () {
	[$tmp, $base] = makePackageTempBase();
	$evil = $tmp . '/outside/passwd';
	file_put_contents($evil, 'root:x:0:0');

	// Mirrors CACTI_PATH_BASE . '/formats/../../../outside/passwd'
	$traversal = $base . '/formats/../../outside/passwd';
	$result    = packageDiffBoundaryAllowed($base, $traversal);

	unlink($evil);
	removePackageTempBase($tmp, $base);

	expect($result)->toBeFalse();
});

test('absolute path outside base is blocked even when file exists', function () {
	[$tmp, $base] = makePackageTempBase();
	// sys_get_temp_dir() itself exists but is not inside $base.
	$result = packageDiffBoundaryAllowed($base, sys_get_temp_dir());

	removePackageTempBase($tmp, $base);

	expect($result)->toBeFalse();
});

// --- null bytes ---

test('null byte in filename is blocked because realpath returns false for non-existent path', function () {
	[$tmp, $base] = makePackageTempBase();
	// A null byte makes the composed path non-existent on the real filesystem,
	// so realpath() returns false and the guard rejects it.
	$result = packageDiffBoundaryAllowed($base, $base . "/formats/safe.xml\x00../../outside/passwd");

	removePackageTempBase($tmp, $base);

	expect($result)->toBeFalse();
});

// --- non-existent file ---

test('non-existent filename is blocked because realpath returns false', function () {
	[$tmp, $base] = makePackageTempBase();
	$result = packageDiffBoundaryAllowed($base, $base . '/formats/does-not-exist.xml');

	removePackageTempBase($tmp, $base);

	expect($result)->toBeFalse();
});

// --- deep traversal ---

test('deep traversal with many ../ segments is blocked', function () {
	[$tmp, $base] = makePackageTempBase();
	$result = packageDiffBoundaryAllowed($base, $base . '/formats/' . str_repeat('../', 10) . 'etc/passwd');

	removePackageTempBase($tmp, $base);

	expect($result)->toBeFalse();
});

// --- dot-dot resolving inside base ---

test('dot-dot that resolves inside base is allowed after realpath canonicalisation', function () {
	[$tmp, $base] = makePackageTempBase();
	$file = $base . '/formats/package.xml';
	file_put_contents($file, '<package/>');

	// Goes up one level then back down — net resolved path is still inside base.
	$with_dotdot = $base . '/formats/../formats/package.xml';
	$result      = packageDiffBoundaryAllowed($base, $with_dotdot);

	unlink($file);
	removePackageTempBase($tmp, $base);

	expect($result)->toBeTrue();
});

// --- symlink whose target is outside base ---

test('symlink inside base pointing outside base is blocked', function () {
	[$tmp, $base] = makePackageTempBase();
	$external = sys_get_temp_dir() . '/cacti_pkg_ext_' . getmypid();
	mkdir($external, 0755);
	$ext_file = $external . '/secret.txt';
	file_put_contents($ext_file, 'secret');

	$link = $base . '/formats/evil-link.xml';
	symlink($ext_file, $link);

	$result = packageDiffBoundaryAllowed($base, $link);

	unlink($link);
	unlink($ext_file);
	rmdir($external);
	removePackageTempBase($tmp, $base);

	// realpath() follows the symlink to its canonical target outside base.
	expect($result)->toBeFalse();
});

// --- symlink whose target is inside base ---

test('symlink inside base pointing inside base is allowed', function () {
	[$tmp, $base] = makePackageTempBase();
	$target = $base . '/formats/real.xml';
	file_put_contents($target, '<package/>');
	$link = $base . '/formats/link.xml';
	symlink($target, $link);

	$result = packageDiffBoundaryAllowed($base, $link);

	unlink($link);
	unlink($target);
	removePackageTempBase($tmp, $base);

	expect($result)->toBeTrue();
});

// --- empty string ---

test('empty string filename is blocked because realpath returns false', function () {
	[$tmp, $base] = makePackageTempBase();
	$result = packageDiffBoundaryAllowed($base, '');

	removePackageTempBase($tmp, $base);

	expect($result)->toBeFalse();
});
