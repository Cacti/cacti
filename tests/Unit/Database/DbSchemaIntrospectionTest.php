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

require_once CACTI_PATH_TESTS . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_TESTS . '/Helpers/FakeMySQLPDO.php';
require_once CACTI_PATH_INCLUDE . '/vendor/autoload.php';
require_once CACTI_PATH_LIBRARY . '/database.php';

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

it('isolates table existence cache entries by connection', function () {
	$with_table    = new FakeMySQLPDO();
	$without_table = new FakeMySQLPDO();
	$with_table->exec('CREATE TABLE connection_cache_table (id INTEGER)');

	expect(db_table_exists('connection_cache_table', false, $with_table))->toBeTrue()
		->and(db_table_exists('connection_cache_table', false, $with_table))->toBeTrue()
		->and(db_table_exists('connection_cache_table', false, $without_table))->toBeFalse();
});

it('isolates column existence cache entries by connection', function () {
	$with_column    = new FakeMySQLPDO();
	$without_column = new FakeMySQLPDO();
	$with_column->exec('CREATE TABLE connection_cache_columns (id INTEGER, present TEXT)');
	$without_column->exec('CREATE TABLE connection_cache_columns (id INTEGER)');

	expect(db_column_exists('connection_cache_columns', 'present', false, $with_column))->toBeTrue()
		->and(db_column_exists('connection_cache_columns', 'present', false, $with_column))->toBeTrue()
		->and(db_column_exists('connection_cache_columns', 'present', false, $without_column))->toBeFalse();
});

it('builds stable cache keys for every supported connection representation', function () {
	$first_object  = new stdClass();
	$second_object = new stdClass();
	$resource      = fopen('php://memory', 'r+');

	expect(db_connection_cache_key(false))->toBe('-1')
		->and(db_connection_cache_key($first_object))->toBe(db_connection_cache_key($first_object))
		->and(db_connection_cache_key($first_object))->not->toBe(db_connection_cache_key($second_object))
		->and(db_connection_cache_key($resource))->toBe('resource:' . get_resource_id($resource))
		->and(db_connection_cache_key('connection-name'))->toBe(db_connection_cache_key('connection-name'))
		->and(db_connection_cache_key('connection-name'))->not->toBe(db_connection_cache_key('other-connection'));

	fclose($resource);
});
