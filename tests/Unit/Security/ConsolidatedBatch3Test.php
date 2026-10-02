<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Batch 3 consolidated GHSA regression guards (develop counterpart of the
 * 1.2.x fixes in PR #8138). Source-scanning so each runs without a database or
 * web context. GHSA-297h-95ff-r767 is omitted: on develop the clog log-file
 * selector renders through the drop_array filter framework, which escapes.
 */

$root = dirname(__DIR__, 3);

/* GHSA-77pr-g949-4v5f */
test('GHSA-77pr: spikekill authorizes the target graph, not just the realm', function () use ($root) {
	$source = file_get_contents($root . '/spikekill.php');

	expect($source)->toContain('is_realm_allowed(1043) && is_graph_allowed($local_graph_id)')
		->and($source)->not->toContain("if (is_realm_allowed(1043)) {\n\t\$local_data_ids");
});

/* GHSA-w3qw-762w-6g9v */
test('GHSA-w3qw: the GET action guard fails closed on an array action', function () use ($root) {
	$source = file_get_contents($root . '/include/global.php');

	$guard = strpos($source, 'if (is_array($action)) {');
	$loop  = strpos($source, 'foreach ($bad_actions as $bad) {');

	expect($guard)->not->toBeFalse();
	expect($loop)->not->toBeFalse();
	expect($guard)->toBeLessThan($loop);
});

/* GHSA-xhpr-w454-cc9w */
test('GHSA-xhpr: update_poller_cache casts output_type before interpolation', function () use ($root) {
	$source = file_get_contents($root . '/lib/utility.php');

	expect($source)->toContain("sqgr.snmp_query_graph_id = ' . (int) \$field['output_type']")
		->and($source)->not->toContain("sqgr.snmp_query_graph_id = ' . \$field['output_type']");
});

/* GHSA-j3px-vw6r-g25x */
test('GHSA-j3px: graphs_new escapes snmp_index in the IN() pivot', function () use ($root) {
	$source = file_get_contents($root . '/graphs_new.php');

	expect($source)->toContain("db_qstr(\$index['snmp_index'])")
		->and($source)->not->toContain("\" AND snmp_index IN('\" . \$index['snmp_index']");
});

/* GHSA-h745-3r76-g253 */
test('GHSA-h745: graphs_new escapes names in the data-query error messages', function () use ($root) {
	$source = file_get_contents($root . '/graphs_new.php');

	expect($source)->toContain("raise_message('xmlfielderr' . \$field_name, __esc(")
		->and($source)->not->toContain("raise_message('xmlfielderr' . \$field_name, __(");
});
