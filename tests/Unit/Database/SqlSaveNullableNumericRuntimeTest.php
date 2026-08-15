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

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 3) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/lib/database.php';

/*
 * Runtime coverage for sql_save() empty-value handling on nullable numeric
 * columns (issue #7023). An empty string must be stored as SQL NULL, but a
 * legitimate numeric 0 must be preserved as 0, not collapsed into NULL.
 */

beforeEach(function () {
	$this->conn = new FakeMySQLPDO();
	// 'threshold' is a nullable numeric column with no default. Declared 'double'
	// rather than 'int' because SQLite's pragma_table_info uppercases int/integer
	// ('INT'), which the case-sensitive str_contains($type, 'int') in sql_save()
	// would miss; float/double/decimal round-trip lowercase and hit the branch.
	$this->conn->exec('CREATE TABLE metric (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, threshold double)');
});

it('stores an empty string on a nullable numeric column as SQL NULL', function () {
	sql_save(['name' => 'a', 'threshold' => ''], 'metric', 'id', true, $this->conn);

	$row = $this->conn->query('SELECT threshold FROM metric WHERE name = \'a\'')->fetch(PDO::FETCH_ASSOC);
	expect($row['threshold'])->toBeNull();
});

it('preserves a numeric 0 on a nullable numeric column', function () {
	sql_save(['name' => 'b', 'threshold' => 0], 'metric', 'id', true, $this->conn);

	$row = $this->conn->query('SELECT threshold FROM metric WHERE name = \'b\'')->fetch(PDO::FETCH_ASSOC);
	expect($row['threshold'])->not->toBeNull()
		->and((int) $row['threshold'])->toBe(0);
});

it('preserves the string "0" on a nullable numeric column', function () {
	sql_save(['name' => 'c', 'threshold' => '0'], 'metric', 'id', true, $this->conn);

	$row = $this->conn->query('SELECT threshold FROM metric WHERE name = \'c\'')->fetch(PDO::FETCH_ASSOC);
	expect($row['threshold'])->not->toBeNull()
		->and((int) $row['threshold'])->toBe(0);
});
