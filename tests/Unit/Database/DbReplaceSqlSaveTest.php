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

require_once dirname(__DIR__, 2) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 3) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/lib/database.php';

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

it('returns false for a failed update instead of the existing or stale insert id', function () {
	sql_save(['hostname' => 'host1'], 'host', 'id', true, $this->conn);
	$this->conn->exec("CREATE TRIGGER reject_host_save BEFORE INSERT ON host BEGIN SELECT RAISE(FAIL, 'save rejected'); END");

	$result = sql_save(['id' => 1, 'hostname' => 'renamed'], 'host', 'id', true, $this->conn);

	expect($result)->toBeFalse()
		->and($this->conn->query('SELECT hostname FROM host WHERE id = 1')->fetchColumn())->toBe('host1');
});

it('returns false for a failed insert even when the connection has an earlier insert id', function () {
	sql_save(['hostname' => 'host1'], 'host', 'id', true, $this->conn);
	$this->conn->exec("CREATE TRIGGER reject_host_save BEFORE INSERT ON host BEGIN SELECT RAISE(FAIL, 'save rejected'); END");

	expect(sql_save(['hostname' => 'host2'], 'host', 'id', true, $this->conn))->toBeFalse()
		->and((int) $this->conn->query('SELECT COUNT(*) FROM host')->fetchColumn())->toBe(1);
});

it('keeps the existing key fallback after a successful write with no new insert id', function () {
	$conn = new class extends FakeMySQLPDO {
		public function lastInsertId(?string $name = null): string|false {
			return '0';
		}

		public function prepare(string $query, array $options = []): PDOStatement|false {
			// MySQL reports numeric column types in lowercase; SQLite's
			// metadata otherwise treats this primary key as a quoted string.
			if ($query === 'SHOW COLUMNS FROM host') {
				$query = "SELECT name AS Field, lower(type) AS Type, 'NO' AS `Null`, '' AS `Default`, '' AS Extra FROM pragma_table_info('host')";
			}

			return parent::prepare($query, $options);
		}
	};
	$conn->exec('CREATE TABLE host (id INTEGER PRIMARY KEY, hostname TEXT NOT NULL)');
	$conn->exec("INSERT INTO host VALUES (1, 'host1')");

	expect((int) sql_save(['id' => 1, 'hostname' => 'renamed'], 'host', 'id', true, $conn))->toBe(1)
		->and($conn->query('SELECT hostname FROM host WHERE id = 1')->fetchColumn())->toBe('renamed');
});
