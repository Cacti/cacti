<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
if (PHP_SAPI !== 'cli' || getenv('CACTI_AGGREGATE_ITEM_DISPOSABLE') !== '1' || !is_file('/tmp/cacti-aggregate-item-disposable')) {
	throw new RuntimeException('Use only the owned disposable aggregate item fixture.');
}
require dirname(__DIR__, 2) . '/include/cli_check.php';
require_once CACTI_PATH_LIBRARY . '/api_aggregate.php';
require_once CACTI_PATH_LIBRARY . '/aggregate.php';
require_once CACTI_PATH_LIBRARY . '/api_graph.php';
require_once CACTI_PATH_LIBRARY . '/data_query.php';
require_once CACTI_PATH_LIBRARY . '/template.php';
require_once dirname(__DIR__, 2) . '/install/functions.php';
require_once dirname(__DIR__, 2) . '/install/upgrades/1_3_0.php';
$_SESSION[SESS_USER_ID] = 1;

if ((int) db_fetch_cell('SELECT COUNT(*) FROM graph_local') !== 0) {
	throw new RuntimeException('Use a fresh database.');
}
set_config_option('auth_method', '1');
set_config_option('log_verbosity', '1');
db_execute_prepared("UPDATE user_auth SET password = ?, must_change_password = '', login_opts = 1 WHERE id = 1", [password_hash('aggregate-item-pass', PASSWORD_DEFAULT)]);
$template = 900002;
db_execute_prepared("INSERT INTO graph_templates (id, hash, name) VALUES (?, '', 'Legend fixture')", [$template]);
db_execute_prepared('INSERT INTO graph_templates_graph (graph_template_id, local_graph_id, title, title_cache) VALUES (?, 0, ?, ?)', [$template, 'Legend fixture', 'Legend fixture']);
$source = sql_save(['id' => 900010, 'local_graph_id' => 0, 'graph_template_id' => $template, 'sequence' => 1, 'graph_type_id' => GRAPH_ITEM_TYPE_LINE1, 'color_id' => 1, 'consolidation_function_id' => 1, 'text_format' => 'Source label', 'legend' => 'Inherited', 'hard_return' => 'on'], 'graph_templates_item');
db_execute_prepared("INSERT INTO aggregate_graph_templates (id, name, graph_template_id, gprint_prefix, gprint_format, graph_type, total, total_type, total_prefix, order_type, user_id) VALUES (900003, 'Legend fixture', ?, 'Device', '', 4, 0, 0, '', 0, 1)", [$template]);
$members = [];
$count   = max(2, min(1000, (int) getenv('CACTI_AGGREGATE_ITEM_SCALE')));

for ($index = 0; $index < $count; $index++) {
	$id = 10001 + $index;
	sql_save(['id' => $id], 'data_local');
	sql_save(['local_data_id' => $id, 'name' => 'Member source', 'name_cache' => 'Member source'], 'data_template_data');
	$task = sql_save(['local_data_id' => $id, 'data_source_name' => 'traffic', 'data_source_type_id' => 1, 'rrd_minimum' => '0', 'rrd_maximum' => 'U'], 'data_template_rrd');

	sql_save(['id' => $id, 'graph_template_id' => $template], 'graph_local');
	sql_save(['local_graph_id' => $id, 'title' => 'Member ' . $index, 'title_cache' => 'Member ' . $index], 'graph_templates_graph');
	sql_save(['task_item_id' => $task, 'local_graph_id' => $id, 'graph_template_id' => $template, 'local_graph_template_item_id' => $source, 'sequence' => 1, 'graph_type_id' => GRAPH_ITEM_TYPE_LINE1, 'color_id' => 1, 'text_format' => 'Member label', 'legend' => 'Inherited', 'hard_return' => 'on'], 'graph_templates_item');
	$members[] = $id;
}
$attributes = ['graph_title' => 'Legend fixture', 'graph_template_id' => $template, 'graph_type' => AGGREGATE_GRAPH_TYPE_KEEP, 'total' => AGGREGATE_TOTAL_NONE, 'gprint_prefix' => 'Device', 'item_no' => 1, 'color_templates' => [], 'graph_item_types' => [], 'cdefs' => [], 'skipped_items' => [], 'total_items' => []];
$profiles   = [];

foreach ([0, 900003] as $aggregateTemplate) {
	$graph                               = 0;
	$attributes['aggregate_template_id'] = $aggregateTemplate;
	$attributes['template_propogation']  = $aggregateTemplate ? 'on' : '';
	$attributes['graph_title']           = $aggregateTemplate ? 'Template legend fixture' : 'Standalone legend fixture';

	if (!aggregate_create_update($graph, $members, $attributes)) {
		throw new RuntimeException('Could not create the browser aggregate.');
	}
	$id    = $aggregateTemplate ?: (int) db_fetch_cell_prepared('SELECT id FROM aggregate_graphs WHERE local_graph_id = ?', [$graph]);
	$key   = $aggregateTemplate ? 'aggregate_template_id' : 'aggregate_graph_id';
	$table = $aggregateTemplate ? 'aggregate_graph_templates_item' : 'aggregate_graphs_graph_item';
	$item  = [$key => $id, 'graph_templates_item_id' => $source, 'sequence' => 1];

	foreach ([false, true] as $enabled) {
		$item['t_text_format'] = $enabled ? 'on' : '';
		$item['text_format']   = 'Custom';
		aggregate_graph_items_save([$item], $table);
		$before = $database_total_queries;
		$start  = hrtime(true);

		if (!aggregate_create_update($graph, $members, $attributes)) {
			throw new RuntimeException('Could not rebuild the profile aggregate.');
		}
		$queries  = $database_total_queries - $before;
		$seconds  = (hrtime(true) - $start) / 1e9;
		$expected = $enabled ? 'Device Custom' : 'Device';

		if ((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_templates_item WHERE local_graph_id = ? AND text_format = ?', [$graph, $expected]) !== $count) {
			throw new RuntimeException('Profile labels were not applied to every member.');
		}
		$beforeMap = $database_total_queries;
		aggregate_item_legend_overrides($aggregateTemplate, $id);

		if ($database_total_queries - $beforeMap !== 1) {
			throw new RuntimeException('Override map must use one query independent of member count.');
		}
		$profiles[] = ['template' => $aggregateTemplate > 0, 'override_enabled' => $enabled, 'members' => $count, 'seconds' => $seconds, 'queries' => $queries, 'override_map_queries' => 1];
	}
}
$savedOverrides = [];

foreach (['aggregate_graph_templates_item', 'aggregate_graphs_graph_item'] as $table) {
	$savedOverrides[$table] = db_fetch_assoc('SELECT * FROM ' . $table . ' ORDER BY id');
}

// Repeated upgrades must preserve the labels operators already saved.
$cacti_upgrade_version = '1.3.0';
upgrade_aggregate_item_overrides();
upgrade_aggregate_item_overrides();

foreach ($savedOverrides as $table => $rows) {
	if (db_fetch_assoc('SELECT * FROM ' . $table . ' ORDER BY id') !== $rows) {
		throw new RuntimeException('Repeating the override migration changed saved labels.');
	}
}
print json_encode(['php' => PHP_VERSION, 'database' => db_fetch_cell('SELECT VERSION()'), 'peak_bytes' => memory_get_peak_usage(true), 'scope' => 'Synthetic SQL and graph metadata only; no RRD or SNMP IO.', 'migration_preserves_labels' => true, 'profiles' => $profiles], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
