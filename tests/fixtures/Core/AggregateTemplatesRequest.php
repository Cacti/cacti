<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Runs aggregate_templates.php for one request and checks how it handles the
 * aggregate automation settings.
 *
 * The page runs unchanged from its first line. Its "./include/auth.php" is a
 * stand-in, in a temporary working directory, that loads the real
 * include/global_form.php the way the real bootstrap does. The libraries the
 * page requires load for real; every other Cacti function it calls is a
 * recording stub answering from the request scenario.
 *
 * Usage: php AggregateTemplatesRequest.php save|template-missing|missing-row|delete|list
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/FixtureStubs.php';

final class AggregateTemplatesStop extends Exception {
}

/** Records the list page filter instead of rendering it. */
final class CactiTableFilter {
	public string $rows_label = '';

	public function __construct(string ...$arguments) {
		$GLOBALS['probe_filter'] = $arguments;
	}

	public function set_sort_array(string $column, string $direction) : void {
	}

	public function render() : void {
	}
}

$root     = dirname(__DIR__, 3);
$scenario = $argv[1] ?? '';

require_once $root . '/include/global_constants.php';
require_once $root . '/include/global_path.php';
define('POLLER_ID', 1);
define('CACTI_WEB', true);
define('CACTI_CLI', false);
define('CACTI_SERVER_OS', 'unix');
define('CACTI_VERSION', '1.3.0');

// The page requires these itself; loading them first keeps the stubs below
// from claiming their names.
require_once CACTI_PATH_LIBRARY . '/api_aggregate.php';
require_once CACTI_PATH_LIBRARY . '/api_automation_aggregate.php';
require_once CACTI_PATH_LIBRARY . '/data_query.php';

$global_arrays = file_get_contents($root . '/include/global_arrays.php') . file_get_contents($root . '/include/global_form.php');
$stub_source   = tempnam(sys_get_temp_dir(), 'cacti-aggregate-templates-');
file_put_contents($stub_source, $global_arrays);
cacti_fixture_stub_functions(array_merge(
	cacti_fixture_called_functions($root . '/aggregate_templates.php', []),
	cacti_fixture_called_functions($stub_source, []),
	['is_error_message', 'raise_message', 'cacti_redirect']
), '');
unlink($stub_source);

$requests = [
	'save' => [
		'action'                      => 'save', 'id' => '5', 'graph_template_id_prev' => '3', 'save_component_template' => '1', 'name' => 'QoS',
		'graph_type'                  => '0', 'automation_enabled' => 'on', 'automation_pattern' => '/QoS.*Gold/i', 'automation_title' => '|host_description| QoS',
		'automation_host_template_id' => '4', 'automation_max_members' => '50',
	],
	// Same request, but the save succeeds so the page goes on to compare and store the template items.
	'save-items' => [
		'action' => 'save', 'id' => '5', 'graph_template_id_prev' => '3', 'save_component_template' => '1', 'name' => 'QoS Items',
	],
	'template-missing' => [
		'action'             => 'save', 'id' => '5', 'graph_template_id_prev' => '3', 'save_component_template' => '1', 'name' => 'QoS',
		'automation_pattern' => '/QoS/', 'automation_host_template_id' => '9',
	],
	'missing-row' => [
		'action' => 'save', 'id' => '6', 'graph_template_id_prev' => '3', 'save_component_template' => '1', 'name' => 'QoS',
	],
	'delete' => ['action' => 'actions', 'drp_action' => '1', 'selected_items' => 'a:2:{i:0;i:3;i:1;i:4;}'],
	'list'   => ['rows' => '20', 'page' => '1', 'filter' => '', 'sort_column' => 'pgt.name', 'sort_direction' => 'ASC'],
];

cacti_fixture_assert(isset($requests[$scenario]), 'known scenario ' . $scenario);
$GLOBALS['probe_request'] = $requests[$scenario];
$_SESSION                 = [SESS_USER_ID => 1];
$_POST                    = $requests[$scenario];
$GLOBALS['probe_errors']  = [];

$request                          = static fn (string $name, mixed $default = '') => $GLOBALS['probe_request'][$name] ?? $default;
$GLOBALS['cacti_fixture_returns'] = [
	'__'                     => static fn (string $text, mixed ...$arguments) => $arguments ? vsprintf($text, $arguments) : $text,
	'__esc'                  => static fn (string $text, mixed ...$arguments) => htmlspecialchars($arguments ? vsprintf($text, $arguments) : $text),
	'grv'                    => $request,
	'gnrv'                   => $request,
	'gfrv'                   => $request,
	'isrv'                   => static fn (string $name) => isset($GLOBALS['probe_request'][$name]),
	'ierv'                   => static fn (string $name) => ($GLOBALS['probe_request'][$name] ?? '') === '',
	'srv'                    => static function (string $name, mixed $value) : void {
		$GLOBALS['probe_request'][$name] = $value;
	},
	'set_default_action'     => static function () : void {
		$GLOBALS['probe_request']['action'] ??= '';
	},
	'form_input_validate'    => static fn (mixed $value) => $value,
	'raise_message'          => static function (string|int $key) : void {
		$GLOBALS['probe_errors'][] = $key;
	},
	'is_error_message'       => static fn () => in_array('aggregate_automation_invalid', $GLOBALS['probe_errors'], true),
	'cacti_redirect'         => static function (string $url) : never {
		throw new AggregateTemplatesStop('redirect ' . $url);
	},
	'sql_save'               => static function (array $save) : int {
		if (($GLOBALS['probe_request']['name'] ?? '') === 'QoS Items') {
			$GLOBALS['probe_sql_saves'][] = $save;

			return (int) ($save['id'] ?? 5);
		}

		throw new AggregateTemplatesStop(json_encode($save));
	},
	'db_fetch_cell_prepared' => static fn (string $sql, array $params) => str_contains($sql, 'FROM host_template') && $params === [4] ? 4 : false,
	'db_fetch_row_prepared'  => static fn (string $sql, array $params) => in_array($params, [['5'], [5]], true) ? [
		'graph_template_id'           => 3,
		'name'                        => 'QoS', 'gprint_prefix' => '', 'gprint_format' => '', 'graph_type' => '0', 'total' => 0, 'total_type' => 0,
		'total_prefix'                => '', 'order_type' => 0, 'automation_enabled' => '', 'automation_pattern' => '', 'automation_title' => '',
		'automation_host_template_id' => 0, 'automation_max_members' => 1000,
	] : [],
	'sanitize_unserialize_selected_items' => static fn () => [3, 4],
	'array_to_sql_or'                     => static fn (array $items, string $column) => $column . ' IN (' . implode(',', $items) . ')',
	// An empty item set: no stored or posted items to compare.
	'array_rekey'                         => static fn (mixed $rows, string $key, mixed $values) : array => [],
	'cacti_sizeof'                        => static fn ($value) => is_countable($value) ? count($value) : 0,
	'db_fetch_cell'                       => 2,
	'db_fetch_assoc'                      => [
		['id' => 3, 'name' => 'QoS', 'graphs' => 0, 'graph_template_name' => 'Traffic', 'automation_enabled' => 'on'],
		['id' => 4, 'name' => 'Errors', 'graphs' => 1, 'graph_template_name' => 'Errors', 'automation_enabled' => ''],
	],
	'filter_value'           => static fn (mixed $value) => (string) $value,
	'number_format_i18n'     => static fn (mixed $value) => (string) $value,
	// Without plugins, a hook hands its argument back unchanged.
	'api_plugin_hook_function' => static fn (string $name, mixed $argument = '') => $argument,
];

// The page's own "require_once('./include/auth.php')" loads this stand-in.
$site = sys_get_temp_dir() . '/cacti-aggregate-templates-' . getmypid();
mkdir($site . '/include', 0700, true);
file_put_contents($site . '/include/auth.php', '<?php require_once ' . var_export($root . '/include/global_form.php', true) . ";\n");
register_shutdown_function(static function () use ($site) : void {
	unlink($site . '/include/auth.php');
	rmdir($site . '/include');
	rmdir($site);
});
register_shutdown_function(static function () use ($scenario) : void {
	while (ob_get_level()) {
		ob_end_clean();
	}
	$errors = $GLOBALS['probe_errors'];
	$saved  = $GLOBALS['probe_saved'] ?? [];

	switch ($scenario) {
		case 'save':
			cacti_fixture_assert(cacti_fixture_stub_arguments('csrf_require_post') === [[]], 'saving requires a POST token');
			cacti_fixture_assert(array_intersect_key($saved, array_flip(['automation_enabled', 'automation_pattern', 'automation_title', 'automation_host_template_id', 'automation_max_members'])) === [
				'automation_enabled'          => 'on', 'automation_pattern' => '/QoS.*Gold/i', 'automation_title' => '|host_description| QoS',
				'automation_host_template_id' => 4, 'automation_max_members' => 50,
			] && $errors === [], 'valid automation settings are saved: ' . json_encode([$saved, $errors]));

			break;
		case 'save-items':
			$queries = array_column(cacti_fixture_stub_arguments('db_fetch_assoc_prepared'), 0);
			cacti_fixture_assert(($GLOBALS['probe_sql_saves'][0]['name'] ?? '') === 'QoS Items' && $errors === [1], 'the template is saved and reports success');
			$stored  = array_filter($queries, static fn (string $sql) : bool => str_starts_with($sql, 'SELECT *') && str_contains($sql, 'FROM aggregate_graph_templates_item'));
			cacti_fixture_assert(count($GLOBALS['probe_sql_saves']) === 1 && count($stored) === 1, 'the stored template items are read to compare with the posted items');

			break;
		case 'template-missing':
			cacti_fixture_assert($errors === ['aggregate_automation_invalid'] && $saved === [], 'an unknown device template is rejected');

			break;
		case 'missing-row':
			cacti_fixture_assert($errors === [2] && ($GLOBALS['probe_stop'] ?? '') === 'redirect aggregate_templates.php' && $saved === [], 'a vanished template is not saved');

			break;
		case 'delete':
			$deleted = array_column(cacti_fixture_stub_arguments('db_execute'), 0);
			cacti_fixture_assert(in_array('DELETE FROM automation_aggregate_graphs WHERE aggregate_template_id IN (3,4)', $deleted, true), 'deleting templates drops their automation ownership');

			break;
		case 'list':
			$columns = cacti_fixture_stub_arguments('html_header_sort_checkbox')[0][0];
			$cells   = array_column(cacti_fixture_stub_arguments('form_selectable_cell'), 0);
			cacti_fixture_assert(($columns['automation_enabled']['display'] ?? '') === 'Aggregate Automation', 'the list has an automation column');
			cacti_fixture_assert(in_array('Enabled', $cells, true) && in_array('Disabled', $cells, true), 'the list shows each template automation state');
			cacti_fixture_assert(isset($GLOBALS['struct_aggregate_template']['automation_pattern'], $GLOBALS['struct_aggregate_template']['automation_max_members']) &&
				$GLOBALS['struct_aggregate_template']['automation_host_template_id']['method'] === 'drop_sql', 'the template form has the automation fields');

			break;
	}
	print json_encode(['result' => 'ok', 'scenario' => $scenario]) . "\n";
});

chdir($site);
ob_start();

try {
	require $root . '/aggregate_templates.php';
} catch (AggregateTemplatesStop $stop) {
	$GLOBALS['probe_stop']  = $stop->getMessage();
	$GLOBALS['probe_saved'] = json_decode($stop->getMessage(), true) ?? [];
}
ob_end_clean();
