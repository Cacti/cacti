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

$rrdSource = file_get_contents(__DIR__ . '/../../../../lib/rrd.php');

test('xport skips VDEF backed graph items', function () use ($rrdSource) {
	expect($rrdSource)->not->toBeFalse();
	expect($rrdSource)->toContain('rrdtool xport only accepts DEF/CDEF references');
	expect($rrdSource)->toContain('$graph_item[\'vdef_id\'] == \'0\' && preg_match');
	expect($rrdSource)->toContain('XPORT:');
});

test('xport returns a controlled empty result when rrdtool emits no metadata', function () use ($rrdSource) {
	expect($rrdSource)->not->toBeFalse();
	expect($rrdSource)->toContain("if (!isset(\$xport_array['meta']))");
	expect($rrdSource)->not->toContain("cacti_log('WARNING: RRDtool xport returned no valid data for Local Graph ID ' . \$local_graph_id, false, 'EXPORT')");
	expect($rrdSource)->toMatch("/if \\(!isset\\(\\$xport_array\\['meta'\\]\\)\\) \\{.*'meta' => array\\(.*'start'\\s+=> \\$xport_start,.*'columns'\\s+=> 0,.*'legend'\\s+=> array\\(\\),.*'title_cache'\\s+=> \\$graph\\['title_cache'\\],.*'data' => array\\(\\),/s");
});
