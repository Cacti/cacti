<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for poller_prefetch_rrd_field_names() (issue#7534).
 *
 * poller_boost.php's boost_process_local_data_ids() and lib/poller.php's
 * process_poller_output() used to re-run the unused_data_source_names and
 * nt_rrd_field_names lookups once per local_data_id boundary (or, in
 * process_poller_output(), once per matching row). Both now call this one
 * shared, batched helper instead.
 *
 * Runs against a real (SQLite, in-memory) PDO connection wired in as the
 * default db_* connection via $database_sessions, so db_fetch_assoc() and
 * friends exercise the actual production code path -- not a re-implementation
 * or a mock -- and $database_total_queries (lib/database.php's own counter,
 * incremented once per db_execute_prepared() call) gives a real query count.
 */

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/poller.php';

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default, $database_total_queries;

	$this->db_globals = [$database_sessions, $database_hostname, $database_port, $database_default];

	$conn = new PDO('sqlite::memory:');

	$conn->exec('CREATE TABLE data_template_rrd (
		id INTEGER PRIMARY KEY,
		local_data_id INTEGER NOT NULL,
		data_source_name TEXT NOT NULL,
		data_input_field_id INTEGER NOT NULL
	)');
	$conn->exec('CREATE TABLE graph_templates_item (task_item_id INTEGER)');
	$conn->exec('CREATE TABLE data_input_fields (id INTEGER PRIMARY KEY, data_name TEXT NOT NULL)');

	// local_data_id 1 (templated): two data sources, 'in' is graphed (used),
	// 'out' is not (unused).
	$conn->exec("INSERT INTO data_template_rrd (id, local_data_id, data_source_name, data_input_field_id) VALUES (1, 1, 'in', 10)");
	$conn->exec("INSERT INTO data_template_rrd (id, local_data_id, data_source_name, data_input_field_id) VALUES (2, 1, 'out', 11)");
	$conn->exec('INSERT INTO graph_templates_item (task_item_id) VALUES (1)');
	$conn->exec("INSERT INTO data_input_fields (id, data_name) VALUES (10, 'ds_in')");
	$conn->exec("INSERT INTO data_input_fields (id, data_name) VALUES (11, 'ds_out')");

	// local_data_id 2 (non-templated): a single, un-graphed data source.
	$conn->exec("INSERT INTO data_template_rrd (id, local_data_id, data_source_name, data_input_field_id) VALUES (3, 2, 'temp', 12)");
	$conn->exec("INSERT INTO data_input_fields (id, data_name) VALUES (12, 'ds_temp')");

	$database_hostname = 'unit_test_host';
	$database_port     = '0';
	$database_default  = 'unit_test_db';

	$database_sessions["$database_hostname:$database_port:$database_default"] = $conn;
	$database_total_queries = 0;

	$this->conn = $conn;
});

// Put the default db_* connection back. Left in place, the sqlite handle
// answers every later read_config_option() in the run and throws on Cacti's
// MySQL SQL, aborting the suite.
afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;
});

test('unused_data_source_names excludes the graphed data source and includes the un-graphed one', function () {
	$result = poller_prefetch_rrd_field_names([1, 2], [1 => 5, 2 => 0]);

	// 'in' (id 1) is referenced by graph_templates_item, so it must not
	// appear as unused; 'out' (id 2) is not referenced, so it must.
	expect($result['unused'][1] ?? [])->toBe(['out' => 'out']);
	expect($result['unused'][2] ?? [])->toBe(['temp' => 'temp']);
});

test('nt_rrd_field_names picks the templated (graph_templates_item-joined) query for a templated local_data_id', function () {
	$result = poller_prefetch_rrd_field_names([1, 2], [1 => 5, 2 => 0]);

	// local_data_id 1 has data_template_id=5 (templated): only the data
	// source reachable through graph_templates_item ('in') qualifies.
	expect($result['nt'][1] ?? [])->toBe(['ds_in' => 'in']);
});

test('nt_rrd_field_names picks the non-templated query for a non-templated local_data_id', function () {
	$result = poller_prefetch_rrd_field_names([1, 2], [1 => 5, 2 => 0]);

	// local_data_id 2 has data_template_id=0 (non-templated): every
	// data_template_rrd row for it qualifies, graphed or not.
	expect($result['nt'][2] ?? [])->toBe(['ds_temp' => 'temp']);
});

test('batches all local_data_ids into a fixed, small number of queries regardless of id count', function () {
	global $database_total_queries;

	// 5 ids (3 templated, 2 non-templated) split across the two nt_rrd_field_names
	// query shapes. The old per-boundary code issued one query per id for
	// unused_data_source_names and one per id for nt_rrd_field_names --
	// up to 10 queries here. The batched helper must issue exactly 3:
	// one IN()-batched query per metadata type (unused, nt-templated, nt-non-templated).
	$database_total_queries = 0;

	poller_prefetch_rrd_field_names(
		[1, 2, 3, 4, 5],
		[1 => 5, 2 => 0, 3 => 5, 4 => 0, 5 => 5]
	);

	expect($database_total_queries)->toBe(3);
});

test('missing local_data_id yields empty arrays, not a fatal error, via the ?? [] fallback contract', function () {
	$result = poller_prefetch_rrd_field_names([999], [999 => 0]);

	expect($result['unused'][999] ?? [])->toBe([]);
	expect($result['nt'][999] ?? [])->toBe([]);
});

test('empty local_data_id list short-circuits without issuing any query', function () {
	global $database_total_queries;

	$database_total_queries = 0;

	$result = poller_prefetch_rrd_field_names([], []);

	expect($database_total_queries)->toBe(0);
	expect($result)->toBe(['unused' => [], 'nt' => []]);
});

test('chunks the IN() clause at 1000 ids, matching the array_chunk(..., 1000) convention', function () {
	global $database_total_queries;

	// 2500 synthetic, non-existent ids -> ceil(2500/1000) = 3 chunks for the
	// unused query and 3 for the non-templated nt query (none are marked
	// templated, so the templated nt query never runs). 6 queries total,
	// not 2500 and not 1 (which would silently truncate on some drivers'
	// max parameter/placeholder limits).
	$ids = range(100000, 102499);
	$data_template_id_by_id = array_fill_keys($ids, 0);

	$database_total_queries = 0;

	$result = poller_prefetch_rrd_field_names($ids, $data_template_id_by_id);

	expect($database_total_queries)->toBe(6);
	expect($result['unused'])->toBe([]);
	expect($result['nt'])->toBe([]);
});
