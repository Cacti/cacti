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
 * MibCache::select() pasted the column names it was given between quotes to
 * build an IN list, and pasted a single name into a column alias. Backticks
 * and quotes delimit; they do not escape. A name holding the delimiter ended
 * the list or the alias and left the remainder as SQL. Every caller in the
 * tree passes a literal array today, so nothing reached it, but the signature
 * accepts anything and the next caller would not have known.
 */

require_once dirname(__DIR__, 2) . '/lib/functions.php';

$src = file_get_contents(dirname(__DIR__, 2) . '/lib/mib_cache.php');

test('the source read succeeded', function () use ($src) {
	expect($src)->toBeString()->not->toBeEmpty();
});

test('column names are bound, not pasted into the IN list', function () use ($src) {
	expect($src)->not->toContain('implode("\',\'", $column)')
		->and(substr_count($src, "array_fill(0, cacti_sizeof(\$column), '?')"))->toBe(2)
		->and(substr_count($src, 'array_merge(array_values($column), array($filter))'))->toBe(2);
});

test('the alias is confined to identifier characters', function () use ($src) {
	expect($src)->not->toContain('SELECT value AS \'" . $column . "\'')
		->and($src)->toContain('$alias = sanitize_sql_column($column);')
		->and($src)->toContain("if (\$alias === '') {");
});

test('a name carrying the delimiter cannot escape the alias', function () {

	// the shape that used to close the alias and continue the statement
	expect(sanitize_sql_column("x` UNION SELECT password FROM user_auth -- "))
		->not->toContain('`')
		->and(sanitize_sql_column("x' UNION SELECT 1 -- "))->not->toContain("'");
});

test('placeholder count always matches the values bound', function () {
	foreach ([1, 2, 5, 17] as $n) {
		$column = array_fill(0, $n, 'cactiApplDeviceIndex');

		$placeholders = implode(',', array_fill(0, cacti_sizeof($column), '?'));
		$params       = array_merge(array_values($column), ['filter']);

		expect(substr_count($placeholders, '?'))->toBe($n)
			->and(cacti_sizeof($params))->toBe($n + 1);
	}
});
