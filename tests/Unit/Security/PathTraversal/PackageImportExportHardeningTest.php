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
 * Hardening for package export and import path and temp-file handling:
 *   - find_paths() confines an export to the scripts/ and resource/ subtrees,
 *     so unrelated in-tree files cannot be bundled into a signed package.
 *   - package_private_tmpdir() gives package import an unpredictable, mode
 *     0700 working directory instead of a guessable one created world-writable.
 *   - package_diff_file() confines the local file it reads to those same two
 *     subtrees, and import_package() refuses to write through a symbolic link.
 */

require_once dirname(__DIR__, 3) . '/Helpers/UnitStubs.php';

if (!defined('CACTI_PATH_BASE')) {
	define('CACTI_PATH_BASE', dirname(__DIR__, 4));
}

require_once CACTI_PATH_BASE . '/lib/package.php';
require_once CACTI_PATH_BASE . '/lib/import.php';

if (!function_exists('cacti_log')) {
	function cacti_log($message, $output = true, $environ = 'IMPORT', $level = 0) {
		return false;
	}
}

$packageImportSource = (string) file_get_contents(CACTI_PATH_BASE . '/package_import.php');
$importSource        = (string) file_get_contents(CACTI_PATH_BASE . '/lib/import.php');

// --- find_paths() boundary, exercised against the real tree ---

test('find_paths accepts a real file under scripts/', function () {
	$script = glob(CACTI_PATH_BASE . '/scripts/*.php')[0] ?? null;
	expect($script)->not->toBeNull('a scripts/ file is required for this test');

	$result = find_paths('<path_cacti>/scripts/' . basename($script));
	$files  = array_column($result['paths'], 'file');

	expect($files)->toContain(realpath($script));
});

test('find_paths accepts a real resource query file', function () {
	$xml = glob(CACTI_PATH_BASE . '/resource/snmp_queries/*.xml')[0] ?? null;
	expect($xml)->not->toBeNull('a resource/ file is required for this test');

	$result = find_paths('<path_cacti>/resource/snmp_queries/' . basename($xml));
	$files  = array_column($result['paths'], 'file');

	expect($files)->toContain(realpath($xml));
});

test('find_paths refuses an in-tree file outside scripts/ and resource/', function () {
	$target = CACTI_PATH_BASE . '/include/cacti_version';
	expect(is_file($target))->toBeTrue('include/cacti_version is required for this test');

	$result   = find_paths('<path_cacti>/include/cacti_version');
	$accepted = array_column($result['paths'], 'file');
	$missing  = array_column($result['missing_paths'], 'file');

	expect($accepted)->not->toContain(realpath($target))
		->and(cacti_sizeof($missing))->toBeGreaterThan(0);
});

// --- package_private_tmpdir() ---

test('package_private_tmpdir creates a private, mode 0700 directory', function () {
	$_SESSION = [];

	$dir = package_private_tmpdir();

	try {
		expect(is_dir($dir))->toBeTrue()
			->and(fileperms($dir) & 0777)->toBe(0700)
			->and(substr($dir, -1))->not->toBe('/');
	} finally {
		@rmdir($dir);
	}
});

test('package_private_tmpdir reuses one directory across a session but varies between sessions', function () {
	$_SESSION = [];
	$first    = package_private_tmpdir();
	$again    = package_private_tmpdir();

	$_SESSION = [];
	$other    = package_private_tmpdir();

	try {
		expect($again)->toBe($first)
			->and($other)->not->toBe($first);
	} finally {
		@rmdir($first);
		@rmdir($other);
	}
});

test('package_private_tmpdir does not key the directory name on the user id alone', function () use ($packageImportSource) {
	expect($packageImportSource)->not->toContain("sys_get_temp_dir() . '/package' . \$_SESSION[SESS_USER_ID]")
		->not->toContain('mkdir($tmp_dir, 0777');
});

// --- package_diff_file() boundary and symlink refusal (source guards) ---

test('package_diff_file confines the compared file to scripts/ and resource/', function () use ($packageImportSource) {
	$start = strpos($packageImportSource, 'function package_diff_file(');
	expect($start)->not->toBeFalse();

	$body = substr($packageImportSource, $start, strpos($packageImportSource, "\nfunction ", $start + 1) - $start);

	expect($body)->toContain("foreach (['scripts', 'resource'] as \$root) {")
		->toContain('$within_package_roots');
});

test('import_package routes the package-file write through the symlink guard', function () use ($importSource) {
	$start = strpos($importSource, 'function import_package(');
	expect($start)->not->toBeFalse();

	$body = substr($importSource, $start, strpos($importSource, "\nfunction ", $start + 1) - $start);

	expect($body)->toContain('if (import_write_blocked_by_symlink($filename, $name)) {');
});

test('import_write_blocked_by_symlink refuses a symlinked destination and allows a regular path', function () {
	$dir = sys_get_temp_dir() . '/cacti-symlink-' . bin2hex(random_bytes(6));
	expect(mkdir($dir, 0700, true))->toBeTrue();

	try {
		$regular = $dir . '/regular.xml';
		file_put_contents($regular, 'x');

		$link = $dir . '/link.xml';
		symlink($regular, $link);

		expect(import_write_blocked_by_symlink($link, 'pkg/link.xml'))->toBeTrue()
			->and(import_write_blocked_by_symlink($regular, 'pkg/regular.xml'))->toBeFalse()
			->and(import_write_blocked_by_symlink($dir . '/missing.xml', 'pkg/missing.xml'))->toBeFalse();
	} finally {
		@unlink($dir . '/link.xml');
		@unlink($dir . '/regular.xml');
		@rmdir($dir);
	}
});
