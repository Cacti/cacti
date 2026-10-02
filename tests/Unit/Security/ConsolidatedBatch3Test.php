<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Batch 3 consolidated GHSA regression guards. Each test scans the shipped
 * source for the specific fix so it runs without a database or web context,
 * matching the source-scanning convention used by the earlier batches.
 */

$root = dirname(__DIR__, 3);

/* GHSA-77pr-g949-4v5f: spikekill.php authorized only the Spike Kill realm
 * (1043) and never the target graph, so a realm holder could destroy the RRD
 * history of a graph their permissions deny. */
test('GHSA-77pr: spikekill authorizes the target graph, not just the realm', function () use ($root) {
	$source = file_get_contents($root . '/spikekill.php');

	expect($source)->toContain('is_realm_allowed(1043) && is_graph_allowed($local_graph_id)')
		->and($source)->not->toContain("if (is_realm_allowed(1043)) {\n\t\$local_data_ids");
});

/* GHSA-w3qw-762w-6g9v: an array-form action[] made every scalar $action == $bad
 * comparison false, slipping a state-changing action past the GET guard. */
test('GHSA-w3qw: the GET action guard fails closed on an array action', function () use ($root) {
	$source = file_get_contents($root . '/include/global.php');

	$guard = strpos($source, 'if (is_array($action)) {');
	$loop  = strpos($source, 'foreach($bad_actions as $bad) {');

	expect($guard)->not->toBeFalse();
	expect($loop)->not->toBeFalse();
	expect($guard)->toBeLessThan($loop);
});

/* GHSA-xhpr-w454-cc9w: output_type is a stored snmp_query_graph_id interpolated
 * into a query in update_poller_cache(); it must be cast to int. */
test('GHSA-xhpr: update_poller_cache casts output_type before interpolation', function () use ($root) {
	$source = file_get_contents($root . '/lib/utility.php');

	expect($source)->toContain("sqgr.snmp_query_graph_id = ' . (int) \$field['output_type']")
		->and($source)->not->toContain("sqgr.snmp_query_graph_id = ' . \$field['output_type']");
});

/* GHSA-j3px-vw6r-g25x: snmp_index is stored host_snmp_cache data interpolated
 * raw into an IN() list in graphs_new.php; it must be escaped. */
test('GHSA-j3px: graphs_new escapes snmp_index in the IN() pivot', function () use ($root) {
	$source = file_get_contents($root . '/graphs_new.php');

	expect($source)->toContain("db_qstr(\$index['snmp_index'])")
		->and($source)->not->toContain("\" AND snmp_index IN('\" . \$index['snmp_index']");
});

/* GHSA-h745-3r76-g253: the data-query name and field name reached a data-query
 * parse error message through __() (no escaping) in graphs_new.php. */
test('GHSA-h745: graphs_new escapes names in the data-query error messages', function () use ($root) {
	$source = file_get_contents($root . '/graphs_new.php');

	expect($source)->toContain("raise_message('xmlfielderr' . \$field_name, __esc(")
		->and($source)->not->toContain("raise_message('xmlfielderr' . \$field_name, __(");
});

/* GHSA-297h-95ff-r767: the clog log-file dropdown printed the log filename and
 * display name (derived from path_cactilog/path_stderrlog) without escaping. */
test('GHSA-297h: the clog log-file dropdown escapes the file name', function () use ($root) {
	$source = file_get_contents($root . '/lib/clog_webapi.php');

	expect($source)->toContain('<option value=\'" . html_escape($logFile) . "\'')
		->and($source)->toContain('html_escape($logName . ($logDate')
		->and($source)->not->toContain('<option value=\'" . $logFile . "\'');
});

