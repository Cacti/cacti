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
 * Regression tests for #8574: RRDcheck did not delete old findings when Boost
 * was enabled.
 *
 * When Boost is active, poller_rrdcheck.php's rrdcheck_master_handler() skips
 * its periodic rollup ("Boost will handle the Periodic Roll-up Cycle") and the
 * handoff runs through rrdcheck_boost_bottom() in lib/rrdcheck.php instead --
 * poller_boost.php calls that function directly rather than launching
 * poller_rrdcheck.php --type=bmaster. The original fix added the retention
 * DELETE to the unreachable 'bmaster' switch case in poller_rrdcheck.php, so
 * Boost runs still never purged and old rrdcheck rows accumulated forever.
 *
 * The fix routes the Boost handoff's purge through rrdcheck_purge_old_findings()
 * (called from rrdcheck_boost_bottom(), alongside the existing BOOST statistics
 * logging) and removes the dead bmaster-case purge. The functional tests below
 * exercise rrdcheck_purge_old_findings() against a live table; the source-level
 * checks keep the call wired into the real Boost handoff.
 */

require_once dirname(__DIR__, 3) . '/Helpers/UnitStubs.php';
require_once __DIR__ . '/../../../../lib/rrdcheck.php';

$rrdcheckLibPath    = __DIR__ . '/../../../../lib/rrdcheck.php';
$pollerRrdcheckPath = __DIR__ . '/../../../../poller_rrdcheck.php';

if (!function_exists('rrdcheck_purge_extract_function')) {
	function rrdcheck_purge_extract_function(string $contents, string $signature) : string {
		$func_pos = strpos($contents, $signature);

		if ($func_pos === false) {
			return '';
		}

		$func_end = strpos($contents, "\nfunction ", $func_pos + 1);

		if ($func_end === false) {
			$func_end = strlen($contents);
		}

		return substr($contents, $func_pos, $func_end - $func_pos);
	}
}

// ---------------------------------------------------------------------
// Functional: rrdcheck_purge_old_findings() prunes against a live table
// ---------------------------------------------------------------------

if (!function_exists('rrdcheck_purge_connect_table')) {
	function rrdcheck_purge_connect_table(PDO $pdo) : void {
		global $database_sessions, $database_hostname, $database_port, $database_default;

		$database_hostname = 'unit-test-host';
		$database_port     = 3306;
		$database_default  = 'cacti_unit_test';

		$database_sessions["$database_hostname:$database_port:$database_default"] = $pdo;
	}
}

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	require_once __DIR__ . '/../../../../lib/database.php';

	$this->db_globals = [$database_sessions, $database_hostname, $database_port, $database_default];

	$this->pdo = new PDO('sqlite::memory:');
	$this->pdo->exec('CREATE TABLE rrdcheck (local_data_id INTEGER, test_date TEXT, message TEXT)');

	rrdcheck_purge_connect_table($this->pdo);
});

// Put the default db_* connection back so the sqlite handle does not answer
// later tests' MySQL SQL and abort the suite.
afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;
});

test('rrdcheck_purge_old_findings deletes rows older than the retention window and keeps recent ones', function () {
	$old    = date('Y-m-d H:i:s', time() - (30 * 86400));
	$recent = date('Y-m-d H:i:s', time() - (1 * 86400));

	$this->pdo->exec("INSERT INTO rrdcheck (local_data_id, test_date, message) VALUES (1, '$old', 'stale')");
	$this->pdo->exec("INSERT INTO rrdcheck (local_data_id, test_date, message) VALUES (2, '$recent', 'fresh')");

	rrdcheck_purge_old_findings(7);

	$rows = $this->pdo->query('SELECT local_data_id FROM rrdcheck ORDER BY local_data_id')->fetchAll(PDO::FETCH_COLUMN);

	expect($rows)->toBe([2]);
});

test('rrdcheck_purge_old_findings honours a custom retention window', function () {
	$fortyFive = date('Y-m-d H:i:s', time() - (45 * 86400));
	$fifteen   = date('Y-m-d H:i:s', time() - (15 * 86400));

	$this->pdo->exec("INSERT INTO rrdcheck (local_data_id, test_date, message) VALUES (1, '$fortyFive', 'stale')");
	$this->pdo->exec("INSERT INTO rrdcheck (local_data_id, test_date, message) VALUES (2, '$fifteen', 'kept')");

	// A 30 day window keeps the 15 day row but drops the 45 day row.
	rrdcheck_purge_old_findings(30);

	$rows = $this->pdo->query('SELECT local_data_id FROM rrdcheck ORDER BY local_data_id')->fetchAll(PDO::FETCH_COLUMN);

	expect($rows)->toBe([2]);
});

test('rrdcheck_purge_old_findings falls back to the 7 day default for invalid retention', function () {
	$expected = date('Y-m-d H:i:s', time() - (7 * 86400));

	// Zero / negative retention must not purge everything; it defaults to 7 days.
	$cutoff = rrdcheck_purge_old_findings(0);

	// Allow a second of clock drift between the two date() calls.
	expect(abs(strtotime($cutoff) - strtotime($expected)))->toBeLessThanOrEqual(1);
});

// ---------------------------------------------------------------------
// Source-inspection: the real Boost handoff is wired to the purge
// ---------------------------------------------------------------------

test('rrdcheck_boost_bottom purges via rrdcheck_purge_old_findings on the Boost handoff', function () use ($rrdcheckLibPath) {
	$contents  = file_get_contents($rrdcheckLibPath);
	$func_body = rrdcheck_purge_extract_function($contents, 'function rrdcheck_boost_bottom()');

	expect($func_body)->not->toBe('');
	expect($func_body)->toContain('rrdcheck_purge_old_findings();');
});

test('rrdcheck_boost_bottom purges only after its children finish writing findings', function () use ($rrdcheckLibPath) {
	$contents  = file_get_contents($rrdcheckLibPath);
	$func_body = rrdcheck_purge_extract_function($contents, 'function rrdcheck_boost_bottom()');

	$wait_pos   = strpos($func_body, 'rrdcheck_wait_for_children(\'bmaster\',');
	$purge_pos  = strpos($func_body, 'rrdcheck_purge_old_findings();');

	expect($wait_pos)->not->toBeFalse();
	expect($purge_pos)->not->toBeFalse();
	expect($wait_pos)->toBeLessThan($purge_pos);
});

test('rrdcheck_boost_bottom logs BOOST statistics so bchild counters are aggregated and cleared', function () use ($rrdcheckLibPath) {
	$contents  = file_get_contents($rrdcheckLibPath);
	$func_body = rrdcheck_purge_extract_function($contents, 'function rrdcheck_boost_bottom()');

	expect($func_body)->toContain("rrdcheck_log_statistics('BOOST');");
	expect($func_body)->not->toContain("rrdcheck_log_statistics('HOURLY');");
});

test('the unreachable bmaster switch case does not carry the retention purge', function () use ($pollerRrdcheckPath) {
	// rrdcheck_launch_children('bmaster') forks poller_rrdcheck.php --type=bchild,
	// never --type=bmaster, so the 'bmaster' case here never runs during a Boost
	// cycle. The purge must live in rrdcheck_boost_bottom(), not here.
	$contents = file_get_contents($pollerRrdcheckPath);

	$bmaster_pos = strpos($contents, "case 'bmaster':");
	$bchild_pos  = strpos($contents, "case 'bchild':");

	expect($bmaster_pos)->not->toBeFalse();
	expect($bchild_pos)->not->toBeFalse();

	$bmaster_case = substr($contents, $bmaster_pos, $bchild_pos - $bmaster_pos);

	expect($bmaster_case)->not->toContain('DELETE FROM rrdcheck');
	expect($bmaster_case)->not->toContain('rrdcheck_purge_old_findings');
});
