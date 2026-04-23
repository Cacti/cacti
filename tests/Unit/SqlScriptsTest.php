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
 * Covers the mysqladmin wrapper hardening:
 * 1. Commands are executed through cacti_exec_string().
 * 2. Arguments are passed as structured argv pieces (no hand-built shell
 *    command strings in these scripts).
 */

$sqlPhpPath   = __DIR__ . '/../../scripts/sql.php';
$ssSqlPhpPath = __DIR__ . '/../../scripts/ss_sql.php';

// --- scripts/sql.php: no backtick operators remain ---

test('sql.php contains no backtick operators', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->not->toMatch('/`[^`]*mysqladmin[^`]*`/');
});

test('sql.php uses cacti_exec_string for command execution', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->toContain("cacti_exec_string('mysqladmin', \$args)");
});

test('sql.php passes host argument as structured mysqladmin flag', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->toContain("'--host=' . \$database_hostname");
});

test('sql.php passes user argument as structured mysqladmin flag', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->toContain("'--user=' . \$database_username");
});

test('sql.php passes password argument as structured mysqladmin flag', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->toContain("'--password=' . \$database_password");
});

test('sql.php does not call shell_exec directly', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->not->toContain('shell_exec(');
});

test('sql.php returns U on empty execution output', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	/* Cacti data source scripts must return 'U' on error, never empty string. */
	expect($contents)->toContain("if (\$output === null || \$output === '')");
	expect($contents)->toContain("print 'U';");
});

// --- scripts/ss_sql.php: no backtick operators remain ---

test('ss_sql.php contains no backtick operators', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->not->toMatch('/`[^`]*mysqladmin[^`]*`/');
});

test('ss_sql.php uses cacti_exec_string for command execution', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->toContain("cacti_exec_string('mysqladmin', \$args)");
});

test('ss_sql.php passes host argument as structured mysqladmin flag', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->toContain("'--host=' . \$database_hostname");
});

test('ss_sql.php passes user argument as structured mysqladmin flag', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->toContain("'--user=' . \$database_username");
});

test('ss_sql.php passes password argument as structured mysqladmin flag', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->toContain("'--password=' . \$database_password");
});

test('ss_sql.php does not call shell_exec directly', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->not->toContain('shell_exec(');
});

test('ss_sql.php returns U on empty/null shell_exec output', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	/* Cacti data source scripts must return 'U' on error, never empty string. */
	expect($contents)->toContain(": 'U'");
});

// --- runtime: cacti_exec_string is callable and ss_sql() falls back to 'U' ---

test('ss_sql() returns U when cacti_exec_string produces no output', function () use ($ssSqlPhpPath) {
	/* Bootstrap cacti_exec_string if global.php has not yet been loaded. */
	if (!function_exists('cacti_exec_string')) {
		function cacti_exec_string($binary, array $args = array()) {
			return '';
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

	/* mysqladmin will fail (bad credentials / no server), cacti_exec_string returns
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
