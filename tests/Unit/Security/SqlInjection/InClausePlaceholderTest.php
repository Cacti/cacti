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

// Proves the IN-clause placeholder rewrite used to parameterise purge_old_graphs()
// targets the same rows as the previous implode(',', $ids) form.

require_once CACTI_PATH_TESTS . '/Helpers/UnitStubs.php';

// The exact placeholder builder used at the call sites.
$in_placeholders = static fn (array $ids): string => trim(str_repeat('?, ', cacti_sizeof($ids)), ', ');

test('placeholder count matches the id count', function () use ($in_placeholders) {
	expect($in_placeholders(array(5, 12, 88)))->toBe('?, ?, ?');
	expect($in_placeholders(array(7)))->toBe('?');
});

test('bound values reproduce the old inline IN list', function () use ($in_placeholders) {
	// array_rekey(..., col, col) yields an associative [id => id] map.
	$rekeyed = array(5 => 5, 12 => 12, 88 => 88);

	$old_inline = 'IN (' . implode(',', $rekeyed) . ')';
	$params     = array_values($rekeyed);
	$new_clause = 'IN (' . $in_placeholders($rekeyed) . ')';

	// Same number of slots, and the params are exactly the ids the old form inlined.
	expect(substr_count($new_clause, '?'))->toBe(count($params));
	expect($params)->toBe(array(5, 12, 88));

	// Substituting the params back into the placeholders rebuilds the old clause
	// (whitespace between list items is not significant to SQL).
	$rebuilt = $new_clause;
	foreach ($params as $v) {
		$rebuilt = preg_replace('/\?/', (string) $v, $rebuilt, 1);
	}
	$strip = fn ($s) => str_replace(' ', '', $s);
	expect($strip($rebuilt))->toBe($strip($old_inline));
});

test('array_values drops the associative keys PDO would misbind', function () {
	$rekeyed = array(5 => 5, 12 => 12);
	expect(array_values($rekeyed))->toBe(array(5, 12));
});

// Text search filter must not go through gfrv() (FILTER_VALIDATE_INT default).
// Sort URLs with an active non-numeric filter would die_html_input_error().
test('aggregate graph list sort URL keeps text filter via grv not gfrv', function () {
	$src = file_get_contents(CACTI_PATH_BASE . '/aggregate_graphs.php');
	expect($src)->not->toBeFalse('Failed to read aggregate_graphs.php');

	// Whitespace-tolerant so reformatting the call site doesn't break the test;
	// still pins that the sort URL passes the filter through grv(), not gfrv()
	// (which applies FILTER_VALIDATE_INT and would die_html_input_error() on a
	// non-numeric filter).
	expect(preg_match("/rawurlencode\(\s*grv\(\s*'filter'\s*\)\s*\)/", $src))->toBe(1,
		"the sort URL must build its filter param via grv('filter')");
	expect(preg_match("/rawurlencode\(\s*gfrv\(\s*'filter'\s*\)\s*\)/", $src))->toBe(0,
		"the sort URL must not switch to gfrv('filter')");
});
