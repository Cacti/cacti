<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Checks that every path removing an automated aggregate, or the device that
 * owns it, also drops its automation ownership row, and that a builder caller
 * without a session can set the aggregate's owner.
 *
 * The aggregate and device removal functions and aggregate_create_update()
 * load unchanged into this namespace with recording stubs for their database
 * and Cacti dependencies, so the probe sees the exact statements they issue.
 */

namespace CactiAggregateAutomationOwnershipProbe;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/FixtureStubs.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

final class SaveStop extends \Exception {
}

$root   = dirname(__DIR__, 3);
$slices = [
	$root . '/lib/aggregate.php'     => ['api_aggregate_remove_multi', 'api_aggregate_convert_to_graph'],
	$root . '/lib/api_aggregate.php' => ['aggregate_create_update'],
	$root . '/lib/api_device.php'    => ['api_device_remove', 'api_device_remove_multi'],
];

foreach ($slices as $file => $functions) {
	foreach ($functions as $function) {
		\cacti_fixture_source_function($file, $function, __NAMESPACE__);
	}
	\cacti_fixture_stub_functions(\cacti_fixture_called_functions($file, $functions), __NAMESPACE__);
}
// set_error_handler() resolves its string callback in the global namespace.
\cacti_fixture_stub_functions(['aggregate_error_handler'], '');

// api_aggregate_remove_multi() includes api_graph.php for its removal helper,
// which the namespace stub replaces; an empty library keeps that include inert.
$library = sys_get_temp_dir() . '/cacti-aggregate-ownership-' . getmypid();
mkdir($library);
touch($library . '/api_graph.php');
define('CACTI_PATH_LIBRARY', $library);

require_once $root . '/include/global_constants.php';
register_shutdown_function(static function () use ($library) : void {
	unlink($library . '/api_graph.php');
	rmdir($library);
});

/**
 * Resets the recorded calls and installs the shared stub answers.
 *
 * @param array $answers Extra answers keyed by stub name.
 *
 * @return void
 */
function answers(array $answers = []) : void {
	$GLOBALS['cacti_fixture_calls']   = [];
	$GLOBALS['cacti_fixture_returns'] = $answers + [
		'cacti_sizeof'         => static fn ($value) => is_countable($value) ? count($value) : 0,
		'array_rekey'          => static fn ($rows) => is_array($rows) ? array_combine(array_column($rows, 'id'), array_column($rows, 'id')) : [],
		'db_in_clause'         => static fn (string $column, array $ids) => $column . ' IN (' . implode(',', $ids) . ')',
		'db_begin_transaction' => true,
		'db_commit_transaction'=> true,
		'db_execute'           => true,
		'db_execute_prepared'  => true,
		'db_fetch_cell'        => 0,
		'db_fetch_assoc'       => [],
	];
}

/**
 * Returns the statements a stub received, with their bound parameters.
 *
 * @param string $name The stub name.
 *
 * @return array[] The [sql, params] pairs.
 */
function statements(string $name) : array {
	return array_map(static fn ($arguments) => [preg_replace('/\s+/', ' ', $arguments[0]), $arguments[1] ?? []], \cacti_fixture_stub_arguments($name));
}

answers(['db_fetch_cell_prepared' => 41]);
api_aggregate_remove_multi([500, 501]);
$removed = array_values(array_filter(statements('db_execute_prepared'), static fn ($statement) => str_contains($statement[0], 'automation_aggregate_graphs')));
\cacti_fixture_assert($removed === [
	['DELETE FROM automation_aggregate_graphs WHERE local_graph_id = ?', [500]],
	['DELETE FROM automation_aggregate_graphs WHERE local_graph_id = ?', [501]],
], 'removing aggregates drops their ownership');
\cacti_fixture_assert(\cacti_fixture_stub_arguments('api_graph_remove_multi') === [[[500, 501]]], 'the aggregate graphs are removed');

answers(['db_fetch_cell_prepared' => 0]);
api_aggregate_convert_to_graph([502]);
\cacti_fixture_assert(in_array(['DELETE FROM automation_aggregate_graphs WHERE local_graph_id = ?', [502]], statements('db_execute_prepared'), true),
	'converting an aggregate to a graph drops its ownership');

answers(['db_fetch_cell_prepared' => 1, 'db_fetch_assoc_prepared' => []]);
api_device_remove(9);
\cacti_fixture_assert(in_array(['DELETE FROM automation_aggregate_graphs WHERE host_id = ?', [9]], statements('db_execute_prepared'), true),
	'removing a device drops its aggregate ownership');

answers();
api_device_remove_multi([9, 10]);
\cacti_fixture_assert(in_array(['DELETE FROM automation_aggregate_graphs WHERE host_id IN (9,10)', []], statements('db_execute'), true),
	'removing several devices drops their aggregate ownership');

// Automation builds aggregates from the poller, which has no session user.
foreach ([[['user_id' => 3], [], 3], [[], [SESS_USER_ID => 5], 5], [[], [], 0]] as [$attributes, $session, $expected]) {
	$_SESSION = $session;
	answers([
		'aggregate_graph_save' => 600,
		'sql_save'             => static function (array $save) : never {
			throw new SaveStop(json_encode($save));
		},
	]);
	$graph = 0;

	try {
		aggregate_create_update($graph, [101], $attributes + ['color_templates' => [], 'graph_item_types' => [], 'cdefs' => [],
			'skipped_items'                                                        => [], 'total_items' => []], false);
		\cacti_fixture_assert(false, 'aggregate_create_update saves the aggregate');
	} catch (SaveStop $stop) {
		$saved = json_decode($stop->getMessage(), true);
	}
	restore_error_handler();
	\cacti_fixture_assert($saved['user_id'] === $expected && $saved['local_graph_id'] === 600, 'aggregate owner is ' . $expected);
}

print json_encode(['result' => 'ok']) . "\n";
