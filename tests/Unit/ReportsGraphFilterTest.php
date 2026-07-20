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

// Guards the reports_tree_has_graphs() permission-filter modernisation: the
// unset-in-foreach was replaced with array_filter() + an arrow function. Both
// must keep exactly the graphs the predicate allows, with keys preserved.

// Old form: iterate and unset disallowed entries.
function legacy_filter_allowed(array $graphs, callable $allowed): array {
	foreach ($graphs as $key => $id) {
		if (!$allowed($id)) {
			unset($graphs[$key]);
		}
	}

	return $graphs;
}

test('array_filter matches the legacy unset loop, keys preserved', function () {
	$graphs  = [10 => 10, 11 => 11, 12 => 12, 13 => 13];
	$allowed = fn($id) => $id % 2 == 0;   // stand-in for is_graph_allowed()

	$new = array_filter($graphs, $allowed);

	expect($new)->toBe(legacy_filter_allowed($graphs, $allowed))
		->and($new)->toBe([10 => 10, 12 => 12]);
});

test('empty and all-denied inputs behave the same', function () {
	$deny = fn($id) => false;
	expect(array_filter([], $deny))->toBe(legacy_filter_allowed([], $deny));
	expect(array_filter([1 => 1, 2 => 2], $deny))->toBe(legacy_filter_allowed([1 => 1, 2 => 2], $deny));
});
