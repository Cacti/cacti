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

// Load the audit tool as a library (functions only, no filesystem scan).
if (!defined('SQL_AUDIT_LIB_ONLY')) {
	define('SQL_AUDIT_LIB_ONLY', true);
}

require_once __DIR__ . '/../tools/sql_interpolation_audit.php';

function audit_db_functions(): array {
	return array(
		'db_execute', 'db_execute_prepared',
		'db_fetch_cell', 'db_fetch_cell_prepared',
		'db_fetch_row', 'db_fetch_row_prepared',
		'db_fetch_assoc', 'db_fetch_assoc_prepared',
		'db_fetch_insert_id',
	);
}

function audit_escaping_helpers(): array {
	return array('db_qstr', 'array_to_sql_or', 'array_to_sql');
}

function audit_scan(string $php): array {
	return scan_source("<?php\n" . $php, audit_db_functions(), audit_escaping_helpers());
}

// Classify the single db_* call in a snippet.
function category_of(string $php): string {
	$sites = audit_scan($php);
	expect($sites)->toHaveCount(1);

	return $sites[0]['category'];
}

test('prepared calls are safe regardless of the SQL text', function () {
	expect(category_of('db_fetch_cell_prepared("SELECT x FROM t WHERE id = ?", [$id]);'))->toBe('prepared');
});

test('fully static SQL is safe', function () {
	expect(category_of('db_execute("DELETE FROM settings WHERE name = \'foo\'");'))->toBe('static');
});

test('db_qstr escaped fragments are safe', function () {
	expect(category_of('db_fetch_assoc("SELECT * FROM host WHERE id = " . db_qstr($id));'))->toBe('qstr');
});

test('array_to_sql_or IN lists are safe', function () {
	expect(category_of('db_execute("DELETE FROM sites WHERE " . array_to_sql_or($items, \'id\'));'))->toBe('qstr');
});

test('a bare scalar in value position is a migration target', function () {
	expect(category_of('db_execute("UPDATE host SET status = $status WHERE id = $id");'))->toBe('value');
});

test('an unescaped function result in value position is a migration target', function () {
	expect(category_of('db_execute("DELETE FROM t WHERE id IN (" . implode(",", $ids) . ")");'))->toBe('value');
});

test('a variable table name is an identifier, not a value', function () {
	expect(category_of('db_execute("ALTER TABLE $table ROW_FORMAT=DYNAMIC");'))->toBe('identifier');
});

test('IF EXISTS does not hide an identifier interpolation', function () {
	expect(category_of('db_execute("DROP TABLE IF EXISTS $archive");'))->toBe('identifier');
});

test('SHOW COLUMNS FROM a variable table is an identifier', function () {
	expect(category_of('db_fetch_assoc("SHOW COLUMNS FROM $table");'))->toBe('identifier');
});

test('a prebuilt sql_where fragment is tracked separately from a scalar', function () {
	expect(category_of('db_fetch_assoc("SELECT * FROM colors $sql_where");'))->toBe('fragment');
});

test('a whole-query variable is opaque', function () {
	expect(category_of('db_execute($sql);'))->toBe('opaque');
});

test('a scalar dominates a fragment in the same statement', function () {
	// host_id = $device_id must still flag for binding even alongside $sql_swhere.
	$php = 'db_fetch_assoc("SELECT id FROM graph_local WHERE host_id = $device_id $sql_swhere");';
	expect(category_of($php))->toBe('value');
});

test('multi-line calls are found and classified', function () {
	$php = "db_fetch_assoc(\"SELECT id\n\tFROM graph_local\n\tWHERE host_id = \$hid\");";
	expect(category_of($php))->toBe('value');
});

test('non-db functions are ignored', function () {
	expect(audit_scan('my_execute("DELETE FROM t WHERE id = $id");'))->toBe(array());
});

test('is_fragment_name recognises Cacti fragment variables', function () {
	expect(is_fragment_name('sql_where'))->toBeTrue();
	expect(is_fragment_name('sql_swhere'))->toBeTrue();
	expect(is_fragment_name('sql_join'))->toBeTrue();
	expect(is_fragment_name('sql'))->toBeTrue();
	expect(is_fragment_name('device_id'))->toBeFalse();
	expect(is_fragment_name('status'))->toBeFalse();
	expect(is_fragment_name(null))->toBeFalse();
});
