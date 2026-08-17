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
 * Source-scan tests for the sql_save() empty-value handling on numeric
 * columns in lib/database.php (issue #7023).
 *
 * A nullable numeric column with an empty incoming value must be saved as
 * SQL NULL rather than silently coerced to 0. A NOT-NULL numeric column
 * with no default has no safe NULL fallback, so it must keep coercing to 0.
 */

function getDatabaseSource(): string {
	$src = file_get_contents(__DIR__ . '/../../../lib/database.php');
	expect($src)->not->toBeFalse('Failed to read lib/database.php');
	return $src;
}

test('sql_save assigns NULL for nullable numeric columns on empty value', function () {
	$src = getDatabaseSource();
	expect($src)->toMatch('/\$cols\[\$key\]\[\'null\'\] == \'YES\'\)\s*\{\s*\$array_items\[\$key\] = \'NULL\';/');
});

test('sql_save no longer coerces nullable numeric columns to 0', function () {
	$src = getDatabaseSource();
	expect($src)->not->toMatch('/\$cols\[\$key\]\[\'null\'\] == \'YES\'\)\s*\{\s*\/\/ TODO/');
});

test('sql_save still coerces NOT-NULL numeric columns with no default to 0', function () {
	$src = getDatabaseSource();
	expect($src)->toMatch('/\$cols\[\$key\]\[\'default\'\] == \'\'\)\s*\{\s*\/\/ TODO: We should make \'NULL\', but there are issues that need to be addressed first\s*\$array_items\[\$key\] = 0;/');
});
