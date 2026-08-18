<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for issue #7531 (update_data_query_cache() commits
 * poller-cache writes per-row instead of batching).
 *
 * Before the fix, every changed row called update_poller_cache($data_source,
 * true) -- $commit = true made it flush immediately via
 * poller_update_poller_cache_from_buffer() on every iteration. The fix
 * accumulates changed rows and calls update_poller_cache(..., false, ...)
 * (no immediate commit) inside the loop, flushing once via
 * poller_update_poller_cache_from_buffer() after the loop.
 *
 * The loop body is pulled directly out of lib/data_query.php and wrapped in
 * a namespaced, throwaway function so the test exercises the shipped code
 * rather than a re-typed copy of it (same extract-and-eval pattern as
 * SsNimbleAlletraVolumesWordAssemblyTest.php). The namespace holds spies for
 * update_data_source_data_query_cache(), update_poller_cache(),
 * poller_update_poller_cache_from_buffer() and db_fetch_cell_prepared() so
 * the test observes call counts/arguments without touching a real DB --
 * see UpdateDataQueryCacheBatchingIntegrationTest.php for the companion
 * test that proves the real poller_update_poller_cache_from_buffer()
 * issues fewer SQL statements when batched this way.
 */

require_once CACTI_PATH_TESTS . '/Helpers/UnitStubs.php'; // provides the global cacti_sizeof() the extracted block falls back to

$source = file_get_contents(CACTI_PATH_LIBRARY . '/data_query.php');

preg_match('/\tif \(cacti_sizeof\(\$data_sources\) > 0\) \{.*?\n\t\}\n/s', $source, $block);

test('the batched update_data_query_cache() loop body is present in lib/data_query.php', function () use ($block) {
	expect($block)->not->toBeEmpty();
});

$namespace = 'CactiTest\\UpdateDataQueryCacheBatching';

// eval() here only wraps PHP regex-extracted from this repo's own
// lib/data_query.php (not external/user input) into a throwaway, namespaced
// function. Test-only. Guarded by function_exists() so re-running this file
// within the same process is safe.
if (!function_exists($namespace . '\\run')) {
	eval("
	namespace $namespace;

	function db_fetch_cell_prepared(\$sql, \$params = []) {
		return 5; // the poller_id every data_source in this scenario shares
	}

	function update_data_source_data_query_cache(\$id, \$host_id, \$data_query_id, \$index) {
		\$GLOBALS['__changed_check_calls'][] = \$id;

		return in_array(\$id, \$GLOBALS['__changed_ids'], true);
	}

	function update_poller_cache(\$data_source, \$commit, \$poller_id) {
		\$GLOBALS['__update_poller_cache_calls'][] = [\$data_source['id'], \$commit, \$poller_id];

		return [\"tuple_{\$data_source['id']}\"];
	}

	function poller_update_poller_cache_from_buffer(\$ids, \$items, \$poller_id) {
		\$GLOBALS['__flush_calls'][] = [\$ids, \$items, \$poller_id];
	}

	function run(int \$host_id, int \$data_query_id, array \$data_sources) {
		{$block[0]}
	}
	");
}

$run = $namespace . '\\run';

beforeEach(function () {
	$GLOBALS['__changed_check_calls']      = [];
	$GLOBALS['__update_poller_cache_calls'] = [];
	$GLOBALS['__flush_calls']               = [];
});

test('every changed row calls update_poller_cache() with commit=false, not commit=true', function () use ($run) {
	$GLOBALS['__changed_ids'] = [1, 2, 3];

	$dataSources = [
		['id' => 1, 'snmp_index' => '1'],
		['id' => 2, 'snmp_index' => '2'],
		['id' => 3, 'snmp_index' => '3'],
	];

	$run(10, 20, $dataSources);

	expect($GLOBALS['__update_poller_cache_calls'])->toHaveCount(3);

	foreach ($GLOBALS['__update_poller_cache_calls'] as $call) {
		[, $commit, $poller_id] = $call;

		expect($commit)->toBeFalse()
			->and($poller_id)->toBe(5);
	}
});

test('poller_update_poller_cache_from_buffer() is called exactly once for all changed rows, not once per row', function () use ($run) {
	$GLOBALS['__changed_ids'] = [1, 2, 3];

	$dataSources = [
		['id' => 1, 'snmp_index' => '1'],
		['id' => 2, 'snmp_index' => '2'],
		['id' => 3, 'snmp_index' => '3'],
	];

	$run(10, 20, $dataSources);

	expect($GLOBALS['__flush_calls'])->toHaveCount(1);

	[$ids, $items, $poller_id] = $GLOBALS['__flush_calls'][0];

	expect($ids)->toBe([1, 2, 3])
		->and($items)->toBe(['tuple_1', 'tuple_2', 'tuple_3'])
		->and($poller_id)->toBe(5);
});

test('unchanged rows are excluded from both the poller_items buffer and the flush id list', function () use ($run) {
	$GLOBALS['__changed_ids'] = [2];

	$dataSources = [
		['id' => 1, 'snmp_index' => '1'],
		['id' => 2, 'snmp_index' => '2'],
		['id' => 3, 'snmp_index' => '3'],
	];

	$run(10, 20, $dataSources);

	// update_data_source_data_query_cache() is still called for every row
	// (that check is unrelated to batching), but only row 2 changed
	expect($GLOBALS['__changed_check_calls'])->toBe([1, 2, 3])
		->and($GLOBALS['__update_poller_cache_calls'])->toHaveCount(1);

	[$ids, $items] = $GLOBALS['__flush_calls'][0];
	expect($ids)->toBe([2])
		->and($items)->toBe(['tuple_2']);
});

test('no rows changed means no flush at all', function () use ($run) {
	$GLOBALS['__changed_ids'] = [];

	$dataSources = [
		['id' => 1, 'snmp_index' => '1'],
	];

	$run(10, 20, $dataSources);

	expect($GLOBALS['__update_poller_cache_calls'])->toBe([])
		->and($GLOBALS['__flush_calls'])->toBe([]);
});
