<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Cross-file contract test for the aggregate totalling path (#7216).
 *
 * Applying the totalling cdef to GPRINT items is safe only while
 * rrdtool_function_graph() keeps the empty-cdef guard from #6985.
 * The guard skips GPRINT items whose consolidation function does not
 * match the data source instead of emitting a bare "CDEF:cdefX=".
 * These two invariants must hold together.
 */

$root = dirname(__DIR__, 2);

test('rrd.php keeps the empty cdef guard that makes full totalling safe', function () use ($root) {
	$src = file_get_contents($root . '/lib/rrd.php');
	expect($src)->not->toBeFalse('Failed to read lib/rrd.php');

	$pattern = '/if\s*\(\s*\$cdef_string\s*===\s*\'\'\s*\)\s*\{[^}]*continue;/s';
	expect(preg_match($pattern, $src))->toBe(1,
		'the empty cdef guard must stay in lib/rrd.php while totalling covers GPRINT items');
});

test('totalling replaces CURRENT_DATA_SOURCE for both total types', function () use ($root) {
	$src = file_get_contents($root . '/lib/aggregate.php');
	expect($src)->not->toBeFalse('Failed to read lib/aggregate.php');

	foreach (['SIMILAR_DATA_SOURCES_NODUPS', 'ALL_DATA_SOURCES_NODUPS'] as $replacement) {
		$pattern = "/str_replace\('CURRENT_DATA_SOURCE',\s*'" . $replacement . "'/";
		expect(preg_match($pattern, $src))->toBe(1,
			"totalling must build the $replacement cdef");
	}
});

test('totalling query and cdef update operate on the same item set', function () use ($root) {
	$src = file_get_contents($root . '/lib/aggregate.php');
	expect($src)->not->toBeFalse('Failed to read lib/aggregate.php');

	$fnPos = strpos($src, 'function aggregate_cdef_totalling(');
	expect($fnPos)->not->toBeFalse('aggregate_cdef_totalling must exist');

	$fnEnd = strpos($src, "\nfunction ", $fnPos + 1);
	$body  = substr($src, $fnPos, ($fnEnd === false ? strlen($src) : $fnEnd) - $fnPos);

	// every selected item gets the new cdef; no filtering between select and update
	expect(strpos($body, 'SELECT id, cdef_id'))->not->toBeFalse('item selection missing');

	$pattern = '/UPDATE graph_templates_item\s+SET cdef_id\s*=\s*\?\s+WHERE id\s*=\s*\?/s';
	expect(preg_match($pattern, $body))->toBe(1,
		'cdef update must remain prepared and keyed by item id');
});
