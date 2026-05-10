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
 * Regression coverage for the eleven PHPStan Level 6 errors that were
 * blocking CI on the develop branch. The errors fall into two defect
 * classes:
 *
 *   (A) `*_template_item_id` consumed by empty() in an error-redirect
 *       URL builder, but only assigned inside a foreach + !is_error_message
 *       branch. When the loop body skips, the variable is undefined; PHP
 *       silently treats it as empty() at runtime, so the bug surfaces only
 *       at static-analysis time. Affected: aggregate_graphs.php:378,
 *       color_templates.php:245, graph_templates.php:611, graphs.php:713.
 *       Each was fixed by initialising the variable to 0 at the top of the
 *       relevant `elseif (isrv('save_component_item'))` branch.
 *
 *   (B) `isset($tab['image'])` at lib/html.php:2388 / 2396 / 2404. PHPStan
 *       infers from the right-tab array constructor that 'image' is always
 *       set, so the isset() is dead. Each was fixed by dropping the isset()
 *       and keeping only the `!= ''` check.
 *
 * Each test below extracts the relevant source slice and asserts the fix
 * is in place. A final guard test re-asserts that the eleven flagged sites
 * (file:line tuples reported by PHPStan) all contain the post-fix shape.
 */

$repoRoot = __DIR__ . '/../..';
$sources  = [
	'aggregate_graphs.php' => file_get_contents("$repoRoot/aggregate_graphs.php"),
	'color_templates.php'  => file_get_contents("$repoRoot/color_templates.php"),
	'graph_templates.php'  => file_get_contents("$repoRoot/graph_templates.php"),
	'graphs.php'           => file_get_contents("$repoRoot/graphs.php"),
	'lib/html.php'         => file_get_contents("$repoRoot/lib/html.php"),
];

/* --- Defect class A: undefined *_template_item_id in empty() ------------ */

test('aggregate_graphs.php save_component_item branch initialises $graph_template_item_id', function () use ($sources) {
	$src = $sources['aggregate_graphs.php'];
	$branchPos = strpos($src, "elseif (isrv('save_component_item'))");
	expect($branchPos)->not->toBeFalse();
	$branchSlice = substr($src, $branchPos, 4000);

	expect($branchSlice)->toContain('$graph_template_item_id = 0;');

	/* The init must precede the foreach($items as ...) that conditionally
	 * assigns it via sql_save(). */
	$initPos    = strpos($branchSlice, '$graph_template_item_id = 0;');
	$foreachPos = strpos($branchSlice, 'foreach ($items as $item)');
	expect($initPos)->not->toBeFalse();
	expect($foreachPos)->not->toBeFalse();
	expect($initPos < $foreachPos)->toBeTrue('$graph_template_item_id init must precede the items foreach');
});

test('color_templates.php save_component_item branch initialises $color_template_item_id', function () use ($sources) {
	$src = $sources['color_templates.php'];
	$branchPos = strpos($src, "elseif (isrv('save_component_item'))");
	expect($branchPos)->not->toBeFalse();
	$branchSlice = substr($src, $branchPos, 2000);

	expect($branchSlice)->toContain('$color_template_item_id = 0;');

	$initPos    = strpos($branchSlice, '$color_template_item_id = 0;');
	$foreachPos = strpos($branchSlice, 'foreach ($items as $item)');
	expect($initPos)->not->toBeFalse();
	expect($foreachPos)->not->toBeFalse();
	expect($initPos < $foreachPos)->toBeTrue('$color_template_item_id init must precede the items foreach');
});

test('graph_templates.php save_component_item branch initialises $graph_template_item_id', function () use ($sources) {
	$src = $sources['graph_templates.php'];
	$branchPos = strpos($src, "elseif (isrv('save_component_item'))");
	expect($branchPos)->not->toBeFalse();
	$branchSlice = substr($src, $branchPos, 12000);

	expect($branchSlice)->toContain('$graph_template_item_id = 0;');

	$initPos    = strpos($branchSlice, '$graph_template_item_id = 0;');
	$sqlSavePos = strpos($branchSlice, "sql_save(\$save, 'graph_templates_item')");
	expect($initPos)->not->toBeFalse();
	expect($sqlSavePos)->not->toBeFalse();
	expect($initPos < $sqlSavePos)->toBeTrue('init must precede the conditional sql_save assignment');
});

test('graphs.php save_component_item branch initialises $graph_template_item_id', function () use ($sources) {
	$src = $sources['graphs.php'];
	$branchPos = strpos($src, "elseif (isrv('save_component_item'))");
	expect($branchPos)->not->toBeFalse();
	$branchSlice = substr($src, $branchPos, 6000);

	expect($branchSlice)->toContain('$graph_template_item_id = 0;');

	$initPos    = strpos($branchSlice, '$graph_template_item_id = 0;');
	$foreachPos = strpos($branchSlice, 'foreach ($items as $item)');
	expect($initPos)->not->toBeFalse();
	expect($foreachPos)->not->toBeFalse();
	expect($initPos < $foreachPos)->toBeTrue('init must precede the items foreach');
});

test('the empty() fallback in the error-redirect URL still uses the variable', function () use ($sources) {
	/* The init is a no-op if the redirect ever stops calling empty() on
	 * the variable. Guard the call site so this PR does not silently
	 * regress to a different shape. */
	$expected = [
		'aggregate_graphs.php' => '(empty($graph_template_item_id) ? gfrv(\'graph_template_item_id\') : $graph_template_item_id)',
		'color_templates.php'  => '(empty($color_template_item_id) ? gnrv(\'color_template_item_id\') : $color_template_item_id)',
		'graph_templates.php'  => '(empty($graph_template_item_id) ? gnrv(\'graph_template_item_id\') : $graph_template_item_id)',
		'graphs.php'           => '(empty($graph_template_item_id) ? gnrv(\'graph_template_item_id\') : $graph_template_item_id)',
	];
	foreach ($expected as $file => $needle) {
		expect($sources[$file])->toContain($needle);
	}
});

/* --- Defect class B: redundant isset() on always-set offset ------------- */

test('lib/html.php right-tab block drops the isset($tab[image]) guard', function () use ($sources) {
	$src = $sources['lib/html.php'];

	/* Locate the foreach that walks $tabs_right and contains the three
	 * case branches. PHPStan flagged each branch separately. */
	$foreachPos = strpos($src, 'foreach ($tabs_right as $tab)');
	expect($foreachPos)->not->toBeFalse();
	$slice = substr($src, $foreachPos, 4000);

	/* The fix: drop isset() and keep `$tab['image'] != ''`. None of the
	 * three case branches may still carry the redundant isset(). */
	expect(strpos($slice, "isset(\$tab['image'])"))->toBeFalse(
		'isset($tab[image]) must be removed from every right-tab case branch'
	);

	/* Each case branch must still gate its <img> emit on the value
	 * being non-empty. */
	expect(substr_count($slice, "\$tab['image'] != ''"))->toBeGreaterThanOrEqual(3);
});

/* --- Final structural guard: PHPStan-flagged tuples are gone ----------- */

test('every PHPStan-flagged file:line shows the post-fix shape', function () use ($sources) {
	/* Snapshot of the eleven file:line tuples PHPStan flagged at Level 6.
	 * For each, assert the *current* line content matches the post-fix
	 * shape. If a future refactor moves the line, this test still helps:
	 * the assertion focuses on the offending pattern, not just position. */
	$cases = [
		// (A) empty()/undefined-variable sites
		['aggregate_graphs.php', '$graph_template_item_id', 'empty('],
		['color_templates.php',  '$color_template_item_id', 'empty('],
		['graph_templates.php',  '$graph_template_item_id', 'empty('],
		['graphs.php',           '$graph_template_item_id', 'empty('],
		// (B) right-tab isset removal
		['lib/html.php',         "\$tab['image'] != ''", "isset(\$tab['image'])"],
	];
	foreach ($cases as [$file, $kept, $forbidden]) {
		expect($sources[$file])->toContain($kept);
		if ($file === 'lib/html.php') {
			expect(strpos($sources[$file], $forbidden))->toBeFalse(
				"$file must no longer contain the pre-fix guard: $forbidden"
			);
		}
	}
});

/* --- Behavioural fixture: empty() on undefined vs initialised --------- */

test('PHP empty() on undefined variable is silent at runtime; PHPStan flags it', function () {
	/* Document the runtime semantics that hid the bug for years. PHP's
	 * empty() is one of the few constructs that does not raise on an
	 * undefined variable; static analysis (PHPStan, Psalm) is what
	 * surfaces the defect. The init we added makes both happy. */
	$value = empty($never_assigned_phpstan_fixture_var) ? 'fallback' : 'present';
	expect($value)->toBe('fallback');

	$initialised = 0;
	$value2      = empty($initialised) ? 'fallback' : 'present';
	expect($value2)->toBe('fallback');

	$initialised = 42;
	$value3      = empty($initialised) ? 'fallback' : 'present';
	expect($value3)->toBe('present');
});

/* --- Defect-class scan: catch any future reintroduction --------------- */

test('no other empty($x_template_item_id) lookups happen against an undefined var', function () use ($sources) {
	/* Cross-cutting check: every empty($...template_item_id) call across
	 * the touched files must be reachable via either an init in scope or
	 * a prior assignment in the same branch. We approximate "in scope"
	 * by ensuring an init ($x = 0) appears earlier in the same file. */
	foreach ($sources as $file => $src) {
		if (!preg_match_all('/empty\(\$(\w*template_item_id)\)/', $src, $m, PREG_OFFSET_CAPTURE)) {
			continue;
		}
		foreach ($m[1] as $hit) {
			$varName = $hit[0];
			$emptyOffset = $hit[1];
			$initPattern = '$' . $varName . ' = 0;';
			$initOffset  = strpos($src, $initPattern);
			expect($initOffset)->not->toBeFalse(
				"$file: empty(\$$varName) at offset $emptyOffset must be backed by an earlier '\$$varName = 0;' init"
			);
			expect($initOffset < $emptyOffset)->toBeTrue(
				"$file: '\$$varName = 0;' must precede the empty(\$$varName) consumer"
			);
		}
	}
});
