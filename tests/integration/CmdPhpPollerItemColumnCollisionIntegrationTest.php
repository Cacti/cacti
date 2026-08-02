<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for issue #7522 (cmd.php's poller_item/host join
 * silently defeats poller_item's SNMP-credential caching) and #7528
 * (the redundant script-server-call COUNT(*) query).
 *
 * poller_item and host share ~13 identically-named columns (hostname, every
 * snmp_* field). The pre-fix query selected `*` across pi/h/s, and PDO
 * FETCH_ASSOC collapses same-named columns to one array key with the last
 * table winning -- so $item['snmp_community'] silently held host's live
 * value instead of poller_item's cached copy. The fix selects `pi.*` plus
 * two explicitly-aliased disabled columns.
 *
 * Both $poller_items SELECT + array_filter count blocks are pulled directly
 * out of cmd.php (one per active_profiles branch) and run against a real
 * (sqlite-backed) connection via FakeMySQLPDO, so this exercises the
 * shipped query text rather than a re-typed copy of it.
 */

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';

if (!defined('SQL_NO_CACHE')) {
	define('SQL_NO_CACHE', '');
}

if (!defined('POLLER_ACTION_SCRIPT_PHP')) {
	define('POLLER_ACTION_SCRIPT_PHP', 2);
}

if (!defined('POLLER_ACTION_SCRIPT_PHP_COUNT')) {
	define('POLLER_ACTION_SCRIPT_PHP_COUNT', 12);
}

$cmdPhpSource = file_get_contents(dirname(__DIR__, 2) . '/cmd.php');

preg_match_all('/\$poller_items = db_fetch_assoc_prepared\(.*?\}\)\);\n/s', $cmdPhpSource, $blocks);

test('both poller_item query + count blocks are present in cmd.php', function () use ($blocks) {
	expect($blocks[0])->toHaveCount(2);
});

// eval() here only wraps SQL/PHP regex-extracted from this repo's own
// cmd.php (not external/user input) into a throwaway function, matching the
// existing extract-and-eval pattern used in Issue7070PercentileContractTest.php
// and SsNimbleAlletraVolumesWordAssemblyTest.php. Test-only. Guarded by
// function_exists() so re-running this file within the same process is safe.

// active_profiles != 1 branch (has the "AND pi.rrd_next_step <= 0" filter)
if (!function_exists('cmd_php_query_branch_1')) {
	eval('function cmd_php_query_branch_1(string $sql_where, string $sql_where1, array $params1) {
		' . $blocks[0][0] . '
		return [$poller_items, $script_server_calls];
	}');
}

// active_profiles == 1 branch (no rrd_next_step filter)
if (!function_exists('cmd_php_query_branch_2')) {
	eval('function cmd_php_query_branch_2(string $sql_where, string $sql_where1, array $params1) {
		' . $blocks[0][1] . '
		return [$poller_items, $script_server_calls];
	}');
}

function cmd_php_seed_schema() : PDO {
	$conn = new FakeMySQLPDO();

	$conn->exec("CREATE TABLE poller_item (
		local_data_id INTEGER, poller_id INTEGER, host_id INTEGER, action INTEGER,
		present INTEGER, hostname TEXT, snmp_community TEXT, snmp_version INTEGER,
		snmp_username TEXT, snmp_password TEXT, snmp_auth_protocol TEXT,
		snmp_priv_passphrase TEXT, snmp_priv_protocol TEXT, snmp_context TEXT,
		snmp_engine_id TEXT, snmp_port INTEGER, snmp_timeout INTEGER, snmp_retries INTEGER,
		rrd_name TEXT, rrd_path TEXT, rrd_num INTEGER, rrd_step INTEGER, rrd_next_step INTEGER,
		arg1 TEXT, arg2 TEXT, arg3 TEXT
	)");

	$conn->exec('CREATE TABLE host (id INTEGER, hostname TEXT, snmp_community TEXT, snmp_version INTEGER, disabled TEXT, site_id INTEGER)');
	$conn->exec('CREATE TABLE sites (id INTEGER, disabled TEXT)');

	// poller_item's own cached SNMP fields differ from host's live fields on
	// purpose, so a query that reads the wrong table's copy is caught.
	$conn->exec("INSERT INTO poller_item
		(local_data_id, poller_id, host_id, action, present, hostname, snmp_community, snmp_version, rrd_name, rrd_step, rrd_next_step)
		VALUES (1, 1, 10, 0, 1, 'cached-host', 'CACHED_COMMUNITY', 2, 'ds1', 300, 0)");

	$conn->exec("INSERT INTO poller_item
		(local_data_id, poller_id, host_id, action, present, hostname, snmp_community, snmp_version, rrd_name, rrd_step, rrd_next_step)
		VALUES (2, 1, 10, " . POLLER_ACTION_SCRIPT_PHP . ", 1, 'cached-host', 'CACHED_COMMUNITY', 2, 'ds2', 300, 0)");

	$conn->exec("INSERT INTO host (id, hostname, snmp_community, snmp_version, disabled, site_id)
		VALUES (10, 'live-host', 'LIVE_COMMUNITY', 3, '', NULL)");

	return $conn;
}

function cmd_php_wire_default_connection(PDO $conn) : void {
	$GLOBALS['database_hostname']      = 'unittest';
	$GLOBALS['database_port']          = 0;
	$GLOBALS['database_default']       = 'unittest';
	$GLOBALS['config']                 = $GLOBALS['config'] ?? [];
	$GLOBALS['database_total_queries'] = 0;
	$GLOBALS['database_sessions']      = ['unittest:0:unittest' => $conn];
}

test('poller_item cached SNMP fields win over host live fields (multiple active profiles branch)', function () {
	$conn = cmd_php_seed_schema();
	cmd_php_wire_default_connection($conn);

	[$items, $script_server_calls] = cmd_php_query_branch_1('', '', [1]);

	expect($items)->toHaveCount(2);
	expect($items[0]['hostname'])->toBe('cached-host')
		->and($items[0]['snmp_community'])->toBe('CACHED_COMMUNITY')
		->and($items[0]['snmp_version'])->toBe(2);

	// the pre-fix bug: host's live values, not poller_item's cache
	expect($items[0]['hostname'])->not->toBe('live-host')
		->and($items[0]['snmp_community'])->not->toBe('LIVE_COMMUNITY')
		->and($items[0]['snmp_version'])->not->toBe(3);

	// #7528: in-PHP count over the already-fetched rows replaces the
	// redundant COUNT(*) re-join; only the POLLER_ACTION_SCRIPT_PHP row matches
	expect($script_server_calls)->toBe(1);
});

test('poller_item cached SNMP fields win over host live fields (single active profile branch)', function () {
	$conn = cmd_php_seed_schema();
	cmd_php_wire_default_connection($conn);

	[$items, $script_server_calls] = cmd_php_query_branch_2('', '', [1]);

	expect($items)->toHaveCount(2);
	expect($items[0]['snmp_community'])->toBe('CACHED_COMMUNITY')
		->and($script_server_calls)->toBe(1);
});

test('cmd.php no longer selects a bare * across the poller_item/host/sites join', function () use ($cmdPhpSource) {
	expect($cmdPhpSource)->not->toMatch('/SELECT.*SQL_NO_CACHE\s*\.\s*"\s*\*/')
		->and(substr_count($cmdPhpSource, 'pi.*,'))->toBe(2);
});

test('cmd.php no longer runs a redundant COUNT(*) join for script_server_calls', function () use ($cmdPhpSource) {
	// the query counts poller_item rows joined to host/sites filtered by
	// pi.action; that specific re-join must be gone even though other,
	// unrelated COUNT(*) queries (e.g. the poller_id existence check) remain
	expect($cmdPhpSource)->not->toMatch('/COUNT\(\*\)[^;]*poller_item[^;]*pi\.action IN/s')
		->and($cmdPhpSource)->toContain('array_filter($poller_items');
});
