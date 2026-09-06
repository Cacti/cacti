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

	foreach (['graph_templates_item', 'aggregate_graph_templates_item', 'aggregate_graphs_graph_item'] as $table) {
		expect($source)->toContain($table);
	}

	expect($source)->toContain('LEFT JOIN cdef ON source.cdef_id = cdef.id')
		->and($source)->toContain('cdef_id NOT IN (SELECT id FROM cdef)')
		->and($source)->toContain("cdef_id = 0, t_cdef_id = ''")
		->and($source)->toContain('Found $invalid_references graph item references')
		->and($source)->toContain('Found and repaired $fixed_references of $invalid_references');
});

test('aggregate push failures are handled at every call site', function () use ($root) {
	foreach (['aggregate_graphs.php', 'aggregate_templates.php', 'color_templates.php', 'lib/aggregate.php'] as $file) {
		$source = file_get_contents($root . '/' . $file);

		expect($source)->not->toMatch('/^\s*push_out_aggregates\(/m');
	}
});
