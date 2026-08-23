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
 *       at static-analysis time. Affected files: aggregate_graphs.php,
 *       color_templates.php, graph_templates.php, and graphs.php.
 *       The original fix initialised the variable in the relevant
 *       `elseif (isrv('save_component_item'))` branch. PR #7317 and #7348
 *       later superseded the empty() consumer with a `=== null` check
 *       against a null sentinel set just before the assignment path, since
 *       empty() also matches a legitimately-falsy 0 id. These tests assert
 *       the null sentinel so the guard remains stable if the older bootstrap
 *       assignment is removed.
 *
 *   (B) `isset($tab['image'])` at lib/html.php:2388 / 2396 / 2404. PHPStan
 *       infers from the right-tab array constructor that 'image' is always
 *       set, so the isset() is dead. Each was fixed by dropping the isset()
 *       and keeping only the `!= ''` check.
 *
 * Each test below extracts the relevant source slice and asserts the fix
 * is in place. A final guard test re-asserts that the flagged patterns
 * reported by PHPStan all contain the post-fix shape.
 */

$repoRoot = dirname(__DIR__, 3);
$sources  = [
	'aggregate_graphs.php' => file_get_contents("$repoRoot/aggregate_graphs.php"),
	'color_templates.php'  => file_get_contents("$repoRoot/color_templates.php"),
	'graph_templates.php'  => file_get_contents("$repoRoot/graph_templates.php"),
	'graphs.php'           => file_get_contents("$repoRoot/graphs.php"),
	'lib/html.php'         => file_get_contents("$repoRoot/lib/html.php"),
];

// --- Defect class A: undefined *_template_item_id in empty() ------------

test('aggregate_graphs.php save_component_item branch sets $graph_template_item_id null sentinel', function () use ($sources) {
	$src       = $sources['aggregate_graphs.php'];
	$branchPos = strpos($src, "elseif (isrv('save_component_item'))");
	expect($branchPos)->not->toBeFalse();
	$branchSlice = substr($src, $branchPos, 8000);

	expect($branchSlice)->toContain('$graph_template_item_id = null;');

	/* The sentinel must precede the foreach($items as ...) that conditionally
	 * assigns it via sql_save(), and the redirect fallback that consumes it. */
	$initPos     = strpos($branchSlice, '$graph_template_item_id = null;');
	$foreachPos  = strpos($branchSlice, 'foreach ($items as $item)');
	$redirectPos = strpos($branchSlice, '$graph_template_item_id === null');
	expect($initPos)->not->toBeFalse();
	expect($foreachPos)->not->toBeFalse();
	expect($redirectPos)->not->toBeFalse();
	expect($initPos < $foreachPos)->toBeTrue('$graph_template_item_id null sentinel must precede the items foreach');
	expect($initPos < $redirectPos)->toBeTrue('$graph_template_item_id null sentinel must precede the redirect fallback');
});

test('color_templates.php save_component_item branch sets $color_template_item_id null sentinel', function () use ($sources) {
	$src       = $sources['color_templates.php'];
	$branchPos = strpos($src, "elseif (isrv('save_component_item'))");
	expect($branchPos)->not->toBeFalse();
	$branchSlice = substr($src, $branchPos, 2000);

	expect($branchSlice)->toContain('$color_template_item_id = null;');

	$initPos     = strpos($branchSlice, '$color_template_item_id = null;');
	$foreachPos  = strpos($branchSlice, 'foreach ($items as $item)');
	$redirectPos = strpos($branchSlice, '$color_template_item_id === null');
	expect($initPos)->not->toBeFalse();
	expect($foreachPos)->not->toBeFalse();
	expect($redirectPos)->not->toBeFalse();
	expect($initPos < $foreachPos)->toBeTrue('$color_template_item_id null sentinel must precede the items foreach');
	expect($initPos < $redirectPos)->toBeTrue('$color_template_item_id null sentinel must precede the redirect fallback');
});

test('graph_templates.php save_component_item branch sets $graph_template_item_id null sentinel', function () use ($sources) {
	$src       = $sources['graph_templates.php'];
	$branchPos = strpos($src, "elseif (isrv('save_component_item'))");
	expect($branchPos)->not->toBeFalse();
	$branchSlice = substr($src, $branchPos, 18000);

	expect($branchSlice)->toContain('$graph_template_item_id = null;');

	$initPos     = strpos($branchSlice, '$graph_template_item_id = null;');
	$sqlSavePos  = strpos($branchSlice, "sql_save(\$save, 'graph_templates_item')");
	$redirectPos = strpos($branchSlice, '$graph_template_item_id === null');
	expect($initPos)->not->toBeFalse();
	expect($sqlSavePos)->not->toBeFalse();
	expect($redirectPos)->not->toBeFalse();
	expect($initPos < $sqlSavePos)->toBeTrue('null sentinel must precede the conditional sql_save assignment');
	expect($initPos < $redirectPos)->toBeTrue('null sentinel must precede the redirect fallback');
});

test('graphs.php save_component_item branch sets $graph_template_item_id null sentinel', function () use ($sources) {
	$src       = $sources['graphs.php'];
	$branchPos = strpos($src, "elseif (isrv('save_component_item'))");
	expect($branchPos)->not->toBeFalse();
	$branchSlice = substr($src, $branchPos, 10000);

	expect($branchSlice)->toContain('$graph_template_item_id = null;');

	$initPos     = strpos($branchSlice, '$graph_template_item_id = null;');
	$foreachPos  = strpos($branchSlice, 'foreach ($items as $item)');
	$redirectPos = strpos($branchSlice, '$graph_template_item_id === null');
	expect($initPos)->not->toBeFalse();
	expect($foreachPos)->not->toBeFalse();
	expect($redirectPos)->not->toBeFalse();
	expect($initPos < $foreachPos)->toBeTrue('null sentinel must precede the items foreach');
	expect($initPos < $redirectPos)->toBeTrue('null sentinel must precede the redirect fallback');
});

test('the null-guarded fallback in the error-redirect URL still uses the variable', function () use ($sources) {
	/* The init is a no-op if the redirect ever stops checking the
	 * variable. Guard the call site so this PR does not silently
	 * regress to a different shape. PR #7317/#7348 replaced the original
	 * empty() consumer with a `=== null` check, since empty() also
	 * matches a legitimately-falsy 0 id. */
	$expected = [
		'aggregate_graphs.php' => '($graph_template_item_id === null ? gfrv(\'graph_template_item_id\') : $graph_template_item_id)',
		'color_templates.php'  => '($color_template_item_id === null ? gnrv(\'color_template_item_id\') : $color_template_item_id)',
		'graph_templates.php'  => '$graph_template_item_id === null ? gnrv(\'graph_template_item_id\') : $graph_template_item_id',
		/* graphs.php passes this through cacti_redirect()'s params array rather
		 * than concatenating it into a Location string, so the guard is not
		 * parenthesised there. Pin the guard, not the punctuation. */
		'graphs.php'           => '$graph_template_item_id === null ? gnrv(\'graph_template_item_id\') : $graph_template_item_id',
	];

	foreach ($expected as $file => $needle) {
		expect($sources[$file])->toContain($needle);
	}
});

// --- Defect class B: redundant isset() on always-set offset -------------

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

// --- Final structural guard: PHPStan-flagged tuples are gone -----------

test('every PHPStan-flagged file:line shows the post-fix shape', function () use ($sources) {
	/* Snapshot of the patterns PHPStan flagged at Level 6.
	 * For each, assert the current source matches the post-fix shape. If a
	 * future refactor moves the code, this test still helps: the assertion
	 * focuses on the offending pattern, not just position. */
	$cases = [
		// (A) undefined-variable sites, now guarded by a null sentinel
		['aggregate_graphs.php', '$graph_template_item_id', '=== null'],
		['color_templates.php',  '$color_template_item_id', '=== null'],
		['graph_templates.php',  '$graph_template_item_id', '=== null'],
		['graphs.php',           '$graph_template_item_id', '=== null'],
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

// --- Behavioural fixture: empty() on undefined vs initialised ---------

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

// --- Defect-class scan: catch any future reintroduction ---------------

test('no other $x_template_item_id === null lookups happen against an undefined var', function () use ($sources) {
	/* Cross-cutting check: every "=== null" consumer of a *_template_item_id
	 * variable across the touched files must be reachable via a null-sentinel
	 * init earlier in the same file (PR #7317/#7348 replaced the original
	 * empty()-based consumer with this shape). We approximate "in scope" by
	 * ensuring an init ($x = null) appears earlier in the same file. */
	foreach ($sources as $file => $src) {
		if (!preg_match_all('/\$(\w*template_item_id)\s*===\s*null/', $src, $m, PREG_OFFSET_CAPTURE)) {
			continue;
		}

		foreach ($m[1] as $hit) {
			$varName       = $hit[0];
			$consumeOffset = $hit[1];
			$initPattern   = '$' . $varName . ' = null;';
			$initOffset    = strpos($src, $initPattern);
			expect($initOffset)->not->toBeFalse(
				"$file: \$$varName === null at offset $consumeOffset must be backed by an earlier '\$$varName = null;' init"
			);
			expect($initOffset < $consumeOffset)->toBeTrue(
				"$file: '\$$varName = null;' must precede the \$$varName === null consumer"
			);
		}
	}
});
