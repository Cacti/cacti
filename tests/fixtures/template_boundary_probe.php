<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
require __DIR__ . '/automation_creation_bootstrap.php';

clear_fixture();
seed_graph_template();
$values  = [];
$created = create_complete_graph_from_template(1, 1, [], $values);
check(is_array($created), 'source graph exists');
$source    = $created['local_data_id'][1];
$candidate = ['id' => $source];
$mode      = $argv[1];

if ($mode === 'profile') {
	$pdo->exec("UPDATE data_template_data SET t_name='on' WHERE local_data_id=0");
	$pdo->exec("UPDATE data_template_data SET name='Local name' WHERE local_data_id=$source");
	check(change_data_template($source, 1, ['step' => 60, 'heartbeat' => 120]), 'profile replacement succeeds');
	$data = $pdo->query("SELECT name,rrd_step FROM data_template_data WHERE local_data_id=$source")->fetch(PDO::FETCH_ASSOC);
	check($data['name'] === 'Local name' && (int)$data['rrd_step'] === 60, 'profile step and local name survive replacement');
	check((int)$pdo->query("SELECT rrd_heartbeat FROM data_template_rrd WHERE local_data_id=$source")->fetchColumn() === 120, 'profile heartbeat reaches the existing source');
} elseif ($mode === 'compatibility') {
	check(create_graph_custom_data_compatible([], $candidate), 'no overrides allow reuse');
	check(!create_graph_custom_data_compatible([1 => ['data_template' => [1 => ['rrd_step' => 300]]]], ['id' => 999999]), 'missing source metadata rejects reuse');
	check(create_graph_custom_data_compatible([1 => ['data_template_item' => [999999 => ['data_source_name' => 'other']]]], $candidate), 'another template item does not constrain this source');
	check(create_graph_custom_data_compatible([1 => ['unknown' => [1 => ['ignored' => 'value']]]], $candidate), 'unrecognized suggestion groups do not change compatibility');
	$review_options['data_source_trace'] = 'on';
	check(!create_graph_custom_data_compatible([1 => ['data_template' => [1 => ['rrd_step' => 999]]]], $candidate), 'conflicting step rejects reuse with tracing enabled');
	check(count(array_filter($review_logs, fn ($message) => str_contains($message, 'Not Compatible:'))) === 1, 'incompatible override is traced');
	$pdo->exec("DELETE FROM data_template_rrd WHERE local_data_id=$source");
	check(!create_graph_custom_data_compatible([1 => ['data_template_item' => [1 => ['data_source_name' => 'traffic']]]], $candidate), 'missing child item rejects reuse');
} elseif ($mode === 'title') {
	$pdo->exec("UPDATE data_local SET host_id=0 WHERE id=$source");
	$pdo->exec("UPDATE data_template_data SET name='Unresolved |query_missing|',name_cache='' WHERE local_data_id=$source");
	$review_hooks = [];
	update_data_source_title_cache($source);
	check($pdo->query("SELECT name_cache FROM data_template_data WHERE local_data_id=$source")->fetchColumn() === 'Unresolved |query_missing|', 'unresolved initial title is retained');
	check($review_hooks === [['update_data_source_title_cache', false]], 'initial title publishes its hook after the transaction');
} elseif ($mode === 'empty-graph') {
	check(graph_template_connect_task_items(1, []), 'absent graph cache requires no wiring');
	check(graph_template_connect_task_items(999999, ['local_graph_id' => $created['local_graph_id']]), 'template without source items requires no wiring');
	$pdo->exec('DELETE FROM graph_templates_graph WHERE local_graph_id=0');
	check(create_complete_graph_from_template(1, 1, [], $values) === false, 'missing graph metadata rejects creation');
	check((int)$pdo->query('SELECT COUNT(*) FROM graph_local')->fetchColumn() === 1, 'missing template does not leave a graph shell');
}

emit_result($mode, ['verified' => true]);
