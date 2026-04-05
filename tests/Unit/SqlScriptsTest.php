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
 * Tests for scripts/sql.php and scripts/ss_sql.php.
 *
 * Covers two hardening steps:
 * 1. Backtick-to-shell_exec migration (PHP 8.4 deprecates the backtick
 *    operator; the fix replaces backticks with shell_exec()).
 * 2. cacti_escapeshellarg() wrapper (Cacti's internal contract; bare
 *    escapeshellarg() bypasses any future Cacti-level escaping hooks).
 */

$sqlPhpPath   = __DIR__ . '/../../scripts/sql.php';
$ssSqlPhpPath = __DIR__ . '/../../scripts/ss_sql.php';

// --- scripts/sql.php: no backtick operators remain ---

test('sql.php contains no backtick operators', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->not->toMatch('/`[^`]*mysqladmin[^`]*`/');
});

test('sql.php uses shell_exec for command execution', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->toContain('shell_exec(');
});

test('sql.php escapes database_hostname with cacti_escapeshellarg', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->toContain('cacti_escapeshellarg($database_hostname)');
});

test('sql.php escapes database_username with cacti_escapeshellarg', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->toContain('cacti_escapeshellarg($database_username)');
});

test('sql.php escapes database_password with cacti_escapeshellarg', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->toContain('cacti_escapeshellarg($database_password)');
});

test('sql.php uses no bare escapeshellarg calls', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	// Negative lookbehind: match escapeshellarg( NOT preceded by cacti_
	expect(preg_match('/(?<!cacti_)escapeshellarg\(/', $contents))->toBe(0);
});

test('sql.php handles null return from shell_exec', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->toContain("?? ''");
});

test('sql.php returns U on empty/null shell_exec output', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	/* Cacti data source scripts must return 'U' on error, never empty string. */
	expect($contents)->toContain(": 'U'");
});

// --- scripts/ss_sql.php: no backtick operators remain ---

test('ss_sql.php contains no backtick operators', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->not->toMatch('/`[^`]*mysqladmin[^`]*`/');
});

test('ss_sql.php uses shell_exec for command execution', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->toContain('shell_exec(');
});

test('ss_sql.php escapes database_hostname with cacti_escapeshellarg', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->toContain('cacti_escapeshellarg($database_hostname)');
});

test('ss_sql.php escapes database_username with cacti_escapeshellarg', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->toContain('cacti_escapeshellarg($database_username)');
});

test('ss_sql.php escapes database_password with cacti_escapeshellarg', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->toContain('cacti_escapeshellarg($database_password)');
});

test('ss_sql.php uses no bare escapeshellarg calls', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	// Negative lookbehind: match escapeshellarg( NOT preceded by cacti_
	expect(preg_match('/(?<!cacti_)escapeshellarg\(/', $contents))->toBe(0);
});

test('ss_sql.php handles null return from shell_exec', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->toContain("?? ''");
});

test('ss_sql.php returns U on empty/null shell_exec output', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	/* Cacti data source scripts must return 'U' on error, never empty string. */
	expect($contents)->toContain(": 'U'");
});

// --- runtime: cacti_escapeshellarg is callable and ss_sql() falls back to 'U' ---

test('ss_sql() returns U when shell_exec produces no output', function () use ($ssSqlPhpPath) {
	/* Bootstrap cacti_escapeshellarg if global.php has not yet been loaded. */
	if (!function_exists('cacti_escapeshellarg')) {
		/* Minimal stub: delegate to the native call so arg-quoting still works. */
		function cacti_escapeshellarg(string $arg, bool $quote = true): string {
			return escapeshellarg($arg);
		}
	}

	/* Provide dummy globals so the function can build its command string. */
	$GLOBALS['database_hostname'] = '127.0.0.1';
	$GLOBALS['database_username'] = 'cacti_test_no_such_user';
	$GLOBALS['database_password'] = '';

	/* Include the script in "called by script server" mode so only the
	 * function definition is loaded, not the top-level print statement. */
	$called_by_script_server = true;
	if (!function_exists('ss_sql')) {
		require $ssSqlPhpPath;
	}

	/* mysqladmin will fail (bad credentials / no server), shell_exec returns
	 * null or empty.  ss_sql() must map that to 'U'. */
	expect(ss_sql())->toBe('U');
});

// --- no raw variable interpolation in shell commands ---

test('sql.php does not interpolate variables directly in shell strings', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->not->toMatch('/`[^`]*\$database_/');
});

test('ss_sql.php does not interpolate variables directly in shell strings', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->not->toMatch('/`[^`]*\$database_/');
});
