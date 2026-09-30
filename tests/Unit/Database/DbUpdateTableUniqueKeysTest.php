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
 * db_update_table() now reconciles unique_keys[] alongside keys[] so a plugin's
 * UNIQUE index survives a schema refresh. 1.2.x has no sqlite-backed DB harness,
 * so the reconciliation itself is guarded as a source-contract test (as the
 * db-layer unit tests on this branch do); db_index_columns_to_array(), which
 * normalizes the legacy string-form columns, is a pure helper and is exercised
 * behaviourally. The end-to-end behaviour is covered on develop in #8116's
 * DbUpdateTableUniqueKeysTest, where the code runs against the fake PDO.
 */

require_once dirname(__DIR__, 3) . '/lib/database.php';

$source = file_get_contents(dirname(__DIR__, 3) . '/lib/database.php');

function _db_unique_keys_body(string $src, string $fn): string {
	$start = strpos($src, 'function ' . $fn . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($src, "\nfunction ", $start + 1);

	return substr($src, $start, $end !== false ? $end - $start : strlen($src) - $start);
}

test('db_index_columns_to_array passes an array through, trimmed', function () {
	expect(db_index_columns_to_array(array('user_id', 'type')))->toBe(array('user_id', 'type'))
		->and(db_index_columns_to_array(array('`user_id`', 'type ')))->toBe(array('user_id', 'type'));
});

test('db_index_columns_to_array splits the legacy backtick-joined string form', function () {
	expect(db_index_columns_to_array('user_id`,`type'))->toBe(array('user_id', 'type'))
		->and(db_index_columns_to_array('`user_id`,`type`'))->toBe(array('user_id', 'type'))
		->and(db_index_columns_to_array('user_id'))->toBe(array('user_id'));
});

test('db_update_table folds unique_keys and normalizes declared columns', function () use ($source) {
	$body = _db_unique_keys_body($source, 'db_update_table');

	expect($body)->not->toBe('')
		->and($body)->toContain("isset(\$data['unique_keys'])")
		->and($body)->toContain("db_index_columns_to_array(\$k['columns'])");
});

test('db_update_table reconciles a declared index and corrects uniqueness drift', function () use ($source) {
	$body = _db_unique_keys_body($source, 'db_update_table');

	// a declared index (regular or unique) is not treated as undeclared, and
	// uniqueness drift against the live schema is corrected with the UNIQUE flag
	expect($body)->toContain('isset($declared_keys[$n])')
		->and($body)->toContain("\$uniqueindex[\$n] !== \$k['unique']")
		->and($body)->toContain("? 'UNIQUE ' : ''");
});

test('db_table_create honors the legacy unique_keys definition', function () use ($source) {
	$body = _db_unique_keys_body($source, 'db_table_create');

	expect($body)->not->toBe('')
		->and($body)->toContain("\$data['unique_keys']");
});
