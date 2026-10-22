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
 * Both scripts read the server counters over Cacti's database connection
 * instead of starting mysqladmin, so the database password never appears on
 * a command line. Runtime behaviour is covered by
 * tests/Unit/Scripts/ScriptServerScriptsUnknownTest.php.
 */

$sqlPhpPath   = __DIR__ . '/../../../scripts/sql.php';
$ssSqlPhpPath = __DIR__ . '/../../../scripts/ss_sql.php';

test('the sql scripts start no subprocess', function (string $path) {
	$contents = file_get_contents($path);

	expect($contents)->not->toContain('shell_exec(')
		->and($contents)->not->toContain('exec(')
		->and($contents)->not->toMatch('/`[^`]*mysqladmin[^`]*`/');
})->with([$sqlPhpPath, $ssSqlPhpPath]);

test('the sql scripts never handle the database password', function (string $path) {
	expect(file_get_contents($path))->not->toContain('$database_password');
})->with([$sqlPhpPath, $ssSqlPhpPath]);

test('the sql scripts read SHOW GLOBAL STATUS through the database helpers', function (string $path) {
	expect(file_get_contents($path))->toMatch('/db_fetch_(cell|assoc)\\("SHOW GLOBAL STATUS/');
})->with([$sqlPhpPath, $ssSqlPhpPath]);

test('the sql scripts return U on error', function (string $path) {
	// Cacti data source scripts must return 'U' on error, never empty string.
	expect(file_get_contents($path))->toContain("'U'");
})->with([$sqlPhpPath, $ssSqlPhpPath]);
