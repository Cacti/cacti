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

require __DIR__ . '/automation_creation_bootstrap.php';

// Load maintained definitions needed by template and graph-input propagation.
$forms = file_get_contents($root . '/include/global_form.php');
$start = strpos($forms, "if (!defined('VALID_HOST_FIELDS'))");
$end = strpos($forms, '$valid_host_fields =', $start);
eval(substr($forms, $start, $end - $start));
require_once $root . '/lib/graph_template_input.php';
load_actual_functions($root . '/lib/functions.php', ['array_to_sql_or', 'get_hash_data_template', 'generate_hash']);

$mode = $argv[1];
clear_fixture();
$pdo->exec('DELETE FROM data_input_fields WHERE id >= 900001');
$pdo->exec('DELETE FROM graph_template_input_defs');
$pdo->exec('DELETE FROM graph_template_input');
$result = ['case' => $mode];
if ($mode == 'binding' || $mode == 'resequence') {
	seed_graph_template();
	$pdo->exec("UPDATE data_template_rrd SET data_source_name='value' WHERE id=1");
	$pdo->exec("INSERT INTO data_template (id,hash,name) VALUES (2,'second','Second template')");
	$pdo->exec("INSERT INTO data_template_rrd (id,data_template_id,data_source_name) VALUES (2,2,'value')");
	$pdo->exec("INSERT INTO data_local (id,host_id,data_template_id) VALUES (101,1,1),(102,1,2)");
	$pdo->exec("INSERT INTO data_template_rrd (id,local_data_id,local_data_template_rrd_id,data_template_id,data_source_name) VALUES (101,101,1,1,'value'),(102,102,2,2,'value')");
	$pdo->exec('INSERT INTO graph_local (id,host_id,graph_template_id) VALUES (1,1,1)');
	$pdo->exec("INSERT INTO graph_templates_graph (id,local_graph_id,local_graph_template_graph_id,graph_template_id,title) VALUES (101,1,1,1,'Local graph')");
	$pdo->exec('INSERT INTO graph_templates_item (id,graph_template_id,task_item_id,sequence) VALUES (2,1,2,2)');
	$pdo->exec('INSERT INTO graph_templates_item (id,local_graph_id,local_graph_template_item_id,graph_template_id,task_item_id,sequence) VALUES (101,1,1,1,101,1),(102,1,2,1,102,2)');
	if ($mode == 'binding') {
		$pdo->exec("UPDATE graph_templates_item SET task_item_id=2,text_format='Changed' WHERE id=1");
		push_out_graph_item(1, true);
		$pdo->exec("INSERT INTO graph_template_input (id,graph_template_id,name,column_name) VALUES (2,1,'Second value','task_item_id')");
		$pdo->exec('INSERT INTO graph_template_input_defs VALUES (2,1),(2,2)');
		push_out_graph_input(2, 1, [1 => 1]);
		$result['changed_binding'] = (int)$pdo->query('SELECT task_item_id FROM graph_templates_item WHERE id=101')->fetchColumn();
		$result['changed_text'] = $pdo->query('SELECT text_format FROM graph_templates_item WHERE id=101')->fetchColumn();
		push_out_graph_item(1, false);
		$result['text_after_unchanged_source_save'] = $pdo->query('SELECT text_format FROM graph_templates_item WHERE id=101')->fetchColumn();
		// Test adding a new item to a graph that still uses both data templates.
		$pdo->exec('UPDATE graph_templates_item SET task_item_id=101 WHERE id=101');
		$pdo->exec('INSERT INTO graph_templates_item (id,graph_template_id,task_item_id,sequence) VALUES (3,1,2,3)');
		push_out_graph_item(3, true);
		$result['new_binding'] = (int)$pdo->query('SELECT task_item_id FROM graph_templates_item WHERE local_graph_id=1 AND local_graph_template_item_id=3')->fetchColumn();
		$result['expected_binding'] = 102;
		$pdo->exec('DELETE FROM graph_templates_item WHERE local_graph_id=1 AND local_graph_template_item_id=3');
		change_graph_template(1, 1, true);
		$result['retemplate_new_binding'] = (int)$pdo->query('SELECT task_item_id FROM graph_templates_item WHERE local_graph_id=1 AND local_graph_template_item_id=3')->fetchColumn();
		$pdo->exec("INSERT INTO graph_template_input (id,graph_template_id,name,column_name) VALUES (3,1,'Label','text_format')");
		$pdo->exec('INSERT INTO graph_template_input_defs VALUES (3,1)');
		$pdo->exec("UPDATE graph_templates_item SET text_format='Local label' WHERE id=101");
		push_out_graph_item(1, true);
		$result['local_label'] = $pdo->query('SELECT text_format FROM graph_templates_item WHERE id=101')->fetchColumn();
		$pdo->exec("INSERT INTO data_template_rrd (id,local_data_id,local_data_template_rrd_id,data_template_id,data_source_name) VALUES (103,103,2,2,'value')");
		$pdo->exec('INSERT INTO graph_templates_item (id,local_graph_id,task_item_id) VALUES (200,1,103)');
		$pdo->exec('INSERT INTO graph_templates_item (id,graph_template_id,task_item_id,sequence) VALUES (4,1,2,4)');
		push_out_graph_item(4, true);
		$result['ambiguous_binding'] = (int)$pdo->query('SELECT task_item_id FROM graph_templates_item WHERE local_graph_id=1 AND local_graph_template_item_id=4')->fetchColumn();

	} else {
		$pdo->exec('UPDATE graph_templates_item SET sequence=3-sequence WHERE local_graph_id=1');
		retemplate_graphs(1, 0, true);
		$result['actual'] = $pdo->query('SELECT local_graph_template_item_id,sequence FROM graph_templates_item WHERE local_graph_id=1 ORDER BY local_graph_template_item_id')->fetchAll(PDO::FETCH_ASSOC);
		$result['repairs'] = $_SESSION['sess_gt_repairs'] ?? 0;
		$pdo->exec('INSERT INTO graph_local (id,host_id,graph_template_id) VALUES (2,1,1)');
		$pdo->exec('INSERT INTO graph_templates_item (id,local_graph_id,local_graph_template_item_id,graph_template_id,task_item_id,sequence) VALUES (201,2,1,1,101,2),(202,2,2,1,102,1)');
		$pdo->exec('UPDATE graph_templates_item SET sequence=5 WHERE id=1');
		retemplate_graphs(1, 0, true);
		$result['all_repaired'] = (int)$pdo->query('SELECT COUNT(*) FROM graph_templates_item AS child JOIN graph_templates_item AS parent ON parent.id=child.local_graph_template_item_id WHERE child.local_graph_id>0 AND child.sequence<>parent.sequence')->fetchColumn();
		$pdo->exec('UPDATE graph_templates_item SET local_graph_template_item_id=1 WHERE id=102');
		retemplate_graphs(1, 0, true);
		$result['duplicates'] = (int)$pdo->query('SELECT COUNT(*) FROM graph_templates_item WHERE local_graph_id=1 AND local_graph_template_item_id=1')->fetchColumn();

	}
} elseif ($mode == 'switch' || $mode == 'duplicate-failure') {
	seed_graph_template();
	$values = [];
	$created = create_complete_graph_from_template(1, 1, [], $values);
	check(is_array($created), 'create fixture source');
	$id = $created['local_data_id'][1];
	if ($mode == 'switch') {
		$pdo->exec("INSERT INTO data_template (id,hash,name) VALUES (2,'second','Second template')");
		$pdo->exec("INSERT INTO data_template_data (id,data_template_id,data_input_id,name) VALUES (900001,2,1,'Second template')");
		$pdo->exec("INSERT INTO data_template_rrd (id,data_template_id,data_source_name) VALUES (900001,2,'traffic')");
		// The UI updates the child template ID before calling this helper.
		$pdo->exec("UPDATE data_template_rrd SET data_template_id=2 WHERE local_data_id=$id");
		change_data_template($id, 2);
		$pdo->exec("UPDATE data_template_rrd SET rrd_heartbeat=1200,t_rrd_heartbeat='' WHERE id=900001");
		push_out_data_source_item(900001);
		$result['heartbeat_after_new_template'] = (int)$pdo->query("SELECT rrd_heartbeat FROM data_template_rrd WHERE local_data_id=$id")->fetchColumn();
		$pdo->exec("UPDATE data_template_rrd SET rrd_heartbeat=1800,t_rrd_heartbeat='' WHERE id=1");
		push_out_data_source_item(1);
		$result['heartbeat_after_old_template'] = (int)$pdo->query("SELECT rrd_heartbeat FROM data_template_rrd WHERE local_data_id=$id")->fetchColumn();
		$result['data_local_template'] = (int)$pdo->query("SELECT data_template_id FROM data_local WHERE id=$id")->fetchColumn();
		$result['data_metadata_template'] = (int)$pdo->query("SELECT data_template_id FROM data_template_data WHERE local_data_id=$id")->fetchColumn();
		$result['rrds'] = $pdo->query("SELECT data_template_id,local_data_template_rrd_id,data_source_name FROM data_template_rrd WHERE local_data_id=$id")->fetchAll(PDO::FETCH_ASSOC);
	} else {
		$pdo->exec("CREATE TRIGGER review_fail_duplicate BEFORE INSERT ON data_template_rrd FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture insert failure'");
		try {
			$duplicate = api_duplicate_data_source(0, 1, 'Copy');
		} finally {
			$pdo->exec('DROP TRIGGER review_fail_duplicate');
		}
		$result['returned_id'] = $duplicate;
		$result['new_source_rows'] = (int)$pdo->query("SELECT COUNT(*) FROM data_template WHERE id<>1")->fetchColumn();
		$result['new_rrd_rows'] = (int)$pdo->query("SELECT COUNT(*) FROM data_template_rrd WHERE data_template_id<>1 AND local_data_id=0")->fetchColumn();
		$result['sql_error'] = str_contains($database_last_error, 'fixture insert failure');
	}
} elseif ($mode == 'reuse') {
	seed_graph_template();
	$pdo->exec("INSERT INTO data_template (id,hash,name) VALUES (2,'second','Second')");
	$pdo->exec("INSERT INTO data_template_data (id,data_template_id,data_input_id,name,active,rrd_step) VALUES (2,2,1,'Second','on',60)");
	$pdo->exec("INSERT INTO data_template_rrd (id,hash,data_template_id,data_source_name,data_source_type_id,rrd_heartbeat) VALUES (2,'second-item',2,'other_counter',2,120)");
	$pdo->exec("INSERT INTO graph_templates_item (id,hash,graph_template_id,task_item_id,graph_type_id,consolidation_function_id,sequence) VALUES (2,'second-graph-item',1,2,4,1,2)");
	$values = [1 => ['data_template' => [1 => ['rrd_step' => 300],2 => ['rrd_step' => 60]]]];
	$first = create_complete_graph_from_template(1, 1, [], $values);
	check(is_array($first), 'first multi-template graph completes');
	$second = create_complete_graph_from_template(1, 1, [], $values);
	check(is_array($second), 'second multi-template graph completes');
	$result['first_sources'] = $first['local_data_id'];
	$result['second_sources'] = $second['local_data_id'];
	$result['source_count'] = (int)$pdo->query('SELECT COUNT(*) FROM data_local')->fetchColumn();
	$result['graphs'] = (int)$pdo->query('SELECT COUNT(*) FROM graph_local')->fetchColumn();
	$result['steps'] = $pdo->query('SELECT data_template_id,rrd_step FROM data_template_data WHERE local_data_id>0 ORDER BY local_data_id')->fetchAll(PDO::FETCH_ASSOC);
	$candidate = ['id' => $first['local_data_id'][1]];
	$result['other_template'] = create_graph_custom_data_compatible([1 => ['data_template' => [2 => ['rrd_step' => 60]]]], $candidate);
	$result['missing_zero'] = create_graph_custom_data_compatible([1 => ['custom_data' => [1 => [999999 => '0']]]], $candidate);
	$result['missing_empty'] = create_graph_custom_data_compatible([1 => ['custom_data' => [1 => [999999 => '']]]], $candidate);
	$result['legacy'] = create_graph_custom_data_compatible([1 => [1 => ['data_template' => ['rrd_step' => 300]],2 => ['data_template' => ['rrd_step' => 60]]]], $candidate);
	$result['wrong_item'] = create_graph_custom_data_compatible([1 => ['data_template_item' => [1 => ['data_source_name' => 'other_counter']]]], $candidate);
	$result['right_item'] = create_graph_custom_data_compatible([1 => ['data_template_item' => [1 => ['data_source_name' => 'traffic']]]], $candidate);

} elseif ($mode == 'custom' || $mode == 'scale') {
	$pdo->exec("INSERT INTO data_template (id,hash,name) VALUES (1,'custom','Custom')");
	$pdo->exec("INSERT INTO data_template_data (id,data_template_id,data_input_id,name) VALUES (1,1,1,'Custom')");
	$n = $mode != 'custom' ? (int)$argv[2] : 3;
	$f = $mode != 'custom' ? 4 : 1;
	$pdo->beginTransaction();
	$field = $pdo->prepare("INSERT INTO data_input_fields (id,data_input_id,name,data_name,input_output,type_code) VALUES (?,1,?,?,'in','')");
	$value = $pdo->prepare("INSERT INTO data_input_data (data_input_field_id,data_template_data_id,t_value,value) VALUES (?,?,'','new-default')");
	$source = $pdo->prepare('INSERT INTO data_template_data (id,local_data_id,data_template_id,local_data_template_data_id,data_input_id,name) VALUES (?,?,1,1,1,?)');
	for ($j = 1;$j <= $f;$j++) {
		$field->execute([900000 + $j,'Field '.$j,'field'.$j]);
		$value->execute([900000 + $j,1]);
	}
	for ($i = 1;$i <= $n;$i++) {
		$source->execute([$i + 1,$i,'Source '.$i]);
		$pdo->exec("INSERT INTO data_local (id,host_id,data_template_id) VALUES ($i,1,1)");
		if ($mode == 'custom' && $i == 3) {
			continue;
		}
		for ($j = 1;$j <= $f;$j++) {
			$value->execute([900000 + $j,$i + 1]);
		}
	}
	$pdo->exec("UPDATE data_input_data SET value='old-default' WHERE data_template_data_id>1");
	if ($mode == 'custom') {
		$pdo->exec("UPDATE data_input_data SET t_value='on',value='local-override' WHERE data_template_data_id=3");
	}
	$pdo->commit();
	$pdo->query_counts = [];
	$start = hrtime(true);
	push_out_data_source_custom_data(1);
	$result['milliseconds'] = (hrtime(true) - $start) / 1e6;
	$result['sources'] = $n;
	$result['fields'] = $f;
	$result['database_calls'] = array_sum($pdo->query_counts);
	$result['peak_mib'] = memory_get_peak_usage(true) / 1048576;
	if ($mode == 'custom') {
		$result['rows'] = $pdo->query('SELECT data_template_data_id,t_value,value FROM data_input_data ORDER BY data_template_data_id')->fetchAll(PDO::FETCH_ASSOC);
	} else {
		$result['updated'] = (int)$pdo->query("SELECT COUNT(*) FROM data_input_data WHERE data_template_data_id>1 AND value='new-default'")->fetchColumn();
	}
}
if ($mode == 'switch-atomic' || $mode == 'copy-atomic') {
	seed_graph_template();
	$pdo->exec("INSERT INTO data_input_data (data_input_field_id,data_template_data_id,t_value,value) VALUES (1,1,'','fixture')");
	$values = [];
	$created = create_complete_graph_from_template(1, 1, [], $values);
	check(is_array($created), 'fixture graph created');
	$source = $created['local_data_id'][1];
	$pdo->exec("UPDATE data_template_data SET data_source_path='keep.rrd' WHERE local_data_id=$source");
	$tables = ['data_local','data_template','data_template_data','data_template_rrd','data_input_data'];
	$snapshot = function () use ($pdo, $tables) {
		$state = [];
		foreach ($tables as $table) {
			$state[$table] = $pdo->query("SELECT * FROM $table ORDER BY 1,2")->fetchAll(PDO::FETCH_ASSOC);
		}
		return $state;
	};
	$nested = ($argv[3] ?? '') == 'nested';
	$target = $argv[2] ?? 'data_template_rrd';
	if ($mode == 'switch-atomic') {
		$pdo->exec("INSERT INTO data_template (id,hash,name) VALUES (2,'second','Second template')");
		$pdo->exec("INSERT INTO data_template_data (id,data_template_id,data_input_id,name) VALUES (900001,2,1,'Second template')");
		$pdo->exec("INSERT INTO data_template_rrd (id,data_template_id,data_source_name,rrd_heartbeat,t_rrd_maximum,rrd_maximum) VALUES (900001,2,'traffic',1200,'on','100')");
		$pdo->exec("UPDATE data_template_rrd SET rrd_maximum='999' WHERE local_data_id=$source");
		if ($target == 'incompatible') {
			$pdo->exec("UPDATE data_template_rrd SET data_source_name='different' WHERE id=900001");
		}
		if ($target == 'extra-item') {
			$pdo->exec("INSERT INTO data_template_rrd (id,data_template_id,data_source_name) VALUES (900002,2,'extra')");
		}
		$pdo->exec("INSERT INTO data_input_data (data_input_field_id,data_template_data_id,t_value,value) VALUES (1,900001,'','new')");
	}
	$before = $snapshot();
	$allowed = ['data_local','data_template','data_template_data','data_template_rrd','data_input_data'];
	if (in_array($target, $allowed, true)) {
		$action = $mode == 'copy-atomic' || $target == 'data_input_data' ? 'INSERT' : 'UPDATE';
		$pdo->exec("CREATE TRIGGER fail_template_write BEFORE $action ON $target FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture write failure'");
	}
	if ($nested) {
		$pdo->beginTransaction();
		$pdo->exec("INSERT INTO host (id,hostname) VALUES (999,'outer-work')");
	}
	$struct_before = $struct_data_source;
	$result['failed'] = $mode == 'copy-atomic' ? api_duplicate_data_source($target == 'data_local' ? $source : 0, 1, 'Copy') : change_data_template($source, 2);
	$result['unchanged'] = $snapshot() === $before;
	$result['struct_unchanged'] = $struct_before === $struct_data_source;
	$result['nested_open'] = $pdo->inTransaction();
	$result['outer_preserved'] = (int)$pdo->query('SELECT COUNT(*) FROM host WHERE id=999')->fetchColumn();
	if ($nested) {
		$pdo->rollBack();
	}
	if (in_array($target, $allowed, true)) {
		$pdo->exec('DROP TRIGGER fail_template_write');
	}
	if ($target == 'incompatible' || $target == 'extra-item') {
		$pdo->exec('DELETE FROM data_template_rrd WHERE id=900002');
		$pdo->exec("UPDATE data_template_rrd SET data_source_name='traffic' WHERE id=900001");
	}
	if ($mode == 'copy-atomic') {
		$result['retry'] = api_duplicate_data_source(0, 1, 'Copy');
		$result['retry_items'] = (int)$pdo->query('SELECT COUNT(*) FROM data_template_rrd WHERE local_data_id=0 AND data_template_id=' . (int)$result['retry'])->fetchColumn();
		$result['source_copy'] = api_duplicate_data_source($source, 0, 'Source copy');
		$result['copy_path'] = $pdo->query('SELECT data_source_path FROM data_template_data WHERE local_data_id=' . (int)$result['source_copy'])->fetchColumn();
	} else {
		$result['retry'] = change_data_template($source, 2);
		$result['maximum'] = $pdo->query("SELECT rrd_maximum FROM data_template_rrd WHERE local_data_id=$source")->fetchColumn();
		$result['path'] = $pdo->query("SELECT data_source_path FROM data_template_data WHERE local_data_id=$source")->fetchColumn();
		$result['detached'] = change_data_template($source, 0);
		$result['detached_parent'] = (int)$pdo->query("SELECT local_data_template_rrd_id FROM data_template_rrd WHERE local_data_id=$source")->fetchColumn();
	}
}

if ($mode == 'custom-boundaries') {
	seed_graph_template();
	$pdo->exec("INSERT INTO data_input_fields (id,data_input_id,name,data_name,input_output,type_code) VALUES
		(900001,1,'Inherited','inherited','in',''),(900002,1,'Override','override','in',''),
		(900003,1,'Index','index','in','index_value'),(900004,1,'Output','output','out','')");
	$pdo->exec("INSERT INTO data_input_data (data_input_field_id,data_template_data_id,t_value,value) VALUES
		(900001,1,'','0'),(900002,1,'on','template'),(900003,1,'','template-index'),(900004,1,'','template-output')");
	$pdo->exec("INSERT INTO data_template_data (id,local_data_id,local_data_template_data_id,data_template_id,data_input_id,name) VALUES (2,1,1,1,1,'Child')");
	$pdo->exec("INSERT INTO data_input_data (data_input_field_id,data_template_data_id,t_value,value) VALUES
		(900002,2,'','local'),(900003,2,'','local-index'),(900004,2,'','local-output')");
	push_out_data_source_custom_data(1);
	$result['values'] = $pdo->query('SELECT data_input_field_id,value FROM data_input_data WHERE data_template_data_id=2 ORDER BY data_input_field_id')->fetchAll(PDO::FETCH_KEY_PAIR);
	$pdo->exec("UPDATE data_input_data SET t_value='on' WHERE data_template_data_id=1");
	push_out_data_source_custom_data(1);
	$result['unchanged'] = $result['values'] === $pdo->query('SELECT data_input_field_id,value FROM data_input_data WHERE data_template_data_id=2 ORDER BY data_input_field_id')->fetchAll(PDO::FETCH_KEY_PAIR);
}
if ($mode == 'switch-multiple') {
	seed_graph_template();
	$pdo->exec("INSERT INTO data_template_rrd (id,data_template_id,data_source_name,rrd_heartbeat) VALUES (2,1,'second',600)");
	$values = [];
	$created = create_complete_graph_from_template(1, 1, [], $values);
	check(is_array($created), 'multi-item source created');
	$id = $created['local_data_id'][1];
	$ids = $pdo->query("SELECT id,data_source_name FROM data_template_rrd WHERE local_data_id=$id ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
	$pdo->exec("INSERT INTO data_template (id,hash,name) VALUES (2,'target','Target')");
	$pdo->exec("INSERT INTO data_template_data (id,data_template_id,data_input_id,name) VALUES (900001,2,1,'Target')");
	$pdo->exec("INSERT INTO data_template_rrd (id,data_template_id,data_source_name,rrd_heartbeat) VALUES (900001,2,'second',1200),(900002,2,'traffic',1800)");
	$result['changed'] = change_data_template($id, 2);
	$result['stable_ids'] = $ids === $pdo->query("SELECT id,data_source_name FROM data_template_rrd WHERE local_data_id=$id ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
	$result['parents'] = $pdo->query("SELECT data_source_name,local_data_template_rrd_id FROM data_template_rrd WHERE local_data_id=$id ORDER BY data_source_name")->fetchAll(PDO::FETCH_KEY_PAIR);
	$pdo->beginTransaction();
	$result['nested_changed'] = change_data_template($id, 1);
	$result['nested_open'] = $pdo->inTransaction();
	$pdo->rollBack();
	$result['rolled_back_parent'] = (int)$pdo->query("SELECT data_template_id FROM data_local WHERE id=$id")->fetchColumn();
}

if ($mode == 'form-switch') {
	seed_graph_template();
	$values = [];
	$created = create_complete_graph_from_template(1, 1, [], $values);
	$id = $created['local_data_id'][1];
	$data = $pdo->query("SELECT * FROM data_template_data WHERE local_data_id=$id")->fetch(PDO::FETCH_ASSOC);
	$rrd = $pdo->query("SELECT * FROM data_template_rrd WHERE local_data_id=$id")->fetch(PDO::FETCH_ASSOC);
	$pdo->exec("INSERT INTO data_template (id,hash,name) VALUES (2,'target','Target')");
	$pdo->exec("INSERT INTO data_template_data (id,data_template_id,data_input_id,name) VALUES (900001,2,1,'Target')");
	$pdo->exec("INSERT INTO data_template_rrd (id,data_template_id,data_source_name,rrd_heartbeat) VALUES (900001,2,'traffic',1200)");
	$failure = $argv[2] ?? 'success';
	if ($failure == 'incompatible') {
		$pdo->exec("UPDATE data_template_rrd SET data_source_name='different' WHERE id=900001");
	}
	if ($failure == 'write') {
		$pdo->exec("CREATE TRIGGER fail_form_write BEFORE UPDATE ON data_template_rrd FOR EACH ROW BEGIN IF NEW.local_data_template_rrd_id=900001 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture form failure'; END IF; END");
	}
	$_REQUEST = ['save_component_data_source' => 1, 'local_data_id' => $id, 'data_template_data_id' => $data['id'],
		'local_data_template_data_id' => 1,'data_template_id' => 2,'_data_template_id' => 1,'host_id' => 1,'_host_id' => 1,
		'data_input_id' => 1,'_data_input_id' => 1,'data_source_profile_id' => 1,'name' => 'Changed form name',
		'data_source_path' => 'keep.rrd','rrd_step' => 300,'current_rrd' => $rrd['id']];
	foreach (['rrd_maximum' => 'U','rrd_minimum' => '0','rrd_heartbeat' => '600','data_source_type_id' => '2','data_source_name' => 'traffic','data_input_field_id' => '0'] as $key => $value) {
		$_REQUEST[$key . '_' . $rrd['id']] = $value;
	}
	$_CACTI_REQUEST = [];
	load_actual_functions($root . '/lib/html_utility.php', ['isset_request_var','isempty_request_var','get_request_var','set_request_var','get_nfilter_request_var','get_filter_request_var']);
	load_actual_functions($root . '/data_sources.php', ['form_save']);
	function csrf_require_post()
	{
	}
	function update_poller_cache($data_source, $commit = false)
	{
		$GLOBALS['form_poller_updates']++;
	}
	$form_poller_updates = 0;
	try {
		form_save();
	} finally {
		if ($failure == 'write') {
			$pdo->exec('DROP TRIGGER fail_form_write');
		}
	}
	$result['template'] = (int)$pdo->query("SELECT data_template_id FROM data_local WHERE id=$id")->fetchColumn();
	$result['metadata_template'] = (int)$pdo->query("SELECT data_template_id FROM data_template_data WHERE local_data_id=$id")->fetchColumn();
	$result['parent'] = (int)$pdo->query("SELECT local_data_template_rrd_id FROM data_template_rrd WHERE local_data_id=$id")->fetchColumn();
	$result['error'] = is_error_message();
	$result['transaction_open'] = $pdo->inTransaction();
	$result['poller_updates'] = $form_poller_updates;
}

if ($mode == 'scale') {
	$result['state_hash'] = hash('sha256', json_encode($pdo->query('SELECT * FROM data_input_data ORDER BY data_template_data_id,data_input_field_id')->fetchAll(PDO::FETCH_ASSOC)));
}
$result['errors'] = array_values(array_filter($review_logs,fn ($s) => str_contains($s,'ERROR:') || str_contains($s,'WARNING:')));
print json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
