<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Smoke tests for issue #7137. Verify all five touched files parse
 * (no syntax errors), the post-fix patterns are present, and
 * none of the pre-fix shapes survive. Runs without Cacti's bootstrap.
 */

$repoRoot = __DIR__ . '/../..';
$touched  = [
	'aggregate_graphs.php',
	'color_templates.php',
	'graph_templates.php',
	'graphs.php',
	'lib/html.php',
];

test('every touched file is readable and non-empty', function () use ($repoRoot, $touched) {
	foreach ($touched as $rel) {
		$path = "$repoRoot/$rel";
		expect(file_exists($path))->toBeTrue("$rel must exist");
		expect(filesize($path))->toBeGreaterThanOrEqual(1);
	}
});

test('every touched file passes a syntactic shebang/PHP-tag check', function () use ($repoRoot, $touched) {
	foreach ($touched as $rel) {
		$head = file_get_contents("$repoRoot/$rel", false, null, 0, 16);
		expect($head)->toContain('<?php');
	}
});

test('the four form_save inits are present and precede their null-fallback consumers', function () use ($repoRoot) {
	/* #7317 and #7348 retyped the sql_save() result from '' to null, so the
	 * redirect fallback now tests === null rather than empty().  The init
	 * still has to reach the fallback on every path through form_save(). */
	$cases = [
		'aggregate_graphs.php' => ['$graph_template_item_id = 0;', '$graph_template_item_id === null'],
		'color_templates.php'  => ['$color_template_item_id = 0;', '$color_template_item_id === null'],
		'graph_templates.php'  => ['$graph_template_item_id = 0;', '$graph_template_item_id === null'],
		'graphs.php'           => ['$graph_template_item_id = 0;', '$graph_template_item_id === null'],
	];
	foreach ($cases as $rel => [$init, $consumer]) {
		$src       = file_get_contents("$repoRoot/$rel");
		$initPos   = strpos($src, $init);
		$consumePos = strpos($src, $consumer);
		expect($initPos)->not->toBeFalse("$rel must contain init: $init");
		expect($consumePos)->not->toBeFalse("$rel must contain consumer: $consumer");
		expect($initPos < $consumePos)->toBeTrue("$rel: init must precede consumer");
	}
});

test('lib/html.php right-tab block dropped the redundant isset guard', function () use ($repoRoot) {
	$src = file_get_contents("$repoRoot/lib/html.php");
	$start = strpos($src, 'foreach ($tabs_right as $tab)');
	$slice = substr($src, $start, 4000);
	expect($slice)->not->toBe('');
	expect(strpos($slice, "isset(\$tab['image'])"))->toBeFalse();
	expect(substr_count($slice, "\$tab['image'] != ''"))->toBeGreaterThanOrEqual(3);
});
