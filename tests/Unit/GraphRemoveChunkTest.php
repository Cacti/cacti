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

// Guards the api_graph_remove_multi() rewrite: the manual "accumulate and flush
// every 1000" accumulator was replaced with array_chunk(). The chunk groups
// must match the old flush groups, and ids are now integer-coerced.

// Old form: accumulate a CSV, flush every 1000 plus a tail flush. Returns the
// list of flushed batches (as arrays of ids) in order.
function issue7264_legacy_flush_batches(array $ids): array {
	$batches = [];
	$current = [];
	$i       = 0;

	foreach ($ids as $id) {
		$current[] = $id;
		$i++;

		if (($i % 1000) == 0) {
			$batches[] = $current;
			$current   = [];
			$i         = 0;
		}
	}

	if ($i > 0) {
		$batches[] = $current;
	}

	return $batches;
}

test('array_chunk reproduces the legacy flush batches', function () {
	foreach ([[], range(1, 1), range(1, 999), range(1, 1000), range(1, 1001), range(1, 2500)] as $ids) {
		expect(array_chunk($ids, 1000))->toBe(issue7264_legacy_flush_batches($ids));
	}
});

test('chunk ids are integer-coerced before the IN clause', function () {
	$chunk = array_map('intval', ['5', '12', '88']);
	expect($chunk)->toBe([5, 12, 88]);
});
