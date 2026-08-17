<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for issue #7527 (update_poller_cache() re-derives
 * poller_id via a JOIN query even when callers already have it).
 *
 * The derivation block is pulled directly out of lib/utility.php and
 * wrapped in a throwaway function, matching the extract-and-eval pattern
 * used in SsNimbleAlletraVolumesWordAssemblyTest.php and
 * Issue7070PercentileContractTest.php, so the test exercises the shipped
 * expression rather than a re-typed copy of it.
 *
 * The wrapper is declared inside a uniquely-named namespace so its bare
 * call to db_fetch_cell_prepared() resolves to this file's namespaced spy
 * instead of the real lib/database.php function -- PHP falls back to the
 * global namespace for an unqualified call only when no function of that
 * name exists in the current namespace, so defining a namespaced spy lets
 * the test count invocations without touching or requiring a real DB
 * connection, and without risking "cannot redeclare" against the real
 * global db_fetch_cell_prepared() if some other test file in the same
 * process has already loaded lib/database.php.
 */

$source = file_get_contents(dirname(__DIR__, 4) . '/lib/utility.php');

preg_match('/if \(\$poller_id === null\) \{.*?\n\t\}\n/s', $source, $block);

test('the poller_id derivation block is present in update_poller_cache()', function () use ($block) {
	expect($block)->not->toBeEmpty();
});

$namespace = 'CactiTest\\UpdatePollerCacheDerive';

// eval() here only wraps PHP regex-extracted from this repo's own
// lib/utility.php (not external/user input) into a throwaway, namespaced
// function. Test-only. Guarded by function_exists() so re-running this
// file within the same process is safe.
if (!function_exists($namespace . '\\derive')) {
	eval("
	namespace $namespace;

	function db_fetch_cell_prepared(\$sql, \$params = []) {
		\$GLOBALS['__update_poller_cache_derive_calls']++;
		\$GLOBALS['__update_poller_cache_derive_last_params'] = \$params;

		return 42; // the value the real JOIN query would have returned
	}

	function derive(array \$data_source, ?int \$poller_id = null) {
		{$block[0]}
		return \$poller_id;
	}
	");
}

$deriveFn = $namespace . '\\derive';

beforeEach(function () {
	$GLOBALS['__update_poller_cache_derive_calls']       = 0;
	$GLOBALS['__update_poller_cache_derive_last_params'] = null;
});

test('a poller_id already present on the $data_source array short-circuits the JOIN lookup', function () use ($deriveFn) {
	$result = $deriveFn(['id' => 7, 'poller_id' => 3], null);

	expect($result)->toBe(3)
		->and($GLOBALS['__update_poller_cache_derive_calls'])->toBe(0);
});

test('an explicit $poller_id argument short-circuits the JOIN lookup too', function () use ($deriveFn) {
	$result = $deriveFn(['id' => 7], 9);

	expect($result)->toBe(9)
		->and($GLOBALS['__update_poller_cache_derive_calls'])->toBe(0);
});

test('a missing poller_id falls back to the JOIN query, keyed by the data_source id', function () use ($deriveFn) {
	$result = $deriveFn(['id' => 7], null);

	expect($result)->toBe(42)
		->and($GLOBALS['__update_poller_cache_derive_calls'])->toBe(1)
		->and($GLOBALS['__update_poller_cache_derive_last_params'])->toBe([7]);
});

test('a poller_id of 0 on the array is honored rather than triggering the fallback (falsy but set)', function () use ($deriveFn) {
	// PHP's ?? operator checks isset()/null, not truthiness, so a
	// legitimately-0 poller_id must not trigger the JOIN fallback
	$result = $deriveFn(['id' => 7, 'poller_id' => 0], null);

	expect($result)->toBe(0)
		->and($GLOBALS['__update_poller_cache_derive_calls'])->toBe(0);
});

test('lib/utility.php threads poller_id through to update_poller_cache() at every loop call site named in #7527', function () {
	$root = dirname(__DIR__, 4);

	$utility = file_get_contents($root . '/lib/utility.php');
	expect($utility)->toContain('update_poller_cache($data, false, $poller_id)') // update_poller_cache_from_query()
		->and($utility)->toContain("\$data['poller_id'] = \$host['poller_id'];"); // push_out_host()

	$apiDevice = file_get_contents($root . '/lib/api_device.php');
	expect($apiDevice)->toContain("update_poller_cache(\$data_source['id'], false, \$poller_id)");
});
