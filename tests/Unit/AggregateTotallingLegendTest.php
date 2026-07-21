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
 * Source-scan tests for the aggregate totalling legend fix (#7216).
 *
 * The totalling cdef must be applied to every graph item from the
 * totalling sequence on, including GPRINT legend items.  #6985 added a
 * graph_type_id exclusion to the selection query; the excluded GPRINT
 * items then kept CURRENT_DATA_SOURCE and the legend printed untotalled
 * single data source values.
 */

function getAggregateTotallingSource(): string {
	$path = __DIR__ . '/../../lib/aggregate.php';
	$src  = file_get_contents($path);
	expect($src)->not->toBeFalse('Failed to read lib/aggregate.php');

	return $src;
}

test('totalling item query has no graph_type_id exclusion', function () {
	$src = getAggregateTotallingSource();

	$fnPos = strpos($src, 'function aggregate_cdef_totalling(');
	expect($fnPos)->not->toBeFalse('aggregate_cdef_totalling must exist');

	$fnEnd = strpos($src, "\nfunction ", $fnPos + 1);
	$body  = substr($src, $fnPos, ($fnEnd === false ? strlen($src) : $fnEnd) - $fnPos);

	expect(strpos($body, 'graph_type_id NOT IN'))->toBeFalse(
		'the totalling query must select GPRINT legend items too, or the legend prints untotalled values (#7216)');
});

test('totalling item query stays prepared and sequence ordered', function () {
	$src = getAggregateTotallingSource();

	$pattern = '/db_fetch_assoc_prepared\(\s*\'SELECT id, cdef_id\s+FROM graph_templates_item\s+WHERE local_graph_id = \?\s+AND sequence >= \?\s+ORDER BY sequence\'/s';
	expect(preg_match($pattern, $src))->toBe(1,
		'the totalling item query must remain a prepared statement selecting by graph and sequence only');
});
