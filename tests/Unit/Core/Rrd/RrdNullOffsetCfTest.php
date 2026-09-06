<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * generate_graph_best_cf() kept an uninitialized static. The first call with
 * local_data_id 0 returned null, which PHP 8.5 then used as a cache-array
 * offset (issue #7810). Graph items without a data source also pass null
 * names and rrd ids into those arrays.
 */

$root = dirname(__DIR__, 4);

function rrdNullOffsetTestCfs(int $local_data_id): array {
	return match ($local_data_id) {
		10      => [2, 4],
		20      => [],
		default => [1],
	};
}

function loadGenerateGraphBestCf(string $root): void {
	if (function_exists('rrdNullOffsetGenerateGraphBestCf')) {
		return;
	}

	$src   = file_get_contents($root . '/lib/functions.php');
	$start = strpos($src, 'function generate_graph_best_cf(');
	$end   = strpos($src, '/**', $start);

	expect($src)->not->toBeFalse()
		->and($start)->not->toBeFalse()
		->and($end)->not->toBeFalse();

	$function = substr($src, $start, $end - $start);
	$function = str_replace(
		['generate_graph_best_cf', 'get_rrd_cfs', 'cacti_sizeof'],
		['rrdNullOffsetGenerateGraphBestCf', 'rrdNullOffsetTestCfs', 'count'],
		$function
	);

	eval($function);
}

test('generate_graph_best_cf initializes the static CF before first use', function () use ($root) {
	$src = file_get_contents($root . '/lib/functions.php');

	expect($src)->toContain('static $best_cf = 1;')
		->and($src)->toMatch('/if \(\$local_data_id <= 0\) \{\s+return 1;/')
		->and($src)->not->toMatch('/static \$best_cf;\s*$/m');
});

test('graph CF caches do not use raw nullable item fields as offsets', function () use ($root) {
	$src = file_get_contents($root . '/lib/rrd.php');

	expect($src)->toContain("\$graph_item['data_source_name'] ?? ''")
		->and($src)->toContain("\$graph_item['local_data_template_rrd_id'] ?? ''")
		->and($src)->toContain("\$graph_item['data_template_rrd_id'] ?? ''")
		->and($src)->toContain('$graph_item[\'cf_reference\'] ?? 1');
});

test('generate_graph_best_cf returns AVERAGE for a missing data source on every call', function () use ($root) {
	loadGenerateGraphBestCf($root);

	expect(rrdNullOffsetGenerateGraphBestCf(0, 4))->toBe(1)
		->and(rrdNullOffsetGenerateGraphBestCf(10, 4))->toBe(4)
		->and(rrdNullOffsetGenerateGraphBestCf(0, 4))->toBe(1)
		->and(rrdNullOffsetGenerateGraphBestCf(-1, 2))->toBe(1);
});

test('generate_graph_best_cf chooses an available fallback and handles no RRA functions', function () use ($root) {
	loadGenerateGraphBestCf($root);

	expect(rrdNullOffsetGenerateGraphBestCf(10, 8))->toBe(2)
		->and(rrdNullOffsetGenerateGraphBestCf(20, 8))->toBe(1);
});
