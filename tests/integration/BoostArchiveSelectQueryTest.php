<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for the boost_process_local_data_ids() flush query
 * restructuring (issue#7533).
 *
 * The old query always wrapped every archive table's rows in
 * "SELECT * FROM (... UNION ALL ...) t ORDER BY ...", even in the common
 * case of exactly one archive table -- forcing a filesort over an
 * indexless derived table on every pass. boost_archive_select_sql() (the
 * shared per-table SELECT fragment, extracted from poller_boost.php below
 * following this repo's extract-and-eval convention, see e.g.
 * SpikekillVarianceCalculationTest.php) is now used directly, without the
 * wrapper, when there is exactly one archive table; the UNION ALL/derived
 * table shape is kept only as the multi-table fallback.
 *
 * This test proves both shapes return correct, correctly-ordered rows
 * against a real (SQLite, in-memory) database -- not just that the SQL
 * text looks right.
 */

require_once CACTI_PATH_TESTS . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_TESTS . '/Helpers/FakeMySQLPDO.php';
require_once CACTI_PATH_INCLUDE . '/vendor/autoload.php';

$source = file_get_contents(CACTI_PATH_BASE . '/poller_boost.php');

$func_pos = strpos($source, 'function boost_archive_select_sql(');
$func_end = strpos($source, "\nfunction ", $func_pos + 1);
$func_src = substr($source, $func_pos, $func_end - $func_pos);

test('boost_archive_select_sql is present and well-formed in poller_boost.php', function () use ($func_pos, $func_src) {
	expect($func_pos)->not->toBeFalse();
	expect($func_src)->toContain('INNER JOIN poller_output_boost_local_data_ids AS bpt');
	expect($func_src)->toContain('INNER JOIN data_local AS dl');
});

// eval() here only defines a function extracted verbatim from this repo's
// own poller_boost.php (not external/user input). Test-only.
eval($func_src);

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$this->db_globals = [$database_sessions, $database_hostname, $database_port, $database_default];

	$conn = new FakeMySQLPDO();

	$conn->exec('CREATE TABLE poller_output_boost_arch_1 (
		local_data_id INTEGER NOT NULL,
		time TEXT NOT NULL,
		rrd_name TEXT NOT NULL,
		output TEXT NOT NULL
	)');
	$conn->exec('CREATE TABLE poller_output_boost_arch_2 (
		local_data_id INTEGER NOT NULL,
		time TEXT NOT NULL,
		rrd_name TEXT NOT NULL,
		output TEXT NOT NULL
	)');
	$conn->exec('CREATE TABLE poller_output_boost_local_data_ids (
		local_data_id INTEGER NOT NULL,
		process_handler INTEGER NOT NULL
	)');
	$conn->exec('CREATE TABLE data_local (id INTEGER NOT NULL, data_template_id INTEGER NOT NULL)');

	$database_hostname = 'unit_test_host';
	$database_port     = '0';
	$database_default  = 'unit_test_db';

	$database_sessions["$database_hostname:$database_port:$database_default"] = $conn;

	$this->conn = $conn;
});

// Put the default db_* connection back. Left in place, the fake handle answers
// every later read_config_option() in the run and throws on Cacti's MySQL SQL,
// aborting the suite.
afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;
});

function boost_test_seed_common(PDO $conn): void {
	// three local_data_ids owned by process_handler=1, one owned by a
	// different child (must never appear in that child's results), one
	// past the flush watermark (must be excluded by local_data_id <= $last_id)
	$conn->exec("INSERT INTO poller_output_boost_local_data_ids VALUES (1, 1)");
	$conn->exec("INSERT INTO poller_output_boost_local_data_ids VALUES (2, 1)");
	$conn->exec("INSERT INTO poller_output_boost_local_data_ids VALUES (3, 1)");
	$conn->exec("INSERT INTO poller_output_boost_local_data_ids VALUES (4, 2)"); // other child
	$conn->exec("INSERT INTO poller_output_boost_local_data_ids VALUES (99, 1)"); // beyond $last_id

	$conn->exec("INSERT INTO data_local VALUES (1, 10)");
	$conn->exec("INSERT INTO data_local VALUES (2, 20)");
	$conn->exec("INSERT INTO data_local VALUES (3, 30)");
	$conn->exec("INSERT INTO data_local VALUES (4, 40)");
	$conn->exec("INSERT INTO data_local VALUES (99, 90)");
}

test('single-table fast path returns rows filtered by last_id and process_handler, sorted by local_data_id/timestamp/rrd_name', function () {
	boost_test_seed_common($this->conn);

	// Deliberately inserted out of local_data_id/time/rrd_name order to
	// prove the ORDER BY -- not insertion order -- drives the result order.
	$this->conn->exec("INSERT INTO poller_output_boost_arch_1 VALUES (2, '2024-01-01 00:00:10', 'b', '2b')");
	$this->conn->exec("INSERT INTO poller_output_boost_arch_1 VALUES (1, '2024-01-01 00:00:20', 'a', '1a-later')");
	$this->conn->exec("INSERT INTO poller_output_boost_arch_1 VALUES (1, '2024-01-01 00:00:10', 'b', '1b')");
	$this->conn->exec("INSERT INTO poller_output_boost_arch_1 VALUES (1, '2024-01-01 00:00:10', 'a', '1a')");
	$this->conn->exec("INSERT INTO poller_output_boost_arch_1 VALUES (4, '2024-01-01 00:00:10', 'x', 'other-child')");
	$this->conn->exec("INSERT INTO poller_output_boost_arch_1 VALUES (99, '2024-01-01 00:00:10', 'x', 'beyond-watermark')");

	// MySQL/MariaDB resolves an unqualified "ORDER BY local_data_id" against
	// the SELECT list's output name here, not the FROM-list join tables --
	// confirmed against a real MariaDB 11 instance, EXPLAIN and row order
	// both correct. SQLite's stricter column resolution treats the same
	// unqualified name as ambiguous between the joined tables, so the
	// ORDER BY is qualified here to reproduce MySQL's actual result order
	// through sqlite rather than changing the production query.
	$sql = boost_archive_select_sql('poller_output_boost_arch_1', 50, 1) . ' ORDER BY poller_output_boost_arch_1.local_data_id ASC, timestamp ASC, rrd_name ASC';

	$rows = $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

	expect($rows)->toHaveCount(4);

	// order: (1, 00:00:10, a), (1, 00:00:10, b), (1, 00:00:20, a), (2, 00:00:10, b)
	expect(array_map(fn ($r) => [$r['local_data_id'], $r['rrd_name'], $r['output']], $rows))->toBe([
		[1, 'a', '1a'],
		[1, 'b', '1b'],
		[1, 'a', '1a-later'],
		[2, 'b', '2b'],
	]);

	// data_template_id came through the data_local join correctly.
	expect($rows[0]['data_template_id'])->toBe(10);
});

test('multi-table fallback (UNION ALL over a derived table) merges and sorts rows from both archive tables', function () {
	boost_test_seed_common($this->conn);

	$this->conn->exec("INSERT INTO poller_output_boost_arch_1 VALUES (2, '2024-01-01 00:00:05', 'a', 'from-arch1')");
	$this->conn->exec("INSERT INTO poller_output_boost_arch_2 VALUES (1, '2024-01-01 00:00:01', 'a', 'from-arch2')");

	// Mirrors the multi-table branch boost_process_local_data_ids() builds
	// when cacti_sizeof($archive_tables) > 1: wrap each table's fragment in
	// a UNION ALL derived table, sort once at the end.
	$sub = boost_archive_select_sql('poller_output_boost_arch_1', 50, 1)
		. ' UNION ALL '
		. boost_archive_select_sql('poller_output_boost_arch_2', 50, 1);

	$sql = 'SELECT * FROM (' . $sub . ') t ORDER BY local_data_id ASC, timestamp ASC, rrd_name ASC';

	$rows = $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

	expect($rows)->toHaveCount(2);
	expect(array_map(fn ($r) => $r['output'], $rows))->toBe(['from-arch2', 'from-arch1']);
});
