<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Smoke tests for issue #7133. Cheap structural checks that verify
 * lib/utility.php still parses, the touched functions exist, and the
 * four post-fix shapes are present. Runs without Cacti's bootstrap.
 */

$repoRoot = __DIR__ . '/../..';
$utility  = file_get_contents("$repoRoot/lib/utility.php");

test('lib/utility.php parses and contains the touched functions', function () use ($utility) {
	expect($utility)->toContain('function update_poller_cache($data_source, $commit = false)');
	expect($utility)->toContain('function push_out_data_input_method($data_input_id)');
	expect($utility)->toContain('function push_out_host($host_id, $local_data_id = 0, $data_template_id = 0)');
});

test('update_poller_cache commit branch is present and unguarded', function () use ($utility) {
	expect($utility)->toContain('if ($commit) {');
	expect(strpos($utility, 'if ($commit && cacti_sizeof($poller_items))'))->toBeFalse();
});

test('poller cache rebuild paths preserve host_id zero data sources', function () use ($utility) {
	expect($utility)->toContain('COALESCE(h.poller_id, 1) AS poller_id');
	expect($utility)->toContain('LEFT JOIN host AS h');
	expect($utility)->toContain('without a device have host_id=0 and belong to the main poller');
});

test('update_poller_cache wraps build code in whitelist guard, not early return', function () use ($utility) {
	expect($utility)->toContain('if (cacti_sizeof($data_input) && data_input_whitelist_check($data_input[\'id\'])) {');
});

test('update_poller_cache logs the whitelist failure on the elseif branch', function () use ($utility) {
	/* Whitelist failure must not silently drop the data source. The
	 * elseif branch is what tells the operator the DI failed the
	 * whitelist; if a future merge collapses it back into the positive
	 * guard the WARNING disappears and corruption goes undiagnosed. */
	expect($utility)->toContain('} elseif (cacti_sizeof($data_input) && !data_input_whitelist_check($data_input[\'id\'])) {');
	expect($utility)->toContain("not Passing Input Whitelist Validation for DS[' . \$data_source['id'] . '].  Database may be corrupted");
});

test('push_out_data_input_method always-appends after boundary flush', function () use ($utility) {
	$start = strpos($utility, 'function push_out_data_input_method');
	$slice = substr($utility, $start, 1500);
	expect($slice)->toContain('$_my_local_data_ids[] = $data_source[\'id\'];');
	expect($slice)->toContain('$poller_items = array_merge($poller_items, update_poller_cache($data_source));');
});

test('push_out_host derives poller_id branchwise and groups host_id=0 by poller', function () use ($utility) {
	$start = strpos($utility, 'function push_out_host(');
	$slice = substr($utility, $start, 8000);
	expect($slice)->toContain('if ($host_id > 0) {');
	expect($slice)->toContain('elseif (cacti_sizeof($local_data_ids))');
	expect($slice)->toContain('$ids_by_poller');
	expect($slice)->toContain('COALESCE(h.poller_id, 1) AS poller_id');
	expect($slice)->toContain('foreach ($ids_by_poller as $pid => $ldid_set)');
});

test('push_out_host PCACHE log uses $old_data not $old_value', function () use ($utility) {
	expect($utility)->toContain("isset(\$old_data['value']) && \$old_data['value'] != \$host[\$field]");
	$matches = [];
	preg_match_all('/\$old_value\[/', $utility, $matches);
	expect($matches[0])->toBeEmpty();
});
