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
 * #7379 (1.2.x backport): db_get_table_column_types() memoizes its SHOW COLUMNS
 * lookup, and every column-mutating DDL helper clears the cache so sql_save()
 * sees the new schema after an ALTER. 1.2.x has no sqlite-backed DB harness, so
 * this is a source-contract test guarding that structure (the behavioural
 * equivalence test runs on develop in #7381, where the code is byte-equivalent).
 */

$source = file_get_contents(__DIR__ . '/../../../lib/database.php');

function _db_col_cache_body(string $src, string $fn): string {
	$start = strpos($src, 'function ' . $fn . '(');
	if ($start === false) {
		return '';
	}

	$end = strpos($src, "\nfunction ", $start + 1);

	return substr($src, $start, $end !== false ? $end - $start : strlen($src) - $start);
}

test('db_get_table_column_types memoizes the SHOW COLUMNS lookup', function () use ($source) {
	$body = _db_col_cache_body($source, 'db_get_table_column_types');

	expect($body)->not->toBe('');
	// it still issues the SHOW COLUMNS query when uncached
	expect($body)->toContain('SHOW COLUMNS FROM $table_identifier');
	// but reads from and writes to the request-scoped cache
	expect($body)->toContain('global $database_sessions, $database_default, $database_hostname, $database_port, $db_column_type_cache');
	expect($body)->toContain('if (isset($db_column_type_cache[$key])) {');
	expect($body)->toContain('return $db_column_type_cache[$key];');
	expect($body)->toContain('$db_column_type_cache[$key] = $cols;');
});

test('db_column_type_cache_reset clears the cache', function () use ($source) {
	$body = _db_col_cache_body($source, 'db_column_type_cache_reset');

	expect($body)->not->toBe('');
	expect($body)->toContain('$db_column_type_cache = array();');
});

test('every column-mutating DDL helper clears the cache', function () use ($source) {
	// db_add_column and db_remove_column are the column mutators on 1.2.x
	foreach (array('db_add_column', 'db_remove_column') as $fn) {
		$body = _db_col_cache_body($source, $fn);

		expect($body)->not->toBe('');
		expect($body)->toContain('db_column_type_cache_reset();');
	}
});
