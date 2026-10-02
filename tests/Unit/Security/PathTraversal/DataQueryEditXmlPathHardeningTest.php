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
 * Tests for the data query XML path existence-oracle hardening in the
 * data_query_edit() UI page (data_queries.php).
 *
 * This is the separate UI code path from get_data_query_array() (covered by
 * DataQueryXmlPathHardeningTest.php). When rendering the edit page, Cacti
 * reports whether the configured XML file was "located"; without containment,
 * that located/not-located banner leaks the existence of arbitrary files
 * (GHSA-2x86-jpm8-9vgp). The guard confines the resolved path to the Cacti
 * base via cacti_path_is_within() and rejects a NUL byte before the filesystem
 * call so realpath() cannot throw a ValueError on PHP 8+.
 *
 * Two test suites:
 *   1. Source-scan: verifies the guard structure is textually present.
 *   2. Runtime boundary: exercises the real cacti_path_is_within() guard in
 *      isolation against a temporary directory tree, including the NUL-byte
 *      boundary that runs during page rendering.
 */

require_once dirname(__DIR__, 4) . '/lib/functions.php';

// ---------------------------------------------------------------------------
// Source-scan suite (secondary lint — does NOT substitute for runtime tests)
// ---------------------------------------------------------------------------

$src = file_get_contents(dirname(__DIR__, 4) . '/data_queries.php');

test('data_query_edit confines the XML existence check with cacti_path_is_within', function () use ($src) {
	expect($src)->toContain('cacti_path_is_within($xml_filename, CACTI_PATH_BASE)');
});

test('data_query_edit rejects a NUL byte before the filesystem call', function () use ($src) {
	expect($src)->toContain('strpos($xml_filename, "\0") === false');
});

test('data_query_edit reads existence via is_file on the confined filename', function () use ($src) {
	expect($src)->toContain('is_file($xml_filename)');
});

test('data_query_edit no longer uses a raw case-sensitive prefix comparison', function () use ($src) {
	expect($src)->not->toContain('strpos($xml_real, $base_real . DIRECTORY_SEPARATOR) === 0');
});

test('NUL-byte guard precedes the cacti_path_is_within() call', function () use ($src) {
	$nul_pos    = strpos($src, 'strpos($xml_filename, "\0") === false');
	$within_pos = strpos($src, 'cacti_path_is_within($xml_filename, CACTI_PATH_BASE)');

	expect($nul_pos)->not->toBeFalse()
		->and($within_pos)->not->toBeFalse()
		->and($nul_pos)->toBeLessThan($within_pos);
});

// ---------------------------------------------------------------------------
// Runtime boundary suite — exercises the real guard logic in isolation
// ---------------------------------------------------------------------------

/*
 * Replicates the guard from data_query_edit() so it can be exercised without
 * the full Cacti bootstrap (DB, session, page rendering). It calls the real
 * cacti_path_is_within() from lib/functions.php, so traversal/symlink/casing
 * behaviour is covered by the production helper, not a re-implementation.
 *
 * Returns true only when $xml_filename is non-empty, contains no NUL byte,
 * resolves within $base, and is a regular file.
 */
function dataQueryEditGuardAllowed(string $base, string $xml_filename): bool {
	if ($xml_filename === '' || strpos($xml_filename, "\0") !== false) {
		return false;
	}

	return cacti_path_is_within($xml_filename, $base) && is_file($xml_filename);
}

function makeDQEditTempBase(): string {
	$tmp = sys_get_temp_dir() . '/cacti_dq_edit_test_' . getmypid() . '_' . bin2hex(random_bytes(4));
	mkdir($tmp . '/resource/snmp-queries', 0755, true);
	mkdir($tmp . '/outside',               0755, true);

	return $tmp;
}

function removeDQEditTempBase(string $base): void {
	foreach (['resource/snmp-queries', 'resource', 'outside'] as $sub) {
		$path = $base . '/' . $sub;

		if (is_file($path)) {
			unlink($path);
		} elseif (is_dir($path)) {
			rmdir($path);
		}
	}

	if (is_dir($base)) {
		rmdir($base);
	}
}

// --- happy path ---

test('xml file inside the base is located', function () {
	$base = makeDQEditTempBase();
	$file = $base . '/resource/snmp-queries/interface.xml';
	file_put_contents($file, '<query/>');

	$result = dataQueryEditGuardAllowed($base, $file);

	unlink($file);
	removeDQEditTempBase($base);

	expect($result)->toBeTrue();
});

// --- traversal / out-of-base ---

test('traversal escaping the base is not located', function () {
	$tmp  = sys_get_temp_dir() . '/cacti_dq_edit_trav_' . getmypid() . '_' . bin2hex(random_bytes(4));
	$base = $tmp . '/cacti';
	mkdir($base . '/resource/snmp-queries', 0755, true);
	mkdir($tmp . '/outside',               0755, true);
	$evil = $tmp . '/outside/passwd';
	file_put_contents($evil, 'root:x:0:0');

	$traversal = $base . '/resource/snmp-queries/../../../outside/passwd';
	$result    = dataQueryEditGuardAllowed($base, $traversal);

	unlink($evil);
	rmdir($tmp . '/outside');
	rmdir($base . '/resource/snmp-queries');
	rmdir($base . '/resource');
	rmdir($base);
	rmdir($tmp);

	expect($result)->toBeFalse();
});

test('an existing absolute path outside the base is not located', function () {
	$base   = makeDQEditTempBase();
	$result = dataQueryEditGuardAllowed($base, sys_get_temp_dir());

	removeDQEditTempBase($base);

	expect($result)->toBeFalse();
});

test('a non-existent in-base path is not located', function () {
	$base   = makeDQEditTempBase();
	$result = dataQueryEditGuardAllowed($base, $base . '/resource/snmp-queries/missing.xml');

	removeDQEditTempBase($base);

	expect($result)->toBeFalse();
});

// --- NUL-byte boundary (runs during page rendering, must not throw) ---

test('a NUL byte in the filename is rejected without raising a ValueError', function () {
	$base = makeDQEditTempBase();
	$file = $base . "/resource/snmp-queries/interface.xml\0.evil";

	// On PHP 8+, realpath() throws a ValueError on a NUL byte; the guard must
	// short-circuit before reaching cacti_path_is_within()/realpath().
	$result = dataQueryEditGuardAllowed($base, $file);

	removeDQEditTempBase($base);

	expect($result)->toBeFalse();
});

test('an empty filename is not located', function () {
	$base   = makeDQEditTempBase();
	$result = dataQueryEditGuardAllowed($base, '');

	removeDQEditTempBase($base);

	expect($result)->toBeFalse();
});

// --- symlink pivots ---

test('a symlink inside the base resolving to a file inside the base is located', function () {
	$base   = makeDQEditTempBase();
	$target = $base . '/resource/snmp-queries/interface.xml';
	file_put_contents($target, '<query/>');

	$link = $base . '/resource/snmp-queries/iface-link.xml';
	symlink($target, $link);

	$result = dataQueryEditGuardAllowed($base, $link);

	unlink($link);
	unlink($target);
	removeDQEditTempBase($base);

	expect($result)->toBeTrue();
});

test('a symlink inside the base resolving outside the base is not located', function () {
	$base     = makeDQEditTempBase();
	$external = sys_get_temp_dir() . '/cacti_dq_edit_ext_' . getmypid() . '_' . bin2hex(random_bytes(4));
	mkdir($external, 0755, true);
	$ext_file = $external . '/secret.txt';
	file_put_contents($ext_file, 'secret');

	$link = $base . '/resource/snmp-queries/evil-link.xml';
	symlink($ext_file, $link);

	$result = dataQueryEditGuardAllowed($base, $link);

	unlink($link);
	unlink($ext_file);
	rmdir($external);
	removeDQEditTempBase($base);

	expect($result)->toBeFalse();
});

// --- directory inside base: is_file() rejects it even though it is contained ---

test('a directory inside the base is not located because is_file rejects it', function () {
	$base   = makeDQEditTempBase();
	$result = dataQueryEditGuardAllowed($base, $base . '/resource/snmp-queries');

	removeDQEditTempBase($base);

	expect($result)->toBeFalse();
});
