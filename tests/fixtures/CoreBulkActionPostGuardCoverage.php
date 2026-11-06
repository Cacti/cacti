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

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require dirname(__DIR__) . '/Helpers/IsolatedProbe.php';
require dirname(__DIR__) . '/Helpers/FixtureCoverage.php';
$files = ['aggregate_graphs.php', 'aggregate_templates.php', 'cdef.php', 'color.php', 'color_templates.php', 'data_input.php', 'data_queries.php', 'data_source_profiles.php', 'data_sources.php', 'data_templates.php', 'gprint_presets.php', 'graph_templates.php', 'graphs.php', 'host_templates.php', 'pollers.php', 'sites.php', 'vdef.php'];

foreach ($files as $file) {
	foreach (['get', 'tokenless', 'valid'] as $mode) {
		$r        = cacti_test_isolated_probe(__DIR__ . '/BulkActionPostGuardProbe.php', [$file, $mode], true);
		$expected = $mode === 'get' ? [405, false, false] : ($mode === 'tokenless' ? [403, true, false] : [200, true, true]);

		if ([$r['status'], $r['checked'], $r['reached']] !== $expected) {
			throw new RuntimeException('POST guard decision mismatch');
		}
	}
}
print "PASS: protected bulk handlers\n";
