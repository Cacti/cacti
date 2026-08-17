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

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/aggregate.php';

if (!function_exists('gnrv')) {
	function gnrv(string $name, mixed $default = '') : mixed {
		return $_REQUEST[$name] ?? $default;
	}
}

/*
 * #7338: tests/Unit/Ui/Aggregate/AggregateFormFieldNamesTest.php only regex-matches
 * aggregate_graphs.php's source text; it never runs api_aggregate_convert_template()
 * and so never exercises the actual regression: "without a valid template the
 * saves below would write null template fields" -- i.e. before the fix, a
 * missing/bad aggregate_template_id fell through into sql_save() and
 * REPLACE INTO aggregate_graphs_items with null template columns instead of
 * stopping.
 *
 * This integration test runs the real function from lib/aggregate.php against
 * a real (sqlite-backed) database, wiring FakeMySQLPDO in as the global default
 * connection so the unmodified production code path (global $db_conn = false)
 * is exercised end to end, not a $db_conn passed in as a test seam.
 */
function issue7338_wire_default_connection(PDO $conn): void {
	global $database_hostname, $database_port, $database_default, $database_sessions;

	$database_hostname = 'issue7338-host';
	$database_port     = 'issue7338-port';
	$database_default  = 'issue7338-db';
	$database_sessions["$database_hostname:$database_port:$database_default"] = $conn;
}

/*
 * issue7338_wire_default_connection() overwrites the process-global
 * $database_hostname/$database_port/$database_default (read by every db_*
 * helper in lib/database.php) and leaves a fake connection sitting in
 * $database_sessions. Left in place, later test files in the same Pest
 * process resolve db_* calls against this stale sqlite handle instead of
 * their own fixture -- snapshot the globals here and put them back exactly
 * as found.
 */
beforeEach(function () {
	global $database_hostname, $database_port, $database_default, $database_sessions;

	$this->issue7338_prior_globals = [
		'database_hostname' => $database_hostname ?? null,
		'database_port'     => $database_port ?? null,
		'database_default'  => $database_default ?? null,
		'database_sessions' => $database_sessions ?? [],
	];
});

afterEach(function () {
	global $database_hostname, $database_port, $database_default, $database_sessions;

	$prior             = $this->issue7338_prior_globals;
	$database_hostname = $prior['database_hostname'];
	$database_port     = $prior['database_port'];
	$database_default  = $prior['database_default'];
	$database_sessions = $prior['database_sessions'];
});

function issue7338_seed(PDO $conn): void {
	$conn->exec('DROP TABLE IF EXISTS aggregate_graph_templates');
	$conn->exec('DROP TABLE IF EXISTS aggregate_graphs');
	$conn->exec('DROP TABLE IF EXISTS aggregate_graphs_items');
	$conn->exec('CREATE TABLE aggregate_graph_templates (id INTEGER PRIMARY KEY, graph_template_id INTEGER, gprint_prefix TEXT, graph_type TEXT, total INTEGER, total_type INTEGER, total_prefix TEXT, order_type INTEGER)');
	$conn->exec('CREATE TABLE aggregate_graphs (id INTEGER PRIMARY KEY, aggregate_template_id INTEGER, local_graph_id INTEGER, title_format TEXT)');
	$conn->exec('CREATE TABLE aggregate_graphs_items (aggregate_graph_id INTEGER, local_graph_id INTEGER, sequence INTEGER)');
}

test('a missing aggregate template stops the migration before any row is written', function () {
	$conn = new FakeMySQLPDO();
	issue7338_seed($conn);
	issue7338_wire_default_connection($conn);

	// aggregate_graph_templates is empty: id 9999 cannot resolve.
	$_REQUEST['aggregate_template_id'] = '9999';

	api_aggregate_convert_template([42, 43]);

	$graphs_count = (int) $conn->query('SELECT COUNT(*) AS c FROM aggregate_graphs')->fetch(PDO::FETCH_ASSOC)['c'];
	$items_count  = (int) $conn->query('SELECT COUNT(*) AS c FROM aggregate_graphs_items')->fetch(PDO::FETCH_ASSOC)['c'];

	expect($graphs_count)->toBe(0);
	expect($items_count)->toBe(0);

	unset($_REQUEST['aggregate_template_id']);
});

test('an existing aggregate template id that does not match any row also stops the migration', function () {
	$conn = new FakeMySQLPDO();
	issue7338_seed($conn);
	issue7338_wire_default_connection($conn);

	$conn->exec("INSERT INTO aggregate_graph_templates VALUES (5, 1, 'in_out_', 'area', 1, 1, 'Total ', 1)");

	// A real template exists, but the requested id (6) is not it.
	$_REQUEST['aggregate_template_id'] = '6';

	api_aggregate_convert_template([42]);

	$graphs_count = (int) $conn->query('SELECT COUNT(*) AS c FROM aggregate_graphs')->fetch(PDO::FETCH_ASSOC)['c'];

	expect($graphs_count)->toBe(0);

	unset($_REQUEST['aggregate_template_id']);
});
