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
 * Source-scan tests for the empty CDEF guard fix.
 *
 * Aggregate graphs can produce an empty RPN expression for GPRINT items
 * whose consolidation function does not match the data source.  Without
 * the guard, rrdtool_function_graph() emits a bare "CDEF:cdefX=" line
 * that rrdtool rejects.
 *
 * The fix adds: if ($cdef_string === '') { continue; }
 * before the CDEF name generation line in lib/rrd.php.
 *
 * A secondary fix in lib/aggregate.php converts the raw db_execute()
 * UPDATE for cdef_id to db_execute_prepared() for SQL safety.
 */

// --- helpers ---

function getRrdSource(): string {
	$path = __DIR__ . '/../../lib/rrd.php';
	$src  = file_get_contents($path);
	expect($src)->not->toBeFalse('Failed to read lib/rrd.php');

	return $src;
}

function getAggregateSource(): string {
	$path = __DIR__ . '/../../lib/aggregate.php';
	$src  = file_get_contents($path);
	expect($src)->not->toBeFalse('Failed to read lib/aggregate.php');

	return $src;
}

// --- lib/rrd.php: empty cdef guard ---

test('rrd.php has empty cdef_string guard before CDEF name generation', function () {
	$src = getRrdSource();

	// The guard: if ($cdef_string === '') { ... continue; }
	// must appear before the CDEF:cdef name generation line
	$guardPos = strpos($src, "\$cdef_string === ''");
	expect($guardPos)->not->toBeFalse(
		"lib/rrd.php must contain the empty cdef_string guard"
	);

	// The CDEF name generation line follows after the guard
	$cdefNamePos = strpos($src, "CDEF:cdef", $guardPos);
	expect($cdefNamePos)->not->toBeFalse(
		"'CDEF:cdef' name generation must appear after the empty cdef guard"
	);
	expect($cdefNamePos)->toBeGreaterThan($guardPos,
		"The empty cdef guard must precede the CDEF name generation"
	);
});

test('empty cdef guard uses strict identity comparison', function () {
	$src = getRrdSource();

	// Must use === not == to avoid false positives on '0' or other falsy strings
	$pattern = '/if\s*\(\s*\$cdef_string\s*===\s*\'\'\s*\)/';
	expect(preg_match($pattern, $src))->toBe(1,
		"The empty cdef guard must use strict === comparison"
	);
});

test('empty cdef guard block contains continue statement', function () {
	$src = getRrdSource();

	// The guard block must have a continue to skip the current loop iteration
	$pattern = '/if\s*\(\s*\$cdef_string\s*===\s*\'\'\s*\)\s*\{[^}]*continue;/s';
	expect(preg_match($pattern, $src))->toBe(1,
		"The empty cdef guard must contain a continue statement"
	);
});

test('empty cdef guard includes debug logging', function () {
	$src = getRrdSource();

	// The guard should log before continuing, so the skip is traceable
	$pattern = '/if\s*\(\s*\$cdef_string\s*===\s*\'\'\s*\)\s*\{[^}]*cacti_log\([^)]*Empty CDEF/s';
	expect(preg_match($pattern, $src))->toBe(1,
		"The empty cdef guard should log a debug message about the empty CDEF"
	);
});

// --- lib/aggregate.php: db_execute_prepared for cdef_id UPDATE ---

test('aggregate.php uses db_execute_prepared for cdef_id UPDATE', function () {
	$src = getAggregateSource();

	// The cdef_id UPDATE must use db_execute_prepared, not raw db_execute
	$pattern = "/db_execute_prepared\s*\(\s*'UPDATE graph_templates_item\s+SET cdef_id/s";
	expect(preg_match($pattern, $src))->toBe(1,
		"lib/aggregate.php must use db_execute_prepared for the cdef_id UPDATE"
	);
});

test('aggregate.php cdef_id UPDATE does not use string interpolation', function () {
	$src = getAggregateSource();

	// Old pattern: "UPDATE graph_templates_item SET cdef_id=$new_cdef_id WHERE id=" . $graph_template_item["id"]
	// This must not exist anymore
	$pattern = '/db_execute\s*\(\s*"UPDATE graph_templates_item\s+SET cdef_id=\$/';
	expect(preg_match($pattern, $src))->toBe(0,
		"lib/aggregate.php must not use string-interpolated db_execute for cdef_id UPDATE"
	);
});

test('aggregate.php cdef_id UPDATE uses parameter binding', function () {
	$src = getAggregateSource();

	// The prepared statement should use ? placeholders and an array parameter
	$pattern = "/UPDATE graph_templates_item\s+SET cdef_id\s*=\s*\?\s+WHERE id\s*=\s*\?/s";
	expect(preg_match($pattern, $src))->toBe(1,
		"cdef_id UPDATE must use ? placeholders for parameter binding"
	);
});
