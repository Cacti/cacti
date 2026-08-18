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

require_once CACTI_PATH_TESTS . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_TESTS . '/Helpers/FakeMySQLPDO.php';
require_once CACTI_PATH_LIBRARY . '/database.php';

if (!function_exists('array_rekey')) {
	function array_rekey(mixed $array, string $key, mixed $key_value) : array {
		$ret_array = [];

		if (is_array($array)) {
			foreach ($array as $item) {
				$item_key = $item[$key];

				if (is_array($key_value)) {
					foreach ($key_value as $value) {
						$ret_array[$item_key][$value] = $item[$value];
					}
				} else {
					$ret_array[$item_key] = $item[$key_value];
				}
			}
		}

		return $ret_array;
	}
}

/*
 * #7261: tests/Unit/Security/SqlInjection/InClausePlaceholderTest.php proves the placeholder-count
 * arithmetic (str_repeat('?, ', N) + array_values()) is internally consistent,
 * but it never runs a prepared statement -- it can't catch a bug where the
 * placeholder count is right yet the bound values are wrong (e.g. reusing the
 * un-rekeyed array, or mixing up which id list feeds which query), which is
 * exactly the class of mistake parameterisation is meant to guard against.
 *
 * purge_old_graphs() in aggregate_graphs.php is a page-script function (the
 * file has top-level auth/session bootstrap so it can't be require()'d), so
 * this test extracts its source with the same eval-based technique used by
 * tests/Unit/Ui/Graph/GraphCreateWiringTest.php and runs the *actual*
 * production function -- parameterised IN clauses, prepared statements and
 * all -- against a real (sqlite-backed) database, across five joined tables.
 */
$src = file_get_contents(CACTI_PATH_BASE . '/aggregate_graphs.php');
if (preg_match('/^function purge_old_graphs\(\) : void \{.*?^\}/sm', $src, $m) !== 1) {
	throw new RuntimeException('could not locate purge_old_graphs() in aggregate_graphs.php');
}
// nosemgrep: eval-use -- test-only: defines a renamed copy of the function whose
// source was just regex-extracted from this repo's own aggregate_graphs.php
// (not external/user input), matching the existing eval-based test technique
// in tests/Unit/Ui/Graph/GraphCreateWiringTest.php.
eval(preg_replace('/^function purge_old_graphs\(\)/m', 'function issue7261_purge_old_graphs()', $m[0]));

function issue7261_wire_default_connection(PDO $conn): void {
	global $database_hostname, $database_port, $database_default, $database_sessions;

	$database_hostname = 'issue7261-host';
	$database_port     = 'issue7261-port';
	$database_default  = 'issue7261-db';
	$database_sessions["$database_hostname:$database_port:$database_default"] = $conn;
}

/*
 * issue7261_wire_default_connection() overwrites the process-global
 * $database_hostname/$database_port/$database_default (read by every db_*
 * helper in lib/database.php) and leaves a fake connection sitting in
 * $database_sessions. Left in place, later test files in the same Pest
 * process resolve db_* calls against this stale sqlite handle instead of
 * their own fixture, or against a key that no longer maps to anything --
 * snapshot the globals here and put them back exactly as found.
 */
beforeEach(function () {
	global $database_hostname, $database_port, $database_default, $database_sessions;

	$this->issue7261_prior_globals = [
		'database_hostname' => $database_hostname ?? null,
		'database_port'     => $database_port ?? null,
		'database_default'  => $database_default ?? null,
		'database_sessions' => $database_sessions ?? [],
	];
});

afterEach(function () {
	global $database_hostname, $database_port, $database_default, $database_sessions;

	$prior             = $this->issue7261_prior_globals;
	$database_hostname = $prior['database_hostname'];
	$database_port     = $prior['database_port'];
	$database_default  = $prior['database_default'];
	$database_sessions = $prior['database_sessions'];
});

function issue7261_seed(PDO $conn): void {
	$conn->exec('DROP TABLE IF EXISTS aggregate_graphs_items');
	$conn->exec('DROP TABLE IF EXISTS aggregate_graphs');
	$conn->exec('DROP TABLE IF EXISTS graph_local');
	$conn->exec('DROP TABLE IF EXISTS graph_templates_item');
	$conn->exec('DROP TABLE IF EXISTS graph_templates_graph');

	$conn->exec('CREATE TABLE aggregate_graphs_items (aggregate_graph_id INTEGER, local_graph_id INTEGER)');
	$conn->exec('CREATE TABLE aggregate_graphs (id INTEGER PRIMARY KEY, local_graph_id INTEGER)');
	$conn->exec('CREATE TABLE graph_local (id INTEGER PRIMARY KEY)');
	$conn->exec('CREATE TABLE graph_templates_item (id INTEGER PRIMARY KEY, local_graph_id INTEGER)');
	$conn->exec('CREATE TABLE graph_templates_graph (id INTEGER PRIMARY KEY, local_graph_id INTEGER)');
}

test('purge_old_graphs deletes only rows whose local graph is actually gone', function () {
	$conn = new FakeMySQLPDO();
	issue7261_seed($conn);
	issue7261_wire_default_connection($conn);

	// graph_local has 100 (still exists) but not 200/201 (purged elsewhere).
	$conn->exec('INSERT INTO graph_local VALUES (100)');

	// aggregate_graphs_items: one row tied to a live graph (100), two tied to
	// gone graphs (200, 201) that must be purged by the local_graph_id sweep,
	// and one (aggregate_graph_id=2, local_graph_id=100) that only the later
	// aggregate_graph_id sweep catches -- its own local_graph_id is alive, but
	// it belongs to aggregate 2, which is itself orphaned.
	$conn->exec('INSERT INTO aggregate_graphs_items VALUES (1, 100)');
	$conn->exec('INSERT INTO aggregate_graphs_items VALUES (1, 200)');
	$conn->exec('INSERT INTO aggregate_graphs_items VALUES (2, 201)');
	$conn->exec('INSERT INTO aggregate_graphs_items VALUES (2, 100)');

	// aggregate_graphs: aggregate 1 backs a live graph, aggregate 2 and 3 are
	// orphaned (their local_graph_id no longer exists in graph_local).
	$conn->exec('INSERT INTO aggregate_graphs VALUES (1, 100)');
	$conn->exec('INSERT INTO aggregate_graphs VALUES (2, 200)');
	$conn->exec('INSERT INTO aggregate_graphs VALUES (3, 201)');

	// downstream template rows for the orphaned local_graph_ids (200, 201).
	$conn->exec('INSERT INTO graph_templates_item VALUES (10, 100)');
	$conn->exec('INSERT INTO graph_templates_item VALUES (11, 200)');
	$conn->exec('INSERT INTO graph_templates_item VALUES (12, 201)');
	$conn->exec('INSERT INTO graph_templates_graph VALUES (20, 100)');
	$conn->exec('INSERT INTO graph_templates_graph VALUES (21, 200)');
	$conn->exec('INSERT INTO graph_templates_graph VALUES (22, 201)');

	issue7261_purge_old_graphs();

	$agi = $conn->query("SELECT aggregate_graph_id || ':' || local_graph_id AS pair FROM aggregate_graphs_items ORDER BY pair")->fetchAll(PDO::FETCH_COLUMN);
	expect($agi)->toBe(['1:100']);

	$ag = $conn->query('SELECT id FROM aggregate_graphs ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
	expect($ag)->toBe([1]);

	$gti = $conn->query('SELECT id FROM graph_templates_item ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
	expect($gti)->toBe([10]);

	$gtg = $conn->query('SELECT id FROM graph_templates_graph ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
	expect($gtg)->toBe([20]);
});

test('purge_old_graphs is a no-op when nothing is orphaned', function () {
	$conn = new FakeMySQLPDO();
	issue7261_seed($conn);
	issue7261_wire_default_connection($conn);

	$conn->exec('INSERT INTO graph_local VALUES (100)');
	$conn->exec('INSERT INTO aggregate_graphs_items VALUES (1, 100)');
	$conn->exec('INSERT INTO aggregate_graphs VALUES (1, 100)');
	$conn->exec('INSERT INTO graph_templates_item VALUES (10, 100)');
	$conn->exec('INSERT INTO graph_templates_graph VALUES (20, 100)');

	issue7261_purge_old_graphs();

	expect($conn->query('SELECT COUNT(*) AS c FROM aggregate_graphs_items')->fetch(PDO::FETCH_ASSOC)['c'])->toBe(1);
	expect($conn->query('SELECT COUNT(*) AS c FROM aggregate_graphs')->fetch(PDO::FETCH_ASSOC)['c'])->toBe(1);
});

test('purge_old_graphs handles a single orphan (one-slot IN clause)', function () {
	$conn = new FakeMySQLPDO();
	issue7261_seed($conn);
	issue7261_wire_default_connection($conn);

	// No live graphs at all: the only aggregate_graphs_items row is orphaned,
	// exercising the "IN (?)" single-placeholder edge case.
	$conn->exec('INSERT INTO aggregate_graphs_items VALUES (9, 500)');

	issue7261_purge_old_graphs();

	expect($conn->query('SELECT COUNT(*) AS c FROM aggregate_graphs_items')->fetch(PDO::FETCH_ASSOC)['c'])->toBe(0);
});
