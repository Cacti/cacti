<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Checks that every data query reindex path reconciles the device's automated
 * aggregates right after its graph automation, and that the 1.3.0 upgrade
 * installs the automation columns and ownership table.
 *
 * run_data_query() loads unchanged into one namespace per poller role, each
 * with its own POLLER_ID and CACTI_CONNECTION, so the main poller, an online
 * remote collector and a main poller relaying a remote reindex each run their
 * own branch. upgrade_to_1_3_0() and its helper load the same way.
 */

namespace CactiAggregateAutomationReindexProbe;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/FixtureStubs.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);

require_once $root . '/include/global_constants.php';

// run_data_query() includes three libraries whose functions the namespace
// stubs replace; empty copies keep those includes inert.
$library = sys_get_temp_dir() . '/cacti-aggregate-reindex-' . getmypid();
mkdir($library);

foreach (['poller.php', 'api_data_source.php', 'utility.php'] as $file) {
	touch($library . '/' . $file);
}
define('CACTI_PATH_LIBRARY', $library);
define('CACTI_PATH_URL', '/cacti/');
define('CACTI_VERSION', '1.3.0');
register_shutdown_function(static function () use ($library) : void {
	array_map('unlink', glob($library . '/*.php'));
	rmdir($library);
});

$query  = $root . '/lib/data_query.php';
$called = \cacti_fixture_called_functions($query, ['run_data_query']);
$roles  = [
	'Main'      => [1, 'online', 1],
	'Collector' => [2, 'online', 2],
	'Relay'     => [1, 'online', 2],
];

foreach ($roles as $role => [$poller_id, $connection]) {
	$namespace = __NAMESPACE__ . '\\' . $role;
	define($namespace . '\POLLER_ID', $poller_id);
	define($namespace . '\CACTI_CONNECTION', $connection);
	\cacti_fixture_source_function($query, 'run_data_query', $namespace);
	\cacti_fixture_stub_functions($called, $namespace);
}

/**
 * Runs one reindex in a poller role and returns the automation call order.
 *
 * @param string $role The namespace holding that role's run_data_query().
 *
 * @return array The result, the ordered automation calls and any printed output.
 */
function reindex(string $role) : array {
	global $roles;

	$GLOBALS['cacti_fixture_calls']   = [];
	$GLOBALS['cacti_fixture_returns'] = [
		'db_column_exists'               => true,
		'db_fetch_row_prepared'          => ['status' => HOST_UP, 'poller_id' => $roles[$role][2], 'disabled' => '', 'site_disabled' => ''],
		'db_fetch_cell_prepared'         => static fn (string $sql) => str_contains($sql, 'data_input.type_id') ? DATA_INPUT_TYPE_SNMP_QUERY : 0,
		'db_fetch_assoc_prepared'        => [],
		'cacti_sizeof'                   => static fn ($value) => is_countable($value) ? count($value) : 0,
		'get_data_query_array'           => [],
		'query_snmp_host'                => true,
		'get_best_data_query_index_type' => 'ifIndex',
		'__esc'                          => static fn (string $text) => $text,
		'call_remote_data_collector'     => static function () : string {
			$_SESSION['debug_log']['response'] = true;

			return json_encode(['data_query' => ['relayed']]);
		},
	];
	$_SESSION = [];
	ob_start();
	$result = ('CactiAggregateAutomationReindexProbe\\' . $role . '\run_data_query')(9, 4);
	$output = ob_get_clean();
	$order  = [];

	foreach ($GLOBALS['cacti_fixture_calls'] as [$name, $arguments]) {
		$short = substr($name, (int) strrpos($name, '\\') + 1);

		if (in_array($short, ['automation_execute_data_query', 'automation_reconcile_device_aggregates', 'api_plugin_hook_function'], true)) {
			$order[] = [$short, $arguments[0] === 'run_data_query' ? $arguments[1]['host_id'] : $arguments[0]];
		}
	}

	return ['result' => $result, 'order' => $order, 'output' => $output];
}

$expected = [['automation_execute_data_query', 9], ['automation_reconcile_device_aggregates', 9], ['api_plugin_hook_function', 9]];

foreach ($roles as $role => $settings) {
	$reindex = reindex($role);
	\cacti_fixture_assert($reindex['result'] === true, $role . ' reindex succeeds');
	\cacti_fixture_assert($reindex['order'] === $expected, $role . ' reindex reconciles aggregates after graph automation');
}

// The 1.3.0 upgrade adds the automation columns, disabled, and the ownership table.
$upgrade = $root . '/install/upgrades/1_3_0.php';
\cacti_fixture_source_function($upgrade, 'upgrade_to_1_3_0', __NAMESPACE__);
\cacti_fixture_source_function($upgrade, 'upgrade_aggregate_automation_schema', __NAMESPACE__);
\cacti_fixture_stub_functions(\cacti_fixture_called_functions($upgrade, ['upgrade_to_1_3_0', 'upgrade_aggregate_automation_schema']), __NAMESPACE__);
$GLOBALS['cacti_fixture_calls']   = [];
$GLOBALS['cacti_fixture_returns'] = ['cacti_sizeof' => static fn ($value) => is_countable($value) ? count($value) : 0];
upgrade_to_1_3_0();
$columns = array_filter(\cacti_fixture_stub_arguments('db_install_add_column'), static fn ($call) => $call[0] === 'aggregate_graph_templates');
$columns = array_column(array_column($columns, 1), null, 'name');
\cacti_fixture_assert(array_keys($columns) === ['automation_enabled', 'automation_pattern', 'automation_title', 'automation_host_template_id', 'automation_max_members'],
	'the upgrade adds every automation column');
\cacti_fixture_assert($columns['automation_enabled']['default'] === '' && $columns['automation_enabled']['NULL'] === false, 'existing templates stay disabled');
$tables = implode("\n", array_column(\cacti_fixture_stub_arguments('db_install_execute'), 0));
\cacti_fixture_assert(str_contains($tables, 'CREATE TABLE IF NOT EXISTS automation_aggregate_graphs') && str_contains($tables, 'UNIQUE KEY local_graph_id (local_graph_id)'),
	'the upgrade creates the ownership table');

print json_encode(['result' => 'ok']) . "\n";
