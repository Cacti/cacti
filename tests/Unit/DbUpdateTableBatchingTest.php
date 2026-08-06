<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$source = file_get_contents(dirname(__DIR__, 2) . '/lib/database.php');
expect($source)->not->toBeFalse('lib/database.php must be readable');

$start = strpos($source, 'function db_update_table(');
expect($start)->not->toBeFalse('db_update_table() must exist');

$end = strpos($source, 'function db_format_index_create(', $start);
expect($end)->not->toBeFalse('db_format_index_create() must follow db_update_table()');

$body = substr($source, $start, $end - $start);

test('db_update_table batches every schema change into one alter statement', function () use ($body) {
	expect(substr_count($body, 'db_execute('))->toBe(1)
		->and($body)->toContain("implode(', ', \$alter_clauses)")
		->and($body)->toContain("'ADD ' . \$column_definition")
		->and($body)->toContain("'CHANGE `'")
		->and($body)->toContain("'DROP COLUMN `'")
		->and($body)->toContain("'ADD' . (isset(\$k['unique'])")
		->and($body)->toContain("'DROP PRIMARY KEY'")
		->and($body)->not->toContain('db_add_column(')
		->and($body)->not->toContain('db_remove_column(');
});
