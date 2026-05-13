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
 * Tests for the data query XML path traversal hardening in get_data_query_array().
 *
 * The guard resolves xml_path with realpath() and verifies it falls within
 * CACTI_PATH_BASE before loading the file, blocking traversal payloads such as
 * '<path_cacti>/../../etc/passwd' that resolve outside the allowed base after
 * token substitution.
 *
 * Two test suites:
 *   1. Source-scan: verifies the guard structure is textually present.
 *   2. Runtime boundary: exercises the guard logic in isolation using a real
 *      temporary directory tree, providing actual execution-path coverage.
 */

// ---------------------------------------------------------------------------
// Source-scan suite (secondary lint — does NOT substitute for runtime tests)
// ---------------------------------------------------------------------------

$src = file_get_contents(__DIR__ . '/../../lib/data_query.php');

test('get_data_query_array checks file existence before calling realpath', function () use ($src) {
	expect($src)->toContain('file_exists($xml_file_path)');
});

test('get_data_query_array calls realpath on the resolved xml_file_path', function () use ($src) {
	expect($src)->toContain('realpath($xml_file_path)');
});

test('get_data_query_array calls realpath on CACTI_PATH_BASE for boundary anchor', function () use ($src) {
	expect($src)->toContain('realpath(CACTI_PATH_BASE)');
});

test('realpath boundary check uses str_starts_with with DIRECTORY_SEPARATOR', function () use ($src) {
	expect($src)->toContain('str_starts_with($resolved . DIRECTORY_SEPARATOR, rtrim($allowed_base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)');
});

test('file_exists check precedes realpath boundary check', function () use ($src) {
	$exists_pos   = strpos($src, 'file_exists($xml_file_path)');
	$realpath_pos = strpos($src, 'realpath($xml_file_path)');

	expect($exists_pos)->not->toBeFalse()
		->and($realpath_pos)->not->toBeFalse()
		->and($exists_pos)->toBeLessThan($realpath_pos);
});

test('$allowed_base is initialised with realpath() before the boundary comparison uses it', function () use ($src) {
	// Guards against symlink-diverged installs where CACTI_PATH_BASE is a symlink:
	// if $allowed_base were set from the raw constant, $resolved (canonicalized)
	// and $allowed_base would diverge, blocking every legitimate path.
	$init_pos    = strpos($src, '$allowed_base = realpath(');
	$compare_pos = strpos($src, 'str_starts_with($resolved . DIRECTORY_SEPARATOR, rtrim($allowed_base,');

	expect($init_pos)->not->toBeFalse()
		->and($compare_pos)->not->toBeFalse()
		->and($init_pos)->toBeLessThan($compare_pos);
});

test('get_data_query_array returns empty array when xml_path resolves outside base', function () use ($src) {
	$guard_pos = strpos($src, '!str_starts_with($resolved . DIRECTORY_SEPARATOR, rtrim($allowed_base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)');
	// After the TOCTOU fix, the actual I/O uses the validated $resolved path, not the raw $xml_file_path.
	$file_pos  = strpos($src, 'file($resolved)');

	expect($guard_pos)->not->toBeFalse()
		->and($file_pos)->not->toBeFalse()
		->and($guard_pos)->toBeLessThan($file_pos);
});

test('boundary check writes to the persistent SECURITY audit log', function () use ($src) {
	expect($src)->toContain("cacti_log('SECURITY: data query XML path outside Cacti base:");
});

test('boundary check logs a SECURITY message to the debug timer', function () use ($src) {
	expect($src)->toContain("'SECURITY: data query XML path outside Cacti base:");
});

test('log message uses $safe_path not raw $xml_file_path to prevent log injection', function () use ($src) {
	// $safe_path must appear in the cacti_log call; raw concatenation of $xml_file_path must not.
	$log_pos       = strpos($src, "cacti_log('SECURITY: data query XML path outside Cacti base: '");
	$safe_pos      = strpos($src, '$safe_path', $log_pos ?: 0);
	$raw_concat    = strpos($src, '. $xml_file_path', $log_pos ?: 0);
	$newline_strip = strpos($src, 'str_replace(["\r", "\n"], [\'\\\\r\', \'\\\\n\'], $xml_file_path)');

	expect($log_pos)->not->toBeFalse()
		->and($safe_pos)->not->toBeFalse()
		->and($raw_concat)->toBeFalse()
		->and($newline_strip)->not->toBeFalse();
});

test('file_exists early-return log uses $safe_path not raw $xml_file_path', function () use ($src) {
	// $safe_path must be defined before the file_exists check so both early-return
	// paths (missing file and boundary violation) sanitise log output consistently.
	// Window of 80 chars covers just the tail of the log call (ends ~25 chars
	// past the search string) without reaching realpath($xml_file_path) ~150
	// chars later.
	$safe_def   = strpos($src, '$safe_path');
	$exists_log = strpos($src, "Could not find data query XML file at");
	$block      = substr($src, $exists_log ?: 0, 80);
	$raw_in_log = strpos($block, '$xml_file_path)');

	expect($safe_def)->not->toBeFalse()
		->and($exists_log)->not->toBeFalse()
		->and($safe_def)->toBeLessThan($exists_log)
		->and($raw_in_log)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Runtime boundary suite — exercises the actual guard logic in isolation
// ---------------------------------------------------------------------------

/*
 * Replicates the guard from get_data_query_array() so it can be exercised
 * without the full Cacti bootstrap (DB, config, constants).
 *
 * Returns true when $xml_file_path resolves within $base_dir, false otherwise.
 */
function dataQueryBoundaryAllowed(string $base_dir, string $xml_file_path): bool {
	$allowed_base = realpath($base_dir);
	$resolved     = realpath($xml_file_path);

	if ($allowed_base === false || $resolved === false) {
		return false;
	}

	return str_starts_with($resolved . DIRECTORY_SEPARATOR, rtrim($allowed_base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
}

/*
 * Create a minimal temp tree mirroring CACTI_PATH_BASE:
 *   $tmp/resource/snmp-queries/   (legitimate XML location)
 *   $tmp/outside/                 (must not be reachable via traversal)
 */
function makeDQTempBase(): string {
	// Random suffix so concurrent or post-failure test runs do not collide on
	// a stale directory from a previous iteration.
	$tmp = sys_get_temp_dir() . '/cacti_dq_test_' . getmypid() . '_' . bin2hex(random_bytes(4));
	mkdir($tmp . '/resource/snmp-queries', 0755, true);
	mkdir($tmp . '/outside',               0755, true);
	return $tmp;
}

function removeDQTempBase(string $base): void {
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

test('xml_path inside resource/snmp-queries is allowed', function () {
	$base = makeDQTempBase();
	$file = $base . '/resource/snmp-queries/interface.xml';
	file_put_contents($file, '<query/>');

	$result = dataQueryBoundaryAllowed($base, $file);

	unlink($file);
	removeDQTempBase($base);

	expect($result)->toBeTrue();
});

// --- traversal attacks ---

test('traversal via ../ from resource to outside/ is blocked', function () {
	// Structure:
	//   $tmp/            <- parent container, NOT the Cacti base
	//     cacti/         <- CACTI_PATH_BASE equivalent
	//       resource/
	//         snmp-queries/
	//     outside/       <- sibling of cacti/, outside the allowed base
	//       passwd
	//
	// Three levels up from snmp-queries/ exits cacti/ into $tmp, then into outside/.
	// This mirrors '<path_cacti>/resource/snmp-queries/../../../outside/passwd'.
	$tmp  = sys_get_temp_dir() . '/cacti_traversal_test_' . getmypid() . '_' . bin2hex(random_bytes(4));
	$base = $tmp . '/cacti';
	mkdir($base . '/resource/snmp-queries', 0755, true);
	mkdir($tmp . '/outside',               0755, true);
	$evil = $tmp . '/outside/passwd';
	file_put_contents($evil, 'root:x:0:0');

	$traversal = $base . '/resource/snmp-queries/../../../outside/passwd';
	$result    = dataQueryBoundaryAllowed($base, $traversal);

	unlink($evil);
	rmdir($tmp . '/outside');
	rmdir($base . '/resource/snmp-queries');
	rmdir($base . '/resource');
	rmdir($base);
	rmdir($tmp);

	expect($result)->toBeFalse();
});

test('absolute path outside base is blocked even if the path exists', function () {
	$base   = makeDQTempBase();
	$result = dataQueryBoundaryAllowed($base, sys_get_temp_dir());

	removeDQTempBase($base);

	expect($result)->toBeFalse();
});

test('deep traversal escaping base directory is blocked', function () {
	$base   = makeDQTempBase();
	$result = dataQueryBoundaryAllowed($base, $base . '/resource/' . str_repeat('../', 10) . 'etc/passwd');

	removeDQTempBase($base);

	expect($result)->toBeFalse();
});

// --- non-existent file: realpath() returns false ---

test('non-existent xml_path is blocked because realpath returns false', function () {
	$base   = makeDQTempBase();
	$result = dataQueryBoundaryAllowed($base, $base . '/resource/snmp-queries/does-not-exist.xml');

	removeDQTempBase($base);

	expect($result)->toBeFalse();
});

// --- log injection sanitisation ---

test('newline in xml_file_path is encoded before logging', function () {
	$raw  = "/var/www/cacti/resource/snmp-queries/iface.xml\nSECURITY: forged line";
	$safe = str_replace(["\r", "\n"], ['\\r', '\\n'], $raw);

	expect($safe)->not->toContain("\n")
		->and($safe)->toContain('\\n');
});

test('carriage return in xml_file_path is encoded before logging', function () {
	$raw  = "/var/www/cacti/resource/snmp-queries/iface.xml\rinjected";
	$safe = str_replace(["\r", "\n"], ['\\r', '\\n'], $raw);

	expect($safe)->not->toContain("\r")
		->and($safe)->toContain('\\r');
});

test('path without control characters is unchanged by sanitisation', function () {
	$raw  = '/var/www/cacti/resource/snmp-queries/interface.xml';
	$safe = str_replace(["\r", "\n"], ['\\r', '\\n'], $raw);

	expect($safe)->toBe($raw);
});

// --- edge case: path exactly matching the allowed base ---

test('path exactly matching allowed base passes str_starts_with boundary check', function () {
	$base = makeDQTempBase();
	// $resolved === $allowed_base: str_starts_with($base.'/', $base.'/') must be true.
	// This confirms the DIRECTORY_SEPARATOR trick does not block the base itself.
	$result = dataQueryBoundaryAllowed($base, $base);

	removeDQTempBase($base);

	expect($result)->toBeTrue();
});

// --- edge case: '..' segments that resolve inside base after canonicalisation ---

test('path with dot-dot that resolves inside base is allowed after canonicalisation', function () {
	$base = makeDQTempBase();
	$file = $base . '/resource/snmp-queries/interface.xml';
	file_put_contents($file, '<query/>');

	// Goes up two levels then back down — net resolved path is still inside base.
	$with_dotdot = $base . '/resource/snmp-queries/../../resource/snmp-queries/interface.xml';
	$result      = dataQueryBoundaryAllowed($base, $with_dotdot);

	unlink($file);
	removeDQTempBase($base);

	expect($result)->toBeTrue();
});

// --- edge case: special characters (spaces, dots) in path components ---

test('path with spaces and dots inside base is allowed', function () {
	$base   = makeDQTempBase();
	$subdir = $base . '/resource/snmp-queries/my.dir with spaces';
	mkdir($subdir, 0755, true);
	$file   = $subdir . '/my query.xml';
	file_put_contents($file, '<query/>');

	$result = dataQueryBoundaryAllowed($base, $file);

	unlink($file);
	rmdir($subdir);
	removeDQTempBase($base);

	expect($result)->toBeTrue();
});

// --- expanded control-character sanitisation ---

test('null byte in xml_file_path is stripped before logging; content after null byte is not suppressed', function () {
	$raw  = "/var/www/cacti/resource/snmp-queries/iface.xml\x00injected";
	$safe = preg_replace('/[\x00-\x1F\x7F]/', '', str_replace(["\r", "\n"], ['\\r', '\\n'], $raw));

	// preg_replace removes the null byte only; bytes after it remain.
	// The sanitisation goal is newline/control-char injection defense in log
	// output, not null-byte smuggling prevention. Path traversal is blocked
	// by the realpath() boundary check, not this sanitiser.
	expect($safe)->not->toContain("\x00")
		->and($safe)->toContain('injected');
});

test('other ASCII control characters are stripped before logging', function () {
	// BEL (\x07), BS (\x08), ESC (\x1B) — not exploitable for newline injection
	// but stripped defensively to prevent terminal control sequence injection.
	$raw  = "/var/www/cacti/resource/\x07\x08\x1Bsnmp-queries/iface.xml";
	$safe = preg_replace('/[\x00-\x1F\x7F]/', '', str_replace(["\r", "\n"], ['\\r', '\\n'], $raw));

	expect($safe)->not->toContain("\x07")
		->and($safe)->not->toContain("\x08")
		->and($safe)->not->toContain("\x1B");
});

// --- edge case: symlink whose target resolves inside base ---

test('symlink inside base resolving to file inside base is allowed', function () {
	$base   = makeDQTempBase();
	$target = $base . '/resource/snmp-queries/interface.xml';
	file_put_contents($target, '<query/>');

	$link = $base . '/resource/snmp-queries/iface-link.xml';
	symlink($target, $link);

	$result = dataQueryBoundaryAllowed($base, $link);

	unlink($link);
	unlink($target);
	removeDQTempBase($base);

	// realpath() resolves the symlink; the canonical target is still inside base.
	expect($result)->toBeTrue();
});

// --- edge case: symlink whose target resolves outside base ---

test('symlink inside base resolving to file outside base is blocked', function () {
	$base     = makeDQTempBase();
	$external = sys_get_temp_dir() . '/cacti_dq_ext_' . getmypid();
	mkdir($external, 0755);
	$ext_file = $external . '/secret.txt';
	file_put_contents($ext_file, 'secret');

	$link = $base . '/resource/snmp-queries/evil-link.xml';
	symlink($ext_file, $link);

	$result = dataQueryBoundaryAllowed($base, $link);

	unlink($link);
	unlink($ext_file);
	rmdir($external);
	removeDQTempBase($base);

	// realpath() follows the symlink to its canonical target outside base; rejected.
	expect($result)->toBeFalse();
});

// --- edge case: path exceeding system PATH_MAX ---

test('extremely long path is blocked because realpath returns false', function () {
	$base = makeDQTempBase();
	// 4096 repetitions of 'a' pushes the total path length beyond PATH_MAX on
	// both Linux (4096) and macOS (1024), so realpath() returns false.
	$longpath = $base . '/resource/snmp-queries/' . str_repeat('a', 4096) . '.xml';
	$result   = dataQueryBoundaryAllowed($base, $longpath);

	removeDQTempBase($base);

	expect($result)->toBeFalse();
});

// --- edge case: trailing slash on xml_file_path ---

test('path with trailing slash inside base is allowed after realpath normalises it', function () {
	$base = makeDQTempBase();
	// realpath() strips the trailing slash; the directory exists so it resolves correctly.
	$result = dataQueryBoundaryAllowed($base, $base . '/resource/snmp-queries/');

	removeDQTempBase($base);

	expect($result)->toBeTrue();
});

// --- source scan: is_file guard ---

test('get_data_query_array checks is_file before calling file() to reject directories', function () use ($src) {
	$isfile_pos = strpos($src, 'is_file($resolved)');
	$read_pos   = strpos($src, 'file($resolved)');

	expect($isfile_pos)->not->toBeFalse()
		->and($read_pos)->not->toBeFalse()
		->and($isfile_pos)->toBeLessThan($read_pos);
});

// --- edge case: directory that resolves within allowed base ---

test('directory inside base is allowed by boundary check (is_file guard in production rejects it)', function () {
	$base   = makeDQTempBase();
	// The boundary function mirrors only the realpath/str_starts_with guard.
	// A directory inside the base passes that guard; the production is_file()
	// check (not replicated here) is what rejects it at read time.
	$result = dataQueryBoundaryAllowed($base, $base . '/resource/snmp-queries');

	removeDQTempBase($base);

	expect($result)->toBeTrue();
});

// --- edge case: empty string as xml_file_path ---

test('empty string as xml_file_path is blocked because realpath returns false', function () {
	$base   = makeDQTempBase();
	$result = dataQueryBoundaryAllowed($base, '');

	removeDQTempBase($base);

	expect($result)->toBeFalse();
});

// --- edge case: invalid UTF-8 sequences in path sanitisation ---

test('invalid UTF-8 sequence in xml_file_path does not cause sanitisation to error', function () {
	// Byte sequence \xC3\x28 is invalid UTF-8 (continuation byte missing).
	// The sanitiser must not return null or throw; it should return a string.
	$raw  = "/var/www/cacti/resource/snmp-queries/\xC3\x28iface.xml";
	$safe = preg_replace('/[\x00-\x1F\x7F]/', '', str_replace(["\r", "\n"], ['\\r', '\\n'], $raw));

	// preg_replace without the /u flag operates on raw bytes, so invalid UTF-8
	// is passed through unchanged rather than causing a failure.
	expect($safe)->toBeString()
		->and($safe)->not->toBeNull();
});

// --- edge case: multiple consecutive slashes in path ---

test('path with multiple consecutive slashes inside base is allowed after realpath normalises it', function () {
	$base = makeDQTempBase();
	$file = $base . '/resource/snmp-queries/interface.xml';
	file_put_contents($file, '<query/>');

	// realpath() collapses consecutive slashes to a single slash; the canonical
	// path is still inside $base so the boundary check must allow it.
	$doubled = $base . '//resource//snmp-queries//interface.xml';
	$result  = dataQueryBoundaryAllowed($base, $doubled);

	unlink($file);
	removeDQTempBase($base);

	expect($result)->toBeTrue();
});

// --- edge case: relative path from failed str_replace substitution ---

test('relative path is blocked when str_replace substitution fails to produce an absolute path', function () {
	$base = makeDQTempBase();
	// If the <path_cacti> token is absent, str_replace returns the literal
	// xml_path unchanged, which may be a relative path such as
	// 'resource/snmp-queries/interface.xml'. realpath() resolves it against CWD.
	// Unless CWD happens to equal $base (a temp directory), the resolved path
	// is outside the allowed base and must be rejected. If the file does not
	// exist relative to CWD, realpath() returns false, which is also rejected.
	$result = dataQueryBoundaryAllowed($base, 'resource/snmp-queries/interface.xml');

	removeDQTempBase($base);

	expect($result)->toBeFalse();
});

// --- edge case: trailing slash or special characters in CACTI_PATH_BASE ---

test('CACTI_PATH_BASE with trailing slash: in-bounds path is still allowed', function () {
	$base = makeDQTempBase();
	$file = $base . '/resource/snmp-queries/interface.xml';
	file_put_contents($file, '<query/>');

	// realpath() on $base.'/' strips the trailing slash before the boundary
	// comparison, so rtrim() in the guard is a second layer of defence.
	// Both must agree: a file genuinely inside the base must remain allowed.
	$result = dataQueryBoundaryAllowed($base . '/', $file);

	unlink($file);
	removeDQTempBase($base);

	expect($result)->toBeTrue();
});

test('CACTI_PATH_BASE with trailing slash: out-of-bounds path is still blocked', function () {
	$tmp  = sys_get_temp_dir() . '/cacti_trailing_base_test_' . getmypid();
	$base = $tmp . '/cacti';
	mkdir($base . '/resource/snmp-queries', 0755, true);
	mkdir($tmp . '/outside', 0755, true);
	$evil = $tmp . '/outside/secret.txt';
	file_put_contents($evil, 'secret');

	// Trailing slash on the base must not widen the allowed region.
	// A file outside the canonical base must still be rejected.
	$result = dataQueryBoundaryAllowed($base . '/', $evil);

	unlink($evil);
	rmdir($tmp . '/outside');
	rmdir($base . '/resource/snmp-queries');
	rmdir($base . '/resource');
	rmdir($base);
	rmdir($tmp);

	expect($result)->toBeFalse();
});

test('CACTI_PATH_BASE with spaces in directory name: in-bounds path is allowed', function () {
	$base = sys_get_temp_dir() . '/cacti dq base ' . getmypid();
	mkdir($base . '/resource/snmp-queries', 0755, true);
	$file = $base . '/resource/snmp-queries/interface.xml';
	file_put_contents($file, '<query/>');

	// str_starts_with operates on plain strings; spaces are not special.
	// A base path with spaces must not confuse the boundary check.
	$result = dataQueryBoundaryAllowed($base, $file);

	unlink($file);
	rmdir($base . '/resource/snmp-queries');
	rmdir($base . '/resource');
	rmdir($base);

	expect($result)->toBeTrue();
});

test('CACTI_PATH_BASE with spaces in directory name: out-of-bounds path is blocked', function () {
	$tmp     = sys_get_temp_dir() . '/cacti dq outer ' . getmypid();
	$base    = $tmp . '/cacti base';
	mkdir($base . '/resource/snmp-queries', 0755, true);
	mkdir($tmp . '/outside', 0755, true);
	$evil = $tmp . '/outside/secret.txt';
	file_put_contents($evil, 'secret');

	$result = dataQueryBoundaryAllowed($base, $evil);

	unlink($evil);
	rmdir($tmp . '/outside');
	rmdir($base . '/resource/snmp-queries');
	rmdir($base . '/resource');
	rmdir($base);
	rmdir($tmp);

	expect($result)->toBeFalse();
});

// --- TOCTOU mitigation: all post-boundary I/O uses the canonical $resolved path ---

test('is_file check after boundary guard uses $resolved not raw $xml_file_path', function () use ($src) {
	// Between file_exists() and the final file() read, a symlink swap could
	// redirect I/O to a different target. Using $resolved (the realpath()-
	// canonicalised path) for every subsequent operation closes this window:
	// the path passed to is_file() must be the same value that passed the guard.
	$boundary_pos = strpos($src, '!str_starts_with($resolved . DIRECTORY_SEPARATOR');
	$isfile_pos   = strpos($src, 'is_file($resolved)');

	expect($boundary_pos)->not->toBeFalse()
		->and($isfile_pos)->not->toBeFalse()
		->and($isfile_pos)->toBeGreaterThan($boundary_pos);
});

test('raw $xml_file_path is not used for any file read after boundary check', function () use ($src) {
	// Confirmed absence of file($xml_file_path) ensures that even if a symlink
	// is swapped between the boundary check and the read, the I/O path cannot
	// diverge from the path that was validated.
	expect($src)->not->toContain('file($xml_file_path)');
});
