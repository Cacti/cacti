<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The New Graphs data query pivot quotes host_snmp_cache.field_name with
 * db_qstr() in both the MAX(CASE WHEN field_name = ...) literal and the column
 * alias, like the automation pivot builders.
 */

$src = file_get_contents(dirname(__DIR__, 4) . '/graphs_new.php');

test('the graphs_new pivot quotes field_name with db_qstr', function () use ($src) {
	expect($src)->toContain("MAX(CASE WHEN field_name = ' . db_qstr(\$field_name) . ' THEN field_value ELSE NULL END) AS ' . db_qstr(\$field_name)")
		->and($src)->not->toContain("MAX(CASE WHEN field_name='\$field_name'");
});
