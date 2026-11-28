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

$repoRoot = realpath(__DIR__ . '/../../..');

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

	file_put_contents($tmpFile, "<?php\nerror_reporting(E_ALL);\n" . $phpBody . "\n");

	try {
		$cmd = array(PHP_BINARY, '-d', 'display_errors=1', $tmpFile);
		$descriptors = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		);
		$proc = proc_open($cmd, $descriptors, $pipes);
		expect(is_resource($proc))->toBeTrue();

		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exitCode = proc_close($proc);

		expect($exitCode)->toBe(0, "Probe failed (stderr: $stderr, stdout: $stdout)");

		$decoded = json_decode((string) $stdout, true);
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

	$end = strpos($source, "\nfunction ", $start + strlen($signature));

	return substr($source, $start, $end !== false ? $end - $start : strlen($source) - $start);
}

/* =========================================================================
 * 1. lib/api_tree.php: SEC1-02 (api_tree_get_main authorization)
 * ========================================================================= */

test('api_tree_get_main enforces show_tree view permission, is_tree_allowed, and enabled trees', function () use ($repoRoot) {
	$libFile = var_export($repoRoot . '/lib/api_tree.php', true);

	$result = pr4RunPhpProbe(<<<PHP
\$GLOBALS['queries'] = array();
\$GLOBALS['view_allowed'] = false;
\$GLOBALS['tree_allowed'] = true;
\$GLOBALS['tree_name'] = 'Secret Tree';

function is_view_allowed(\$view = 'show_tree') {
	return \$GLOBALS['view_allowed'];
}
function is_tree_allowed(\$tree_id, \$user_id = 0) {
	return \$GLOBALS['tree_allowed'];
}
function cacti_sizeof(\$v) {
	return is_array(\$v) ? count(\$v) : 0;
}
function html_escape(\$s) {
	return htmlspecialchars((string) \$s, ENT_QUOTES, 'UTF-8');
}
function db_fetch_cell_prepared(\$sql, \$params = array()) {
	\$GLOBALS['queries'][] = array('sql' => \$sql, 'params' => \$params);
	return \$GLOBALS['tree_name'];
}
function draw_dhtml_tree_level_graphing(\$tree_id, \$parent = 0) {
	return array('<ul><li>child</li></ul>');
}

require_once $libFile;

// Case 1: show_tree denied
ob_start();
api_tree_get_main(5, -1);
\$outNoView = ob_get_clean();
\$queriesNoView = count(\$GLOBALS['queries']);

// Case 2: show_tree allowed, tree denied
\$GLOBALS['view_allowed'] = true;
\$GLOBALS['tree_allowed'] = false;
ob_start();
api_tree_get_main(5, -1);
\$outNoTree = ob_get_clean();
\$queriesNoTree = count(\$GLOBALS['queries']);

// Case 3: allowed, but tree disabled / not found
\$GLOBALS['tree_allowed'] = true;
\$GLOBALS['tree_name'] = false;
ob_start();
api_tree_get_main(5, -1);
\$outDisabled = ob_get_clean();
\$disabledSql = \$GLOBALS['queries'][0]['sql'];

// Case 4: allowed and enabled
\$GLOBALS['tree_name'] = 'Prod Tree';
ob_start();
api_tree_get_main(5, -1);
\$outAllowed = ob_get_clean();

echo json_encode(array(
	'outNoView' => \$outNoView,
	'queriesNoView' => \$queriesNoView,
	'outNoTree' => \$outNoTree,
	'queriesNoTree' => \$queriesNoTree,
	'outDisabled' => \$outDisabled,
	'disabledSql' => \$disabledSql,
	'outAllowed' => \$outAllowed,
));
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

/* =========================================================================
 * 2. lib/api_tree.php: ALGO2-12 & ALGO2-13 (move_node, sort_branch, delete_node_content)
 * ========================================================================= */

test('api_tree_move_node scopes sibling renumbering by graph_tree_id and ORDER BY position', function () use ($repoRoot) {
	$libFile = var_export($repoRoot . '/lib/api_tree.php', true);

	$result = pr4RunPhpProbe(<<<PHP
define('TREE_ORDERING_INHERIT', 0);
define('TREE_ORDERING_NONE', 1);
define('TREE_ORDERING_ALPHABETIC', 2);
define('TREE_ORDERING_NUMERIC', 3);
define('TREE_ORDERING_NATURAL', 4);

\$GLOBALS['selects'] = array();
\$GLOBALS['updates'] = array();

function input_validate_input_number(&\$v) { return true; }
function cacti_log(\$msg, \$out = false) {}
function cacti_sizeof(\$v) { return is_array(\$v) ? count(\$v) : 0; }
function set_config_option(\$k, \$v) {}
function db_fetch_cell_prepared(\$sql, \$params = array()) {
	if (strpos(\$sql, 'SELECT parent') !== false) {
		return 0;
	}
	if (strpos(\$sql, 'SELECT sort_type') !== false) {
		return TREE_ORDERING_NONE;
	}
	return 0;
}
function db_fetch_row_prepared(\$sql, \$params = array()) {
	if (strpos(\$sql, 'SELECT parent, graph_tree_id') !== false) {
		return array('parent' => 0, 'graph_tree_id' => 7);
	}
	return array();
}
function db_fetch_assoc_prepared(\$sql, \$params = array()) {
	\$GLOBALS['selects'][] = array('sql' => \$sql, 'params' => \$params);
	if (strpos(\$sql, 'AND position >= ?') !== false) {
		return array(array('id' => 20), array('id' => 21));
	}
	return array();
}
function db_execute_prepared(\$sql, \$params = array()) {
	\$GLOBALS['updates'][] = array('sql' => \$sql, 'params' => \$params);
}
function db_execute(\$sql) {
	\$GLOBALS['updates'][] = array('sql' => \$sql, 'params' => array());
}

require_once $libFile;

// Same-parent root move (parent 0 -> '#')
api_tree_move_node(7, 'tbranch:10', '#', 0);

// Cross-parent move to root (simulated by returning parent=99 for node 11)
echo json_encode(array(
	'selects' => \$GLOBALS['selects'],
	'updates' => \$GLOBALS['updates'],
));
PHP);

	$siblingSelects = array_values(array_filter(
		$result['selects'],
		fn ($q) => strpos($q['sql'], 'AND position >= ?') !== false
	));
	expect(count($siblingSelects))->toBe(1);
	expect($siblingSelects[0]['sql'])->toContain('graph_tree_id = ?');
	expect($siblingSelects[0]['sql'])->toContain('ORDER BY position ASC');
	expect($siblingSelects[0]['params'][0])->toBe(7);

	$siblingUpdates = array_values(array_filter(
		$result['updates'],
		fn ($q) => strpos($q['sql'], 'SET position = ?') !== false && strpos($q['sql'], 'graph_tree_id = ?') !== false
	));
	expect(count($siblingUpdates))->toBeGreaterThanOrEqual(3);

	// Also verify both branches of api_tree_move_node in source
	$apiTreeSrc = file_get_contents($repoRoot . '/lib/api_tree.php');
	$moveBody = pr4ExtractFunctionBody($apiTreeSrc, 'function api_tree_move_node(');
	expect(substr_count($moveBody, 'ORDER BY position ASC'))->toBe(2);
	expect(substr_count($moveBody, "WHERE graph_tree_id = ?\n\t\t\tAND parent = ?"))->toBe(2);
	expect(substr_count($moveBody, 'api_tree_sort_branch($data[\'leaf_id\'], $tree_id, true, false)'))->toBe(2);
});

test('api_tree_delete_node_content hoists set_config_option out of recursion and api_tree_sort_branch avoids cascading re-sorts', function () use ($repoRoot) {
	$libFile = var_export($repoRoot . '/lib/api_tree.php', true);

	$result = pr4RunPhpProbe(<<<PHP
define('TREE_ORDERING_INHERIT', 0);
define('TREE_ORDERING_NONE', 1);
define('TREE_ORDERING_ALPHABETIC', 2);
define('TREE_ORDERING_NUMERIC', 3);
define('TREE_ORDERING_NATURAL', 4);

\$GLOBALS['config_sets'] = 0;
\$GLOBALS['deletes'] = array();
\$GLOBALS['sort_queries'] = array();
\$GLOBALS['batch_updates'] = array();

function cacti_sizeof(\$v) { return is_array(\$v) ? count(\$v) : 0; }
function cacti_log(\$msg, \$out = false) {}
function set_config_option(\$k, \$v) {
	if (\$k === 'time_last_change_branch') {
		\$GLOBALS['config_sets']++;
	}
}
function db_fetch_assoc_prepared(\$sql, \$params = array()) {
	if (strpos(\$sql, 'SELECT id, host_id, local_graph_id') !== false) {
		\$parent = \$params[1];
		if (\$parent == 1) {
			return array(
				array('id' => 2, 'host_id' => 0, 'local_graph_id' => 0),
				array('id' => 3, 'host_id' => 10, 'local_graph_id' => 0),
			);
		}
		if (\$parent == 2) {
			return array(
				array('id' => 4, 'host_id' => 11, 'local_graph_id' => 0),
				array('id' => 5, 'host_id' => 12, 'local_graph_id' => 0),
			);
		}
		return array();
	}

	\$GLOBALS['sort_queries'][] = array('sql' => \$sql, 'params' => \$params);
	if (strpos(\$sql, 'SELECT id, title, position, sort_children_type') !== false && \$params[0] == 0) {
		return array(
			array('id' => 100, 'title' => 'Beta', 'position' => 1, 'sort_children_type' => TREE_ORDERING_INHERIT),
			array('id' => 101, 'title' => 'Alpha', 'position' => 2, 'sort_children_type' => TREE_ORDERING_INHERIT),
		);
	}
	if (strpos(\$sql, 'MIN(id) AS first_child') !== false) {
		return array(
			array('parent' => 100, 'first_child' => 200),
		);
	}
	if (strpos(\$sql, 'SELECT id, title, position, sort_children_type') !== false && \$params[0] == 100) {
		return array(
			array('id' => 200, 'title' => 'Child2', 'position' => 1, 'sort_children_type' => TREE_ORDERING_NONE),
			array('id' => 201, 'title' => 'Child1', 'position' => 2, 'sort_children_type' => TREE_ORDERING_NONE),
		);
	}
	return array();
}
function db_fetch_row_prepared(\$sql, \$params = array()) {
	if (\$params[0] == 200) {
		return array('parent' => 100, 'graph_tree_id' => 1);
	}
	return array('parent' => 0, 'graph_tree_id' => 1);
}
function db_fetch_cell_prepared(\$sql, \$params = array()) {
	\$GLOBALS['sort_queries'][] = array('sql' => \$sql, 'params' => \$params);
	if (strpos(\$sql, 'SELECT sort_type') !== false) {
		return TREE_ORDERING_NATURAL;
	}
	return TREE_ORDERING_NATURAL;
}
function db_execute_prepared(\$sql, \$params = array()) {
	if (strpos(\$sql, 'DELETE') !== false) {
		\$GLOBALS['deletes'][] = array('sql' => \$sql, 'params' => \$params);
	}
}
function db_execute(\$sql) {
	\$GLOBALS['batch_updates'][] = \$sql;
}

require_once $libFile;

// 1) Recursive delete across 4 descendants must call set_config_option once
api_tree_delete_node_content(1, 1);

// 2) Sort with recurse=false must NOT query MIN(id) / child branches
api_tree_sort_branch(0, 1, false, false);
\$nonRecursiveQueries = \$GLOBALS['sort_queries'];
\$nonRecursiveUpdates = \$GLOBALS['batch_updates'];

// 3) Sort with recurse=true reuses sort_style without calling api_tree_get_branch_ordering
\$GLOBALS['sort_queries'] = array();
\$GLOBALS['batch_updates'] = array();
api_tree_sort_branch(0, 1, false, true);

echo json_encode(array(
	'config_sets' => \$GLOBALS['config_sets'],
	'delete_count' => count(\$GLOBALS['deletes']),
	'non_recursive_min_queries' => count(array_filter(\$nonRecursiveQueries, function (\$q) {
		return strpos(\$q['sql'], 'MIN(id)') !== false;
	})),
	'non_recursive_updates' => \$nonRecursiveUpdates,
	'recursive_ordering_lookups' => count(array_filter(\$GLOBALS['sort_queries'], function (\$q) {
		return strpos(\$q['sql'], 'SELECT sort_children_type, parent') !== false;
	})),
	'recursive_updates' => \$GLOBALS['batch_updates'],
));
PHP);

	expect($result['config_sets'])->toBe(1);
	expect($result['delete_count'])->toBe(2);
	expect($result['non_recursive_min_queries'])->toBe(0);
	expect($result['non_recursive_updates'][0])->toContain('CASE id WHEN 101 THEN 1 WHEN 100 THEN 2 END');
	expect($result['recursive_ordering_lookups'])->toBe(0);
	expect(count($result['recursive_updates']))->toBe(2);
});

/* =========================================================================
 * 3. lib/html_tree.php: ALGO2-14 & ALGO2-15
 * ========================================================================= */

test('get_host_graph_list does not leak non-templated graphs and labels each graph with its own template name', function () use ($repoRoot) {
	$libFile = var_export($repoRoot . '/lib/html_tree.php', true);

	$result = pr4RunPhpProbe(<<<PHP
define('HOST_GROUPING_GRAPH_TEMPLATE', 1);
define('HOST_GROUPING_DATA_QUERY_INDEX', 2);

\$GLOBALS['captured_where'] = array();

function __(\$s) { return \$s; }
function cacti_sizeof(\$v) { return is_array(\$v) ? count(\$v) : 0; }
function get_request_var(\$k) { return ''; }
function read_user_setting(\$k, \$default = '') { return 'off'; }
function naturally_sort_graphs(\$a, \$b) { return strnatcasecmp(\$a['title_cache'], \$b['title_cache']); }
function get_allowed_graph_templates(\$where, \$order, \$limit, &\$total_rows) {
	return array(
		array('id' => '5', 'name' => 'Interface Traffic'),
		array('id' => '9', 'name' => 'CPU Usage'),
	);
}
function get_allowed_graphs(\$sql_where) {
	\$GLOBALS['captured_where'][] = \$sql_where;
	return array(
		array('local_graph_id' => 10, 'title_cache' => 'A - Traffic', 'template_name' => 'Interface Traffic'),
		array('local_graph_id' => 11, 'title_cache' => 'B - CPU', 'template_name' => 'CPU Usage'),
		array('local_graph_id' => 12, 'title_cache' => 'C - Custom', 'template_name' => null),
	);
}
function get_allowed_aggregate_graphs(\$sql_where) { return array(); }

require_once $libFile;

// Filtered to template 5 only: must NOT include template 0 in IN (...)
\$filtered = get_host_graph_list(1, '5', -1, HOST_GROUPING_GRAPH_TEMPLATE);

// All templates (-1): must include 5, 9, and 0, and preserve each graph's own template name
\$all = get_host_graph_list(1, '-1', -1, HOST_GROUPING_GRAPH_TEMPLATE);

// Explicitly non-templated ('0')
\$onlyZero = get_host_graph_list(1, '0', -1, HOST_GROUPING_GRAPH_TEMPLATE);

echo json_encode(array(
	'where_filtered' => \$GLOBALS['captured_where'][0],
	'where_all' => \$GLOBALS['captured_where'][1],
	'where_zero' => \$GLOBALS['captured_where'][2],
	'names' => array_column(\$all, 'graph_template_name'),
));
PHP);

	expect($result['where_filtered'])->toContain('gl.graph_template_id IN (5)');
	expect($result['where_filtered'])->not->toContain('0');
	expect($result['where_all'])->toContain('gl.graph_template_id IN (5, 9, 0)');
	expect($result['where_zero'])->toContain('gl.graph_template_id IN (0)');
	expect($result['names'])->toBe(array('Interface Traffic', 'CPU Usage', '(Non Graph Template)'));
});

test('draw_dhtml_tree_level_graphing and create_site_branch batch DB lookups and avoid array_merge in loops', function () use ($repoRoot) {
	$libFile = var_export($repoRoot . '/lib/html_tree.php', true);

	$result = pr4RunPhpProbe(<<<PHP
define('HOST_UP', 3);
define('HOST_DOWN', 1);
define('HOST_RECOVERING', 2);
define('HOST_UNKNOWN', 0);
define('HOST_ERROR', 4);

\$config = array('base_path' => sys_get_temp_dir(), 'url_path' => '/');
\$unique_id = 0;
\$GLOBALS['cell_queries'] = 0;
\$GLOBALS['assoc_queries'] = array();

// Create a no-op stub for data_query.php so include_once succeeds
@mkdir(sys_get_temp_dir() . '/lib', 0777, true);
file_put_contents(sys_get_temp_dir() . '/lib/data_query.php', "<?php\n");

function __(\$s) { return \$s; }
function cacti_sizeof(\$v) { return is_array(\$v) ? count(\$v) : 0; }
function html_escape(\$s) { return htmlspecialchars((string) \$s, ENT_QUOTES, 'UTF-8'); }
function strip_domain(\$s) { return \$s; }
function read_user_setting(\$k, \$default = '') { return 'off'; }
function array_rekey(\$array, \$key, \$value) {
	\$ret = array();
	if (is_array(\$array)) {
		foreach (\$array as \$item) {
			\$ret[\$item[\$key]] = \$item[\$value];
		}
	}
	return \$ret;
}
function get_allowed_tree_content(\$tree_id, \$parent) {
	return array(
		array('id' => 10, 'site_id' => 0, 'host_id' => 0, 'title' => 'Branch A'),
		array('id' => 11, 'site_id' => 0, 'host_id' => 0, 'title' => 'Branch B'),
		array('id' => 12, 'site_id' => 0, 'host_id' => 101, 'hostname' => 'host-1', 'host_grouping_type' => 1),
		array('id' => 13, 'site_id' => 0, 'host_id' => 102, 'hostname' => 'host-2', 'host_grouping_type' => 1),
		array('id' => 14, 'site_id' => 5, 'host_id' => 0, 'sitename' => 'HQ'),
	);
}
function get_allowed_site_devices(\$site_id, \$where, \$order) {
	return array(
		array('id' => 201, 'description' => 'sw1', 'host_template_id' => 1, 'host_template_name' => 'Switch', 'status' => HOST_UP),
		array('id' => 202, 'description' => 'sw2', 'host_template_id' => 1, 'host_template_name' => 'Switch', 'status' => HOST_DOWN),
	);
}
function get_allowed_graph_templates(\$where, \$order, \$limit, &\$total_rows) { return array(); }
function db_fetch_assoc(\$sql) {
	\$GLOBALS['assoc_queries'][] = \$sql;
	if (strpos(\$sql, 'GROUP BY parent') !== false) {
		return array(array('parent' => 10, 'totals' => 2));
	}
	if (strpos(\$sql, 'FROM host') !== false) {
		return array(
			array('id' => 101, 'status' => HOST_UP),
			array('id' => 102, 'status' => HOST_DOWN),
		);
	}
	return array();
}
function db_fetch_cell_prepared(\$sql, \$params = array()) {
	\$GLOBALS['cell_queries']++;
	return 0;
}

require_once $libFile;

\$html = implode('', draw_dhtml_tree_level_graphing(1, 0));

echo json_encode(array(
	'cell_queries' => \$GLOBALS['cell_queries'],
	'assoc_query_count' => count(\$GLOBALS['assoc_queries']),
	'has_branch_a_closed' => strpos(\$html, "id='tbranch-10' class='jstree-closed'") !== false,
	'has_host_up' => strpos(\$html, "class='deviceUp'") !== false,
	'has_host_down' => strpos(\$html, "class='deviceDown'") !== false,
));
PHP);

	expect($result['cell_queries'])->toBe(0);
	expect($result['assoc_query_count'])->toBe(2);
	expect($result['has_branch_a_closed'])->toBeTrue();
	expect($result['has_host_up'])->toBeTrue();
	expect($result['has_host_down'])->toBeTrue();

	// Structural check: no array_merge in draw_dhtml_tree_level_graphing/create_site_branch and single DISTINCT query in grow_right_pane_tree
	$htmlTreeSrc = file_get_contents($repoRoot . '/lib/html_tree.php');
	expect(pr4ExtractFunctionBody($htmlTreeSrc, 'function draw_dhtml_tree_level_graphing('))->not->toContain('array_merge(');
	expect(pr4ExtractFunctionBody($htmlTreeSrc, 'function create_site_branch('))->not->toContain('array_merge(');
	$rightPaneBody = pr4ExtractFunctionBody($htmlTreeSrc, 'function grow_right_pane_tree(');
	expect($rightPaneBody)->toContain('SELECT DISTINCT graph_template_id');
	expect($rightPaneBody)->not->toContain('WHERE graph_template_id = ? LIMIT 1');
});

/* =========================================================================
 * 4. lib/api_device.php: ALGO2-09, ALGO2-10, ALGO2-11, ALGO2-31, ALGO2-32
 * ========================================================================= */

test('api_device_enable_devices checks integer poller cache count and batches updates without array_merge', function () use ($repoRoot) {
	$libFile = var_export($repoRoot . '/lib/api_device.php', true);

	$result = pr4RunPhpProbe(<<<PHP
\$GLOBALS['buffer_calls'] = array();
\$GLOBALS['cache_count'] = '5';
\$GLOBALS['ds_count'] = 1200;

function cacti_sizeof(\$v) { return is_array(\$v) ? count(\$v) : 0; }
function db_fetch_cell_prepared(\$sql, \$params = array()) {
	if (strpos(\$sql, 'SELECT poller_id') !== false) {
		return 1;
	}
	if (strpos(\$sql, 'COUNT(local_data_id)') !== false) {
		return \$GLOBALS['cache_count'];
	}
	return 0;
}
function db_execute_prepared(\$sql, \$params = array()) {}
function db_fetch_assoc_prepared(\$sql, \$params = array()) {
	\$rows = array();
	for (\$i = 1; \$i <= \$GLOBALS['ds_count']; \$i++) {
		\$rows[] = array('id' => \$i);
	}
	return \$rows;
}
function update_poller_cache(\$id) {
	return array('item_' . \$id);
}
function poller_update_poller_cache_from_buffer(\$local_data_ids, &\$poller_items, \$poller_id = 1) {
	\$GLOBALS['buffer_calls'][] = array(
		'ids' => count(\$local_data_ids),
		'items' => count(\$poller_items),
	);
}

require_once $libFile;

// When poller_item COUNT is '5', must NOT rebuild poller cache
api_device_enable_devices(array(10));
\$callsWhenPopulated = \$GLOBALS['buffer_calls'];

// When poller_item COUNT is '0', must rebuild in batches of 500 (500 + 500 + 200 = 1200)
\$GLOBALS['cache_count'] = '0';
api_device_enable_devices(array(10));
\$callsWhenEmpty = \$GLOBALS['buffer_calls'];

echo json_encode(array(
	'whenPopulated' => \$callsWhenPopulated,
	'whenEmpty' => \$callsWhenEmpty,
));
PHP);

	expect($result['whenPopulated'])->toBe(array());
	expect($result['whenEmpty'])->toBe(array(
		array('ids' => 500, 'items' => 500),
		array('ids' => 500, 'items' => 500),
		array('ids' => 200, 'items' => 200),
	));
});

test('api_device_remove, purge_deleted, update_host_template, and clone_device_template bind scoped parameters', function () use ($repoRoot) {
	$src = file_get_contents($repoRoot . '/lib/api_device.php');

	// ALGO2-31: reports_items binds integer device_id, not "$device_id:%"
	$removeBody = pr4ExtractFunctionBody($src, 'function api_device_remove(');
	expect($removeBody)->toContain("DELETE FROM reports_items    WHERE host_id = ?', array(\$device_id)");
	expect($removeBody)->not->toContain("DELETE FROM reports_items    WHERE host_id = ?', array(\$device_id . ':%')");

	$purgeBody = pr4ExtractFunctionBody($src, 'function api_device_purge_deleted_devices(');
	expect($purgeBody)->toContain("DELETE FROM reports_items    WHERE host_id = ?', array(\$d['id'])");
	expect($purgeBody)->not->toContain("DELETE FROM reports_items    WHERE host_id = ?', array(\$d['id'] . ':%')");

	// ALGO2-32: api_device_update_host_template filters host_graph by hg.host_id = ?
	$updateHtBody = pr4ExtractFunctionBody($src, 'function api_device_update_host_template(');
	expect($updateHtBody)->toContain('WHERE hg.host_id = ?');
	expect($updateHtBody)->toContain('array($device_id, $device_template_id, $device_id)');

	// ALGO2-11: api_clone_device_template scopes task_item_id UPDATE to cloned graph_template_id and local_graph_id = 0
	$cloneBody = pr4ExtractFunctionBody($src, 'function api_clone_device_template(');
	expect($cloneBody)->toContain('AND graph_template_id = ?');
	expect($cloneBody)->toContain('AND local_graph_id = 0');
	expect($cloneBody)->toContain("array(\$dt_rrd_id, \$rrd['data_template_rrd_id'], \$new_gt)");

	// ALGO2-09/10: no array_merge or cacti_sizeof($poller_cache) in api_device_enable_devices
	$enableBody = pr4ExtractFunctionBody($src, 'function api_device_enable_devices(');
	expect($enableBody)->not->toContain('array_merge(');
	expect($enableBody)->not->toContain('cacti_sizeof($poller_cache)');
});

/* =========================================================================
 * 5. lib/api_graph.php: ALGO2-07 & ALGO2-08
 * ========================================================================= */

test('api_delete_graphs fixes SQL 3VL orphan cleanup, moves local_data_id to WHERE, and avoids double aggregate removal', function () use ($repoRoot) {
	$libFile = var_export($repoRoot . '/lib/api_graph.php', true);

	$result = pr4RunPhpProbe(<<<PHP
\$GLOBALS['sql_log'] = array();
\$GLOBALS['disassoc_calls'] = 0;
\$GLOBALS['ds_removed'] = array();

function cacti_sizeof(\$v) { return is_array(\$v) ? count(\$v) : 0; }
function set_config_option(\$k, \$v) {}
function api_plugin_hook_function(\$hook, \$args = array()) {}
function array_rekey(\$array, \$key, \$value) {
	\$ret = array();
	if (is_array(\$array)) {
		foreach (\$array as \$item) {
			\$ret[\$item[\$key]] = \$item[\$value];
		}
	}
	return \$ret;
}
function array_to_sql_or(\$arr, \$col) {
	return \$col . ' IN (' . implode(', ', array_map('intval', \$arr)) . ')';
}
function api_aggregate_disassociate(\$ag, \$lgid) {
	\$GLOBALS['disassoc_calls']++;
}
function api_data_source_remove_multi(\$ds) {
	\$GLOBALS['ds_removed'][] = array_values(\$ds);
}
function db_execute(\$sql) {}
function db_fetch_assoc_prepared(\$sql, \$params = array()) {
	if (strpos(\$sql, 'aggregate_graphs_items') !== false) {
		return array(array('aggregate_graph_id' => 99));
	}
	return array();
}
function db_fetch_assoc(\$sql) {
	\$GLOBALS['sql_log'][] = \$sql;
	if (strpos(\$sql, 'COUNT(DISTINCT gti.local_graph_id)') !== false) {
		return array();
	}
	if (strpos(\$sql, 'LEFT JOIN graph_templates_item') !== false) {
		return array(array('local_data_id' => 500));
	}
	return array(array('local_data_id' => 500));
}

require_once $libFile;

\$ids = array(10, 11);
api_delete_graphs(\$ids, '2');

echo json_encode(array(
	'disassoc_calls' => \$GLOBALS['disassoc_calls'],
	'sql_log' => \$GLOBALS['sql_log'],
	'ds_removed' => \$GLOBALS['ds_removed'],
));
PHP);

	// Called once per deleted graph (2 total), NOT doubled to 4
	expect($result['disassoc_calls'])->toBe(2);
	expect($result['ds_removed'])->toBe(array(array(500)));

	// Second query filters dtd.local_data_id in WHERE, not HAVING
	$countSql = $result['sql_log'][1];
	expect($countSql)->toContain('WHERE dtd.local_data_id IN (500)');
	expect($countSql)->toContain('HAVING graphs = 1');
	expect(substr($countSql, strpos($countSql, 'HAVING')))->not->toContain('local_data_id');

	// Third query avoids NULL NOT IN (...) 3VL trap and evaluates correctly on real SQLite
	$orphanSql = $result['sql_log'][2];
	expect($orphanSql)->toContain('NOT EXISTS');
	expect($orphanSql)->not->toContain('gti.local_graph_id NOT IN');

	if (class_exists('SQLite3')) {
		$db = new SQLite3(':memory:');
		$db->exec('CREATE TABLE data_template_data (local_data_id INT)');
		$db->exec('CREATE TABLE data_template_rrd (id INT, local_data_id INT)');
		$db->exec('CREATE TABLE graph_templates_item (local_graph_id INT, task_item_id INT)');
		$db->exec('CREATE TABLE aggregate_graphs (local_graph_id INT)');

		// DS 500 is orphaned (no remaining normal gti); DS 501 is referenced only by aggregate 99; DS 502 is still used by normal graph 77
		$db->exec('INSERT INTO data_template_data VALUES (500), (501), (502)');
		$db->exec('INSERT INTO data_template_rrd VALUES (1, 500), (2, 501), (3, 502)');
		$db->exec('INSERT INTO aggregate_graphs VALUES (99)');
		$db->exec('INSERT INTO graph_templates_item VALUES (99, 2), (77, 3)');

		$sql = str_replace('dtd.local_data_id IN (500)', 'dtd.local_data_id IN (500, 501, 502)', $orphanSql);
		$res = $db->query($sql);
		$found = array();
		while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
			$found[] = (int) $row['local_data_id'];
		}
		sort($found);
		expect($found)->toBe(array(500, 501));
		$db->close();
	}
});

/* =========================================================================
 * 6. lib/template.php: ALGO2-05 & ALGO2-06
 * ========================================================================= */

test('resequence_graphs preserves $local_graph_id = 0 across repair loop so all graphs are resequenced', function () use ($repoRoot) {
	$libFile = var_export($repoRoot . '/lib/template.php', true);

	$result = pr4RunPhpProbe(<<<PHP
\$_SESSION = array();
\$GLOBALS['updates'] = array();

function cacti_sizeof(\$v) { return is_array(\$v) ? count(\$v) : 0; }
function cacti_log(\$msg, \$out = false) {}
function array_rekey(\$array, \$key, \$value) {
	\$ret = array();
	foreach (\$array as \$item) {
		\$ret[\$item[\$key]] = \$item[\$value];
	}
	return \$ret;
}
function db_fetch_assoc_prepared(\$sql, \$params = array()) {
	if (strpos(\$sql, 'AND local_graph_id = 0') !== false) {
		return array(
			array('id' => 10, 'sequence' => 1),
			array('id' => 11, 'sequence' => 2),
		);
	}
	if (strpos(\$sql, 'FROM graph_local') !== false) {
		return array(
			array('id' => 101),
			array('id' => 102),
			array('id' => 103),
		);
	}
	// Graph items match template count (2 items)
	return array(
		array('id' => 1, 'local_graph_template_item_id' => 10),
		array('id' => 2, 'local_graph_template_item_id' => 11),
	);
}
function db_execute_prepared(\$sql, \$params = array()) {
	\$GLOBALS['updates'][] = array('sql' => \$sql, 'params' => \$params);
}

require_once $libFile;

resequence_graphs(5, 0, true);

echo json_encode(array('updates' => \$GLOBALS['updates']));
PHP);

	expect(count($result['updates']))->toBe(2);
	foreach ($result['updates'] as $u) {
		expect($u['sql'])->toContain('AND local_graph_id > 0');
		expect($u['sql'])->not->toContain('AND local_graph_id = ?');
	}
});

test('push_out_data_source_custom_data indexes input fields by data_template_data_id and updates all data sources', function () use ($repoRoot) {
	$libFile = var_export($repoRoot . '/lib/template.php', true);

	$result = pr4RunPhpProbe(<<<PHP
define('VALID_HOST_FIELDS', '(hostname|host_id)');
\$GLOBALS['inserts'] = array();

function cacti_sizeof(\$v) { return is_array(\$v) ? count(\$v) : 0; }
function db_qstr(\$s) { return "'" . \$s . "'"; }
function array_rekey(\$array, \$key, \$value) {
	\$ret = array();
	if (is_array(\$array)) {
		foreach (\$array as \$item) {
			if (is_array(\$value)) {
				foreach (\$value as \$v) {
					\$ret[\$item[\$key]][\$v] = \$item[\$v];
				}
			} else {
				\$ret[\$item[\$key]] = \$item[\$value];
			}
		}
	}
	return \$ret;
}
function db_fetch_row_prepared(\$sql, \$params = array()) {
	return array('id' => 1, 'data_input_id' => 7);
}
function db_fetch_assoc_prepared(\$sql, \$params = array()) {
	if (strpos(\$sql, 'WHERE hash IN') !== false) {
		return array();
	}
	if (strpos(\$sql, 'SELECT data_template_data.id') !== false) {
		return array(
			array('id' => 100),
			array('id' => 101),
		);
	}
	if (strpos(\$sql, 'local_data_template_data_id = 0') !== false) {
		return array(
			array('id' => 50, 'value' => 'new_val', 't_value' => '', 'type_code' => 'custom_arg'),
		);
	}
	return array(
		array('id' => 50, 'value' => 'old_1', 't_value' => '', 'type_code' => 'custom_arg', 'data_template_data_id' => 100),
		array('id' => 50, 'value' => 'old_2', 't_value' => '', 'type_code' => 'custom_arg', 'data_template_data_id' => 101),
	);
}
function db_execute(\$sql) {
	\$GLOBALS['inserts'][] = \$sql;
}

require_once $libFile;

push_out_data_source_custom_data(7);

echo json_encode(array('inserts' => \$GLOBALS['inserts']));
PHP);

	expect(count($result['inserts']))->toBe(1);
	expect($result['inserts'][0])->toContain("(50, 100, 'new_val')");
	expect($result['inserts'][0])->toContain("(50, 101, 'new_val')");

	// Ensure O(N^2) inner scan over $input_fields is eliminated
	$templateSrc = file_get_contents($repoRoot . '/lib/template.php');
	$pushBody = pr4ExtractFunctionBody($templateSrc, 'function push_out_data_source_custom_data(');
	expect($pushBody)->toContain('$input_fields_by_dtd');
});

/* =========================================================================
 * 7. lib/aggregate.php & lib/api_aggregate.php: ALGO2-22
 * ========================================================================= */

test('aggregate helpers fix get_next_sequence SQL, title cache update, stacked graph return, per-aggregate pruning, and template filter', function () use ($repoRoot) {
	$libFile = var_export($repoRoot . '/lib/aggregate.php', true);

	$result = pr4RunPhpProbe(<<<PHP
define('POLLER_VERBOSITY_DEVDBG', 6);
define('GRAPH_ITEM_TYPE_STACK', 8);

\$GLOBALS['queries'] = array();
\$GLOBALS['title_updated'] = array();
\$GLOBALS['pushed_out'] = array();
\$GLOBALS['deletes'] = array();

function cacti_log(\$msg, \$out = false, \$fac = '', \$lvl = 0) {}
function cacti_sizeof(\$v) { return is_array(\$v) ? count(\$v) : 0; }
function array_rekey(\$array, \$key, \$value) {
	\$ret = array();
	if (is_array(\$array)) {
		foreach (\$array as \$item) {
			\$ret[\$item[\$key]] = \$item[\$value];
		}
	}
	return \$ret;
}
function db_fetch_row_prepared(\$sql, \$params = array()) {
	\$GLOBALS['queries'][] = array('fn' => 'row', 'sql' => \$sql, 'params' => \$params);
	if (strpos(\$sql, 'FROM color_template_items') !== false && \$params[0] == 42) {
		return array('sequence' => 9);
	}
	if (strpos(\$sql, 'FROM aggregate_graph_templates') !== false) {
		return array('id' => 3, 'aggregate_template_id' => 3, 'name' => 'AggTpl');
	}
	if (strpos(\$sql, 'FROM graph_templates_graph') !== false) {
		return array('graph_template_id' => 12);
	}
	return array();
}
function db_fetch_assoc_prepared(\$sql, \$params = array()) {
	\$GLOBALS['queries'][] = array('fn' => 'assoc', 'sql' => \$sql, 'params' => \$params);
	if (strpos(\$sql, 'FROM aggregate_graphs_items AS pagi') !== false) {
		return array(
			array('aggregate_graph_id' => 88, 'local_graph_id' => 701),
		);
	}
	return array();
}
function db_fetch_cell_prepared(\$sql, \$params = array()) {
	\$GLOBALS['queries'][] = array('fn' => 'cell', 'sql' => \$sql, 'params' => \$params);
	if (strpos(\$sql, 'SELECT id') !== false && strpos(\$sql, 'FROM aggregate_graphs') !== false) {
		return 88;
	}
	if (strpos(\$sql, 'SELECT local_graph_id') !== false && strpos(\$sql, 'FROM aggregate_graphs') !== false) {
		return 555;
	}
	return 0;
}
function db_execute(\$sql) {
	\$GLOBALS['deletes'][] = \$sql;
}
function aggregate_graph_save(\$id, \$gt, \$name, \$agg_tpl) {
	return 321;
}
function sql_save(\$save, \$table) {
	return 77;
}
function update_graph_title_cache(\$lgid) {
	\$GLOBALS['title_updated'][] = \$lgid;
}
function push_out_aggregates(\$tpl, \$lgid) {
	\$GLOBALS['pushed_out'][] = array(\$tpl, \$lgid);
}

require_once $libFile;

// 1) get_next_sequence binds \$id instead of literal 'id' column
\$seqFound = get_next_sequence(42, 'sequence', 'color_template_items', 'color_template_id=7', 'color_template_id');
\$seqMissing = get_next_sequence(99, 'sequence', 'color_template_items', 'color_template_id=7', 'color_template_id');

// 2) aggregate_is_stacked_graph(0) returns false cleanly with E_ALL (no undefined variable warning)
\$stackedZero = aggregate_is_stacked_graph(0);

// 3) api_aggregate_create updates graph title cache for \$local_graph_id
api_aggregate_create('My Agg', array(10), 3);

// 4) aggregate_prune_graphs(\$agg_local_graph_id) joins aggregate_graphs and filters ag.local_graph_id = ?
aggregate_prune_graphs(555);

echo json_encode(array(
	'seqFound' => \$seqFound,
	'seqMissing' => \$seqMissing,
	'stackedZero' => \$stackedZero,
	'title_updated' => \$GLOBALS['title_updated'],
	'queries' => \$GLOBALS['queries'],
	'deletes' => \$GLOBALS['deletes'],
));
PHP);

	expect($result['seqFound'])->toBe(9);
	expect($result['seqMissing'])->toBe(1);
	expect($result['stackedZero'])->toBeFalse();
	expect($result['title_updated'])->toBe(array(321));

	// Verify get_next_sequence used parameterized WHERE key_field = ?
	$seqQueries = array_values(array_filter(
		$result['queries'],
		fn ($q) => strpos($q['sql'], 'FROM color_template_items') !== false
	));
	expect($seqQueries[0]['sql'])->toContain('WHERE color_template_id = ?');
	expect($seqQueries[0]['params'])->toBe(array(42));

	// Verify aggregate_prune_graphs joined aggregate_graphs on ag.local_graph_id = ?
	$pruneQueries = array_values(array_filter(
		$result['queries'],
		fn ($q) => strpos($q['sql'], 'FROM aggregate_graphs_items AS pagi') !== false
	));
	expect($pruneQueries[0]['sql'])->toContain('INNER JOIN aggregate_graphs AS ag');
	expect($pruneQueries[0]['sql'])->toContain('AND ag.local_graph_id = ?');
	expect($pruneQueries[0]['params'])->toBe(array(555));

	$pruneDeletes = array_values(array_filter(
		$result['deletes'],
		fn ($sql) => strpos($sql, 'DELETE FROM aggregate_graphs_items') !== false
	));
	expect($pruneDeletes[0])->toContain('aggregate_graph_id IN (88)');
	expect($pruneDeletes[0])->toContain('local_graph_id IN (701)');

	// 5) lib/api_aggregate.php: draw_aggregate_template_graph_config filters local_graph_id = 0
	$apiAggSrc = file_get_contents($repoRoot . '/lib/api_aggregate.php');
	$drawBody = pr4ExtractFunctionBody($apiAggSrc, 'function draw_aggregate_template_graph_config(');
	expect($drawBody)->toContain('WHERE graph_template_id = ?');
	expect($drawBody)->toContain('AND local_graph_id = 0');
});
