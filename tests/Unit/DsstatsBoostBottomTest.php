<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * After Boost, dsstats_launch_children('bmaster') starts one bchild process
 * per configured thread, and each bchild collects its own slice.  The parent
 * must not collect a slice itself, or that slice is read twice.  Every parent
 * waits for its children through the dsstats_wait_for_children() barrier.
 */

test('dsstats_boost_bottom leaves the collection to the children it launched', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/dsstats.php');
	$parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
	$finder = new \PhpParser\NodeFinder();
	$node   = $finder->findFirst($parser->parse($source), static function ($node) {
		return $node instanceof \PhpParser\Node\Stmt\Function_ && $node->name->toString() === 'dsstats_boost_bottom';
	});

	expect($node)->not->toBeNull();

	$calls = array_map(static function ($call) {
		return $call->name->toString();
	}, $finder->find($node->stmts, static function ($node) {
		return $node instanceof \PhpParser\Node\Expr\FuncCall && $node->name instanceof \PhpParser\Node\Name;
	}));

	expect($calls)->toContain('dsstats_launch_children')
		->and($calls)->toContain('dsstats_wait_for_children')
		->and($calls)->not->toContain('dsstats_get_and_store_ds_avgpeak_values');
});

test('poller_dsstats.php drains every launch through the startup barrier', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/poller_dsstats.php');

	// a bare running-count poll can see zero children before any has registered
	expect($source)->not->toMatch('/while\s*\(\s*\$running\s*=\s*dsstats_processes_running\(/')
		->and(preg_match_all('/\$expected_children = dsstats_launch_children\(([^)]+)\);\s+dsstats_wait_for_children\(\1, \$expected_children\);/', $source))
		->toBe(substr_count($source, 'dsstats_launch_children('))
		->and(substr_count($source, 'dsstats_launch_children('))->toBe(3);
});
