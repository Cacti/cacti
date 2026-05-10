<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Mutation protection for the PHPStan Level 6 baseline fixes. Each
 * mutation here represents a single-character revert: drop the init,
 * reintroduce the isset() guard, change the empty() branch order. The
 * fix is purely static-analysis-driven (PHP runtime behaviour was
 * always correct via empty()'s undefined-variable lenience), so the
 * mutations are all source-level.
 */

$repoRoot = __DIR__ . '/../..';
$sources  = [
	'aggregate_graphs.php' => file_get_contents("$repoRoot/aggregate_graphs.php"),
	'color_templates.php'  => file_get_contents("$repoRoot/color_templates.php"),
	'graph_templates.php'  => file_get_contents("$repoRoot/graph_templates.php"),
	'graphs.php'           => file_get_contents("$repoRoot/graphs.php"),
	'lib/html.php'         => file_get_contents("$repoRoot/lib/html.php"),
];

test('aggregate_graphs.php save_component_item init survives (Mutation Protection)', function () use ($sources) {
	/* If a mutation drops the init line, PHPStan flags variable.undefined
	 * again at the empty()/redirect site. */
	expect($sources['aggregate_graphs.php'])
		->toContain('$graph_template_item_id = 0;');

	$initPos    = strpos($sources['aggregate_graphs.php'], '$graph_template_item_id = 0;');
	$emptyPos   = strpos($sources['aggregate_graphs.php'], 'empty($graph_template_item_id)');
	expect($initPos < $emptyPos)->toBeTrue();
});

test('color_templates.php save_component_item init survives (Mutation Protection)', function () use ($sources) {
	expect($sources['color_templates.php'])
		->toContain('$color_template_item_id = 0;');

	$initPos  = strpos($sources['color_templates.php'], '$color_template_item_id = 0;');
	$emptyPos = strpos($sources['color_templates.php'], 'empty($color_template_item_id)');
	expect($initPos < $emptyPos)->toBeTrue();
});

test('graph_templates.php save_component_item init survives (Mutation Protection)', function () use ($sources) {
	expect($sources['graph_templates.php'])
		->toContain('$graph_template_item_id = 0;');

	$initPos  = strpos($sources['graph_templates.php'], '$graph_template_item_id = 0;');
	$emptyPos = strpos($sources['graph_templates.php'], 'empty($graph_template_item_id)');
	expect($initPos < $emptyPos)->toBeTrue();
});

test('graphs.php save_component_item init survives (Mutation Protection)', function () use ($sources) {
	expect($sources['graphs.php'])
		->toContain('$graph_template_item_id = 0;');

	$initPos  = strpos($sources['graphs.php'], '$graph_template_item_id = 0;');
	$emptyPos = strpos($sources['graphs.php'], 'empty($graph_template_item_id)');
	expect($initPos < $emptyPos)->toBeTrue();
});

test('lib/html.php right-tab block keeps the != \'\' image guard (Mutation Protection)', function () use ($sources) {
	/* If a mutation drops the `!= ''` check, every right-tab entry
	 * renders an <img> tag even when 'image' is the empty string,
	 * producing a broken-image badge in the UI. */
	$src = $sources['lib/html.php'];
	$foreachStart = strpos($src, 'foreach ($tabs_right as $tab)');
	$slice = substr($src, $foreachStart, 4000);
	expect(substr_count($slice, "\$tab['image'] != ''"))->toBeGreaterThanOrEqual(3);
});

test('lib/html.php right-tab block does not reintroduce isset($tab[image]) (Mutation Protection)', function () use ($sources) {
	/* The pre-fix dead guard. PHPStan flagged this as
	 * `isset.offset always exists`. A mutation that re-adds it would
	 * silently reintroduce dead code and the Level 6 error. */
	$src = $sources['lib/html.php'];
	$foreachStart = strpos($src, 'foreach ($tabs_right as $tab)');
	$slice = substr($src, $foreachStart, 4000);
	expect(strpos($slice, "isset(\$tab['image'])"))->toBeFalse();
});

test('init binds to integer 0, not bool false or null (Mutation Protection)', function () use ($sources) {
	/* The redirect URL appends the value via concat. If a mutation
	 * changes the init from `0` to `false` or `null`, PHP coerces
	 * differently in the URL and PHPStan may flag a different error.
	 * Pin the integer-zero shape. */
	foreach (['aggregate_graphs.php', 'color_templates.php', 'graph_templates.php', 'graphs.php'] as $file) {
		$src = $sources[$file];
		expect(preg_match('/\$\w*template_item_id\s*=\s*false\s*;/', $src))->toBe(0);
		expect(preg_match('/\$\w*template_item_id\s*=\s*null\s*;/', $src))->toBe(0);
	}
});

test('every Level 6 fix preserves the empty() fallback semantics (Mutation Protection)', function () use ($sources) {
	/* The init is only useful because empty(0) is true and the URL
	 * falls through to the request var. If a mutation flips the
	 * ternary order (`empty($x) ? $x : gnrv(...)`), the URL would emit
	 * "0" on every error redirect and lose the form's submitted id. */
	$expected = [
		'aggregate_graphs.php' => "(empty(\$graph_template_item_id) ? gfrv('graph_template_item_id') : \$graph_template_item_id)",
		'color_templates.php'  => "(empty(\$color_template_item_id) ? gnrv('color_template_item_id') : \$color_template_item_id)",
		'graph_templates.php'  => "(empty(\$graph_template_item_id) ? gnrv('graph_template_item_id') : \$graph_template_item_id)",
		'graphs.php'           => "(empty(\$graph_template_item_id) ? gnrv('graph_template_item_id') : \$graph_template_item_id)",
	];
	foreach ($expected as $file => $needle) {
		expect($sources[$file])->toContain($needle);
	}
});
