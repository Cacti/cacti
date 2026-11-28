<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

declare(strict_types=1);

$repoRoot = (string) realpath(__DIR__ . '/../../..');

/**
 * Run an isolated PHP probe script in a child process so production Cacti
 * library files can be required alongside deterministic test doubles without
 * colliding with global bootstrap definitions.
 *
 * @param string $phpBody PHP source body (without leading <?php tag)
 *
 * @return array<string, mixed>
 */
function pr4RunPhpProbe(string $phpBody) : array {
	$tmpFile = tempnam(sys_get_temp_dir(), 'cacti_pr4_probe_');
	expect($tmpFile)->not->toBeFalse();

	$commonHelpers = <<<'PHP'
if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof(mixed $v) : int { return is_array($v) ? count($v) : 0; }
}
if (!function_exists('array_rekey')) {
	function array_rekey(mixed $array, string $key, string|array $value) : array {
		$ret = [];
		if (is_array($array)) {
			foreach ($array as $item) {
				if (is_array($value)) {
					foreach ($value as $v) {
						$ret[$item[$key]][$v] = $item[$v];
					}
				} else {
					$ret[$item[$key]] = $item[$value];
				}
			}
		}
		return $ret;
	}
}
PHP;

	file_put_contents((string) $tmpFile, "<?php\nerror_reporting(E_ALL);\n" . $commonHelpers . "\n" . $phpBody . "\n");

	try {
		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];
		$proc = proc_open([PHP_BINARY, '-d', 'display_errors=1', (string) $tmpFile], $descriptors, $pipes);
		expect(is_resource($proc))->toBeTrue();

		fclose($pipes[0]);
		$stdout   = (string) stream_get_contents($pipes[1]);
		$stderr   = (string) stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exitCode = proc_close($proc);

		expect($exitCode)->toBe(0, "Probe failed (stderr: $stderr, stdout: $stdout)");

		$decoded = json_decode($stdout, true);
		expect(is_array($decoded))->toBeTrue("Invalid JSON from probe: $stdout (stderr: $stderr)");

		return $decoded;
	} finally {
		if (is_string($tmpFile) && file_exists($tmpFile)) {
			@unlink($tmpFile);
		}
	}
}

/**
 * Extract the source body of a named PHP function for structural regression checks.
 */
function pr4ExtractFunctionBody(string $source, string $signature) : string {
	$start = strpos($source, $signature);
	expect($start)->not->toBeFalse("Function signature not found: $signature");

	$end = strpos($source, "\nfunction ", (int) $start + strlen($signature));

	return substr($source, (int) $start, $end !== false ? $end - (int) $start : strlen($source) - (int) $start);
}

/* =========================================================================
 * Structural regression contracts across tree, device, template, aggregate
 * ========================================================================= */

test('core function regression contracts hold', function (string $relPath, string $signature, array $mustContain, array $mustNotContain) use ($repoRoot) : void {
	$src  = (string) file_get_contents($repoRoot . '/' . $relPath);
	$body = pr4ExtractFunctionBody($src, $signature);

	foreach ($mustContain as $needle) {
		expect($body)->toContain($needle);
	}

	foreach ($mustNotContain as $forbidden) {
		expect($body)->not->toContain($forbidden);
	}
})->with([
	'api_tree_renumber_siblings scopes by graph_tree_id and orders by position' => [
		'lib/api_tree.php',
		'function api_tree_renumber_siblings(',
		['ORDER BY position ASC', "WHERE graph_tree_id = ?\n\t\tAND parent = ?"],
		[],
	],
	'draw_dhtml_tree_level_graphing avoids array_merge in loops' => [
		'lib/html_tree.php',
		'function draw_dhtml_tree_level_graphing(',
		[],
		['array_merge('],
	],
	'create_site_branch avoids array_merge in loops' => [
		'lib/html_tree.php',
		'function create_site_branch(',
		[],
		['array_merge('],
	],
	'grow_right_pane_tree batches active template lookup with DISTINCT' => [
		'lib/html_tree.php',
		'function grow_right_pane_tree(',
		['SELECT DISTINCT graph_template_id'],
		['WHERE graph_template_id = ? LIMIT 1'],
	],
	'api_device_remove binds integer host_id for reports_items' => [
		'lib/api_device.php',
		'function api_device_remove(',
		["DELETE FROM reports_items    WHERE host_id = ?', [\$device_id]"],
		["DELETE FROM reports_items    WHERE host_id = ?', array(\$device_id . ':%')"],
	],
	'api_device_purge_deleted_devices binds integer host_id for reports_items' => [
		'lib/api_device.php',
		'function api_device_purge_deleted_devices(',
		["DELETE FROM reports_items    WHERE host_id = ?', [\$d['id']]"],
		["DELETE FROM reports_items    WHERE host_id = ?', array(\$d['id'] . ':%')"],
	],
	'api_device_update_host_template filters host_graph by host_id' => [
		'lib/api_device.php',
		'function api_device_update_host_template(',
		['WHERE hg.host_id = ?', '[$device_id, $device_template_id, $device_id]'],
		[],
	],
	'api_clone_device_template scopes task_item_id UPDATE to cloned template' => [
		'lib/api_device.php',
		'function api_clone_device_template(',
		['AND graph_template_id = ?', 'AND local_graph_id = 0', "[\$dt_rrd_id, \$rrd['data_template_rrd_id'], \$new_gt]"],
		[],
	],
	'api_device_enable_devices avoids array_merge and cacti_sizeof on scalar count' => [
		'lib/api_device.php',
		'function api_device_enable_devices(',
		['(int) $poller_cache === 0'],
		['array_merge(', 'cacti_sizeof($poller_cache)'],
	],
	'push_out_data_source_custom_data indexes input fields by data_template_data_id' => [
		'lib/template.php',
		'function push_out_data_source_custom_data(',
		['$input_fields_by_dtd'],
		[],
	],
	'draw_aggregate_template_graph_config filters local_graph_id = 0' => [
		'lib/api_aggregate.php',
		'function draw_aggregate_template_graph_config(',
		['WHERE graph_template_id = ?', 'AND local_graph_id = 0'],
		[],
	],
]);

/* =========================================================================
 * 1. lib/api_tree.php: authorization, move_node, sort_branch, delete_node
 * ========================================================================= */

test('api_tree_get_main enforces show_tree view permission, is_tree_allowed, and enabled trees', function () use ($repoRoot) : void {
	$libFile = var_export($repoRoot . '/lib/api_tree.php', true);

	$result = pr4RunPhpProbe(<<<PHP
\$GLOBALS['queries']      = [];
\$GLOBALS['view_allowed'] = false;
\$GLOBALS['tree_allowed'] = true;
\$GLOBALS['tree_name']    = 'Secret Tree';

function is_view_allowed(\$view = 'show_tree') { return \$GLOBALS['view_allowed']; }
function is_tree_allowed(\$tree_id, \$user_id = 0) { return \$GLOBALS['tree_allowed']; }
function html_escape(\$s) { return htmlspecialchars((string) \$s, ENT_QUOTES, 'UTF-8'); }
function db_fetch_cell_prepared(\$sql, \$params = []) {
	\$GLOBALS['queries'][] = ['sql' => \$sql, 'params' => \$params];
	return \$GLOBALS['tree_name'];
}
function draw_dhtml_tree_level_graphing(\$tree_id, \$parent = 0) { return ['<ul><li>child</li></ul>']; }

require_once $libFile;

\$run = static function () : string {
	ob_start();
	api_tree_get_main(5, -1);
	return (string) ob_get_clean();
};

\$outNoView     = \$run();
\$queriesNoView = count(\$GLOBALS['queries']);

\$GLOBALS['view_allowed'] = true;
\$GLOBALS['tree_allowed'] = false;
\$outNoTree     = \$run();
\$queriesNoTree = count(\$GLOBALS['queries']);

\$GLOBALS['tree_allowed'] = true;
\$GLOBALS['tree_name']    = false;
\$outDisabled = \$run();
\$disabledSql = \$GLOBALS['queries'][0]['sql'];

\$GLOBALS['tree_name'] = 'Prod Tree';
\$outAllowed = \$run();

echo json_encode([
	'outNoView'     => \$outNoView,
	'queriesNoView' => \$queriesNoView,
	'outNoTree'     => \$outNoTree,
	'queriesNoTree' => \$queriesNoTree,
	'outDisabled'   => \$outDisabled,
	'disabledSql'   => \$disabledSql,
	'outAllowed'    => \$outAllowed,
]);
PHP);

	expect($result['outNoView'])->toBe('');
	expect($result['queriesNoView'])->toBe(0);
	expect($result['outNoTree'])->toBe('');
	expect($result['queriesNoTree'])->toBe(0);
	expect($result['outDisabled'])->toBe('');
	expect($result['disabledSql'])->toContain("enabled = 'on'");
	expect($result['outAllowed'])->toContain('Prod Tree');
	expect($result['outAllowed'])->toContain('</li></ul>');
});

test('api_tree_move_node scopes sibling renumbering by graph_tree_id and ORDER BY position', function () use ($repoRoot) : void {
	$libFile = var_export($repoRoot . '/lib/api_tree.php', true);

	$result = pr4RunPhpProbe(<<<PHP
define('TREE_ORDERING_INHERIT', 0);
define('TREE_ORDERING_NONE', 1);
define('TREE_ORDERING_ALPHABETIC', 2);
define('TREE_ORDERING_NUMERIC', 3);
define('TREE_ORDERING_NATURAL', 4);

\$GLOBALS['selects'] = [];
\$GLOBALS['updates'] = [];

function input_validate_input_number(&\$v) { return true; }
function cacti_log(\$msg, \$out = false) {}
function set_config_option(\$k, \$v) {}
function db_fetch_cell_prepared(\$sql, \$params = []) {
	return str_contains(\$sql, 'SELECT sort_type') ? TREE_ORDERING_NONE : 0;
}
function db_fetch_row_prepared(\$sql, \$params = []) {
	return str_contains(\$sql, 'SELECT parent, graph_tree_id') ? ['parent' => 0, 'graph_tree_id' => 7] : [];
}
function db_fetch_assoc_prepared(\$sql, \$params = []) {
	\$GLOBALS['selects'][] = ['sql' => \$sql, 'params' => \$params];
	return str_contains(\$sql, 'AND position >= ?') ? [['id' => 20], ['id' => 21]] : [];
}
function db_execute_prepared(\$sql, \$params = []) {
	\$GLOBALS['updates'][] = ['sql' => \$sql, 'params' => \$params];
}
function db_execute(\$sql) {
	\$GLOBALS['updates'][] = ['sql' => \$sql, 'params' => []];
}

require_once $libFile;
api_tree_move_node(7, 'tbranch:10', '#', 0);

echo json_encode(['selects' => \$GLOBALS['selects'], 'updates' => \$GLOBALS['updates']]);
PHP);

	$siblingSelects = array_values(array_filter(
		$result['selects'],
		static fn (array $q) : bool => str_contains($q['sql'], 'AND position >= ?')
	));
	expect($siblingSelects)->toHaveCount(1);
	expect($siblingSelects[0]['sql'])->toContain('graph_tree_id = ?');
	expect($siblingSelects[0]['sql'])->toContain('ORDER BY position ASC');
	expect($siblingSelects[0]['params'][0])->toBe(7);

	$siblingUpdates = array_values(array_filter(
		$result['updates'],
		static fn (array $q) : bool => str_contains($q['sql'], 'SET position = ?') && str_contains($q['sql'], 'graph_tree_id = ?')
	));
	expect(count($siblingUpdates))->toBeGreaterThanOrEqual(3);

	$moveBody = pr4ExtractFunctionBody((string) file_get_contents($repoRoot . '/lib/api_tree.php'), 'function api_tree_move_node(');
	expect(substr_count($moveBody, 'api_tree_renumber_siblings($tree_id, $pdata[\'leaf_id\'], $data[\'leaf_id\'], $new_position)'))->toBe(2);
	expect(substr_count($moveBody, 'api_tree_sort_branch($data[\'leaf_id\'], $tree_id, true, false)'))->toBe(2);
});

test('api_tree_delete_node_content hoists set_config_option out of recursion and api_tree_sort_branch avoids cascading re-sorts', function () use ($repoRoot) : void {
	$libFile = var_export($repoRoot . '/lib/api_tree.php', true);

	$result = pr4RunPhpProbe(<<<PHP
define('TREE_ORDERING_INHERIT', 0);
define('TREE_ORDERING_NONE', 1);
define('TREE_ORDERING_ALPHABETIC', 2);
define('TREE_ORDERING_NUMERIC', 3);
define('TREE_ORDERING_NATURAL', 4);

\$GLOBALS['config_sets']   = 0;
\$GLOBALS['deletes']       = [];
\$GLOBALS['sort_queries']  = [];
\$GLOBALS['batch_updates'] = [];

function cacti_log(\$msg, \$out = false) {}
function set_config_option(\$k, \$v) {
	if (\$k === 'time_last_change_branch') {
		\$GLOBALS['config_sets']++;
	}
}
function db_fetch_assoc_prepared(\$sql, \$params = []) {
	if (str_contains(\$sql, 'SELECT id, host_id, local_graph_id')) {
		return match ((int) \$params[1]) {
			1       => [['id' => 2, 'host_id' => 0, 'local_graph_id' => 0], ['id' => 3, 'host_id' => 10, 'local_graph_id' => 0]],
			2       => [['id' => 4, 'host_id' => 11, 'local_graph_id' => 0], ['id' => 5, 'host_id' => 12, 'local_graph_id' => 0]],
			default => [],
		};
	}

	\$GLOBALS['sort_queries'][] = ['sql' => \$sql, 'params' => \$params];
	if (str_contains(\$sql, 'MIN(id) AS first_child')) {
		return [['parent' => 100, 'first_child' => 200]];
	}
	if (str_contains(\$sql, 'SELECT id, title, position, sort_children_type')) {
		return match ((int) \$params[0]) {
			0       => [
				['id' => 100, 'title' => 'Beta', 'position' => 1, 'sort_children_type' => TREE_ORDERING_INHERIT],
				['id' => 101, 'title' => 'Alpha', 'position' => 2, 'sort_children_type' => TREE_ORDERING_INHERIT],
			],
			100     => [
				['id' => 200, 'title' => 'Child2', 'position' => 1, 'sort_children_type' => TREE_ORDERING_NONE],
				['id' => 201, 'title' => 'Child1', 'position' => 2, 'sort_children_type' => TREE_ORDERING_NONE],
			],
			default => [],
		};
	}
	return [];
}
function db_fetch_row_prepared(\$sql, \$params = []) {
	return (\$params[0] == 200) ? ['parent' => 100, 'graph_tree_id' => 1] : ['parent' => 0, 'graph_tree_id' => 1];
}
function db_fetch_cell_prepared(\$sql, \$params = []) {
	\$GLOBALS['sort_queries'][] = ['sql' => \$sql, 'params' => \$params];
	return TREE_ORDERING_NATURAL;
}
function db_execute_prepared(\$sql, \$params = []) {
	if (str_contains(\$sql, 'DELETE')) {
		\$GLOBALS['deletes'][] = ['sql' => \$sql, 'params' => \$params];
	}
}
function db_execute(\$sql) { \$GLOBALS['batch_updates'][] = \$sql; }

require_once $libFile;

api_tree_delete_node_content(1, 1);

api_tree_sort_branch(0, 1, false, false);
\$nonRecursiveQueries = \$GLOBALS['sort_queries'];
\$nonRecursiveUpdates = \$GLOBALS['batch_updates'];

\$GLOBALS['sort_queries']  = [];
\$GLOBALS['batch_updates'] = [];
api_tree_sort_branch(0, 1, false, true);

echo json_encode([
	'config_sets'                => \$GLOBALS['config_sets'],
	'delete_count'               => count(\$GLOBALS['deletes']),
	'non_recursive_min_queries'  => count(array_filter(\$nonRecursiveQueries, static fn (array \$q) : bool => str_contains(\$q['sql'], 'MIN(id)'))),
	'non_recursive_updates'      => \$nonRecursiveUpdates,
	'recursive_ordering_lookups' => count(array_filter(\$GLOBALS['sort_queries'], static fn (array \$q) : bool => str_contains(\$q['sql'], 'SELECT sort_children_type, parent'))),
	'recursive_updates'          => \$GLOBALS['batch_updates'],
]);
PHP);

	expect($result['config_sets'])->toBe(1);
	expect($result['delete_count'])->toBe(2);
	expect($result['non_recursive_min_queries'])->toBe(0);
	expect($result['non_recursive_updates'][0])->toContain('CASE id WHEN 101 THEN 1 WHEN 100 THEN 2 END');
	expect($result['recursive_ordering_lookups'])->toBe(0);
	expect($result['recursive_updates'])->toHaveCount(2);
});

/* =========================================================================
 * 2. lib/html_tree.php: graph listing, batch lookups, status class match
 * ========================================================================= */

test('get_host_graph_list does not leak non-templated graphs and labels each graph with its own template name', function () use ($repoRoot) : void {
	$libFile = var_export($repoRoot . '/lib/html_tree.php', true);

	$result = pr4RunPhpProbe(<<<PHP
define('HOST_GROUPING_GRAPH_TEMPLATE', 1);
define('HOST_GROUPING_DATA_QUERY_INDEX', 2);

\$GLOBALS['captured_where'] = [];

function __(\$s) { return \$s; }
function get_request_var(\$k) { return ''; }
function read_user_setting(\$k, \$default = '') { return 'off'; }
function naturally_sort_graphs(\$a, \$b) { return strnatcasecmp(\$a['title_cache'], \$b['title_cache']); }
function get_allowed_graph_templates(\$where, \$order, \$limit, &\$total_rows) {
	return [
		['id' => '5', 'name' => 'Interface Traffic'],
		['id' => '9', 'name' => 'CPU Usage'],
	];
}
function get_allowed_graphs(\$sql_where) {
	\$GLOBALS['captured_where'][] = \$sql_where;
	return [
		['local_graph_id' => 10, 'title_cache' => 'A - Traffic', 'template_name' => 'Interface Traffic'],
		['local_graph_id' => 11, 'title_cache' => 'B - CPU', 'template_name' => 'CPU Usage'],
		['local_graph_id' => 12, 'title_cache' => 'C - Custom', 'template_name' => null],
	];
}
function get_allowed_aggregate_graphs(\$sql_where) { return []; }

require_once $libFile;

get_host_graph_list(1, '5', -1, HOST_GROUPING_GRAPH_TEMPLATE);
\$all = get_host_graph_list(1, '-1', -1, HOST_GROUPING_GRAPH_TEMPLATE);
get_host_graph_list(1, '0', -1, HOST_GROUPING_GRAPH_TEMPLATE);

echo json_encode([
	'where_filtered' => \$GLOBALS['captured_where'][0],
	'where_all'      => \$GLOBALS['captured_where'][1],
	'where_zero'     => \$GLOBALS['captured_where'][2],
	'names'          => array_column(\$all, 'graph_template_name'),
]);
PHP);

	expect($result['where_filtered'])->toContain('gl.graph_template_id IN (5)');
	expect($result['where_filtered'])->not->toContain('0');
	expect($result['where_all'])->toContain('gl.graph_template_id IN (5, 9, 0)');
	expect($result['where_zero'])->toContain('gl.graph_template_id IN (0)');
	expect($result['names'])->toBe(['Interface Traffic', 'CPU Usage', '(Non Graph Template)']);
});

test('draw_dhtml_tree_level_graphing, create_site_branch, and get_device_leaf_class batch DB lookups cleanly', function () use ($repoRoot) : void {
	$libFile = var_export($repoRoot . '/lib/html_tree.php', true);

	$result = pr4RunPhpProbe(<<<PHP
define('HOST_UNKNOWN', 0);
define('HOST_DOWN', 1);
define('HOST_RECOVERING', 2);
define('HOST_UP', 3);
define('HOST_ERROR', 4);

\$config = ['base_path' => sys_get_temp_dir(), 'url_path' => '/'];
\$unique_id = 0;
\$GLOBALS['cell_queries']  = 0;
\$GLOBALS['assoc_queries'] = [];

@mkdir(sys_get_temp_dir() . '/lib', 0777, true);
file_put_contents(sys_get_temp_dir() . '/lib/data_query.php', "<?php\n");

function __(\$s) { return \$s; }
function html_escape(\$s) { return htmlspecialchars((string) \$s, ENT_QUOTES, 'UTF-8'); }
function strip_domain(\$s) { return \$s; }
function read_user_setting(\$k, \$default = '') { return 'off'; }
function get_allowed_tree_content(\$tree_id, \$parent) {
	return [
		['id' => 10, 'site_id' => 0, 'host_id' => 0, 'title' => 'Branch A'],
		['id' => 11, 'site_id' => 0, 'host_id' => 0, 'title' => 'Branch B'],
		['id' => 12, 'site_id' => 0, 'host_id' => 101, 'hostname' => 'host-1', 'host_grouping_type' => 1],
		['id' => 13, 'site_id' => 0, 'host_id' => 102, 'hostname' => 'host-2', 'host_grouping_type' => 1],
		['id' => 14, 'site_id' => 5, 'host_id' => 0, 'sitename' => 'HQ'],
	];
}
function get_allowed_site_devices(\$site_id, \$where, \$order) {
	return [
		['id' => 201, 'description' => 'sw1', 'host_template_id' => 1, 'host_template_name' => 'Switch', 'status' => HOST_UP],
		['id' => 202, 'description' => 'sw2', 'host_template_id' => 1, 'host_template_name' => 'Switch', 'status' => HOST_DOWN],
	];
}
function get_allowed_graph_templates(\$where, \$order, \$limit, &\$total_rows) { return []; }
function db_fetch_assoc(\$sql) {
	\$GLOBALS['assoc_queries'][] = \$sql;
	if (str_contains(\$sql, 'GROUP BY parent')) {
		return [['parent' => 10, 'totals' => 2]];
	}
	if (str_contains(\$sql, 'FROM host')) {
		return [['id' => 101, 'status' => HOST_UP], ['id' => 102, 'status' => HOST_DOWN]];
	}
	return [];
}
function db_fetch_cell_prepared(\$sql, \$params = []) {
	\$GLOBALS['cell_queries']++;
	return false;
}

require_once $libFile;

\$html = implode('', draw_dhtml_tree_level_graphing(1, 0));

echo json_encode([
	'cell_queries'        => \$GLOBALS['cell_queries'],
	'assoc_query_count'   => count(\$GLOBALS['assoc_queries']),
	'has_branch_a_closed' => str_contains(\$html, "id='tbranch-10' class='jstree-closed'"),
	'has_host_up'         => str_contains(\$html, "class='deviceUp'"),
	'has_host_down'       => str_contains(\$html, "class='deviceDown'"),
	'class_unknown'       => get_device_leaf_class(1, '0'),
	'class_missing'       => get_device_leaf_class(1, false),
]);
PHP);

	expect($result['cell_queries'])->toBe(0);
	expect($result['assoc_query_count'])->toBe(2);
	expect($result['has_branch_a_closed'])->toBeTrue();
	expect($result['has_host_up'])->toBeTrue();
	expect($result['has_host_down'])->toBeTrue();
	expect($result['class_unknown'])->toBe('deviceUnknown');
	expect($result['class_missing'])->toBe('');
});

/* =========================================================================
 * 3. lib/api_device.php & lib/api_graph.php
 * ========================================================================= */

test('api_device_enable_devices checks integer poller cache count and batches updates without array_merge', function () use ($repoRoot) : void {
	$libFile = var_export($repoRoot . '/lib/api_device.php', true);

	$result = pr4RunPhpProbe(<<<PHP
\$GLOBALS['buffer_calls'] = [];
\$GLOBALS['cache_count']  = '5';
\$GLOBALS['ds_count']     = 1200;

function db_fetch_cell_prepared(\$sql, \$params = []) {
	if (str_contains(\$sql, 'SELECT poller_id')) { return 1; }
	if (str_contains(\$sql, 'COUNT(local_data_id)')) { return \$GLOBALS['cache_count']; }
	return 0;
}
function db_execute_prepared(\$sql, \$params = []) {}
function db_fetch_assoc_prepared(\$sql, \$params = []) {
	return array_map(static fn (int \$i) : array => ['id' => \$i], range(1, \$GLOBALS['ds_count']));
}
function update_poller_cache(\$id) { return ['item_' . \$id]; }
function poller_update_poller_cache_from_buffer(\$local_data_ids, &\$poller_items, \$poller_id = 1) {
	\$GLOBALS['buffer_calls'][] = ['ids' => count(\$local_data_ids), 'items' => count(\$poller_items)];
}

require_once $libFile;

api_device_enable_devices([10]);
\$callsWhenPopulated = \$GLOBALS['buffer_calls'];

\$GLOBALS['cache_count'] = '0';
api_device_enable_devices([10]);

echo json_encode(['whenPopulated' => \$callsWhenPopulated, 'whenEmpty' => \$GLOBALS['buffer_calls']]);
PHP);

	expect($result['whenPopulated'])->toBe([]);
	expect($result['whenEmpty'])->toBe([
		['ids' => 500, 'items' => 500],
		['ids' => 500, 'items' => 500],
		['ids' => 200, 'items' => 200],
	]);
});

test('api_delete_graphs fixes SQL 3VL orphan cleanup, moves local_data_id to WHERE, and avoids double aggregate removal', function () use ($repoRoot) : void {
	$libFile = var_export($repoRoot . '/lib/api_graph.php', true);

	$result = pr4RunPhpProbe(<<<PHP
\$GLOBALS['sql_log']        = [];
\$GLOBALS['disassoc_calls'] = 0;
\$GLOBALS['ds_removed']     = [];

function set_config_option(\$k, \$v) {}
function api_plugin_hook_function(\$hook, \$args = []) {}
function array_to_sql_or(\$arr, \$col) { return \$col . ' IN (' . implode(', ', array_map('intval', \$arr)) . ')'; }
function api_aggregate_disassociate(\$ag, \$lgid) { \$GLOBALS['disassoc_calls']++; }
function api_data_source_remove_multi(\$ds) { \$GLOBALS['ds_removed'][] = array_values(\$ds); }
function db_execute(\$sql) {}
function db_fetch_assoc_prepared(\$sql, \$params = []) {
	return str_contains(\$sql, 'aggregate_graphs_items') ? [['aggregate_graph_id' => 99]] : [];
}
function db_fetch_assoc(\$sql) {
	\$GLOBALS['sql_log'][] = \$sql;
	return str_contains(\$sql, 'COUNT(DISTINCT gti.local_graph_id)') ? [] : [['local_data_id' => 500]];
}

require_once $libFile;

\$ids = [10, 11];
api_delete_graphs(\$ids, '2');

echo json_encode([
	'disassoc_calls' => \$GLOBALS['disassoc_calls'],
	'sql_log'        => \$GLOBALS['sql_log'],
	'ds_removed'     => \$GLOBALS['ds_removed'],
]);
PHP);

	expect($result['disassoc_calls'])->toBe(2);
	expect($result['ds_removed'])->toBe([[500]]);

	$countSql = $result['sql_log'][1];
	expect($countSql)->toContain('WHERE dtd.local_data_id IN (500)');
	expect($countSql)->toContain('HAVING graphs = 1');
	expect(substr($countSql, (int) strpos($countSql, 'HAVING')))->not->toContain('local_data_id');

	$orphanSql = $result['sql_log'][2];
	expect($orphanSql)->toContain('NOT EXISTS');
	expect($orphanSql)->not->toContain('gti.local_graph_id NOT IN');

	if (class_exists('SQLite3')) {
		$db = new SQLite3(':memory:');
		$db->exec('CREATE TABLE data_template_data (local_data_id INT)');
		$db->exec('CREATE TABLE data_template_rrd (id INT, local_data_id INT)');
		$db->exec('CREATE TABLE graph_templates_item (local_graph_id INT, task_item_id INT)');
		$db->exec('CREATE TABLE aggregate_graphs (local_graph_id INT)');
		$db->exec('INSERT INTO data_template_data VALUES (500), (501), (502)');
		$db->exec('INSERT INTO data_template_rrd VALUES (1, 500), (2, 501), (3, 502)');
		$db->exec('INSERT INTO aggregate_graphs VALUES (99)');
		$db->exec('INSERT INTO graph_templates_item VALUES (99, 2), (77, 3)');

		$sql   = str_replace('dtd.local_data_id IN (500)', 'dtd.local_data_id IN (500, 501, 502)', $orphanSql);
		$res   = $db->query($sql);
		$found = [];
		while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
			$found[] = (int) $row['local_data_id'];
		}
		sort($found);
		expect($found)->toBe([500, 501]);
		$db->close();
	}
});

/* =========================================================================
 * 4. lib/template.php & lib/aggregate.php
 * ========================================================================= */

test('resequence_graphs preserves $local_graph_id = 0 across repair loop so all graphs are resequenced', function () use ($repoRoot) : void {
	$libFile = var_export($repoRoot . '/lib/template.php', true);

	$result = pr4RunPhpProbe(<<<PHP
\$_SESSION = [];
\$GLOBALS['updates'] = [];

function cacti_log(\$msg, \$out = false) {}
function db_fetch_assoc_prepared(\$sql, \$params = []) {
	if (str_contains(\$sql, 'AND local_graph_id = 0')) {
		return [['id' => 10, 'sequence' => 1], ['id' => 11, 'sequence' => 2]];
	}
	if (str_contains(\$sql, 'FROM graph_local')) {
		return [['id' => 101], ['id' => 102], ['id' => 103]];
	}
	return [['id' => 1, 'local_graph_template_item_id' => 10], ['id' => 2, 'local_graph_template_item_id' => 11]];
}
function db_execute_prepared(\$sql, \$params = []) {
	\$GLOBALS['updates'][] = ['sql' => \$sql, 'params' => \$params];
}

require_once $libFile;
resequence_graphs(5, 0, true);

echo json_encode(['updates' => \$GLOBALS['updates']]);
PHP);

	expect($result['updates'])->toHaveCount(2);
	foreach ($result['updates'] as $u) {
		expect($u['sql'])->toContain('AND local_graph_id > 0');
		expect($u['sql'])->not->toContain('AND local_graph_id = ?');
	}
});

test('push_out_data_source_custom_data indexes input fields by data_template_data_id and updates all data sources', function () use ($repoRoot) : void {
	$libFile = var_export($repoRoot . '/lib/template.php', true);

	$result = pr4RunPhpProbe(<<<PHP
define('VALID_HOST_FIELDS', '(hostname|host_id)');
\$GLOBALS['inserts'] = [];

function db_qstr(\$s) { return "'" . \$s . "'"; }
function db_fetch_row_prepared(\$sql, \$params = []) { return ['id' => 1, 'data_input_id' => 7]; }
function db_fetch_assoc_prepared(\$sql, \$params = []) {
	if (str_contains(\$sql, 'WHERE hash IN')) { return []; }
	if (str_contains(\$sql, 'SELECT data_template_data.id')) { return [['id' => 100], ['id' => 101]]; }
	if (str_contains(\$sql, 'local_data_template_data_id = 0')) {
		return [['id' => 50, 'value' => 'new_val', 't_value' => '', 'type_code' => 'custom_arg']];
	}
	return [
		['id' => 50, 'value' => 'old_1', 't_value' => '', 'type_code' => 'custom_arg', 'data_template_data_id' => 100],
		['id' => 50, 'value' => 'old_2', 't_value' => '', 'type_code' => 'custom_arg', 'data_template_data_id' => 101],
	];
}
function db_execute(\$sql) { \$GLOBALS['inserts'][] = \$sql; }

require_once $libFile;
push_out_data_source_custom_data(7);

echo json_encode(['inserts' => \$GLOBALS['inserts']]);
PHP);

	expect($result['inserts'])->toHaveCount(1);
	expect($result['inserts'][0])->toContain("(50, 100, 'new_val')");
	expect($result['inserts'][0])->toContain("(50, 101, 'new_val')");
});

test('aggregate helpers fix get_next_sequence SQL, title cache update, stacked graph return, and per-aggregate pruning', function () use ($repoRoot) : void {
	$libFile = var_export($repoRoot . '/lib/aggregate.php', true);

	$result = pr4RunPhpProbe(<<<PHP
define('POLLER_VERBOSITY_DEVDBG', 6);
define('GRAPH_ITEM_TYPE_STACK', 8);

\$GLOBALS['queries']       = [];
\$GLOBALS['title_updated'] = [];
\$GLOBALS['deletes']       = [];

function cacti_log(\$msg, \$out = false, \$fac = '', \$lvl = 0) {}
function db_fetch_row_prepared(\$sql, \$params = []) {
	\$GLOBALS['queries'][] = ['fn' => 'row', 'sql' => \$sql, 'params' => \$params];
	if (str_contains(\$sql, 'FROM color_template_items') && \$params[0] == 42) { return ['sequence' => 9]; }
	if (str_contains(\$sql, 'FROM aggregate_graph_templates')) { return ['id' => 3, 'aggregate_template_id' => 3, 'name' => 'AggTpl']; }
	if (str_contains(\$sql, 'FROM graph_templates_graph')) { return ['graph_template_id' => 12]; }
	return [];
}
function db_fetch_assoc_prepared(\$sql, \$params = []) {
	\$GLOBALS['queries'][] = ['fn' => 'assoc', 'sql' => \$sql, 'params' => \$params];
	return str_contains(\$sql, 'FROM aggregate_graphs_items AS pagi') ? [['aggregate_graph_id' => 88, 'local_graph_id' => 701]] : [];
}
function db_fetch_cell_prepared(\$sql, \$params = []) {
	\$GLOBALS['queries'][] = ['fn' => 'cell', 'sql' => \$sql, 'params' => \$params];
	if (str_contains(\$sql, 'SELECT id') && str_contains(\$sql, 'FROM aggregate_graphs')) { return 88; }
	if (str_contains(\$sql, 'SELECT local_graph_id') && str_contains(\$sql, 'FROM aggregate_graphs')) { return 555; }
	return 0;
}
function db_execute(\$sql) { \$GLOBALS['deletes'][] = \$sql; }
function aggregate_graph_save(\$id, \$gt, \$name, \$agg_tpl) { return 321; }
function sql_save(\$save, \$table) { return 77; }
function update_graph_title_cache(\$lgid) { \$GLOBALS['title_updated'][] = \$lgid; }
function push_out_aggregates(\$tpl, \$lgid) {}

require_once $libFile;

\$seqFound    = get_next_sequence(42, 'sequence', 'color_template_items', 'color_template_id=7', 'color_template_id');
\$seqMissing  = get_next_sequence(99, 'sequence', 'color_template_items', 'color_template_id=7', 'color_template_id');
\$stackedZero = aggregate_is_stacked_graph(0);
api_aggregate_create('My Agg', [10], 3);
aggregate_prune_graphs(555);

echo json_encode([
	'seqFound'      => \$seqFound,
	'seqMissing'    => \$seqMissing,
	'stackedZero'   => \$stackedZero,
	'title_updated' => \$GLOBALS['title_updated'],
	'queries'       => \$GLOBALS['queries'],
	'deletes'       => \$GLOBALS['deletes'],
]);
PHP);

	expect($result['seqFound'])->toBe(9);
	expect($result['seqMissing'])->toBe(1);
	expect($result['stackedZero'])->toBeFalse();
	expect($result['title_updated'])->toBe([321]);

	$seqQueries = array_values(array_filter(
		$result['queries'],
		static fn (array $q) : bool => str_contains($q['sql'], 'FROM color_template_items')
	));
	expect($seqQueries[0]['sql'])->toContain('WHERE color_template_id = ?');
	expect($seqQueries[0]['params'])->toBe([42]);

	$pruneQueries = array_values(array_filter(
		$result['queries'],
		static fn (array $q) : bool => str_contains($q['sql'], 'FROM aggregate_graphs_items AS pagi')
	));
	expect($pruneQueries[0]['sql'])->toContain('INNER JOIN aggregate_graphs AS ag');
	expect($pruneQueries[0]['sql'])->toContain('AND ag.local_graph_id = ?');
	expect($pruneQueries[0]['params'])->toBe([555]);

	$pruneDeletes = array_values(array_filter(
		$result['deletes'],
		static fn (string $sql) : bool => str_contains($sql, 'DELETE FROM aggregate_graphs_items')
	));
	expect($pruneDeletes[0])->toContain('aggregate_graph_id IN (88)');
	expect($pruneDeletes[0])->toContain('local_graph_id IN (701)');
});
