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
 * Tests for the script-server path containment hardening.
 *
 * The script server validates the include file and the resolved source file
 * of the dispatched function against the configured Cacti script roots
 * (CACTI_PATH_BASE and CACTI_PATH_SCRIPTS) via script_server_path_is_allowed().
 *
 * get_script_query_path() sanitizes the base script path to block command
 * injection from malicious templates. It must NOT run the path through
 * cacti_escapeshellcmd() on Windows, which replaces every backslash (the path
 * separator) with a space and corrupts 'C:\path\script.php' into
 * 'C: path script.php'; the script server then truncates the include file at
 * the first space to 'C:' and the containment check rejects it. On Windows the
 * shell metacharacters are stripped while the backslash separator is preserved.
 *
 * Two suites:
 *   1. Source-scan: verifies the guard structure is textually present.
 *   2. Runtime: exercises replicated containment/sanitization logic in
 *      isolation using a real temporary directory tree.
 */

// ---------------------------------------------------------------------------
// Source-scan suite (secondary lint — does NOT substitute for runtime tests)
// ---------------------------------------------------------------------------

$ss = file_get_contents(__DIR__ . '/../../../../script_server.php');
$dq = file_get_contents(__DIR__ . '/../../../../lib/data_query.php');

test('script_server defines the path containment helper', function () use ($ss) {
	expect($ss)->toContain('function script_server_path_is_allowed(');
});

test('script_server builds the allowed roots from base and scripts path', function () use ($ss) {
	expect($ss)->toContain('$allowed_roots = [CACTI_PATH_BASE]')
		->and($ss)->toContain('CACTI_PATH_SCRIPTS');
});

test('script_server validates the include file against the allowed roots before loading', function () use ($ss) {
	$guard = strpos($ss, 'script_server_path_is_allowed($real_include, $allowed_roots)');
	$load  = strpos($ss, 'require_once($include_file)');

	expect($guard)->not->toBeFalse()
		->and($load)->not->toBeFalse()
		->and($guard)->toBeLessThan($load);
});

test('script_server refuses PHP internal functions and validates the function source file', function () use ($ss) {
	expect($ss)->toContain('$ref->isInternal()')
		->and($ss)->toContain('script_server_path_is_allowed(realpath($fn_file), $allowed_roots)');
});

test('get_script_query_path preserves Windows path separators while sanitizing', function () use ($dq) {
	$start = strpos($dq, 'function get_script_query_path(');
	$body  = substr($dq, $start, 3200);

	expect($body)->toContain("CACTI_SERVER_OS == 'win32'")
		->and($body)->toContain("str_replace(['#', '&', ';'")
		->and($body)->toContain("], '', \$parsed_script_path)")
		->and($body)->toContain('cacti_escapeshellcmd($parsed_script_path)');
});

test('get_script_query_path rejects path traversal', function () use ($dq) {
	$start = strpos($dq, 'function get_script_query_path(');
	$body  = substr($dq, $start, 3200);

	expect(str_contains($body, "strpos(\$parsed_script_path, '..')"))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Runtime suite — exercises replicated containment / sanitization logic
// ---------------------------------------------------------------------------

/* Replicates script_server_path_is_allowed() so it can be exercised without
 * running script_server.php (which has top-level bootstrap code). */
function ssContainmentAllowed($resolved_path, array $roots): bool {
	if ($resolved_path === false || !is_string($resolved_path)) {
		return false;
	}

	$resolved_path    = rtrim(str_replace('\\', '/', $resolved_path), '/');
	$case_insensitive = (DIRECTORY_SEPARATOR === '\\');

	foreach ($roots as $root) {
		$root = realpath($root);

		if ($root === false) {
			continue;
		}

		$prefix = rtrim(str_replace('\\', '/', $root), '/') . '/';

		if ($case_insensitive) {
			if (stripos($resolved_path, $prefix) === 0) {
				return true;
			}
		} elseif (strpos($resolved_path, $prefix) === 0) {
			return true;
		}
	}

	return false;
}

/* Replicates the Windows script-path sanitization from get_script_query_path(). */
function ssWin32PathStrip(string $path): string {
	return str_replace(['#', '&', ';', '`', '|', '*', '?', '<', '>', '^', '(', ')', '[', ']', '{', '}', '$'], '', $path);
}

test('containment accepts a file inside the configured root', function () {
	$base = sys_get_temp_dir() . '/ss_contain_' . uniqid();
	mkdir($base . '/scripts', 0700, true);
	$script = $base . '/scripts/ss_host_disk.php';
	file_put_contents($script, "<?php\n");

	expect(ssContainmentAllowed(realpath($script), [$base]))->toBeTrue();

	unlink($script);
	rmdir($base . '/scripts');
	rmdir($base);
});

test('containment rejects a file outside every configured root', function () {
	$base = sys_get_temp_dir() . '/ss_contain_' . uniqid();
	mkdir($base, 0700, true);

	$outside = tempnam(sys_get_temp_dir(), 'ss_outside_');

	expect(ssContainmentAllowed(realpath($outside), [$base]))->toBeFalse();

	unlink($outside);
	rmdir($base);
});

test('containment rejects an unresolved path', function () {
	expect(ssContainmentAllowed(false, [sys_get_temp_dir()]))->toBeFalse();
});

test('win32 sanitization keeps a backslash path intact', function () {
	$path = 'C:\\Apache24\\htdocs\\cacti\\scripts\\ss_host_disk.php';

	expect(ssWin32PathStrip($path))->toBe($path);
});

test('win32 sanitization strips injected command chaining', function () {
	$evil = 'C:\\x\\ss.php & calc.exe';

	expect(ssWin32PathStrip($evil))->not->toContain('&')
		->and(ssWin32PathStrip($evil))->toContain('C:\\x\\ss.php');
});
