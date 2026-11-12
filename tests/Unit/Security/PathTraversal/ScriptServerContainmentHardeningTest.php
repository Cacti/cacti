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
 * The script server validates the include file against the configured Cacti
 * script roots (CACTI_PATH_SCRIPTS and CACTI_PATH_PLUGINS) via
 * script_server_path_is_allowed(), and only dispatches a user function defined
 * by that resolved include file.
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

$ss  = file_get_contents(__DIR__ . '/../../../../script_server.php');
$lss = file_get_contents(__DIR__ . '/../../../../lib/script_server.php');
$dq  = file_get_contents(__DIR__ . '/../../../../lib/data_query.php');

test('script_server defines the path containment helper', function () use ($lss) {
	expect($lss)->toContain('function script_server_path_is_allowed(');
});

test('script_server builds the allowed roots from the scripts and plugins paths', function () use ($ss, $lss) {
	$start = strpos($lss, 'function script_server_allowed_roots(');
	$body  = substr($lss, $start, strpos($lss, "\n}\n", $start) - $start);

	expect($ss)->toContain('$allowed_roots     = script_server_allowed_roots();')
		->and($body)->toContain('CACTI_PATH_SCRIPTS')
		->and($body)->toContain('CACTI_PATH_PLUGINS')
		->and($body)->not->toContain('CACTI_PATH_BASE');
});

test('script_server validates the include file against the allowed roots before loading', function () use ($ss, $lss) {
	$guard = strpos($ss, 'script_server_validate_request($input_string, $allowed_roots)');
	$load  = strpos($ss, 'require_once($include_file)');

	expect($guard)->not->toBeFalse()
		->and($load)->not->toBeFalse()
		->and($guard)->toBeLessThan($load)
		->and($lss)->toContain('script_server_path_is_allowed($real_include, $roots)');
});

test('script_server refuses PHP internal functions and validates the function source file', function () use ($lss) {
	expect($lss)->toContain('$ref->isInternal()')
		->and($lss)->toContain('$resolved_file === $resolved_include');
});

test('get_script_query_path preserves Windows path separators while rejecting cmd.exe metacharacters', function () use ($dq) {
	$start = strpos($dq, 'function get_script_query_path(');
	$body  = substr($dq, $start, 3200);

	expect($body)->toContain("CACTI_SERVER_OS == 'win32'")
		->and($body)->toContain("preg_match('/[&|<>^%]/', \$parsed_script_path)")
		->and($body)->toContain('$base_command = $parsed_script_path;')
		->and($body)->toContain('cacti_escapeshellcmd($parsed_script_path)');
});

test('get_script_query_path rejects path traversal', function () use ($dq) {
	$start = strpos($dq, 'function get_script_query_path(');
	$body  = substr($dq, $start, 3200);

	expect(str_contains($body, "str_contains(\$parsed_script_path, '..')"))->toBeTrue();
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

// Replicates the Windows script-path check from get_script_query_path().
function ssWin32PathAccepted(string $path): bool {
	return !preg_match('/[\x00-\x1f\x7f]/', $path)
		&& !preg_match('/[&|<>^%]/', $path)
		&& substr_count($path, '"') % 2 === 0;
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

test('win32 check accepts backslash paths, including Program Files (x86)', function () {
	expect(ssWin32PathAccepted('C:\\Apache24\\htdocs\\cacti\\scripts\\ss_host_disk.php'))->toBeTrue()
		->and(ssWin32PathAccepted('C:\\Program Files (x86)\\cacti\\scripts\\ss_host_disk.php'))->toBeTrue();
});

test('win32 check rejects command chaining and environment expansion', function () {
	expect(ssWin32PathAccepted('C:\\x\\ss.php & calc.exe'))->toBeFalse()
		->and(ssWin32PathAccepted('C:\\x\\%COMSPEC%.php'))->toBeFalse()
		->and(ssWin32PathAccepted('C:\\x\\ss.php" x'))->toBeFalse();
});
