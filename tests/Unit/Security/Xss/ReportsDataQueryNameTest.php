<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Report output (previews and HTML report emails) escapes the data query name
 * in both "Data Query:" cells.
 */

$src = file_get_contents(dirname(__DIR__, 4) . '/lib/reports.php');

test('the report data-query name is escaped', function () use ($src) {
	expect(substr_count($src, "__('Data Query:') . ' ' . html_escape(\$data_query['name'])"))->toBe(2)
		->and($src)->not->toContain("__('Data Query:') . ' ' . \$data_query['name'] . PHP_EOL");
});
