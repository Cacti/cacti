<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Regression test for the GPRINT consolidation-function cache key in
 * rrdtool_function_graph() (lib/rrd.php ~1812/1824).
 *
 * The $last_graph_cf cache records, for each drawn graph item (AREA/LINE/...),
 * the CF that was used so a following GPRINT can reuse it. It was keyed by
 * data_source_name plus local_data_template_rrd_id. local_data_template_rrd_id
 * is the DATA TEMPLATE's rrd row, so two local data sources built from the same
 * template (e.g. traffic_in on host A and host B inside one aggregate graph)
 * share a key, and non-templated sources all collide on 0. The GPRINT then
 * prints the wrong host's CF.
 *
 * The fix keys the cache by data_template_rrd_id (dtr.id), which is per graph
 * item / per local data source, matching how the DEF cache ($cf_ds_cache) is
 * already keyed. A GPRINT shares its AREA's task_item_id, hence the same
 * data_template_rrd_id, so it resolves to its own item's CF.
 *
 * @group regression
 */

/**
 * Build the $last_graph_cf cache for drawn items, then resolve the CF a GPRINT
 * would reuse. $keyField selects the discriminator: 'data_template_rrd_id'
 * (fixed, per-instance) or 'local_data_template_rrd_id' (buggy, template-scoped).
 *
 * @param array  $items    drawn items followed by GPRINTs, each carrying
 *                         data_source_name, data_template_rrd_id,
 *                         local_data_template_rrd_id and graph_cf
 * @param string $keyField discriminator field to key the cache by
 *
 * @return array map of GPRINT index => resolved CF
 */
function gprint_cf_resolve(array $items, string $keyField): array {
	$last_graph_cf = [];
	$resolved      = [];

	foreach ($items as $idx => $item) {
		if ($item['kind'] === 'draw') {
			$last_graph_cf[$item['data_source_name']][$item[$keyField]] = $item['graph_cf'];
		} elseif ($item['kind'] === 'gprint') {
			$resolved[$idx] = $last_graph_cf[$item['data_source_name']][$item[$keyField]] ?? null;
		}
	}

	return $resolved;
}

// Aggregate graph: two hosts, both traffic_in from the same data template.
// Host A draws AVERAGE (cf 1), host B draws MAX (cf 3). Aggregate graphs emit
// the AREA/STACK draw items grouped ahead of the GPRINT legend block, so by the
// time a GPRINT reads the cache a later same-key draw has already written it.
// Each GPRINT shares its AREA's task_item_id, hence the same data_template_rrd_id.
function two_host_aggregate(): array {
	return [
		// host A AREA/AVERAGE
		['kind'                 => 'draw', 'data_source_name' => 'traffic_in',
			'data_template_rrd_id' => 10, 'local_data_template_rrd_id' => 100, 'graph_cf' => 1],
		// host B AREA/MAX
		['kind'                 => 'draw', 'data_source_name' => 'traffic_in',
			'data_template_rrd_id' => 20, 'local_data_template_rrd_id' => 100, 'graph_cf' => 3],
		// host A GPRINT
		['kind'                 => 'gprint', 'data_source_name' => 'traffic_in',
			'data_template_rrd_id' => 10, 'local_data_template_rrd_id' => 100],
		// host B GPRINT
		['kind'                 => 'gprint', 'data_source_name' => 'traffic_in',
			'data_template_rrd_id' => 20, 'local_data_template_rrd_id' => 100],
	];
}

test('fixed key resolves each GPRINT to its own item CF', function () {
	$resolved = gprint_cf_resolve(two_host_aggregate(), 'data_template_rrd_id');

	// host A GPRINT at index 2 -> AVERAGE (1); host B GPRINT at index 3 -> MAX (3)
	expect($resolved[2])->toBe(1);
	expect($resolved[3])->toBe(3);
});

test('template-scoped key collides across instances of the same template', function () {
	$resolved = gprint_cf_resolve(two_host_aggregate(), 'local_data_template_rrd_id');

	// Both hosts share local_data_template_rrd_id 100, so host B's MAX overwrites
	// host A's AVERAGE before either GPRINT reads, and both GPRINTs print MAX.
	// This is the bug the fix removes.
	expect($resolved[2])->toBe(3);
	expect($resolved[3])->toBe(3);
});

test('non-templated sources do not collide under the fixed key', function () {
	// Manually built data sources have local_data_template_rrd_id 0 for every
	// item, so the template-scoped key collapses them all together.
	$items = [
		['kind'                 => 'draw', 'data_source_name' => 'in',
			'data_template_rrd_id' => 5, 'local_data_template_rrd_id' => 0, 'graph_cf' => 1],
		['kind'                 => 'draw', 'data_source_name' => 'in',
			'data_template_rrd_id' => 6, 'local_data_template_rrd_id' => 0, 'graph_cf' => 2],
		['kind'                 => 'gprint', 'data_source_name' => 'in',
			'data_template_rrd_id' => 5, 'local_data_template_rrd_id' => 0],
		['kind'                 => 'gprint', 'data_source_name' => 'in',
			'data_template_rrd_id' => 6, 'local_data_template_rrd_id' => 0],
	];

	$fixed = gprint_cf_resolve($items, 'data_template_rrd_id');
	expect($fixed[2])->toBe(1);
	expect($fixed[3])->toBe(2);

	$buggy = gprint_cf_resolve($items, 'local_data_template_rrd_id');
	expect($buggy[2])->toBe(2);
	expect($buggy[3])->toBe(2);
});
