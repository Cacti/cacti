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
 * Both scripts report server status from SHOW GLOBAL STATUS over Cacti's own
 * database connection, so neither may start a client process or touch the
 * database password.  Their output is checked in
 * tests/fixtures/Core/MysqlClientCredentials.php.
 */

dataset('sql scripts', [
	'sql.php'    => __DIR__ . '/../../../scripts/sql.php',
	'ss_sql.php' => __DIR__ . '/../../../scripts/ss_sql.php',
]);

test('the script starts no client process', function (string $path) {
	$contents = file_get_contents($path);

	expect($contents)->not->toMatch('/`[^`]*`/')
		->and(preg_match('/\b(shell_exec|exec|passthru|system|proc_open|popen)\s*\(/', $contents))->toBe(0)
		->and($contents)->not->toContain('mysqladmin --');
})->with('sql scripts');

test('the script never reads the database password', function (string $path) {
	expect(file_get_contents($path))->not->toContain('$database_password');
})->with('sql scripts');

test('the script reads server status over the database connection', function (string $path) {
	$contents = file_get_contents($path);

	expect($contents)->toContain('SHOW GLOBAL STATUS')
		->and($contents)->toContain("return 'U';");
})->with('sql scripts');
