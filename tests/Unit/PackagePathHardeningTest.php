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
 * Tests for hardening of lib/package.php find_paths().
 *
 * Issue #6933: find_paths() accepted any file_exists() path with no check that
 * it falls within CACTI_PATH_BASE, allowing sensitive files to be bundled into
 * exported packages. Fixed with a realpath() boundary check.
 *
 * Issue #6934: str_contains args were reversed in the excluded_basenames loop —
 * str_contains($binary, basename($part)) always returned false. Fixed to
 * str_contains(basename($part), $binary).
 */

function getPackageFindPathsSource(): string {
	$src = file_get_contents(__DIR__ . '/../../lib/package.php');
	expect($src)->not->toBeFalse('Failed to read lib/package.php');
	$start = strpos($src, 'function find_paths(');
	expect($start)->not->toBeFalse('find_paths() must exist in lib/package.php');
	$end = strpos($src, "\nfunction ", $start + 1);

	if ($end === false) {
		$end = strlen($src);
	}

	return substr($src, $start, $end - $start);
}

// --- Fix #6934: exact binary exclusion against basename() ---

test('find_paths binary exclusion does not use reversed str_contains args', function () {
	$source = getPackageFindPathsSource();
	$bad = '/str_contains\s*\(\s*\$binary\s*,\s*basename\s*\(\s*\$part\s*\)\s*\)/';
	expect(preg_match($bad, $source))->toBe(0,
		'str_contains($binary, basename($part)) is reversed — binary is never the haystack'
	);
});

test('find_paths binary exclusion uses exact basename matching against excluded basenames', function () {
	$source = getPackageFindPathsSource();
	$good = '/in_array\s*\(\s*basename\s*\(\s*\$part\s*\)\s*,\s*\$excluded_basenames\s*,\s*true\s*\)/';
	expect(preg_match($good, $source))->toBe(1,
		'find_paths must use in_array(basename($part), $excluded_basenames, true)'
	);
});

// --- Fix #6933: realpath boundary check ---

test('find_paths resolves exported paths with realpath before accepting them', function () {
	$source = getPackageFindPathsSource();
	$pattern = '/realpath\s*\(\s*CACTI_PATH_BASE/';
	expect(preg_match($pattern, $source))->toBe(1,
		'find_paths must verify resolved paths fall within CACTI_PATH_BASE via realpath()'
	);
});

test('find_paths uses a prefix guard to enforce the CACTI_PATH_BASE boundary', function () {
	$source = getPackageFindPathsSource();
	$pattern = '/strpos\s*\(\s*\$path_prefix\s*,\s*\$base_prefix\s*\)\s*!==\s*0/';
	expect(preg_match($pattern, $source))->toBe(1,
		'find_paths must use a prefix guard to enforce the base directory'
	);
});

test('find_paths rejects paths where realpath returns false', function () {
	$source = getPackageFindPathsSource();
	$pattern = '/\$real_part\s*===\s*false/';
	expect(preg_match($pattern, $source))->toBe(1,
		'find_paths must continue (skip) when realpath() returns false'
	);
});

test('find_paths string type declaration prevents non-string inputs', function () {
	$source = getPackageFindPathsSource();
	// PHP 8.1+ throws TypeError when a non-string is passed; the type hint is the guard.
	expect($source)->toContain('function find_paths(string $input,');
});

test('find_paths boundary check uses DIRECTORY_SEPARATOR to prevent prefix collision', function () {
	$source = getPackageFindPathsSource();
	// Appending DIRECTORY_SEPARATOR before str_starts_with prevents $base/scripts-evil
	// from matching $base/scripts when the attacker controls the path prefix.
	expect($source)->toContain('DIRECTORY_SEPARATOR');
});

test('find_paths records skipped out-of-bounds files and logs the drop', function () {
	$source = getPackageFindPathsSource();
	expect($source)->toContain("\$mpaths[] = ['opath' => \$opath, 'file' => \$part]");
	expect($source)->toContain("Skipping package path outside CACTI_PATH_BASE");
});

// --- inline boundary logic with real temp filesystem ---

/*
 * Replicates the realpath boundary guard from find_paths() so it can be
 * exercised against a real temp filesystem without the Cacti bootstrap.
 */
function findPathsBoundaryAllowed(string $base, string $part): bool {
	$real_base = realpath($base);
	$real_part = realpath($part);

	if ($real_base === false || $real_part === false) {
		return false;
	}

	return strpos($real_part . DIRECTORY_SEPARATOR, $real_base . DIRECTORY_SEPARATOR) === 0;
}

function makeFindPathsTempBase(): string {
	$tmp = sys_get_temp_dir() . '/cacti_fp_test_' . bin2hex(random_bytes(4));
	mkdir($tmp . '/scripts', 0755, true);
	touch($tmp . '/scripts/query.xml');
	return $tmp;
}

function removeFindPathsTempBase(string $base): void {
	if (file_exists($base . '/scripts/query.xml')) {
		unlink($base . '/scripts/query.xml');
	}

	if (is_dir($base . '/scripts')) {
		rmdir($base . '/scripts');
	}

	if (is_dir($base)) {
		rmdir($base);
	}
}

// --- happy path ---

test('find_paths boundary allows valid existing file inside base directory', function () {
	$base = makeFindPathsTempBase();
	$part = $base . '/scripts/query.xml';
	expect(findPathsBoundaryAllowed($base, $part))->toBeTrue();
	removeFindPathsTempBase($base);
});

// --- dotdot paths that canonicalize inside the base ---

test('find_paths boundary allows dotdot path that resolves inside base', function () {
	$base = makeFindPathsTempBase();
	// scripts/../scripts/query.xml resolves to $base/scripts/query.xml — still inside.
	$part = $base . '/scripts/../scripts/query.xml';
	expect(findPathsBoundaryAllowed($base, $part))->toBeTrue();
	removeFindPathsTempBase($base);
});

// --- dotdot paths that canonicalize outside the base ---

test('find_paths boundary blocks dotdot path that resolves outside base', function () {
	$base = makeFindPathsTempBase();
	// scripts/../../etc/passwd escapes the base; realpath resolves to /etc/passwd.
	$part = $base . '/scripts/../../etc/passwd';
	expect(findPathsBoundaryAllowed($base, $part))->toBeFalse();
	removeFindPathsTempBase($base);
});

// --- path exactly equal to the base directory ---

test('find_paths boundary allows path exactly equal to base directory', function () {
	$base = makeFindPathsTempBase();
	// realpath($base) == $real_base; str_starts_with(X . DS, X . DS) is true.
	expect(findPathsBoundaryAllowed($base, $base))->toBeTrue();
	removeFindPathsTempBase($base);
});
