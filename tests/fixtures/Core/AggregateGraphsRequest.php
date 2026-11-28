<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Runs aggregate_graphs.php for one request and checks how it saves and draws
 * an aggregate graph item's overrides.
 *
 * The page runs unchanged from its first line. Its "./include/auth.php" is a
 * stand-in, in a temporary working directory, that loads the real
 * include/global_arrays.php and include/global_form.php the way the real
 * bootstrap does. The libraries the page requires load for real; every other
 * Cacti function it calls is a recording stub answering from the request scenario.
 *
 * Usage: php AggregateGraphsRequest.php <scenario>
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/FixtureStubs.php';

final class AggregateGraphsStop extends Exception {
}

$root     = dirname(__DIR__, 3);
$scenario = $argv[1] ?? '';

require_once $root . '/include/global_constants.php';
define('POLLER_ID', 1);
define('CACTI_WEB', true);
define('CACTI_CLI', false);
define('CACTI_SERVER_OS', 'unix');
define('CACTI_VERSION', '1.3.0');

// The libraries the page requires are empty files here, so none of them runs
// its own start-up code; every function they would supply is a stub below.
$site = sys_get_temp_dir() . '/cacti-aggregate-graphs-' . getmypid();
mkdir($site . '/include', 0700, true);
mkdir($site . '/lib', 0700, true);
$library_path = $site . '/lib';
require_once $root . '/include/global_path.php';

foreach (['api_graph', 'api_tree', 'api_data_source', 'api_aggregate', 'data_query', 'html_tree', 'html_form_template', 'poller', 'reports', 'rrd', 'template', 'utility'] as $library) {
	file_put_contents($site . '/lib/' . $library . '.php', "<?php\n");
}

$global_arrays = file_get_contents($root . '/include/global_arrays.php') . file_get_contents($root . '/include/global_form.php');
$stub_source   = tempnam(sys_get_temp_dir(), 'cacti-aggregate-graphs-');
file_put_contents($stub_source, $global_arrays);
cacti_fixture_stub_functions(array_merge(
	cacti_fixture_called_functions($root . '/aggregate_graphs.php', []),
	cacti_fixture_called_functions($stub_source, []),
	['is_error_message', 'raise_message', 'cacti_redirect']
), '');
unlink($stub_source);

$item     = ['action' => 'save', 'save_component_item' => '1', 'aggregate_template_id' => '3', 'aggregate_graph_id' => '0', 'local_graph_id' => '77', 'graph_template_item_id' => '100'];
$requests = [
	'save-template-item' => $item + ['t_graph_type_id' => 'on', 'graph_type_id' => '4', 't_cdef_id' => 'on', 'cdef_id' => '8', 't_text_format' => 'on', 'text_format' => 'Traffic', 't_legend' => 'on', 'legend' => ''],
	'save-graph-item'    => ['aggregate_template_id' => '0', 'aggregate_graph_id' => '9'] + $item + ['t_text_format' => 'on', 'text_format' => 'Own'],
	'save-invalid'       => $item + ['t_graph_type_id' => 'on', 'graph_type_id' => ['4'], 't_cdef_id' => 'on', 'cdef_id' => '999', 't_text_format' => 'on', 'text_format' => str_repeat('x', 256), 't_legend' => 'on', 'legend' => ['array']],
	'save-legend-type'   => $item + ['t_graph_type_id' => 'on', 'graph_type_id' => (string) GRAPH_ITEM_TYPE_LEGEND],
	'save-vanished'      => ['aggregate_template_id' => '404'] + $item,
	'save-graph'         => ['action' => 'save', 'save_component_graph' => '1', 'local_graph_id' => '77', 'graph_template_id' => '2', 'title_format' => 'Combined', 'total_prefix' => 'Sum', 'total' => '2', 'graph_type' => '1', 'total_type' => '1', 'order_type' => '0'],
	'edit-template'      => ['action' => 'item_edit', 'id' => '100', 'aggregate_template_id' => '3'],
	'edit-graph'         => ['action' => 'item_edit', 'id' => '100', 'aggregate_graph_id' => '9', 'local_graph_id' => '77'],
	'edit-unrelated'     => ['action' => 'item_edit', 'id' => '555', 'aggregate_template_id' => '3'],
];

cacti_fixture_assert(isset($requests[$scenario]), 'known scenario ' . $scenario);
$GLOBALS['probe_request'] = $requests[$scenario];
$_SESSION                 = [SESS_USER_ID => 1];
$_POST                    = $requests[$scenario];
$GLOBALS['probe_errors']  = [];
$GLOBALS['probe_saves']   = [];

$request = static fn (string $name, mixed $default = '') => $GLOBALS['probe_request'][$name] ?? $default;
$parents = [
	'aggregate_graph_templates' => [3 => ['id' => 3, 'graph_template_id' => 2], 404 => []],
	'aggregate_graphs'          => [9 => ['id' => 9, 'graph_template_id' => 2, 'aggregate_template_id' => 0, 'local_graph_id' => 77, 'template_propogation' => '', 'gprint_prefix' => '', 'gprint_format' => '', 'graph_type' => 1, 'total' => 2, 'total_type' => 1, 'total_prefix' => '', 'order_type' => 0]],
];
$GLOBALS['cacti_fixture_returns'] = [
	'__'                 => static fn (string $text, mixed ...$arguments) => $arguments ? vsprintf($text, $arguments) : $text,
	'__esc'              => static fn (string $text, mixed ...$arguments) => htmlspecialchars($arguments ? vsprintf($text, $arguments) : $text),
	'grv'                => $request,
	'gnrv'               => $request,
	'gfrv'               => static fn (string $name) => is_array($request($name)) ? 0 : (int) $request($name, 0),
	'isrv'               => static fn (string $name) => isset($GLOBALS['probe_request'][$name]),
	'ierv'               => static fn (string $name) => ($GLOBALS['probe_request'][$name] ?? '') === '' || ($GLOBALS['probe_request'][$name] ?? '') === '0',
	'srv'                => static function (string $name, mixed $value) : void {
		$GLOBALS['probe_request'][$name] = $value;
	},
	'set_default_action' => static function () : void {
		$GLOBALS['probe_request']['action'] ??= '';
	},
	'form_input_validate' => static fn (mixed $value) => $value,
	'raise_message'       => static function (string|int $key) : void {
		$GLOBALS['probe_errors'][] = $key;
	},
	'is_error_message'    => static fn () => $GLOBALS['probe_errors'] !== [] && $GLOBALS['probe_errors'][0] === 2,
	'cacti_redirect'      => static function (string $url) : never {
		$GLOBALS['probe_redirect'] = $url;

		throw new AggregateGraphsStop('redirect ' . $url);
	},
	'cacti_sizeof'                         => static fn ($value) => is_countable($value) ? count($value) : 0,
	'cacti_url'                            => static fn (string $page, array $query = []) => $page . '?' . http_build_query($query),
	'db_fetch_cell_prepared'               => static fn (string $sql, array $params) => str_contains($sql, 'FROM cdef') ? ($params === [8] ? 8 : false) : (str_contains($sql, 'FROM aggregate_graphs') ? 9 : false),
	'array_rekey'                          => [],
	'aggregate_validate_graph_params'      => [],
	'aggregate_graph_templates_graph_save' => 1,
	'db_fetch_row_prepared'                => static function (string $sql, array $params) use ($parents) {
		if (preg_match('/FROM (aggregate_graph_templates|aggregate_graphs) WHERE id = \?/', $sql, $match)) {
			return $parents[$match[1]][(int) $params[0]] ?? [];
		}

		if (str_contains($sql, 'FROM graph_templates_item WHERE id = ?')) {
			return $params[0] == 100 ? ['id' => 100, 'sequence' => 4] : [];
		}

		if (str_contains($sql, 'FROM graph_templates_item')) {
			return $params[0] == 100 ? [
				'id'            => 100, 'local_graph_id' => 0, 'graph_template_id' => 2, 'local_graph_template_item_id' => 0, 'sequence' => 4,
				'graph_type_id' => GRAPH_ITEM_TYPE_AREA, 'cdef_id' => 0, 'text_format' => 'Traffic', 'legend' => '',
			] : ($params[0] == 555 ? ['id' => 555, 'local_graph_id' => 12, 'graph_template_id' => 2] : []);
		}

		// the stored overrides of the item
		return ['t_graph_type_id' => 'on', 'graph_type_id' => GRAPH_ITEM_TYPE_LINE1, 't_cdef_id' => '', 't_text_format' => 'on', 'text_format' => 'Stored', 't_legend' => '', 'legend' => ''];
	},
	'db_execute_prepared'       => static function (string $sql, array $params) : bool {
		$GLOBALS['probe_saves'][] = [$sql, $params];

		return true;
	},
	'push_out_aggregates'        => true,
	'get_rrdtool_version'        => '1.8.0',
	'cacti_version_compare'      => static fn (string $a, string $b, string $operator) => version_compare($a, $b, $operator),
	'get_auth_realms'            => [],
	'is_realm_allowed'           => true,
	'db_table_exists'            => true,
	'load_current_session_value' => null,
];

// The page's own "require('./include/auth.php')" loads this stand-in.
file_put_contents($site . '/include/auth.php', '<?php require_once ' . var_export($root . '/include/global_arrays.php', true) . '; require_once ' . var_export($root . '/include/global_form.php', true) . ";\n");
register_shutdown_function(static function () use ($site) : void {
	array_map('unlink', glob($site . '/lib/*.php'));
	unlink($site . '/include/auth.php');
	rmdir($site . '/lib');
	rmdir($site . '/include');
	rmdir($site);
});
register_shutdown_function(static function () use ($scenario) : void {
	$output = '';

	while (ob_get_level()) {
		$output = ob_get_clean() . $output;
	}
	$errors = $GLOBALS['probe_errors'];
	$saves  = $GLOBALS['probe_saves'];
	$sql    = $saves[0][0] ?? '';
	$params = $saves[0][1] ?? [];

	switch ($scenario) {
		case 'save-template-item':
			cacti_fixture_assert(cacti_fixture_stub_arguments('csrf_require_post') === [[]], 'saving requires a POST token');
			cacti_fixture_assert(str_contains($sql, 'INSERT INTO aggregate_graph_templates_item (aggregate_template_id') && str_ends_with($sql, 't_legend = VALUES(t_legend), legend = VALUES(legend)'), 'the overrides are stored for the template item');
			cacti_fixture_assert(array_slice($params, 0, 3) === [3, 100, 4] && in_array('Traffic', $params, true) && in_array(8, $params, true) && $errors === [1], 'the stored overrides, key and sequence are bound');
			cacti_fixture_assert(cacti_fixture_stub_arguments('push_out_aggregates') === [[3]], 'the template change is pushed to its graphs');

			break;
		case 'save-graph-item':
			cacti_fixture_assert(str_contains($sql, 'INSERT INTO aggregate_graphs_graph_item (aggregate_graph_id') && array_slice($params, 0, 3) === [9, 100, 4], 'the overrides are stored for the graph item');
			cacti_fixture_assert(($GLOBALS['probe_request']['local_graph_id'] ?? '') === 77 && cacti_fixture_stub_arguments('push_out_aggregates') === [[0, 77]], 'the graph change is pushed to its graph');

			break;
		case 'save-invalid':
			cacti_fixture_assert($saves === [] && $errors !== [] && ($_SESSION[SESS_ERROR_FIELDS] ?? []) === ['text_format' => 2, 'legend' => 2, 'graph_type_id' => 2, 'cdef_id' => 2], 'invalid overrides are refused field by field');

			break;
		case 'save-graph':
			cacti_fixture_assert($sql === 'UPDATE aggregate_graphs
			SET title_format = ?
			WHERE id = ?' && $params === ['Combined', 9], 'the title is stored on the aggregate');
			cacti_fixture_assert(count(cacti_fixture_stub_arguments('sql_save')) === 1 && cacti_fixture_stub_arguments('push_out_aggregates') === [[0, '77']] && $errors === [1], 'a changed aggregate is saved and pushed out');

			break;
		case 'save-legend-type':
			cacti_fixture_assert($saves === [] && ($_SESSION[SESS_ERROR_FIELDS] ?? []) === ['graph_type_id' => 2], 'a legend graph item type cannot be an override');

			break;
		case 'save-vanished':
			cacti_fixture_assert($saves === [] && ($GLOBALS['probe_redirect'] ?? '') === 'aggregate_templates.php' && $errors === [2], 'an item of a vanished template is not saved');

			break;
		case 'edit-template':
		case 'edit-graph':
			cacti_fixture_assert(str_contains($output, 'Stored') || cacti_fixture_stub_arguments('draw_edit_form') !== [], 'the item editor draws its form');
			$form = cacti_fixture_stub_arguments('draw_edit_form')[0][0]['fields'] ?? [];
			cacti_fixture_assert(array_keys($form) === ['graph_type_id', 'cdef_id', 'text_format', 'legend'], 'the editor offers the four overridable fields');
			cacti_fixture_assert(!isset($form['graph_type_id']['array'][GRAPH_ITEM_TYPE_LEGEND], $form['graph_type_id']['array'][GRAPH_ITEM_TYPE_LEGEND_CAMM]), 'the legend types cannot be chosen');
			cacti_fixture_assert($form['text_format']['value'] === 'Stored' && $form['graph_type_id']['value'] === GRAPH_ITEM_TYPE_LINE1 && $form['legend']['sub_checkbox']['value'] === '', 'enabled overrides replace the template values');
			cacti_fixture_assert(cacti_fixture_stub_arguments('form_start') === [['aggregate_graphs.php', 'aggregate_item_form']], 'the editor posts back to the page');

			if ($scenario === 'edit-graph') {
				cacti_fixture_assert(($GLOBALS['probe_request']['local_graph_id'] ?? '') === 77, 'the graph editor remembers its graph');
			}

			break;
		case 'edit-unrelated':
			cacti_fixture_assert(($GLOBALS['probe_redirect'] ?? '') === 'aggregate_templates.php' && $errors === [2], 'an item of another graph template is refused');

			break;
	}
	print json_encode(['result' => 'ok', 'scenario' => $scenario]) . "\n";
});

chdir($site);
ob_start();

try {
	require $root . '/aggregate_graphs.php';
} catch (AggregateGraphsStop $stop) {
	$GLOBALS['probe_stop'] = $stop->getMessage();
}
