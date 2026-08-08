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

$source = file_get_contents(dirname(__DIR__, 2) . '/lib/rrd.php');

if ($source === false) {
	throw new RuntimeException('Unable to read lib/rrd.php for graph query efficiency tests.');
}

test('graph rendering loads axis formats with the graph metadata', function () use ($source) {
	expect($source)
		->toContain('right_gprint.gprint_text AS right_axis_format_text')
		->toContain('left_gprint.gprint_text AS left_axis_format_text')
		->toContain('ON right_gprint.id = gtg.right_axis_format')
		->toContain('ON left_gprint.id = gtg.left_axis_format')
		->toContain("\$format = \$graph['right_axis_format_text'];")
		->toContain("\$format = \$graph['left_axis_format_text'];")
		->not->toContain("db_fetch_cell_prepared('SELECT gprint_text from graph_templates_gprint");
});

test('CDEF polling intervals come from the graph item query', function () use ($source) {
	expect($source)
		->toContain('dtd.rrd_step')
		->toContain('ON dtd.local_data_id = dtr.local_data_id')
		->toContain("\$polling_interval = \$graph_item['rrd_step'];")
		->not->toContain("db_fetch_cell_prepared('SELECT rrd_step FROM data_template_data WHERE local_data_id = ?'");
});
