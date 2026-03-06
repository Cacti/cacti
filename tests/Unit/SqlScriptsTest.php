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
 * Tests for the backtick-to-shell_exec migration in scripts/sql.php
 * and scripts/ss_sql.php.
 *
 * PHP 8.4 deprecates the backtick operator. These scripts previously
 * used backticks to invoke mysqladmin, with unescaped variable
 * interpolation. The fix replaces backticks with shell_exec() and
 * wraps all user-supplied values in escapeshellarg().
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

test('sql.php escapes database_hostname with escapeshellarg', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->toContain('escapeshellarg($database_hostname)');
});

test('sql.php escapes database_username with escapeshellarg', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->toContain('escapeshellarg($database_username)');
});

test('sql.php escapes database_password with escapeshellarg', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->toContain('escapeshellarg($database_password)');
});

test('sql.php handles null return from shell_exec', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->toContain("?? ''");
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

test('ss_sql.php escapes database_hostname with escapeshellarg', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->toContain('escapeshellarg($database_hostname)');
});

test('ss_sql.php escapes database_username with escapeshellarg', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->toContain('escapeshellarg($database_username)');
});

test('ss_sql.php escapes database_password with escapeshellarg', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->toContain('escapeshellarg($database_password)');
});

test('ss_sql.php handles null return from shell_exec', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);

	expect($contents)->toContain("?? ''");
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
