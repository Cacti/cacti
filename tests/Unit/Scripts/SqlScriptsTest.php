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

$sqlPhpPath   = __DIR__ . '/../../../scripts/sql.php';
$ssSqlPhpPath = __DIR__ . '/../../../scripts/ss_sql.php';

// --- scripts/sql.php: no backtick operators remain ---

test('sql.php contains no backtick operators', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->not->toMatch('/`[^`]*mysqladmin[^`]*`/');
});

test('sql.php uses cacti_exec_string for command execution', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->toContain("cacti_exec_string('mysqladmin', \$args)");
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

// --- no raw variable interpolation in shell commands ---

test('sql.php does not interpolate variables directly in shell strings', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->not->toMatch('/`[^`]*\$database_/');
});

test('ss_sql.php does not interpolate variables directly in shell strings', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->not->toMatch('/`[^`]*\$database_/');
});
