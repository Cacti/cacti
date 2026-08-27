<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for the second-order SQL injection via the unvalidated
 * output_type data-input value in update_poller_cache() (GHSA-xhpr-w454-cc9w).
 * output_type is stored with an empty validation regex, so it must be bound
 * rather than concatenated into the poller-cache query.
 */

$source = file_get_contents(dirname(__DIR__, 2) . '/lib/utility.php');

test('update_poller_cache binds output_type with a placeholder', function () use ($source) {
	expect($source)->toContain("\$output_type_sql = ' AND sqgr.snmp_query_graph_id = ?';");
});

test('update_poller_cache pushes output_type onto the bound parameter list', function () use ($source) {
	/* the value must be appended to $params (last, after the two existing binds) */
	expect($source)->toMatch('/if \(\$output_type_sql != \'\'\) \{\s*\$params\[\] = \$field\[\'output_type\'\];/');
});

test('update_poller_cache never concatenates output_type into the query', function () use ($source) {
	/* the vulnerable form interpolated the raw value into the SQL fragment */
	expect($source)->not->toContain("sqgr.snmp_query_graph_id = ' . \$field['output_type']");
});
