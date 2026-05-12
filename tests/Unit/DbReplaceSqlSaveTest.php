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

// FakeMySQLPDO rewrites SHOW TABLES / SHOW COLUMNS and
// INSERT ... ON DUPLICATE KEY UPDATE into the sqlite_master /
// pragma_table_info / INSERT OR REPLACE equivalents, so the real
// _db_replace, db_replace, and sql_save functions execute unmodified.

beforeEach(function () {
	$this->conn = new FakeMySQLPDO();
	$this->conn->exec('CREATE TABLE host (id INTEGER PRIMARY KEY AUTOINCREMENT, hostname TEXT NOT NULL, disabled TEXT DEFAULT \'\')');
	$this->conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
});

it('inserts via sql_save and returns the new auto-increment id', function () {
	$id = sql_save(['hostname' => 'host1', 'disabled' => ''], 'host', 'id', true, $this->conn);

	expect((int) $id)->toBe(1);

	$row = $this->conn->query('SELECT hostname FROM host WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
	expect($row['hostname'])->toBe('host1');
});

it('updates an existing row when sql_save receives an explicit id', function () {
	sql_save(['hostname' => 'host1', 'disabled' => ''], 'host', 'id', true, $this->conn);
	sql_save(['id' => 1, 'hostname' => 'renamed'], 'host', 'id', true, $this->conn);

	$row = $this->conn->query('SELECT hostname FROM host WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
	expect($row['hostname'])->toBe('renamed');
});

it('REPLACE-INTO semantics keep a single row keyed by name', function () {
	// _db_replace inlines field values directly into the SQL string, so the
	// caller must pre-quote string values (sql_save does this via db_qstr).
	db_replace('settings', ['name' => "'opt'", 'value' => "'v1'"], 'name', $this->conn);
	db_replace('settings', ['name' => "'opt'", 'value' => "'v2'"], 'name', $this->conn);

	$rows = $this->conn->query('SELECT name, value FROM settings')->fetchAll(PDO::FETCH_ASSOC);
	expect(count($rows))->toBe(1)
		->and($rows[0]['name'])->toBe('opt')
		->and($rows[0]['value'])->toBe('v2');
});
