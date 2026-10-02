<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-xhpr-w454-cc9w: output_type is a stored snmp_query_graph_id interpolated
 * into a query in update_poller_cache(); it must be cast to int so a tampered
 * data-query field cannot inject SQL second-hand.
 */

$root = dirname(__DIR__, 4);

test('update_poller_cache casts the stored output_type to int before interpolation', function () use ($root) {
	$source = file_get_contents($root . '/lib/utility.php');

	expect($source)->toContain("sqgr.snmp_query_graph_id = ' . (int) \$field['output_type']")
		->and($source)->not->toContain("sqgr.snmp_query_graph_id = ' . \$field['output_type']");
});
