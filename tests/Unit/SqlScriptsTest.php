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

$sqlPhpPath   = __DIR__ . '/../../scripts/sql.php';
$ssSqlPhpPath = __DIR__ . '/../../scripts/ss_sql.php';

test('sql.php uses CactiProcess for command execution', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);
	expect($contents)->toContain('CactiProcess::run(');
});

test('sql.php uses MYSQL_PWD for passwords', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);
	expect($contents)->toContain("['MYSQL_PWD'] = \$database_password;");
});

test('ss_sql.php uses CactiProcess for command execution', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);
	expect($contents)->toContain('CactiProcess::run(');
});

test('ss_sql.php uses MYSQL_PWD for passwords', function () use ($ssSqlPhpPath) {
	$contents = file_get_contents($ssSqlPhpPath);
	expect($contents)->toContain("['MYSQL_PWD'] = \$database_password;");
});
