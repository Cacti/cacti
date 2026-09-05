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

$repoRoot = dirname(__DIR__, 3);
$sources  = [
	'aggregate_graphs.php' => file_get_contents("$repoRoot/aggregate_graphs.php"),
	'color_templates.php'  => file_get_contents("$repoRoot/color_templates.php"),
	'graph_templates.php'  => file_get_contents("$repoRoot/graph_templates.php"),
	'graphs.php'           => file_get_contents("$repoRoot/graphs.php"),
	'lib/html.php'         => file_get_contents("$repoRoot/lib/html.php"),
];

test('component item ids use a null sentinel before their redirect fallback (Mutation Protection)', function () use ($sources) {
	$variables = [
		'aggregate_graphs.php' => 'graph_template_item_id',
		'color_templates.php'  => 'color_template_item_id',
		'graph_templates.php'  => 'graph_template_item_id',
		'graphs.php'           => 'graph_template_item_id',
	];

	foreach ($variables as $file => $variable) {
		$init     = '$' . $variable . ' = null;';
		$fallback = '$' . $variable . ' === null ?';
		$initPos  = strrpos($sources[$file], $init);
		$guardPos = strrpos($sources[$file], $fallback);

		expect($initPos)->not->toBeFalse()
			->and($guardPos)->not->toBeFalse()
			->and($initPos)->toBeLessThan($guardPos);
	}
});

test('lib/html.php right-tab block keeps the != \'\' image guard (Mutation Protection)', function () use ($sources) {
	/* If a mutation drops the `!= ''` check, every right-tab entry
	 * renders an <img> tag even when 'image' is the empty string,
	 * producing a broken-image badge in the UI. */
	$src          = $sources['lib/html.php'];
	$foreachStart = strpos($src, 'foreach ($tabs_right as $tab)');

	expect($foreachStart)->not->toBeFalse();

	$slice        = substr($src, $foreachStart, 4000);
	expect(substr_count($slice, "\$tab['image'] != ''"))->toBeGreaterThanOrEqual(3);
});

test('lib/html.php right-tab block does not reintroduce isset($tab[image]) (Mutation Protection)', function () use ($sources) {
	/* The pre-fix dead guard. PHPStan flagged this as
	 * `isset.offset always exists`. A mutation that re-adds it would
	 * silently reintroduce dead code and the Level 6 error. */
	$src          = $sources['lib/html.php'];
	$foreachStart = strpos($src, 'foreach ($tabs_right as $tab)');

	expect($foreachStart)->not->toBeFalse();

	$slice        = substr($src, $foreachStart, 4000);
	expect(strpos($slice, "isset(\$tab['image'])"))->toBeFalse();
});

test('every Level 6 fix preserves the null fallback semantics (Mutation Protection)', function () use ($sources) {
	$expected = [
		'aggregate_graphs.php' => "(\$graph_template_item_id === null ? gfrv('graph_template_item_id') : \$graph_template_item_id)",
		'color_templates.php'  => "(\$color_template_item_id === null ? gnrv('color_template_item_id') : \$color_template_item_id)",
		'graph_templates.php'  => "\$graph_template_item_id === null ? gnrv('graph_template_item_id') : \$graph_template_item_id",
		'graphs.php'           => "\$graph_template_item_id === null ? gnrv('graph_template_item_id') : \$graph_template_item_id",
	];

	foreach ($expected as $file => $needle) {
		expect($sources[$file])->toContain($needle);
	}
});
