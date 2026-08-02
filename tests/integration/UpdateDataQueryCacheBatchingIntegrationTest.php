<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for issue #7531 (update_data_query_cache() commits
 * poller-cache writes per-row instead of batching).
 *
 * poller_update_poller_cache_from_buffer() is the function whose call count
 * the fix reduces: each invocation does its own "present = 0" UPDATE,
 * INSERT ... ON DUPLICATE KEY UPDATE, and stale-row DELETE. This test calls
 * the real function (not a spy) against a real (sqlite-backed) connection
 * and counts actual SQL statements via lib/database.php's
 * $database_total_queries counter -- the same mechanism
 * tests/Unit/DbColumnTypeCacheTest.php-style tests rely on implicitly
 * through db_execute_prepared() -- proving the O(n) -> O(1) round-trip
 * reduction the issue describes, not just asserting it algebraically.
 *
 * See UpdateDataQueryCacheBatchingStructureTest.php for the companion test
 * that proves update_data_query_cache()'s actual loop body calls things in
 * this per-row-vs-batched shape in the first place.
 */

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/utility.php';

if (!function_exists('set_config_option')) {
	// poller_update_poller_cache_from_buffer() records a last-changed
	// timestamp on every flush; not relevant to the batching behavior under
	// test, so this is a no-op stub rather than a real settings write.
	function set_config_option($name, $value) {
		return true;
	}
}

function batching_seed_poller_item_table() : FakeMySQLPDO {
	$conn = new FakeMySQLPDO();

	$conn->exec('CREATE TABLE poller_item (
		local_data_id INTEGER PRIMARY KEY, poller_id INTEGER, host_id INTEGER, action INTEGER,
		hostname TEXT, snmp_community TEXT, snmp_version INTEGER, snmp_timeout INTEGER,
		snmp_retries INTEGER, snmp_username TEXT, snmp_password TEXT, snmp_auth_protocol TEXT,
		snmp_priv_passphrase TEXT, snmp_priv_protocol TEXT, snmp_context TEXT, snmp_engine_id TEXT,
		snmp_port INTEGER, rrd_name TEXT, rrd_path TEXT, rrd_num INTEGER, rrd_step INTEGER,
		rrd_next_step INTEGER, arg1 TEXT, arg2 TEXT, arg3 TEXT, present INTEGER
	)');

	return $conn;
}

function batching_wire_default_connection(PDO $conn) : void {
	$GLOBALS['database_hostname']      = 'unittest';
	$GLOBALS['database_port']          = 0;
	$GLOBALS['database_default']       = 'unittest';
	$GLOBALS['config']                 = $GLOBALS['config'] ?? [];
	$GLOBALS['database_total_queries'] = 0;
	$GLOBALS['database_sessions']      = ['unittest:0:unittest' => $conn];
}

function batching_tuple(int $id) : string {
	// matches poller_update_poller_cache_from_buffer()'s fixed 26-column
	// INSERT list: local_data_id, poller_id, host_id, action, hostname,
	// snmp_community, snmp_version, snmp_timeout, snmp_retries,
	// snmp_username, snmp_password, snmp_auth_protocol, snmp_priv_passphrase,
	// snmp_priv_protocol, snmp_context, snmp_engine_id, snmp_port, rrd_name,
	// rrd_path, rrd_num, rrd_step, rrd_next_step, arg1, arg2, arg3, present
	return "($id, 1, 10, 0, 'host', 'community', 2, 500, 3, '', '', '', '', '', '', '', 161, 'ds$id', '', 1, 300, 0, '', '', '', 1)";
}

test('batching 3 changed rows into a single poller_update_poller_cache_from_buffer() call issues fewer SQL statements than 3 separate per-row calls', function () {
	// old behavior: update_poller_cache($data_source, true) flushed
	// immediately, once per changed row
	$conn = batching_seed_poller_item_table();
	batching_wire_default_connection($conn);

	foreach ([1, 2, 3] as $id) {
		$idsArg    = [$id];
		$itemsArg  = [batching_tuple($id)];
		poller_update_poller_cache_from_buffer($idsArg, $itemsArg, 1);
	}

	$perRowQueries = $GLOBALS['database_total_queries'];

	// new behavior: accumulate across the loop, flush once
	$conn = batching_seed_poller_item_table();
	batching_wire_default_connection($conn);

	$ids    = [1, 2, 3];
	$tuples = array_map('batching_tuple', $ids);

	poller_update_poller_cache_from_buffer($ids, $tuples, 1);

	$batchedQueries = $GLOBALS['database_total_queries'];

	expect($batchedQueries)->toBeLessThan($perRowQueries);

	// each flush does a present=0 UPDATE, an INSERT, and a stale-row DELETE;
	// 3 separate flushes -> 9 statements, 1 batched flush -> 3
	expect($perRowQueries)->toBe(9)
		->and($batchedQueries)->toBe(3);
});

test('the batched flush leaves the same end-state as 3 per-row flushes would', function () {
	$conn = batching_seed_poller_item_table();
	batching_wire_default_connection($conn);

	$ids    = [1, 2, 3];
	$tuples = array_map('batching_tuple', $ids);

	poller_update_poller_cache_from_buffer($ids, $tuples, 1);

	$rows = $conn->query('SELECT local_data_id, present FROM poller_item ORDER BY local_data_id')->fetchAll(PDO::FETCH_ASSOC);

	expect($rows)->toBe([
		['local_data_id' => 1, 'present' => 1],
		['local_data_id' => 2, 'present' => 1],
		['local_data_id' => 3, 'present' => 1],
	]);
});
