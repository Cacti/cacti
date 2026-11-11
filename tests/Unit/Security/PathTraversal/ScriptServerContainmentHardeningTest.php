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
 *   2. Runtime: exercises the unchanged production functions, the
 *      containment helper against a real temporary directory tree and
 *      get_script_query_path() in a child process per platform.
 */

namespace CactiScriptServerContainmentTests;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

if (!function_exists(__NAMESPACE__ . '\\script_server_path_is_allowed')) {
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/script_server.php', 'script_server_path_is_allowed', __NAMESPACE__);
}

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

test('get_script_query_path checks for traversal after sanitizing', function () use ($dq) {
	$start = strpos($dq, 'function get_script_query_path(');
	$body  = substr($dq, $start, 3200);

	$strip = strpos($body, '$safe_script_path = str_replace(');
	$check = strpos($body, "strpos(str_replace('\"', '', \$safe_script_path), '..')");

	expect($strip)->not->toBeFalse()
		->and($check)->not->toBeFalse()
		->and($check)->toBeGreaterThan($strip)
		->and($body)->not->toContain("strpos(\$parsed_script_path, '..')");
});

// ---------------------------------------------------------------------------
// Runtime suite — exercises the unchanged production functions
// ---------------------------------------------------------------------------

test('containment accepts a file inside the configured root', function () {
	$base = sys_get_temp_dir() . '/ss_contain_' . uniqid();
	mkdir($base . '/scripts', 0700, true);
	$script = $base . '/scripts/ss_host_disk.php';
	file_put_contents($script, "<?php\n");

	expect(script_server_path_is_allowed(realpath($script), [$base]))->toBeTrue();

	unlink($script);
	rmdir($base . '/scripts');
	rmdir($base);
});

test('containment rejects a file outside every configured root', function () {
	$base = sys_get_temp_dir() . '/ss_contain_' . uniqid();
	mkdir($base, 0700, true);

	$outside = tempnam(sys_get_temp_dir(), 'ss_outside_');

	expect(script_server_path_is_allowed(realpath($outside), [$base]))->toBeFalse();

	unlink($outside);
	rmdir($base);
});

test('containment rejects an unresolved path', function () {
	expect(script_server_path_is_allowed(false, [sys_get_temp_dir()]))->toBeFalse();
});

/* get_script_query_path() reads CACTI_SERVER_OS, a constant, so each case
 * runs the production function in its own process. */
test('get_script_query_path builds the command and rejects traversal', function (string $os, string $path, string $args, string $expected) {
	$result = \cacti_test_isolated_probe(dirname(__DIR__, 4) . '/tests/fixtures/Core/ScriptQueryPath.php', [$os, $path, $args]);

	expect($result['command'])->toBe($expected)
		->and($result['rejected'])->toBe($expected === '');
})->with([
	'win32 backslash path is kept'      => ['win32', 'C:\\Apache24\\htdocs\\cacti\\scripts\\ss_host_disk.php ss_host_disk', 'get total 2', 'C:\\Apache24\\htdocs\\cacti\\scripts\\ss_host_disk.php ss_host_disk "get" "total" "2"'],
	'win32 placeholder path'            => ['win32', '|path_cacti|/scripts/ss_host_cpu.php ss_host_cpu', '', 'C:\\Apache24\\htdocs\\cacti\\scripts\\ss_host_cpu.php ss_host_cpu '],
	'win32 command chaining stripped'   => ['win32', 'C:\\x\\ss.php & calc.exe', '', 'C:\\x\\ss.php  calc.exe '],
	'win32 percent stripped'            => ['win32', 'C:\\x\\%COMSPEC%ss.php', '', 'C:\\x\\COMSPECss.php '],
	'win32 quoted path with spaces'     => ['win32', '"C:\\Program Files\\Cacti\\scripts\\test.php" test', '"two words"', '"C:\\Program Files\\Cacti\\scripts\\test.php" test "two words"'],
	'win32 traversal'                   => ['win32', 'C:\\x\\scripts\\..\\ss.php', '', ''],
	'win32 traversal after stripping ;' => ['win32', 'C:\\x\\scripts\\.;.\\ss.php', '', ''],
	'win32 traversal after stripping %' => ['win32', 'C:\\x\\scripts\\.%.\\ss.php', '', ''],
	'win32 traversal split by quotes'   => ['win32', '/cacti/scripts/."."/x.php', '', ''],
	'win32 nested traversal'            => ['win32', 'C:\\x\\scripts\\.^.\\.#.\\ss.php', '', ''],
	'win32 drive relative traversal'    => ['win32', 'C:..\\ss.php', '', ''],
	'win32 dots inside a name'          => ['win32', 'C:\\x\\scripts..old\\ss.php', '', ''],
	'unix placeholder path'             => ['unix', '|path_cacti|/scripts/ss_host_disk.php ss_host_disk', 'get total 2', "/srv/cacti/scripts/ss_host_disk.php ss_host_disk 'get' 'total' '2'"],
	'unix traversal split by quotes'    => ['unix', '/srv/cacti/scripts/."."/x.php', '', ''],
	'unix traversal'                    => ['unix', '/srv/cacti/scripts/../ss.php', '', ''],
]);
