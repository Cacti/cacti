<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Drives the aggregate automation rule library and the device automation hook
 * against recorded database answers.
 *
 * lib/api_automation.php and lib/api_automation_aggregate.php load unchanged
 * in the global namespace. Only the database, logging and the other Cacti
 * runtime functions they call are stubbed, so the settings validation, PCRE
 * limits, candidate batching, attribute mapping and per-device dispatch run
 * for real. The reconcile failure paths are driven separately in
 * AggregateAutomationReconcileProbe.php, where the builder can be replaced.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/FixtureStubs.php';

$root = dirname(__DIR__, 3);

require_once $root . '/include/global_constants.php';
require_once $root . '/lib/api_automation.php';
require_once $root . '/lib/api_automation_aggregate.php';
// automation_execute_device_aggregates() loads these itself; preloading them
// keeps that require_once a no-op instead of a second declaration.
require_once $root . '/lib/api_aggregate.php';
require_once $root . '/lib/aggregate.php';

cacti_fixture_stub_functions(array_merge(
	cacti_fixture_called_functions($root . '/lib/api_automation_aggregate.php', [
		'automation_aggregate_rows', 'automation_aggregate_reconcile', 'automation_execute_device_aggregates',
	]),
	cacti_fixture_called_functions($root . '/lib/api_automation.php', [
		'automation_update_device', 'automation_reconcile_device_aggregates', 'automation_execute_device_create_tree',
		'automation_function_with_pid', 'automation_get_pid',
	])
), '');

/**
 * Asserts that a callable throws the given exception class.
 *
 * @param callable $callable The code expected to throw.
 * @param string   $class    The expected exception class.
 * @param string   $message  What was expected.
 *
 * @return void
 */
function aggregate_rule_expect_throw(callable $callable, string $class, string $message) : void {
	try {
		$callable();
	} catch (Throwable $error) {
		cacti_fixture_assert($error instanceof $class, $message . ' (got ' . get_class($error) . ')');

		return;
	}
	cacti_fixture_assert(false, $message . ' (nothing thrown)');
}

/**
 * Answers db_fetch_assoc_prepared() from a queue of results keyed by a SQL fragment.
 *
 * @param array $answers Fragment => list of results; an Exception entry sets the database error.
 *
 * @return void
 */
function aggregate_rule_answers(array $answers) : void {
	$GLOBALS['aggregate_rule_answers']                           = $answers;
	$GLOBALS['cacti_fixture_returns']['db_fetch_assoc_prepared'] = static function (string $sql, array $params = []) : array|false {
		foreach ($GLOBALS['aggregate_rule_answers'] as $fragment => $queue) {
			if (str_contains($sql, $fragment) && $queue) {
				$answer = array_shift($GLOBALS['aggregate_rule_answers'][$fragment]);

				if ($answer instanceof Exception) {
					$GLOBALS['database_last_error'] = $answer->getMessage();

					return [];
				}

				return $answer;
			}
		}

		return [];
	};
}

$GLOBALS['database_last_error']   = '';
$GLOBALS['cacti_fixture_returns'] = [
	'cacti_sizeof' => static fn ($value) => is_countable($value) ? count($value) : 0,
	'array_rekey'  => static fn () => [],
];

// Settings: the defaults, a full valid rule and each rejected shape.
$defaults = automation_aggregate_settings([]);
cacti_fixture_assert($defaults === [
	'automation_enabled'          => '', 'automation_pattern' => '',
	'automation_title'            => '|host_description| - |aggregate_template_name|',
	'automation_host_template_id' => 0, 'automation_max_members' => 1000,
], 'default settings stay disabled');
$valid = automation_aggregate_settings([
	'automation_enabled'          => 'on', 'automation_pattern' => ' /QoS.*(Gold|Silver)/i ',
	'automation_title'            => '|host_description| QoS', 'automation_host_template_id' => '4', 'automation_max_members' => '5000',
]);
cacti_fixture_assert($valid['automation_pattern'] === '/QoS.*(Gold|Silver)/i' && $valid['automation_host_template_id'] === 4 &&
	$valid['automation_max_members']                 === 5000 && $valid['automation_enabled'] === 'on', 'valid settings are normalised');

foreach ([
	'array pattern'      => ['automation_pattern' => ['QoS']],
	'array title'        => ['automation_title' => ['QoS']],
	'negative template'  => ['automation_host_template_id' => -1],
	'injected template'  => ['automation_host_template_id' => '4 OR 1=1'],
	'zero members'       => ['automation_max_members' => 0],
	'too many members'   => ['automation_max_members' => 5001],
	'oversized pattern'  => ['automation_pattern' => '/' . str_repeat('x', 511) . '/'],
	'oversized title'    => ['automation_title' => str_repeat('x', 256)],
	'empty title'        => ['automation_title' => ' '],
	'undelimited regex'  => ['automation_pattern' => 'QoS'],
	'enabled no pattern' => ['automation_enabled' => 'on'],
] as $case => $values) {
	aggregate_rule_expect_throw(static fn () => automation_aggregate_settings($values), InvalidArgumentException::class, 'rejects ' . $case);
}

// Matching restores the PCRE limits and fails closed on a runaway pattern.
$backtrack = ini_get('pcre.backtrack_limit');
cacti_fixture_assert(automation_aggregate_match('/QoS/', 'router QoS') === 1, 'pattern matches');
cacti_fixture_assert(automation_aggregate_match('/QoS/', 'router') === 0, 'pattern misses');
cacti_fixture_assert(automation_aggregate_match('/(a+)+$/', str_repeat('a', 40) . 'b') === false, 'runaway pattern hits the limit');
cacti_fixture_assert(ini_get('pcre.backtrack_limit') === $backtrack, 'backtrack limit is restored');

// Titles expand each placeholder once.
cacti_fixture_assert(automation_aggregate_title(
	['automation_title' => '|host_description| / |host_hostname| / |host_id| / |aggregate_template_name|', 'name' => 'QoS'],
	['description' => 'Router |host_id|', 'hostname' => 'router.example', 'id' => 42]
) === 'Router |host_id| / router.example / 42 / QoS', 'title placeholders expand once');

// A database error turns an empty read into an exception and keeps the earlier error.
$GLOBALS['database_last_error'] = 'earlier';
aggregate_rule_answers(['FROM graph_local' => [[['id' => 1]]]]);
cacti_fixture_assert(automation_aggregate_rows('SELECT gl.id FROM graph_local AS gl') === [['id' => 1]], 'rows are returned');
cacti_fixture_assert($GLOBALS['database_last_error'] === 'earlier', 'earlier database error is restored');
aggregate_rule_answers(['FROM graph_local' => [new Exception('table missing')]]);
aggregate_rule_expect_throw(static fn () => automation_aggregate_rows('SELECT gl.id FROM graph_local AS gl'), RuntimeException::class, 'read failure throws');
$GLOBALS['database_last_error'] = '';

// Members: a full batch continues to the next one, aggregates are skipped and a
// graph with several matching data sources is counted once.
$GLOBALS['cacti_fixture_calls'] = [];
$rule                           = ['id' => 7, 'name' => 'QoS', 'graph_template_id' => 3, 'automation_pattern' => '/QoS/', 'automation_max_members' => 1000];
$batch                          = array_map(static fn ($id) => ['id' => $id], range(1, 200));
aggregate_rule_answers([
	'FROM graph_local'           => [$batch, [], [['id' => 300]], [['id' => 400]], [['id' => 500]], [['id' => 600]]],
	'FROM graph_templates_item'  => [
		[['local_graph_id' => 2, 'name' => 'QoS in'], ['local_graph_id' => 2, 'name' => 'QoS out'], ['local_graph_id' => 1, 'name' => 'Traffic']],
		[['local_graph_id' => 300, 'name' => 'QoS']],
		array_fill(0, 10001, ['local_graph_id' => 400, 'name' => 'QoS']),
		[['local_graph_id' => 500, 'name' => str_repeat('a', 40) . 'b']],
	],
]);
cacti_fixture_assert(automation_aggregate_members($rule, 9) === [2], 'members are matched across batches');
cacti_fixture_assert(cacti_fixture_stub_arguments('db_fetch_assoc_prepared')[2][1] === [9, 3, 200], 'next batch starts after the cursor');
aggregate_rule_expect_throw(static fn () => automation_aggregate_members(['automation_max_members' => 0] + $rule, 9), LengthException::class, 'member limit is enforced');
aggregate_rule_expect_throw(static fn () => automation_aggregate_members($rule, 9), LengthException::class, 'oversized name batch is rejected');
aggregate_rule_expect_throw(static fn () => automation_aggregate_members(['automation_pattern' => '/(a+)+$/'] + $rule, 9), RuntimeException::class, 'pattern failure aborts matching');
cacti_fixture_assert(automation_aggregate_members($rule, 9) === [], 'graphs without data sources match nothing');

// Attributes carry every per-item override the template enables.
$template = ['id' => 7, 'user_id' => 1, 'graph_template_id' => 3, 'gprint_prefix' => '', 'gprint_format' => '', 'graph_type' => 0,
	'total'          => 0, 'total_type' => 0, 'total_prefix' => '', 'order_type' => 0];
aggregate_rule_answers(['FROM aggregate_graph_templates_item' => [[
	['sequence' => 1, 'item_skip' => 'on', 'item_total' => 'on', 'color_template' => 4, 't_graph_type_id' => 'on', 'graph_type_id' => 8, 't_cdef_id' => 'on', 'cdef_id' => 2],
	['sequence' => 2, 'item_skip' => '', 'item_total' => '', 'color_template' => -1, 't_graph_type_id' => '', 'graph_type_id' => 0, 't_cdef_id' => '', 'cdef_id' => 0],
]]]);
$attributes = automation_aggregate_attributes($template);
cacti_fixture_assert($attributes['item_no'] === 2 && $attributes['skipped_items'] === [1 => 1] && $attributes['total_items'] === [1 => 1] &&
	$attributes['color_templates']             === [1 => 4] && $attributes['graph_item_types'] === [1 => 8] && $attributes['cdefs'] === [1 => 2] &&
	$attributes['aggregate_template_id']       === 7 && $attributes['template_propogation'] === 'on', 'item overrides map to attributes');

// Device dispatch: invalid templates are logged, a device template filter
// skips a rule, a busy rule reports busy and a failing rule reports failed.
$rules = [
	['id' => 11, 'automation_enabled' => 'on', 'automation_pattern' => 'QoS'] + $template,
	['id' => 12, 'automation_enabled' => 'on', 'automation_pattern' => '/QoS/', 'automation_host_template_id' => 5, 'automation_max_members' => 10] + $template,
	['id' => 13, 'automation_enabled' => 'on', 'automation_pattern' => '/QoS/', 'automation_host_template_id' => 4, 'automation_max_members' => 10] + $template,
	['id' => 14, 'automation_enabled' => 'on', 'automation_pattern' => '/QoS/', 'automation_host_template_id' => 0, 'automation_max_members' => 10] + $template,
];
$GLOBALS['cacti_fixture_returns']['db_fetch_cell_prepared'] = static fn (string $sql, array $params = []) => (int) ($params[0] !== 'aggregate_automation_13_9');
aggregate_rule_answers([
	'FROM aggregate_graph_templates WHERE' => [$rules],
	'FROM aggregate_graph_templates_item'  => [[], [], []],
	'FROM host WHERE'                      => [[['id' => 9, 'description' => 'router', 'hostname' => 'router', 'host_template_id' => 4]]],
	'FROM graph_local'                     => [new Exception('graph_local is unavailable')],
]);
cacti_fixture_assert(automation_execute_device_aggregates(9, true) === [13 => 'busy', 14 => 'failed'], 'device rules dispatch per template');
$logged = implode("\n", array_column(cacti_fixture_stub_arguments('cacti_log'), 0));
cacti_fixture_assert(str_contains($logged, 'Invalid aggregate automation template 11') && str_contains($logged, 'template 14 Device[9]'), 'invalid and failed templates are logged');
$GLOBALS['database_last_error'] = '';

// The cached rules are reused until a reload; a missing device does nothing.
aggregate_rule_answers(['FROM host WHERE' => [[]]]);
cacti_fixture_assert(automation_execute_device_aggregates(10) === [], 'a deleted device is skipped');
aggregate_rule_answers(['FROM aggregate_graph_templates WHERE' => [[]]]);
cacti_fixture_assert(automation_execute_device_aggregates(9, true) === [], 'no enabled templates means no work');

// Device automation reconciles aggregates after its graph rules and before
// tree rules, and an aggregate failure is logged without aborting the caller.
aggregate_rule_answers(['FROM aggregate_graph_templates WHERE' => [[$rules[3]]], 'FROM host WHERE' => [[]]]);
automation_execute_device_aggregates(0, true);
$GLOBALS['cacti_fixture_calls'] = [];
automation_update_device(0);
$order = [];

foreach ($GLOBALS['cacti_fixture_calls'] as $index => [$name, $arguments]) {
	$sql = is_string($arguments[0] ?? null) ? $arguments[0] : '';

	if (str_contains($sql, 'FROM host WHERE id = ?')) {
		$order['aggregates'] = $index;
	} elseif (str_contains($sql, 'FROM automation_templates_rules')) {
		$order['tree'] ??= $index;
	} elseif (str_contains($sql, 'FROM snmp_query')) {
		$order['queries'] = $index;
	}
}
cacti_fixture_assert(isset($order['queries'], $order['aggregates'], $order['tree']) && $order['queries'] < $order['aggregates'] &&
	$order['aggregates'] < $order['tree'], 'aggregates reconcile between graph and tree rules');

aggregate_rule_answers(['FROM host WHERE' => [new Exception('host is unavailable')]]);
$GLOBALS['cacti_fixture_calls'] = [];
automation_reconcile_device_aggregates(9);
$logged = implode("\n", array_column(cacti_fixture_stub_arguments('cacti_log'), 0));
cacti_fixture_assert(str_contains($logged, 'ERROR: Aggregate automation Device[9]: Unable to read aggregate automation metadata.'), 'aggregate failure is logged');
$GLOBALS['database_last_error'] = '';

print json_encode(['result' => 'ok']) . "\n";
