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
		->and($source)->toContain("cdef_id = 0, t_cdef_id = ''");
});
