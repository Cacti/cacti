<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 */

$root = dirname(__DIR__, 2);

test('database repair clears every direct graph reference to a missing CDEF', function () use ($root) {
	$source = file_get_contents($root . '/cli/repair_database.php');

	expect($source)->not->toBeFalse();

	$parser         = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
	$nodes          = $parser->parse($source);
	$constantString = static function (\PhpParser\Node $node) use (&$constantString): ?string {
		if ($node instanceof \PhpParser\Node\Scalar\String_) {
			return $node->value;
		}

		if ($node instanceof \PhpParser\Node\Expr\BinaryOp\Concat) {
			$left  = $constantString($node->left);
			$right = $constantString($node->right);

			return $left !== null && $right !== null ? $left . $right : null;
		}

		return null;
	};
	$calls = (new \PhpParser\NodeFinder())->find($nodes, static fn ($node) => $node instanceof \PhpParser\Node\Expr\FuncCall &&
		$node->name instanceof \PhpParser\Node\Name &&
		in_array($node->name->toString(), ['db_fetch_cell', 'db_execute'], true));
	$queries = array_map(static fn ($call) => $constantString($call->args[0]->value), $calls);

	foreach (['graph_templates_item', 'aggregate_graph_templates_item', 'aggregate_graphs_graph_item'] as $table) {
		expect($queries)->toContain("SELECT COUNT(*) FROM $table AS source LEFT JOIN cdef ON source.cdef_id = cdef.id WHERE source.cdef_id > 0 AND cdef.id IS NULL");
		$clear = $table === 'graph_templates_item' ? 'cdef_id = 0' : "cdef_id = 0, t_cdef_id = ''";
		expect($queries)->toContain("UPDATE $table SET $clear WHERE cdef_id > 0 AND cdef_id NOT IN (SELECT id FROM cdef)");
	}

	expect($source)->toContain('Found $invalid_references graph item references')
		->and($source)->toContain('Found and repaired $fixed_references of $invalid_references');
});

test('aggregate push failures are handled at every call site', function () use ($root) {
	foreach (['aggregate_graphs.php', 'aggregate_templates.php', 'color_templates.php', 'lib/aggregate.php'] as $file) {
		$source = file_get_contents($root . '/' . $file);

		expect($source)->not->toMatch('/^\s*push_out_aggregates\(/m');
	}
});
