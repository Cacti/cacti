<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-77pr-g949-4v5f: spikekill.php authorized only the Spike Kill realm
 * (1043) and never the target graph, so a realm holder could destroy the RRD
 * history of a graph their permissions deny.
 */

$root = dirname(__DIR__, 4);

test('spikekill authorizes the target graph in addition to the Spike Kill realm', function () use ($root) {
	$source = file_get_contents($root . '/spikekill.php');

	expect($source)->toContain('is_realm_allowed(1043) && is_graph_allowed($local_graph_id)')
		->and($source)->not->toContain("if (is_realm_allowed(1043)) {\n\t\$local_data_ids");
});
