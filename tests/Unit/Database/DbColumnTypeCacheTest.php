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

/*
 * #7379: db_get_table_column_types() memoizes its SHOW COLUMNS lookup so that
 * sql_save() does not re-query the same tables on every write. These tests
 * prove the cache is transparent (identical output whether served fresh or from
 * cache), that it can be cleared, and that every column-mutating DDL helper
 * clears it so sql_save() sees the new schema after an ALTER.
 */

beforeEach(function () {
	// isolate the process-global cache between tests
	db_column_type_cache_reset();
});

it('returns the column types for a table', function () {
	$c = new FakeMySQLPDO();
	$c->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');

	expect(array_keys(db_get_table_column_types('t', $c)))->toBe(['id', 'name']);
});

it('serves the same result from cache and re-reads after a reset', function () {
	$c = new FakeMySQLPDO();
	$c->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');

	$first = db_get_table_column_types('t', $c);
	expect(array_keys($first))->toBe(['id', 'name']);

	// change the schema WITHOUT the DDL helpers, so the cache is not invalidated
	$c->exec('ALTER TABLE t ADD COLUMN extra TEXT');

	$cached = db_get_table_column_types('t', $c);
	// still cached: the out-of-band column is not visible, and the output is
	// byte-identical to the first call (the cache never changes the result)
	expect($cached)->toBe($first);
	expect(array_keys($cached))->toBe(['id', 'name']);

	// clearing the cache (what the DDL helpers do) makes it re-query
	db_column_type_cache_reset();
	expect(array_keys(db_get_table_column_types('t', $c)))->toBe(['id', 'name', 'extra']);
});

it('keys the cache per connection', function () {
	$a = new FakeMySQLPDO();
	$a->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, a TEXT)');

	$b = new FakeMySQLPDO();
	$b->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, b TEXT)');

	// same table name, different connections -> each returns its own columns
	expect(array_keys(db_get_table_column_types('t', $a)))->toBe(['id', 'a']);
	expect(array_keys(db_get_table_column_types('t', $b)))->toBe(['id', 'b']);
});

test('every column-mutating DDL helper clears the cache', function () {
	$src = file_get_contents(dirname(__DIR__, 3) . '/lib/database.php');

	foreach (['db_add_column', 'db_change_column', 'db_remove_column'] as $fn) {
		if (!preg_match('/^function ' . $fn . '\(.*?^\}/sm', $src, $m)) {
			continue; // db_change_column does not exist on 1.2.x
		}

		expect($m[0])->toContain('db_column_type_cache_reset();');
	}
});
