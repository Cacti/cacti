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
 * Drives automation_aggregate_reconcile() through create, detach and rerun.
 *
 * The ownership lookup is the code under test, so its SQL runs for real
 * against an in-memory SQLite copy of the tables it joins. Only the MySQL
 * advisory lock, row locking and the aggregate builder are replaced: the
 * builder records each graph it creates so a duplicate is observable.
 */

namespace CactiAggregateAutomationDetachProbe;

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/FixtureStubs.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$GLOBALS['database_last_error'] = '';
$GLOBALS['probe_pdo']           = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
$GLOBALS['probe_members']       = [101, 102];
$GLOBALS['probe_builds']        = [];

$GLOBALS['probe_pdo']->exec('CREATE TABLE graph_local (id INTEGER PRIMARY KEY, host_id INTEGER, graph_template_id INTEGER)');
$GLOBALS['probe_pdo']->exec('CREATE TABLE aggregate_graphs (id INTEGER PRIMARY KEY AUTOINCREMENT, local_graph_id INTEGER, aggregate_template_id INTEGER)');
$GLOBALS['probe_pdo']->exec('CREATE TABLE aggregate_graphs_items (aggregate_graph_id INTEGER, local_graph_id INTEGER)');
$GLOBALS['probe_pdo']->exec('CREATE TABLE automation_aggregate_graphs (aggregate_template_id INTEGER, host_id INTEGER, local_graph_id INTEGER UNIQUE,
	configuration_hash TEXT, PRIMARY KEY (aggregate_template_id, host_id))');

/**
 * Runs a read against SQLite, dropping the MySQL-only row lock clause.
 *
 * @param string $sql    The query.
 * @param array  $params The bound parameters.
 *
 * @return array The fetched rows.
 */
function db_fetch_assoc_prepared(string $sql, array $params = []) : array {
	$statement = $GLOBALS['probe_pdo']->prepare(str_replace(' FOR UPDATE', '', $sql));
	$statement->execute($params);

	return $statement->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * Answers the advisory lock calls, which SQLite has no equivalent for.
 *
 * @param string $sql    The query.
 * @param array  $params The bound parameters.
 *
 * @return int Always 1, meaning the lock was granted or released.
 */
function db_fetch_cell_prepared(string $sql, array $params = []) : int {
	return 1;
}

/**
 * Runs a write against SQLite.
 *
 * @param string $sql    The statement.
 * @param array  $params The bound parameters.
 *
 * @return bool True when the statement ran.
 */
function db_execute_prepared(string $sql, array $params = []) : bool {
	return $GLOBALS['probe_pdo']->prepare($sql)->execute($params);
}

/**
 * Starts a SQLite transaction.
 *
 * @return bool True when it started.
 */
function db_begin_transaction() : bool {
	return $GLOBALS['probe_pdo']->beginTransaction();
}

/**
 * Commits the SQLite transaction.
 *
 * @return bool True when it committed.
 */
function db_commit_transaction() : bool {
	return $GLOBALS['probe_pdo']->commit();
}

/**
 * Rolls back the SQLite transaction.
 *
 * @return bool True when it rolled back.
 */
function db_rollback_transaction() : bool {
	return $GLOBALS['probe_pdo']->rollBack();
}

/**
 * Returns the member graphs the rule currently matches.
 *
 * @param array $rule    The aggregate template rule.
 * @param int   $host_id The device.
 *
 * @return array The matching graph ids.
 */
function automation_aggregate_members(array $rule, int $host_id) : array {
	return $GLOBALS['probe_members'];
}

/**
 * Stands in for the aggregate builder, creating a graph when given id 0.
 *
 * @param int   $local_graph_id The aggregate to update, or 0 to create one.
 * @param array $members        The member graph ids.
 * @param array $attributes     The aggregate attributes.
 * @param bool  $transaction    Whether the builder manages its own transaction.
 *
 * @return bool True when the aggregate was written.
 */
function aggregate_create_update(int &$local_graph_id, array $members, array $attributes, bool $transaction) : bool {
	if ($local_graph_id === 0) {
		$local_graph_id = 500 + count($GLOBALS['probe_builds']);

		db_execute_prepared('INSERT INTO graph_local (id, host_id, graph_template_id) VALUES (?, 0, 0)', [$local_graph_id]);
		db_execute_prepared('INSERT INTO aggregate_graphs (local_graph_id, aggregate_template_id) VALUES (?, ?)', [$local_graph_id, $attributes['aggregate_template_id']]);
	}
	$GLOBALS['probe_builds'][] = $local_graph_id;

	return true;
}

foreach (['automation_aggregate_database_error', 'automation_aggregate_rows', 'automation_aggregate_title', 'automation_aggregate_reconcile'] as $function) {
	\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/api_automation_aggregate.php', $function, __NAMESPACE__);
}

$rule       = ['id' => 800001, 'name' => 'QoS', 'automation_title' => '|host_description| QoS'];
$host       = ['id' => 900001, 'description' => 'router', 'hostname' => 'router'];
$attributes = ['aggregate_template_id' => 800001];
$states     = [];

$states[] = automation_aggregate_reconcile($rule, $host, $attributes);
$states[] = automation_aggregate_reconcile($rule, $host, $attributes);

// While still attached, a membership change rebuilds the same graph in place.
$GLOBALS['probe_members'][] = 103;
$states[]                   = automation_aggregate_reconcile($rule, $host, $attributes);

// Unchecking template propagation in aggregate_graphs.php saves the aggregate with no template.
db_execute_prepared('UPDATE aggregate_graphs SET aggregate_template_id = 0 WHERE local_graph_id = 500');

// A new member changes the configuration hash, which is what used to trigger the rebuild.
$GLOBALS['probe_members'][] = 104;
$states[]                   = automation_aggregate_reconcile($rule, $host, $attributes);
$states[]                   = automation_aggregate_reconcile($rule, $host, $attributes);

$result = [
	'states'     => $states,
	'builds'     => $GLOBALS['probe_builds'],
	'aggregates' => (int) db_fetch_assoc_prepared('SELECT COUNT(*) AS total FROM aggregate_graphs')[0]['total'],
	'mapping'    => db_fetch_assoc_prepared('SELECT local_graph_id FROM automation_aggregate_graphs'),
];

// The changed-line coverage job runs this probe directly, so it asserts too.
\cacti_fixture_assert($result['states'] === ['created', 'unchanged', 'updated', 'detached', 'detached'], 'detached aggregate keeps its state');
\cacti_fixture_assert($result['builds'] === [500, 500] && $result['aggregates'] === 1, 'detached aggregate is never rebuilt or duplicated');
\cacti_fixture_assert($result['mapping'] === [['local_graph_id' => 500]], 'ownership stays on the detached aggregate');

print json_encode($result);
