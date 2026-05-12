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
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';

// db_table_exists / db_column_exists rely on MySQL's SHOW TABLES /
// SHOW COLUMNS syntax. FakeMySQLPDO rewrites those statements at
// prepare()/exec()/query() time into the sqlite_master / pragma_table_info
// equivalents so the real lib/database.php functions execute unmodified.

beforeEach(function () {
	$this->conn = new FakeMySQLPDO();
	$this->conn->exec('CREATE TABLE host (id INTEGER PRIMARY KEY AUTOINCREMENT, hostname TEXT NOT NULL)');
});

it('reports an existing table as present', function () {
	expect(db_table_exists('host', false, $this->conn))->toBe(true);
});

it('reports a missing table as absent', function () {
	expect(db_table_exists('does_not_exist', false, $this->conn))->toBe(false);
});

it('reports an existing column as present', function () {
	expect(db_column_exists('host', 'hostname', false, $this->conn))->toBe(true);
});

it('reports a missing column as absent', function () {
	expect(db_column_exists('host', 'missing', false, $this->conn))->toBe(false);
});
