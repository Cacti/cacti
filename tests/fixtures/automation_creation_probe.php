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
*/

require __DIR__ . '/automation_creation_bootstrap.php';
$mode = $argv[1] ?? 'graph';
clear_fixture();
$result = array();

if ($mode == 'graph' || $mode == 'graph-failure' || $mode == 'graph-interruption' || $mode == 'graph-resume') {
	seed_graph_template();
	$suggested = array();
	if ($mode == 'graph-failure') {
		$table = $argv[2] ?? 'data_template_rrd';
		check(in_array($table, array('graph_local', 'graph_templates_graph', 'graph_templates_item', 'data_local', 'data_template_data', 'data_template_rrd', 'data_input_data', 'graph_attachment'), true), 'failure table allowlist');
		// Ensure custom data writes are exercised as well as graph/data-source inserts.
		$pdo->exec("INSERT INTO data_input_data (data_input_field_id,data_template_data_id,value) VALUES (1,1,'fixture')");
		$event = $table == 'graph_attachment' ? 'UPDATE' : 'INSERT';
		if ($table == 'graph_attachment') $table = 'graph_templates_item';
		$pdo->exec("CREATE TRIGGER automation_fail_insert BEFORE $event ON $table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture insertion failure'");
		$failed = create_complete_graph_from_template(1, 1, array(), $suggested);
		$pdo->exec('DROP TRIGGER automation_fail_insert');
		$result['failed'] = $failed;
		$result['rows_after_failure'] = (int) $pdo->query('SELECT COUNT(*) FROM graph_local')->fetchColumn();
		$result['sources_after_failure'] = (int) $pdo->query('SELECT COUNT(*) FROM data_local')->fetchColumn();
		$result['hooks_after_failure'] = $review_hooks;
	} elseif ($mode == 'graph-interruption') {
		$pdo->before = function ($sql) {
			if (preg_match('/SELECT \*\s+FROM graph_templates_graph\s+WHERE local_graph_id = \?$/', trim($sql))) {
				throw new RuntimeException('fixture worker interruption');
			}
		};
		$result['failed'] = create_complete_graph_from_template(1, 1, array(), $suggested);
		$pdo->before = null;
		$result['rows_after_failure'] = (int) $pdo->query('SELECT COUNT(*) FROM graph_local')->fetchColumn();
	} elseif ($mode == 'graph-resume') {
		$pdo->exec('INSERT INTO graph_local (id,host_id,graph_template_id) VALUES (999,1,1)');
	}

	$created = automation_create_graph(1, 1, array(), $suggested);
	$result['created'] = $created;
	$result['second'] = automation_create_graph(1, 1, array(), $suggested);
	$result['graphs'] = (int) $pdo->query('SELECT COUNT(*) FROM graph_local')->fetchColumn();
	$result['sources'] = (int) $pdo->query('SELECT COUNT(*) FROM data_local')->fetchColumn();
	$result['linked_sources'] = (int) $pdo->query('SELECT COUNT(*) FROM graph_templates_item AS gti INNER JOIN data_template_rrd AS dtr ON dtr.id=gti.task_item_id WHERE gti.local_graph_id > 0 AND dtr.local_data_id > 0')->fetchColumn();
	$result['hooks_inside_transaction'] = count(array_filter($review_hooks, fn($hook) => $hook[1]));
	$result['transaction_open'] = $pdo->inTransaction();
	$result['errors'] = $review_logs;
} elseif ($mode == 'rule' || $mode == 'tree-failure' || $mode == 'destination') {
	seed_graph_template();
	$pdo->exec('INSERT INTO graph_local (id,host_id,graph_template_id) VALUES (1,1,1),(2,2,1)');
	$pdo->exec("INSERT INTO graph_templates_graph (local_graph_id,graph_template_id,title_cache) VALUES (1,1,'graph-a'),(2,1,'graph-b')");
	$pdo->exec("INSERT INTO graph_tree (id,name,sort_type) VALUES (1,'fixture',2),(2,'other tree',2)");
	$pdo->exec("INSERT INTO automation_tree_rules (id,name,tree_id,tree_item_id,leaf_type,host_grouping_type,enabled) VALUES (1,'fixture',1,0,2,1,'on')");
	$statement = $pdo->prepare('INSERT INTO automation_match_rule_items (rule_id,rule_type,sequence,operation,field,operator,pattern) VALUES (1,3,?,?,?,?,?)');
	$statement->execute(array(1, AUTOMATION_OPER_NULL, 'h.hostname', AUTOMATION_OP_MATCHES, $mode == 'rule' ? 'router-a' : 'server-b'));
	if ($mode == 'rule') {
		$statement->execute(array(2, AUTOMATION_OPER_OR, 'h.hostname', AUTOMATION_OP_MATCHES, 'switch-c'));
		automation_execute_graph_create_tree(2);
		$result['nonmatching_placements'] = (int) $pdo->query('SELECT COUNT(*) FROM graph_tree_items')->fetchColumn();
		automation_execute_graph_create_tree(1);
		$result['matching_placements'] = $pdo->query('SELECT local_graph_id FROM graph_tree_items')->fetchAll(PDO::FETCH_COLUMN);
	} elseif ($mode == 'tree-failure') {
		$pdo->exec("INSERT INTO automation_tree_rule_items (rule_id,sequence,field,search_pattern,replace_pattern,sort_type,propagate_changes) VALUES (1,1,'0','Required Header','',2,'')");
		$pdo->exec("CREATE TRIGGER automation_fail_header BEFORE INSERT ON graph_tree_items FOR EACH ROW BEGIN IF NEW.title = 'Required Header' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture header error'; END IF; END");
		automation_execute_graph_create_tree(2);
		$result['after_failure'] = (int) $pdo->query('SELECT COUNT(*) FROM graph_tree_items')->fetchColumn();
		$pdo->exec('DROP TRIGGER automation_fail_header');
		automation_execute_graph_create_tree(2);
		$result['leaves'] = (int) $pdo->query('SELECT COUNT(*) FROM graph_tree_items WHERE local_graph_id=2')->fetchColumn();
		$result['root_leaves'] = (int) $pdo->query('SELECT COUNT(*) FROM graph_tree_items WHERE local_graph_id=2 AND parent=0')->fetchColumn();
	} else {
		$pdo->exec("INSERT INTO graph_tree_items (id,graph_tree_id,parent,title) VALUES (999,1,0,'Destination')");
		$pdo->exec('UPDATE automation_tree_rules SET tree_item_id=999 WHERE id=1');
		api_tree_delete_node(1, 'tbranch:999');
		automation_execute_graph_create_tree(2);
		$result['deleted_parent_leaves'] = (int) $pdo->query('SELECT COUNT(*) FROM graph_tree_items')->fetchColumn();
		$pdo->exec("INSERT INTO graph_tree_items (id,graph_tree_id,parent,title) VALUES (999,2,0,'Other tree')");
		automation_execute_graph_create_tree(2);
		$result['foreign_parent_leaves'] = (int) $pdo->query('SELECT COUNT(*) FROM graph_tree_items WHERE local_graph_id>0')->fetchColumn();
	}
} elseif ($mode == 'tree-order') {
	$style = (int)$argv[2];
	$pdo->exec("INSERT INTO graph_tree (id,name,sort_type) VALUES (1,'immediate',$style),(2,'batched',$style)");
	$insert=$pdo->prepare('INSERT INTO graph_templates_graph (local_graph_id,title_cache) VALUES (?,?)');
	foreach (array('20', '3', '10', '2', '1') as $i=>$title) $insert->execute(array($i+1,$title));
	foreach (array(1,2) as $tree) {
		$rule=array('tree_id'=>$tree, 'host_grouping_type'=>1);
		$parent=create_header_node('Inherited', $rule, array('sort_type'=>0, 'propagate_changes'=>''),0);
		create_graph_node(1,$parent,$rule);
		if ($tree==2) { api_tree_sort_batch('begin'); api_tree_sort_batch('begin'); }
		for ($id=2; $id<=5; $id++) create_graph_node($id,$parent,$rule);
		if ($tree==2) { api_tree_sort_batch('end'); api_tree_sort_batch('end'); }
		$result[$tree==1?'immediate':'batched']=array_map('intval',$pdo->query("SELECT local_graph_id FROM graph_tree_items WHERE graph_tree_id=$tree AND local_graph_id>0 ORDER BY position")->fetchAll(PDO::FETCH_COLUMN));
	}
} elseif ($mode == 'tree-scale') {
	$n = (int) ($argv[2] ?? 200);
	$pdo->exec("INSERT INTO graph_tree (id,name,sort_type) VALUES (1,'fixture',4)");
	$insert = $pdo->prepare('INSERT INTO graph_templates_graph (local_graph_id,title_cache) VALUES (?,?)');
	for ($i=1; $i<=$n; $i++) $insert->execute(array($i, 'Graph ' . ($n-$i+1)));
	$pdo->query_counts = array();
	$start = hrtime(true);
	api_tree_sort_batch('begin');
	try {
		for ($i=1; $i<=$n; $i++) create_graph_node($i, 0, array('tree_id'=>1, 'host_grouping_type'=>1));
	} finally {
		api_tree_sort_batch('end');
	}
	$result['milliseconds'] = (hrtime(true)-$start)/1e6;
	$result['calls'] = array_sum($pdo->query_counts);
	$result['position_updates'] = 0;
	foreach ($pdo->query_counts as $sql=>$count) if (str_starts_with($sql, 'UPDATE graph_tree_items SET position')) $result['position_updates'] += $count;
	$result['ordered_graphs'] = array_map('intval', $pdo->query('SELECT local_graph_id FROM graph_tree_items ORDER BY position')->fetchAll(PDO::FETCH_COLUMN));
	$pdo->query_counts = array();
	api_tree_sort_branch(0,1);
	$result['repeat_position_updates'] = 0;
	foreach ($pdo->query_counts as $sql=>$count) if (str_starts_with($sql, 'UPDATE graph_tree_items SET position')) $result['repeat_position_updates'] += $count;
} else {
	throw new RuntimeException('Unknown fixture scenario');
}

print json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
