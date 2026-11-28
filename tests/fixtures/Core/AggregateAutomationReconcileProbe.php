<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Drives automation_aggregate_reconcile() through every exit: a busy lock, no
 * members, create and update, and each database or builder failure.
 *
 * The function is loaded unchanged into this namespace. The database, the
 * member query and the aggregate builder are stubs whose answers each scenario
 * sets, so a failure can be injected at every step and the probe can check
 * that the transaction is rolled back and the advisory lock released.
 */

namespace CactiAggregateAutomationReconcileProbe;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/FixtureStubs.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

/**
 * Stands in for the aggregate builder, assigning a graph id to a new aggregate.
 *
 * @param int   $local_graph_id The aggregate to update, or 0 to create one.
 * @param array $members        The member graph ids.
 * @param array $attributes     The aggregate attributes.
 * @param bool  $transaction    Whether the builder manages its own transaction.
 *
 * @return bool The scenario's builder result.
 */
function aggregate_create_update(int &$local_graph_id, array $members, array $attributes, bool $transaction) : bool {
	$GLOBALS['cacti_fixture_calls'][] = ['aggregate_create_update', [$local_graph_id, $members, $attributes, $transaction]];

	if ($local_graph_id === 0) {
		$local_graph_id = 600;
	}
	$GLOBALS['database_last_error'] = $GLOBALS['probe_builder_error'];

	return $GLOBALS['probe_builder_result'];
}

/**
 * Refuses one PCRE limit, as a locked-down ini policy would.
 *
 * @param string $option The ini option.
 * @param string $value  The new value.
 *
 * @return string|false The previous value, or false for the refused option.
 */
function ini_set(string $option, string $value) : string|false {
	$GLOBALS['probe_ini_set'][] = [$option, $value];

	return $option === $GLOBALS['probe_ini_refused'] ? false : \ini_set($option, $value);
}

$source = dirname(__DIR__, 3) . '/lib/api_automation_aggregate.php';

foreach (['automation_aggregate_match', 'automation_aggregate_database_error', 'automation_aggregate_rows', 'automation_aggregate_title', 'automation_aggregate_reconcile'] as $function) {
	\cacti_fixture_source_function($source, $function, __NAMESPACE__);
}
\cacti_fixture_stub_functions(\cacti_fixture_called_functions($source, ['automation_aggregate_rows', 'automation_aggregate_reconcile']), __NAMESPACE__);
\cacti_fixture_alias_classes(['RuntimeException', 'Throwable'], __NAMESPACE__);

/**
 * Runs one reconcile pass under the given stub answers.
 *
 * @param array $answers Overrides: lock, members, mapping, begin, commit, delete, replace, builder, builder_error.
 *
 * @return array The state or exception message, plus the recorded calls.
 */
function reconcile(array $answers) : array {
	$answers += [
		'lock'   => 1, 'members' => [101, 102], 'mapping' => [], 'begin' => true, 'commit' => true,
		'delete' => true, 'replace' => true, 'builder' => true, 'builder_error' => '',
	];
	$GLOBALS['database_last_error']   = 'earlier';
	$GLOBALS['probe_builder_result']  = $answers['builder'];
	$GLOBALS['probe_builder_error']   = $answers['builder_error'];
	$GLOBALS['cacti_fixture_calls']   = [];
	$GLOBALS['cacti_fixture_returns'] = [
		__NAMESPACE__ . '\db_fetch_cell_prepared'       => static fn (string $sql) => str_contains($sql, 'GET_LOCK') ? $answers['lock'] : 1,
		__NAMESPACE__ . '\automation_aggregate_members' => $answers['members'],
		__NAMESPACE__ . '\db_fetch_assoc_prepared'      => $answers['mapping'],
		__NAMESPACE__ . '\db_begin_transaction'         => $answers['begin'],
		__NAMESPACE__ . '\db_commit_transaction'        => $answers['commit'],
		__NAMESPACE__ . '\db_rollback_transaction'      => true,
		__NAMESPACE__ . '\db_execute_prepared'          => static fn (string $sql) => str_starts_with($sql, 'DELETE') ? $answers['delete'] : $answers['replace'],
	];

	try {
		$state = automation_aggregate_reconcile(
			['id' => 7, 'name' => 'QoS', 'automation_title' => '|host_description| QoS'],
			['id' => 9, 'description' => 'router', 'hostname' => 'router'],
			['aggregate_template_id' => 7]
		);
	} catch (\Throwable $error) {
		$state = $error->getMessage();
	}
	$calls = [];

	foreach ($GLOBALS['cacti_fixture_calls'] as [$name, $arguments]) {
		$short           = substr($name, (int) strrpos('\\' . $name, '\\'));
		$calls[$short][] = $arguments;
	}

	return ['state' => $state, 'calls' => $calls, 'error' => $GLOBALS['database_last_error']];
}

/**
 * Asserts the lock was released and whether the transaction was rolled back.
 *
 * @param array  $result   A reconcile() result.
 * @param bool   $rollback Whether a rollback is expected.
 * @param string $scenario The scenario name.
 *
 * @return void
 */
function expect_cleanup(array $result, bool $rollback, string $scenario) : void {
	$released = array_filter($result['calls']['db_fetch_cell_prepared'] ?? [], static fn ($call) => str_contains($call[0], 'RELEASE_LOCK'));
	\cacti_fixture_assert(count($released) === 1 && $released[array_key_last($released)][1] === ['aggregate_automation_7_9'], $scenario . ' releases the lock');
	\cacti_fixture_assert(isset($result['calls']['db_rollback_transaction']) === $rollback, $scenario . ($rollback ? ' rolls back' : ' does not roll back'));
}

// A PCRE limit that cannot be lowered fails closed and still restores both limits.
$limits = [ini_get('pcre.backtrack_limit'), ini_get('pcre.recursion_limit')];

foreach (['pcre.backtrack_limit', 'pcre.recursion_limit'] as $refused) {
	$GLOBALS['probe_ini_refused'] = $refused;
	$GLOBALS['probe_ini_set']     = [];
	\cacti_fixture_assert(automation_aggregate_match('/QoS/', 'QoS') === false, 'a refused ' . $refused . ' fails closed');
	\cacti_fixture_assert(array_slice($GLOBALS['probe_ini_set'], -2) === [['pcre.backtrack_limit', $limits[0]], ['pcre.recursion_limit', $limits[1]]], 'limits are restored after ' . $refused);
}
$GLOBALS['probe_ini_refused'] = '';
\cacti_fixture_assert(automation_aggregate_match('/QoS/', 'QoS') === 1, 'accepted limits match');

$busy = reconcile(['lock' => 0]);
\cacti_fixture_assert($busy['state'] === 'busy' && !isset($busy['calls']['automation_aggregate_members']), 'a held lock skips the pass');

$empty = reconcile(['members' => []]);
\cacti_fixture_assert($empty['state'] === 'empty' && !isset($empty['calls']['db_begin_transaction']), 'no members creates nothing');
expect_cleanup($empty, false, 'empty');

$created = reconcile([]);
\cacti_fixture_assert($created['state'] === 'created' && $created['error'] === 'earlier', 'a new aggregate is created and the earlier error kept');
$replace = $created['calls']['db_execute_prepared'][0][1];
\cacti_fixture_assert($replace[0] === 7 && $replace[1] === 9 && $replace[2] === 600 && strlen($replace[3]) === 64, 'ownership is recorded for the new aggregate');
\cacti_fixture_assert($created['calls']['aggregate_create_update'][0][2]['graph_title'] === 'router QoS', 'the aggregate title is expanded');
expect_cleanup($created, false, 'created');
$owned = ['local_graph_id' => 600, 'configuration_hash' => $replace[3], 'aggregate_id' => 30, 'aggregate_template_id' => 7];
$stale = ['configuration_hash' => str_repeat('0', 64)] + $owned;

$updated = reconcile(['mapping' => [$stale]]);
\cacti_fixture_assert($updated['state'] === 'updated' && $updated['calls']['db_execute_prepared'][0][1] === [30], 'a changed rule replaces the members in place');
\cacti_fixture_assert($updated['calls']['aggregate_create_update'][0][0] === 600, 'the owned aggregate is rebuilt, not duplicated');

foreach ([
	'begin'          => [['begin' => false], 'Unable to start aggregate automation transaction.', false],
	'detached'       => [['mapping' => [['aggregate_template_id' => 0] + $owned], 'commit' => false], 'Unable to commit aggregate automation transaction.', true],
	'unchanged'      => [['mapping' => [$owned], 'commit' => false], 'Unable to commit aggregate automation transaction.', true],
	'delete'         => [['mapping' => [$stale], 'delete' => false], 'Unable to replace automated aggregate members.', true],
	'builder'        => [['builder' => false], 'Unable to rebuild automated aggregate.', true],
	'builder error'  => [['builder_error' => 'Deadlock found'], 'Unable to rebuild automated aggregate.', true],
	'replace'        => [['replace' => false], 'Unable to persist aggregate automation ownership.', true],
	'commit'         => [['commit' => false], 'Unable to persist aggregate automation ownership.', true],
] as $scenario => [$answers, $message, $rollback]) {
	$failed = reconcile($answers);
	\cacti_fixture_assert($failed['state'] === $message, $scenario . ' failure is reported: ' . $failed['state']);
	expect_cleanup($failed, $rollback, $scenario);
}

$unchanged = reconcile(['mapping' => [$owned]]);
\cacti_fixture_assert($unchanged['state'] === 'unchanged' && !isset($unchanged['calls']['aggregate_create_update']), 'an unchanged rule is not rebuilt');
$detached = reconcile(['mapping' => [['aggregate_template_id' => 0] + $owned]]);
\cacti_fixture_assert($detached['state'] === 'detached' && !isset($detached['calls']['aggregate_create_update']), 'a detached aggregate is left alone');

print json_encode(['result' => 'ok']) . "\n";
