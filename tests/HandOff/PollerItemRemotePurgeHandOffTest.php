<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Hand-off tests for #7521/#7524: a data source's ownership moving from one
 * remote Data Collector to another (or being removed/converted) is a
 * hand-off between two physically separate poller databases. The bug in
 * all three fixed sites was the same shape: the "new" side got written but
 * the "old" side's poller_item row was left behind, so the old Data
 * Collector kept polling an item that no longer belonged to it.
 *
 * These tests exercise the real, unmodified production functions --
 * api_data_source_change_host(), data_query_remove_disabled_items(), and
 * data_source_to_data_template() -- against two live database connections
 * standing in for the central database and a remote Data Collector's
 * database, per the project's established two-database stand-in pattern
 * (see tests/Unit/DbExecutePreparedTest.php and
 * tests/Unit/DbReplaceSqlSaveTest.php for the same style applied to
 * lower-level db_* functions).
 *
 * poller_push_to_remote_db_connect()/poller_connect_to_remote() are the
 * real, unmodified functions, exercised two different ways here:
 *
 *  - poller_connect_to_remote() looks up the `poller` row via
 *    db_fetch_row_prepared(), which gates its result on
 *    PDOStatement::rowCount() (lib/database.php:910). The PDO sqlite driver
 *    always reports 0 for a SELECT's rowCount(), so that lookup silently
 *    returns [] against sqlite regardless of how the fixture is seeded --
 *    the same limitation already documented in
 *    tests/Unit/DbExecutePreparedTest.php's skipped case. The central/
 *    default connection therefore has to speak real MySQL protocol; this
 *    file connects to a MariaDB container (see "Docker fixture" below) and
 *    skips itself cleanly if one isn't reachable, the same graceful
 *    fall-back tests/HandOff/CactiSettingsHandOffTest.php uses when its
 *    target feature isn't present on the branch.
 *  - Reaching the "old poller" branch without a live TCP connection to a
 *    second MySQL server exploits db_connect_real()'s own connection cache
 *    (lib/database.php:55 - `$database_sessions["$device:$port:$db_name"]`):
 *    pre-seeding that cache with the "remote" PDO handle, keyed by the
 *    exact host/port/dbname the fixture's `poller` row carries, makes
 *    db_connect_real() return the fake connection on the first cache
 *    lookup -- before it ever builds a real DSN. DELETE statements don't
 *    depend on rowCount(), so a plain sqlite::memory: handle is fine for
 *    this side.
 *
 * Neither of these depends on POLLER_ID, which the shared Pest process may
 * already have pinned to 1 via a prior test file's UnitStubs.php include
 * (HandOff runs after Unit per phpunit.xml's testsuite order, so it always
 * has been in a full-suite run).
 *
 * Docker fixture: set CACTI_HANDOFF_MYSQL_HOST/_PORT/_USER/_PASS/_DB to
 * point at an already-running MySQL/MariaDB instance. With no env set, the
 * fixture tries 127.0.0.1:33071 (root/root, db "handoff_central") -- start
 * one locally with:
 *   docker run -d --rm --name cacti-handoff-poller-test \
 *     -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=handoff_central \
 *     -p 33071:3306 mariadb:11
 */

require_once CACTI_PATH_TESTS . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_INCLUDE . '/vendor/autoload.php';
require_once CACTI_PATH_LIBRARY . '/database.php';

// push_out_host() (exercised via api_data_source_change_host()) needs
// CACTI_PATH_LIBRARY to include_once() a sibling lib/ file. This file never
// requires include/global.php (which normally defines it via
// include/global_path.php), so define it directly rather than depending on
// some other test file in the suite having bootstrapped it first as a
// process-wide side effect -- that ordering isn't guaranteed when this file
// runs standalone or alongside a different subset of tests.
if (!defined('CACTI_PATH_LIBRARY')) {
	define('CACTI_PATH_LIBRARY', dirname(__DIR__, 2) . '/lib');
}

function handoffMysqlDsnParts(): array {
	return [
		'host' => getenv('CACTI_HANDOFF_MYSQL_HOST') ?: '127.0.0.1',
		'port' => (int) (getenv('CACTI_HANDOFF_MYSQL_PORT') ?: 33071),
		'user' => getenv('CACTI_HANDOFF_MYSQL_USER') ?: 'root',
		'pass' => getenv('CACTI_HANDOFF_MYSQL_PASS') ?: 'root',
		'db'   => getenv('CACTI_HANDOFF_MYSQL_DB') ?: 'handoff_central',
	];
}

function handoffTryConnectMysql(): ?PDO {
	$parts = handoffMysqlDsnParts();

	try {
		return new PDO(
			"mysql:host={$parts['host']};port={$parts['port']};dbname={$parts['db']};charset=utf8",
			$parts['user'],
			$parts['pass'],
			[PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
		);
	} catch (Throwable $e) {
		return null;
	}
}

if (handoffTryConnectMysql() === null) {
	$parts = handoffMysqlDsnParts();

	test("Hand-off: {$parts['host']}:{$parts['port']} not reachable, skipping poller_item remote-purge hand-off tests")
		->skip('no MySQL/MariaDB reachable for the central-connection fixture -- see file header for how to start one');

	return;
}

if (!defined('MESSAGE_LEVEL_WARN')) {
	define('MESSAGE_LEVEL_WARN', 2);
}

require_once CACTI_PATH_LIBRARY . '/poller.php';

// api_data_source_change_host()/data_source_to_data_template() call these;
// neither touches poller_item or anything this test asserts on, so they are
// stubbed to no-ops rather than pulling in lib/variables.php + lib/utility.php
// and their own dependency trees.
if (!function_exists('push_out_host')) {
	function push_out_host(int $host_id, int $local_data_id = 0, int $data_template_id = 0) : void {
	}
}

if (!function_exists('update_data_source_title_cache')) {
	function update_data_source_title_cache(int $local_data_id) : void {
	}
}

// data_source_to_data_template() calls get_hash_data_template(), which
// lives in lib/functions.php alongside hundreds of unrelated functions
// (several of which collide with tests/Helpers/UnitStubs.php's stubs, e.g.
// read_user_setting()). Stub the one function this test path needs instead
// of pulling in that whole file.
if (!function_exists('get_hash_data_template')) {
	function get_hash_data_template(int $data_template_id, string $sub_type = 'data_template') : string {
		return md5($sub_type . ':' . $data_template_id . ':' . random_int(0, PHP_INT_MAX));
	}
}

// data_query_remove_disabled_items() calls array_rekey(); also lives in
// lib/functions.php, same collision concern as get_hash_data_template()
// above.
if (!function_exists('array_rekey')) {
	function array_rekey(mixed $array, string $key, mixed $key_value) : array {
		$ret_array = [];

		if (is_array($array)) {
			foreach ($array as $item) {
				$ret_array[$item[$key]] = $item[$key_value];
			}
		}

		return $ret_array;
	}
}

require_once CACTI_PATH_LIBRARY . '/api_data_source.php';
require_once CACTI_PATH_LIBRARY . '/data_query.php';
require_once CACTI_PATH_LIBRARY . '/template.php';

const HANDOFF_OLD_POLLER_ID = 7;
const HANDOFF_NEW_POLLER_ID = 1; // central; keeps the (unrelated, already-correct) "push to new" side inert

// Fake connection-string parts for the old remote poller. Must not be
// 'localhost'/'127.0.0.1' -- poller_connect_to_remote() treats those as a
// self-referential loop and bails out with false before ever calling
// db_connect_real().
const HANDOFF_OLD_POLLER_DBHOST = 'remote-poller-a.fixture';
const HANDOFF_OLD_POLLER_DBPORT = 3306;
const HANDOFF_OLD_POLLER_DBNAME = 'remote_a';

/*
 * Builds the central database on the real MySQL/MariaDB fixture: host,
 * data_local, poller, and poller_item tables with just the columns the
 * fixed functions actually read. Dropped and recreated per test so state
 * never leaks between cases.
 */
function handoffCentralConn(): PDO {
	$conn = handoffTryConnectMysql();

	foreach (['poller_item', 'poller', 'data_template_rrd', 'data_template', 'data_template_data', 'data_local', 'host', 'poller_output_boost'] as $table) {
		$conn->exec("DROP TABLE IF EXISTS $table");
	}

	$conn->exec('CREATE TABLE host (id INTEGER PRIMARY KEY, poller_id INTEGER)');
	$conn->exec('CREATE TABLE data_local (id INTEGER PRIMARY KEY, host_id INTEGER)');
	$conn->exec('CREATE TABLE data_template_data (id INTEGER PRIMARY KEY AUTO_INCREMENT, local_data_id INTEGER, local_data_template_data_id INTEGER, data_template_id INTEGER, name VARCHAR(255), active VARCHAR(3))');
	$conn->exec('CREATE TABLE data_template (id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255), hash VARCHAR(32))');
	$conn->exec('CREATE TABLE data_template_rrd (id INTEGER PRIMARY KEY AUTO_INCREMENT, local_data_id INTEGER, local_data_template_rrd_id INTEGER, data_template_id INTEGER, hash VARCHAR(32))');
	$conn->exec('CREATE TABLE poller (
		id INTEGER PRIMARY KEY, name VARCHAR(255), dbhost VARCHAR(255), dbuser VARCHAR(64), dbpass VARCHAR(64), dbdefault VARCHAR(64),
		dbport INTEGER, dbretries INTEGER, dbssl TINYINT, dbsslkey VARCHAR(255), dbsslcert VARCHAR(255), dbsslca VARCHAR(255),
		dbsslcapath VARCHAR(255), dbsslverifyservercert TINYINT, last_status DATETIME, disabled VARCHAR(3))');
	$conn->exec('CREATE TABLE poller_item (local_data_id INTEGER, host_id INTEGER, poller_id INTEGER)');
	$conn->exec('CREATE TABLE poller_output_boost (local_data_id INTEGER)');

	// The row poller_connect_to_remote() looks up for HANDOFF_OLD_POLLER_ID.
	// remote_poller_up() additionally needs a recent heartbeat + disabled=''.
	// tests/Helpers/UnitStubs.php stubs read_config_option() to always
	// return false, so remote_poller_up()'s $gone_time (poller_interval * 2)
	// is always 0 -- "UNIX_TIMESTAMP() - UNIX_TIMESTAMP(last_status) < 0"
	// can then only be true if last_status is in the future. NOW() + 60s
	// keeps the check satisfied without needing to touch that shared stub.
	$stmt = $conn->prepare('INSERT INTO poller
		(id, name, dbhost, dbuser, dbpass, dbdefault, dbport, dbretries, dbssl,
		 dbsslkey, dbsslcert, dbsslca, dbsslcapath, dbsslverifyservercert, last_status, disabled)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW() + INTERVAL 60 SECOND, \'\')');
	$stmt->execute([
		HANDOFF_OLD_POLLER_ID, 'Remote Poller A', HANDOFF_OLD_POLLER_DBHOST, 'user', 'pass',
		HANDOFF_OLD_POLLER_DBNAME, HANDOFF_OLD_POLLER_DBPORT, 0, 0, '', '', '', '', 0,
	]);

	return $conn;
}

function handoffRemoteConn(): PDO {
	$conn = new PDO('sqlite::memory:');
	$conn->exec('CREATE TABLE poller_item (local_data_id INTEGER, host_id INTEGER, poller_id INTEGER)');
	// mirrors the subset of central's schema that the (pre-existing,
	// unmodified) "push to new device" side writes to when the new device
	// also happens to live on this same poller.
	$conn->exec('CREATE TABLE data_local (id INTEGER PRIMARY KEY, host_id INTEGER)');

	return $conn;
}

function handoffPollerItemCount(PDO $conn, string $where = '1=1'): int {
	return (int) $conn->query("SELECT COUNT(*) FROM poller_item WHERE $where")->fetchColumn();
}

beforeEach(function () {
	global $database_hostname, $database_port, $database_default, $database_sessions;

	$this->db_globals = [$database_sessions, $database_hostname, $database_port, $database_default];

	$parts = handoffMysqlDsnParts();

	$database_hostname = $parts['host'];
	$database_port     = $parts['port'];
	$database_default  = $parts['db'];

	$this->central   = handoffCentralConn();
	$this->oldRemote = handoffRemoteConn();

	// db_connect_real() checks this cache by "$device:$port:$db_name" before
	// building a DSN or opening a socket -- see file header.
	$database_sessions = [
		"$database_hostname:$database_port:$database_default"                             => $this->central,
		HANDOFF_OLD_POLLER_DBHOST . ':' . HANDOFF_OLD_POLLER_DBPORT . ':' . HANDOFF_OLD_POLLER_DBNAME => $this->oldRemote,
	];
});

// Put the default db_* connection back. Left in place, the test handles answer
// every later read_config_option() in the run and throw on Cacti's MySQL SQL,
// aborting the suite.
afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;
});

// ---------------------------------------------------------------------
// #7521: api_data_source_change_host() moving a data source off a
// remote-managed device must purge the old remote poller's row.
// ---------------------------------------------------------------------

it('purges the stale poller_item row from the old remote poller when a data source changes host (#7521 fix)', function () {
	// old device is managed by the remote poller; new device is central
	$this->central->exec('INSERT INTO host (id, poller_id) VALUES (100, ' . HANDOFF_OLD_POLLER_ID . ')');
	$this->central->exec('INSERT INTO host (id, poller_id) VALUES (200, ' . HANDOFF_NEW_POLLER_ID . ')');
	$this->central->exec('INSERT INTO data_local (id, host_id) VALUES (500, 100)');

	// the row as it exists on the old poller today, mid-flight
	$this->oldRemote->exec('INSERT INTO poller_item (local_data_id, host_id, poller_id) VALUES (500, 100, ' . HANDOFF_OLD_POLLER_ID . ')');

	api_data_source_change_host([500], 200);

	expect(handoffPollerItemCount($this->oldRemote, 'local_data_id = 500'))->toBe(0)
		->and((int) $this->central->query('SELECT host_id FROM data_local WHERE id = 500')->fetchColumn())->toBe(200);
});

it('demonstrates the pre-#7521 bug: the unfixed logic leaves the old poller row behind', function () {
	// Faithful reproduction of api_data_source_change_host() as filed in
	// #7521 -- pushes to the new device's remote poller but never purges
	// the old one. Kept local to this test file (not sourced from
	// lib/api_data_source.php) purely as a regression pin: if this
	// "legacy" shape ever again matched the real function, this test
	// would start failing for the wrong reason and flag the drift.
	$legacyChangeHost = function (array $data_sources, int $device_id) {
		foreach ($data_sources as $data_source) {
			db_execute_prepared('UPDATE data_local SET host_id = ? WHERE id = ?', [$device_id, $data_source]);

			if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
				db_execute_prepared('UPDATE data_local SET host_id = ? WHERE id = ?', [$device_id, $data_source], true, $rcnn_id);
			}
		}
	};

	$this->central->exec('INSERT INTO host (id, poller_id) VALUES (100, ' . HANDOFF_OLD_POLLER_ID . ')');
	$this->central->exec('INSERT INTO host (id, poller_id) VALUES (200, ' . HANDOFF_NEW_POLLER_ID . ')');
	$this->central->exec('INSERT INTO data_local (id, host_id) VALUES (500, 100)');
	$this->oldRemote->exec('INSERT INTO poller_item (local_data_id, host_id, poller_id) VALUES (500, 100, ' . HANDOFF_OLD_POLLER_ID . ')');

	$legacyChangeHost([500], 200);

	// this is the bug: the old Data Collector keeps polling item 500 forever
	expect(handoffPollerItemCount($this->oldRemote, 'local_data_id = 500'))->toBe(1);
});

it('leaves the remote row alone when the data source stays on the same poller', function () {
	$this->central->exec('INSERT INTO host (id, poller_id) VALUES (100, ' . HANDOFF_OLD_POLLER_ID . ')');
	$this->central->exec('INSERT INTO host (id, poller_id) VALUES (101, ' . HANDOFF_OLD_POLLER_ID . ')');
	$this->central->exec('INSERT INTO data_local (id, host_id) VALUES (500, 100)');
	$this->oldRemote->exec('INSERT INTO poller_item (local_data_id, host_id, poller_id) VALUES (500, 100, ' . HANDOFF_OLD_POLLER_ID . ')');

	// reassigned to a different device that is on the SAME remote poller
	api_data_source_change_host([500], 101);

	expect(handoffPollerItemCount($this->oldRemote, 'local_data_id = 500'))->toBe(1);
});

// ---------------------------------------------------------------------
// #7524a: data_query_remove_disabled_items() orphan removal.
// ---------------------------------------------------------------------

it('purges orphaned poller_item rows from the remote poller during data query re-index (#7524 fix)', function () {
	$this->central->exec('INSERT INTO poller_item (local_data_id, host_id, poller_id) VALUES (500, 100, ' . HANDOFF_OLD_POLLER_ID . ')');
	$this->oldRemote->exec('INSERT INTO poller_item (local_data_id, host_id, poller_id) VALUES (500, 100, ' . HANDOFF_OLD_POLLER_ID . ')');

	data_query_remove_disabled_items([500]);

	expect(handoffPollerItemCount($this->central, 'local_data_id = 500'))->toBe(0)
		->and(handoffPollerItemCount($this->oldRemote, 'local_data_id = 500'))->toBe(0);
});

// ---------------------------------------------------------------------
// #7524b: data_source_to_data_template() conversion delete.
// ---------------------------------------------------------------------

it('purges the remote poller_item row when converting a data source to a template (#7524 fix)', function () {
	$this->central->exec('INSERT INTO host (id, poller_id) VALUES (100, ' . HANDOFF_OLD_POLLER_ID . ')');
	$this->central->exec('INSERT INTO data_local (id, host_id) VALUES (500, 100)');
	$this->central->exec("INSERT INTO data_template_data (id, local_data_id, name, active) VALUES (1, 500, 'ds', 'on')");
	$this->oldRemote->exec('INSERT INTO poller_item (local_data_id, host_id, poller_id) VALUES (500, 100, ' . HANDOFF_OLD_POLLER_ID . ')');

	data_source_to_data_template(500, '<ds_title>');

	expect((int) $this->central->query('SELECT COUNT(*) FROM data_local WHERE id = 500')->fetchColumn())->toBe(0)
		->and(handoffPollerItemCount($this->oldRemote, 'local_data_id = 500'))->toBe(0);
});
