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

require __DIR__ . '/aggregate_creation_bootstrap.php';

load_actual_functions("$root/lib/functions.php", ['get_hash_cdef', 'generate_hash', 'array_to_sql_or', 'get_uptime']);
set_error_handler(function ($severity, $message, $file, $line) {
	if ($severity & error_reporting()) {
		throw new ErrorException($message, 0, $severity, $file, $line);
	}
	return false;
});
$mode = $argv[1] ?? 'baseline';
if ($mode === 'concurrent-member') {
	$_SESSION['sess_user_id'] = 1;
	print json_encode(['returned' => api_aggregate_associate((int) $argv[2], [(int) $argv[3]])]);
	exit;
}
$n = in_array($mode, ['member-scale', 'cdef-scale']) ? (int)($argv[2] ?? 2) : 2;
clear_fixture();
foreach (['aggregate_graphs', 'aggregate_graphs_items', 'aggregate_graphs_graph_item', 'aggregate_graph_templates', 'aggregate_graph_templates_item', 'aggregate_graph_templates_graph', 'cdef', 'cdef_items'] as $table) {
	$pdo->exec("DELETE FROM $table");
}
$_SESSION['sess_user_id'] = 1;
seed_graph_template();
$pdo->exec("INSERT INTO cdef (id,hash,name,system) VALUES (1,'review-make-zero','_MAKE 0',1)");
$pdo->exec("INSERT INTO cdef_items (cdef_id,sequence,type,value) VALUES (1,1,6,'CURRENT_DATA_SOURCE,0,*')");
$pdo->exec("UPDATE graph_templates_item SET graph_type_id=4,color_id=1,text_format='traffic',hard_return='on' WHERE id=1");
$members = [];
for ($i = 1; $i <= $n; $i++) {
	$g = 100 + $i;
	$members[] = $g;
	$pdo->exec("INSERT INTO graph_local (id,host_id,graph_template_id) VALUES ($g,1,1)");
	$pdo->exec("INSERT INTO graph_templates_graph (local_graph_id,graph_template_id,title,title_cache) VALUES ($g,1,'member $i','member $i')");
	$pdo->exec("INSERT INTO data_template_rrd (id,local_data_id,local_data_template_rrd_id,data_template_id,data_source_name,data_source_type_id) VALUES ($g,$g,1,1,'traffic',2)");
	$pdo->exec("INSERT INTO graph_templates_item (local_graph_id,graph_template_id,task_item_id,graph_type_id,color_id,text_format,hard_return,sequence,consolidation_function_id) VALUES ($g,1,$g,4,1,'member $i','on',1,1)");
}
$attrs = ['graph_template_id' => 1, 'graph_title' => 'Review aggregate', 'aggregate_template_id' => 0, 'gprint_format' => 'on', 'graph_type' => AGGREGATE_GRAPH_TYPE_KEEP, 'total' => AGGREGATE_TOTAL_NONE, 'total_type' => AGGREGATE_TOTAL_TYPE_ALL, 'reorder' => AGGREGATE_ORDER_NONE, 'item_no' => 1, 'color_templates' => [1 => 0], 'graph_item_types' => [1 => 0], 'cdefs' => [1 => 0], 'skipped_items' => [], 'total_items' => [1 => 1]];
function items($id) {
	return $GLOBALS['pdo']->query("SELECT id,task_item_id,sequence,text_format,value,cdef_id,color_id,graph_type_id,line_width FROM graph_templates_item WHERE local_graph_id=$id ORDER BY sequence,id")->fetchAll(PDO::FETCH_ASSOC);
}
$id = 0;
$ok = aggregate_create_update($id, array_reverse($members), $attrs);
check($ok && $id > 0 && count(items($id)) === $n, 'baseline creation');
$agid = (int)$pdo->query("SELECT id FROM aggregate_graphs WHERE local_graph_id=$id")->fetchColumn();
$pdo->exec("INSERT INTO aggregate_graphs_graph_item (aggregate_graph_id,graph_templates_item_id,sequence,color_template,item_skip,item_total) VALUES ($agid,1,1,0,'','on')");
$result = ['mode' => $mode, 'baseline' => $ok];
if ($mode === 'failure') {
	$before = items($id);
	$pdo->exec("CREATE TRIGGER review_reject_item BEFORE INSERT ON graph_templates_item FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='review write failure'");
	try {
		$result['returned'] = aggregate_create_update($id, $members, $attrs);
	} finally {
		$pdo->exec('DROP TRIGGER review_reject_item');
	}
	$result['before_count'] = count($before);
	$result['after_count'] = count(items($id));
	$result['unchanged'] = items($id) === $before;
	$result['transaction_open'] = $pdo->inTransaction();
	$result['errors'] = count($review_logs);
} elseif ($mode === 'template-checkbox') {
	load_actual_functions("$root/lib/html_utility.php", ['isset_request_var', 'isempty_request_var', 'set_request_var', 'get_request_var', 'get_nfilter_request_var', 'get_filter_request_var']);
	load_actual_functions("$root/aggregate_templates.php", ['aggregate_form_save', 'aggregate_template_save']);
	$pdo->exec("INSERT INTO aggregate_graph_templates (id,name,graph_template_id,gprint_prefix,gprint_format,graph_type,total,total_type,total_prefix,order_type,user_id) VALUES (1,'Review',1,'','on',0,1,2,'',1,1)");
	$pdo->exec("INSERT INTO aggregate_graph_templates_item (aggregate_template_id,graph_templates_item_id,sequence,color_template,item_skip,item_total) VALUES (1,1,1,0,'on','on')");
	$_REQUEST = $_POST = ['save_component_template' => 1, 'id' => 1, 'name' => 'Review', 'graph_template_id_prev' => 1, 'gprint_prefix' => '', 'gprint_format' => 'on', 'graph_type' => '0', 'total' => 1, 'total_type' => 2, 'total_prefix' => '', 'order_type' => 1, 'agg_color_1' => 0];
	aggregate_form_save();
	$result['saved'] = $pdo->query('SELECT item_skip,item_total FROM aggregate_graph_templates_item WHERE aggregate_template_id=1')->fetch(PDO::FETCH_ASSOC);
	$result['errors'] = is_error_message();
} elseif ($mode === 'order') {
	$result['before'] = array_column(items($id), 'task_item_id');
	$result['saved_order'] = $pdo->query("SELECT local_graph_id FROM aggregate_graphs_items WHERE aggregate_graph_id=$agid ORDER BY sequence")->fetchAll(PDO::FETCH_COLUMN);
	$result['returned'] = push_out_aggregates(0, $id);
	$result['after'] = array_column(items($id), 'task_item_id');
	$result['saved_after'] = $pdo->query("SELECT local_graph_id FROM aggregate_graphs_items WHERE aggregate_graph_id=$agid ORDER BY sequence")->fetchAll(PDO::FETCH_COLUMN);
} elseif ($mode === 'empty') {
	api_aggregate_disassociate($id, $members);
	$result['members_remaining'] = (int)$pdo->query("SELECT COUNT(*) FROM aggregate_graphs_items WHERE aggregate_graph_id=$agid")->fetchColumn();
	$result['items_remaining'] = count(items($id));
} elseif ($mode === 'percentile') {
	$pdo->exec("INSERT INTO graph_templates_item (graph_template_id,local_graph_id,graph_type_id,task_item_id,text_format,sequence) VALUES (1,0,1,1,'95th |95:bits:0:aggregate_current:2|',2),(1,0,1,1,'peak |95:bits:0:all_max_current:2|',3)");
	$before = items($id);
	aggregate_handle_ptile_type($members, [], $id, AGGREGATE_TOTAL_ALL, AGGREGATE_TOTAL_TYPE_ALL);
	$result['last_sequence_before'] = max(array_column($before, 'sequence'));
	$result['items'] = items($id);
} elseif ($mode === 'cdef-scale') {
	$count = (int)($argv[3] ?? 100);
	$pdo->beginTransaction();
	for ($i = 2;$i <= $count + 1;$i++) {
		$pdo->exec("INSERT INTO cdef (id,hash,name,system) VALUES ($i,'review-$i','other $i',0)");
		$pdo->exec("INSERT INTO cdef_items (cdef_id,sequence,type,value) VALUES ($i,1,6,'CURRENT_DATA_SOURCE,$i,*')");
	}
	$pdo->commit();
	$pdo->query_counts = [];
	$start = hrtime(true);
	$result['returned'] = aggregate_cdef_totalling($id, 1, AGGREGATE_TOTAL_TYPE_ALL);
	$result['ms'] = (hrtime(true) - $start) / 1e6;
	$result['calls'] = array_sum($pdo->query_counts);
	$result['library_size'] = $count + 1;
	$result['items'] = count(items($id));
} elseif ($mode === 'member-scale') {
	$attrs['total'] = AGGREGATE_TOTAL_ONLY;
	$pdo->query_counts = [];
	$start = hrtime(true);
	$result['returned'] = aggregate_create_update($id, $members, $attrs);
	$result['ms'] = (hrtime(true) - $start) / 1e6;
	$result['calls'] = array_sum($pdo->query_counts);
	$result['sql_bytes'] = array_sum(array_map(fn ($q, $count) => strlen($q) * $count, array_keys($pdo->query_counts), array_values($pdo->query_counts)));
	$result['members'] = $n;
	$result['items'] = count(items($id));
} elseif ($mode === 'write-failure') {
	$stage = $argv[2];
	$stages = ['create-local' => ['INSERT', 'graph_local'], 'create-metadata' => ['INSERT', 'graph_templates_graph'], 'create-aggregate' => ['INSERT', 'aggregate_graphs'], 'local' => ['UPDATE', 'graph_local'], 'metadata' => ['UPDATE', 'graph_templates_graph'], 'aggregate' => ['UPDATE', 'aggregate_graphs'], 'delete-items' => ['DELETE', 'graph_templates_item'], 'insert-items' => ['INSERT', 'graph_templates_item'], 'delete-members' => ['DELETE', 'aggregate_graphs_items'], 'insert-members' => ['INSERT', 'aggregate_graphs_items'], 'cdef' => ['INSERT', 'cdef'], 'cdef-item' => ['INSERT', 'cdef_items'], 'total-update' => ['UPDATE', 'graph_templates_item']];
	check(isset($stages[$stage]), 'known failure stage');
	[$event,$table] = $stages[$stage];
	$before = snapshot_aggregate_tables();
	if (str_starts_with($stage, 'create-')) {
		$id = 0;
	}
	$original_id = $id;
	$attrs['total'] = AGGREGATE_TOTAL_ALL;
	$pdo->exec("CREATE TRIGGER aggregate_fail BEFORE $event ON $table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='aggregate fixture write failure'");
	try {
		$result['returned'] = aggregate_create_update($id, $members, $attrs);
	} finally {
		$pdo->exec('DROP TRIGGER aggregate_fail');
	}
	$result['unchanged'] = snapshot_aggregate_tables() === $before;
	$result['restored_id'] = $id === $original_id;
	$result['transaction_open'] = $pdo->inTransaction();
	$_SESSION['sess_messages'] = [];
	$result['retry'] = aggregate_create_update($id, $members, $attrs);
} elseif ($mode === 'transaction') {
	$case = $argv[2];
	$before = snapshot_aggregate_tables();
	if ($case === 'begin') {
		$pdo->fail_begin = true;
	}
	if ($case === 'commit') {
		$pdo->fail_commit = true;
	}
	if ($case === 'exception') {
		$pdo->before = function ($sql) {
			if (str_starts_with($sql, 'DELETE FROM graph_templates_item')) {
				throw new RuntimeException('fixture interruption');
			}
		};
	}
	if ($case === 'caller' || $case === 'nested') {
		$pdo->beginTransaction();
	}
	$result['returned'] = aggregate_create_update($id, $members, $attrs, $case !== 'caller');
	$result['transaction_open'] = $pdo->inTransaction();
	$pdo->before = null;
	$pdo->fail_begin = false;
	$pdo->fail_commit = false;
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	$result['unchanged'] = snapshot_aggregate_tables() === $before;
} elseif ($mode === 'membership') {
	$case = $argv[2];
	$pdo->exec('INSERT INTO graph_local (id,graph_template_id,host_id) VALUES (200,1,1)');
	$pdo->exec("INSERT INTO graph_templates_item (local_graph_id,graph_template_id,task_item_id,graph_type_id,color_id,text_format,sequence,consolidation_function_id) VALUES (200,1,101,4,1,'new',1,1)");
	$before = snapshot_aggregate_tables();
	if ($case === 'failure-add' || $case === 'failure-remove') {
		$pdo->exec("CREATE TRIGGER aggregate_fail BEFORE INSERT ON graph_templates_item FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='membership rebuild failure'");
	}
	try {
		if ($case === 'remove' || $case === 'failure-remove') {
			$result['returned'] = api_aggregate_disassociate($id, [101]);
		} else {
			$result['returned'] = api_aggregate_associate($id, $case === 'duplicate' ? [102] : [200]);
		}
	} finally {
		if (str_starts_with($case, 'failure-')) {
			$pdo->exec('DROP TRIGGER aggregate_fail');
		}
	}
	$result['unchanged'] = snapshot_aggregate_tables() === $before;
	$result['members'] = $pdo->query("SELECT local_graph_id FROM aggregate_graphs_items WHERE aggregate_graph_id=$agid ORDER BY sequence")->fetchAll(PDO::FETCH_COLUMN);
	$result['tasks'] = array_column(items($id), 'task_item_id');
} elseif ($mode === 'matrix') {
	$attrs['total'] = (int)$argv[2];
	$attrs['total_type'] = (int)$argv[3];
	$attrs['graph_type'] = (int)$argv[4];
	$attrs['reorder'] = (int)$argv[5];
	$pdo->exec("INSERT INTO graph_templates_item (local_graph_id,graph_template_id,task_item_id,graph_type_id,consolidation_function_id,text_format,hard_return,sequence) SELECT local_graph_id,graph_template_id,task_item_id,9,1,'Average','on',2 FROM graph_templates_item WHERE local_graph_id IN (0,101,102)");
	$attrs['item_no'] = 2;
	$attrs['total_items'] = [1 => 1, 2 => 2];
	$result['returned'] = aggregate_create_update($id, [102, 101], $attrs);
	$first = items($id);
	$result['count'] = count($first);
	$result['sequences_unique'] = count(array_unique(array_column($first, 'sequence'))) === count($first);
	$result['member_order'] = array_values(array_unique(array_filter(array_column($first, 'task_item_id'), fn ($v) => $v > 100)));
	$result['expressions'] = [];
	foreach ($first as $item) {
		if ($item['cdef_id']) {
			$result['expressions'][] = get_cdef($item['cdef_id']);
		}
	}
	$result['repeat'] = aggregate_create_update($id, [102, 101], $attrs);
	$second = items($id);
	foreach ($first as &$item) {
		unset($item['id']);
	} unset($item);
	foreach ($second as &$item) {
		unset($item['id']);
	} unset($item);
	$result['stable'] = $first === $second;
} elseif ($mode === 'percentile-mode') {
	$token = $argv[2];
	$type = (int)$argv[3];
	check(in_array($token, ['current', 'max', 'total', 'total_peak', 'all_max_current', 'all_max_peak', 'aggregate_max', 'aggregate_sum', 'aggregate_sum_peak', 'aggregate_current', 'aggregate_current_peak', 'aggregate', 'aggregate_peak']), 'known percentile mode');
	$statement = $pdo->prepare('INSERT INTO graph_templates_item (graph_template_id,local_graph_id,graph_type_id,task_item_id,text_format,value,sequence) VALUES (1,0,?,1,?,?,?)');
	$statement->execute([1, '95th |95:bits:0:' . $token . ':2|', '', 2]);
	$statement->execute([2, '95th', '|95:bits:0:' . $token . ':2|', 3]);
	$result['returned'] = aggregate_handle_ptile_type($members, [], $id, AGGREGATE_TOTAL_ALL, $type);
	$out = items($id);
	$result['sequences'] = array_column($out, 'sequence');
	$result['text'] = $out[2]['text_format'];
	$result['value'] = $out[4]['value'];
} elseif ($mode === 'checkbox') {
	load_actual_functions("$root/lib/html_utility.php", ['isset_request_var', 'isempty_request_var', 'set_request_var', 'get_request_var', 'get_nfilter_request_var', 'get_filter_request_var']);
	load_actual_functions("$root/aggregate_templates.php", ['aggregate_form_save', 'aggregate_template_save']);
	$pdo->exec("INSERT INTO aggregate_graph_templates (id,name,graph_template_id,gprint_prefix,gprint_format,graph_type,total,total_type,total_prefix,order_type,user_id) VALUES (1,'Review',1,'','on',0,1,2,'',1,1)");
	$pdo->exec("INSERT INTO aggregate_graph_templates_item (aggregate_template_id,graph_templates_item_id,sequence,color_template,item_skip,item_total) VALUES (1,1,1,0,'on','on')");
	$_REQUEST = $_POST = ['save_component_template' => 1, 'id' => 1, 'name' => 'Review', 'graph_template_id_prev' => 1, 'gprint_prefix' => '', 'gprint_format' => 'on', 'graph_type' => '0', 'total' => 1, 'total_type' => 2, 'total_prefix' => '', 'order_type' => 1, 'agg_color_1' => 0];
	if ($argv[2] === 'skip') {
		$_POST['agg_total_1'] = 'on';
	}
	if ($argv[2] === 'total') {
		$_POST['agg_skip_1'] = 'on';
	}
	if ($argv[2] === 'neither') {
		$_POST['agg_skip_1'] = 'on';
		$_POST['agg_total_1'] = 'on';
	}
	aggregate_form_save();
	$result['saved'] = $pdo->query('SELECT item_skip,item_total FROM aggregate_graph_templates_item WHERE aggregate_template_id=1')->fetch(PDO::FETCH_ASSOC);
	$result['errors'] = is_error_message();
} elseif ($mode === 'cdef-snapshot') {
	require_once "$root/lib/cdef.php";
	$pdo->exec("INSERT INTO cdef (id,name,hash) VALUES (2,'Nested','nested'),(3,'Cycle','cycle'),(4,'Empty','empty'),(5,'Missing','missing'),(6,'Invalid','invalid'),(7,'Diamond','diamond')");
	$pdo->exec("INSERT INTO cdef_items (cdef_id,sequence,type,value) VALUES (2,1,5,'1'),(2,2,6,'2,*'),(3,1,5,'3'),(5,1,5,'999'),(6,1,2,'9999'),(7,1,5,'2'),(7,2,5,'2')");
	$context = aggregate_cdef_context();
	$result['same'] = true;
	foreach (range(1, 7) as $cdef) {
		if (get_cdef($cdef) !== $context['definitions'][$cdef]['cdef_text']) {
			$result['same'] = false;
		}
	}
	$result['nested'] = $context['definitions'][2]['cdef_text'];
	$result['invalid'] = array_map(fn ($i) => $context['definitions'][$i]['cdef_text'], [3, 4, 5, 6]);
} elseif ($mode === 'multiple-sources') {
	$pdo->exec("INSERT INTO data_template_rrd (id,local_data_id,local_data_template_rrd_id,data_template_id,data_source_name,data_source_type_id) VALUES (2,0,0,1,'outbound',2),(301,101,2,1,'outbound',2),(302,102,2,1,'outbound',2)");
	$pdo->exec("INSERT INTO graph_templates_item (local_graph_id,graph_template_id,task_item_id,graph_type_id,color_id,text_format,hard_return,sequence,consolidation_function_id) VALUES (0,1,2,4,2,'outbound','on',3,1),(101,1,301,4,2,'outbound a','on',3,1),(102,1,302,4,2,'outbound b','on',3,1)");
	$pdo->exec("INSERT INTO graph_templates_item (local_graph_id,graph_template_id,task_item_id,graph_type_id,consolidation_function_id,text_format,hard_return,sequence) SELECT local_graph_id,graph_template_id,task_item_id,9,1,'Average','on',sequence+1 FROM graph_templates_item WHERE local_graph_id IN (0,101,102)");
	$attrs['item_no'] = 4;
	$attrs['total_items'] = [1 => 1, 2 => 2, 3 => 3, 4 => 4];
	$attrs['total'] = (int) $argv[2];
	$attrs['total_type'] = (int) $argv[3];
	$result['returned'] = aggregate_create_update($id, [102, 101], $attrs);
	$result['count'] = count(items($id));
	$result['sources'] = array_values(array_unique(array_filter(array_column(items($id), 'task_item_id'), fn ($i) => $i > 100)));
	$result['total_sources'] = array_values(array_unique(array_filter(array_column(items($id), 'task_item_id'), fn ($i) => $i > 0 && $i < 100)));
} elseif ($mode === 'batch') {
	$second = 0;
	check(aggregate_create_update($second, [102, 101], $attrs), 'second aggregate');
	$pdo->exec("INSERT INTO aggregate_graph_templates (id,name,graph_template_id,gprint_prefix,gprint_format,graph_type,total,total_type,total_prefix,order_type,user_id) VALUES (1,'Batch',1,'','on',0,2,2,'Total',1,1)");
	$pdo->exec("INSERT INTO aggregate_graph_templates_item (aggregate_template_id,graph_templates_item_id,sequence,color_template,item_skip,item_total) VALUES (1,1,1,0,'','on')");
	$pdo->exec("UPDATE aggregate_graphs SET aggregate_template_id=1,template_propogation='on'");
	$before = snapshot_aggregate_tables();
	$failure = $argv[2] === 'failure';
	if ($failure) {
		$pdo->exec("CREATE TRIGGER aggregate_fail BEFORE INSERT ON graph_templates_item FOR EACH ROW BEGIN IF NEW.local_graph_id=$second THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='second child rebuild failure'; END IF; END");
	}
	$pdo->query_counts = [];
	try {
		$result['returned'] = aggregate_transaction(function () { return push_out_aggregates(1, 0, false); });
	} finally {
		if ($failure) {
			$pdo->exec('DROP TRIGGER aggregate_fail');
		}
	}
	$result['unchanged'] = snapshot_aggregate_tables() === $before;
	$result['catalogue_reads'] = 0;
	foreach ($pdo->query_counts as $sql => $count) {
		if (str_starts_with($sql, 'SELECT id, name FROM cdef')) {
			$result['catalogue_reads'] += $count;
		}
	}
	if ($failure) {
		$result['retry'] = aggregate_transaction(function () { return push_out_aggregates(1, 0, false); });
	}
	$result['definitions'] = (int) $pdo->query('SELECT COUNT(*) FROM cdef')->fetchColumn();
	$result['first'] = count(items($id));
	$result['second'] = count(items($second));
} elseif ($mode === 'invalid-request') {
	$case = $argv[2];
	$before = snapshot_aggregate_tables();
	$selected = $members;
	if ($case === 'member') {
		$selected = [999999];
	}
	if ($case === 'template') {
		$attrs['graph_template_id'] = 999999;
	}
	if ($case === 'graph') {
		$id = 999999;
	}
	if ($case === 'empty-new') {
		$id = 0;
		$selected = [];
	}
	if ($case === 'total-type') {
		$attrs['total'] = 2;
		$attrs['total_type'] = 99;
	}
	$result['returned'] = aggregate_create_update($id, $selected, $attrs, $case !== 'unowned');
	$result['unchanged'] = snapshot_aggregate_tables() === $before;
	$result['transaction_open'] = $pdo->inTransaction();
} elseif ($mode === 'query-failure') {
	$stage = $argv[2];
	$patterns = [
		'rebuild-options' => '/^SELECT sequence FROM aggregate_graphs_graph_item/',
		'graph-metadata' => '/^SELECT \* FROM graph_templates_graph/',
		'member-metadata' => '/^SELECT host_id, graph_template_id, snmp_query_id, snmp_index FROM graph_local/',
		'palette' => '/FROM color_template_items/',
		'consolidation' => '/^SELECT local_graph_id, sequence, consolidation_function_id/',
		'cdef-catalogue' => '/^SELECT id, name FROM cdef/',
		'cdef-items' => '/^SELECT id, cdef_id, type, value FROM cdef_items/',
		'make-zero' => '/^INSERT INTO cdef /',
		'make-zero-item' => '/^INSERT INTO cdef_items /',
		'total-insert' => '/^INSERT INTO graph_templates_item .*VALUES \([0-9]+, 1, 0,/',
		'blank-line' => '/^INSERT INTO graph_templates_item .*VALUES \(\?, 1,/',
		'reorder' => '/^UPDATE graph_templates_item SET sequence/',
		'convert-type' => '/^UPDATE graph_templates_item SET graph_templates_item.graph_type_id/',
		'line-width' => '/^UPDATE graph_templates_item SET line_width = \?/',
		'line-prefix' => '/^UPDATE graph_templates_item SET graph_type_id = \? WHERE local_graph_id/',
		'line-total' => '/^UPDATE graph_templates_item SET graph_type_id = \?, line_width/',
		'line-missing' => '/^UPDATE graph_templates_item SET line_width="0.01"/',
		'percentile' => '/^INSERT INTO graph_templates_item .*VALUES \(\?, \?, \?, \?, 1,/',
	];
	check(isset($patterns[$stage]), 'known query failure');
	$attrs['total'] = AGGREGATE_TOTAL_ALL;
	if (in_array($stage, ['consolidation', 'make-zero', 'make-zero-item'])) {
		$attrs['total'] = AGGREGATE_TOTAL_ONLY;
	}
	if ($stage === 'palette') {
		$attrs['color_templates'] = [1 => 1];
	}
	if (str_starts_with($stage, 'make-zero')) {
		$pdo->exec('DELETE FROM cdef WHERE id=1');
	}
	if ($stage === 'reorder') {
		$attrs['reorder'] = AGGREGATE_ORDER_DS_GRAPH;
	}
	if ($stage === 'convert-type') {
		$pdo->exec('UPDATE graph_templates_item SET graph_type_id=8 WHERE local_graph_id IN (101,102)');
	}
	if (str_starts_with($stage, 'line-')) {
		$attrs['graph_type'] = AGGREGATE_GRAPH_TYPE_LINE1_STACK;
	}
	if ($stage === 'line-width') {
		$attrs['total'] = AGGREGATE_TOTAL_NONE;
	}
	if ($stage === 'percentile') {
		$pdo->exec("INSERT INTO graph_templates_item (graph_template_id,local_graph_id,graph_type_id,task_item_id,text_format,sequence) VALUES (1,0,1,1,'95th |95:bits:0:current:2|',2)");
	}
	$before = snapshot_aggregate_tables();
	$pdo->reject_query = $patterns[$stage];
	$result['returned'] = $stage === 'rebuild-options' ? push_out_aggregates(0, $id) : aggregate_create_update($id, [102, 101], $attrs);
	$pdo->reject_query = null;
	$result['faults'] = $pdo->rejected_queries;
	$result['unchanged'] = snapshot_aggregate_tables() === $before;
	$result['transaction_open'] = $pdo->inTransaction();
} elseif ($mode === 'graph-action') {
	load_actual_functions("$root/lib/html_utility.php", ['isset_request_var', 'isempty_request_var', 'set_request_var', 'get_request_var', 'get_nfilter_request_var', 'get_filter_request_var']);
	load_actual_functions("$root/lib/functions.php", ['sanitize_unserialize_selected_items']);
	load_actual_functions("$root/graphs.php", ['form_actions']);
	$_REQUEST = $_POST = ['selected_items' => serialize([102, 101]), 'drp_action' => '9', 'title_format' => 'Action aggregate', 'graph_template_id' => 1, 'gprint_prefix' => '', 'aggregate_graph_type' => 0, 'aggregate_total' => 1, 'aggregate_total_type' => 2, 'aggregate_total_prefix' => '', 'aggregate_order_type' => 1, 'item_no' => 1, 'agg_color_1' => 0];
	$before = snapshot_aggregate_tables();
	$stage = $argv[2];
	if ($stage === 'fresh-connection') {
		unset($GLOBALS['database_last_error']);
	}
	$tables = ['metadata' => 'aggregate_graphs', 'settings' => 'aggregate_graphs_graph_item', 'items' => 'graph_templates_item'];
	if (isset($tables[$stage])) {
		$pdo->exec('CREATE TRIGGER aggregate_fail BEFORE INSERT ON ' . $tables[$stage] . " FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='graph action failure'");
	}
	register_shutdown_function(function () use ($pdo, $before, $stage, $tables) {
		$result = ['transaction_open' => $pdo->inTransaction(), 'unchanged' => snapshot_aggregate_tables() === $before, 'graphs' => (int) $pdo->query('SELECT COUNT(*) FROM aggregate_graphs')->fetchColumn()];
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		if (isset($tables[$stage])) {
			$pdo->exec('DROP TRIGGER aggregate_fail');
		}
		print json_encode($result, JSON_THROW_ON_ERROR);
	});
	form_actions();
	exit;
} elseif ($mode === 'graph-form') {
	load_actual_functions("$root/lib/html_utility.php", ['isset_request_var', 'isempty_request_var', 'set_request_var', 'get_request_var', 'get_nfilter_request_var', 'get_filter_request_var']);
	load_actual_functions("$root/aggregate_graphs.php", ['form_save', 'aggregate_graph_form_save']);
	$_REQUEST = $_POST = ['save_component_graph' => 1, 'id' => $id, 'local_graph_id' => $id, 'graph_template_id' => 1, 'aggregate_template_id' => 0, 'title_format' => 'Changed title', 'gprint_prefix' => '', 'gprint_format' => 'on', 'total_prefix' => '', 'total' => 1, 'total_type' => 2, 'graph_type' => 0, 'order_type' => 1, 'agg_color_1' => 0];
	$case = $argv[2];
	$reject = ['metadata' => '/^INSERT INTO graph_templates_graph /', 'title' => '/^UPDATE aggregate_graphs SET title_format/', 'settings' => '/^INSERT INTO aggregate_graphs /', 'items' => '/^INSERT INTO aggregate_graphs_graph_item\b/'];
	$pdo->reject_query = $reject[$case] ?? null;
	if ($case === 'empty-title') {
		$_POST['title_format'] = $_REQUEST['title_format'] = '';
	}
	if ($case === 'missing-graph') {
		$_POST['local_graph_id'] = $_REQUEST['local_graph_id'] = 999999;
	}
	if ($case === 'invalid-width') {
		$_POST['width'] = $_REQUEST['width'] = 'invalid';
	}
	$before = snapshot_aggregate_tables();
	if ($argv[2] === 'failure') {
		$pdo->exec("CREATE TRIGGER aggregate_fail BEFORE INSERT ON graph_templates_item FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='form rebuild failure'");
	}
	try {
		form_save();
	} finally {
		if ($argv[2] === 'failure') {
			$pdo->exec('DROP TRIGGER aggregate_fail');
		}
	}
	$result['unchanged'] = snapshot_aggregate_tables() === $before;
	$result['title'] = $pdo->query("SELECT title_cache FROM graph_templates_graph WHERE local_graph_id=$id")->fetchColumn();
	$result['flags'] = $pdo->query("SELECT item_skip,item_total FROM aggregate_graphs_graph_item WHERE aggregate_graph_id=$agid")->fetch(PDO::FETCH_ASSOC);
	$result['transaction_open'] = $pdo->inTransaction();
} elseif ($mode === 'template-form' || $mode === 'public-api') {
	load_actual_functions("$root/lib/html_utility.php", ['isset_request_var', 'isempty_request_var', 'set_request_var', 'get_request_var', 'get_nfilter_request_var', 'get_filter_request_var']);
	$pdo->exec("INSERT INTO aggregate_graph_templates (id,name,graph_template_id,gprint_prefix,gprint_format,graph_type,total,total_type,total_prefix,order_type,user_id) VALUES (1,'Fixture',1,'','on',0,1,2,'',1,1)");
	$pdo->exec("INSERT INTO aggregate_graph_templates_item (aggregate_template_id,graph_templates_item_id,sequence,color_template,item_skip,item_total) VALUES (1,1,1,0,'','on')");
	$case = $argv[2];
	if ($mode === 'template-form') {
		$pdo->exec("UPDATE aggregate_graphs SET aggregate_template_id=1,template_propogation='on' WHERE id=$agid");
		load_actual_functions("$root/aggregate_templates.php", ['aggregate_form_save', 'aggregate_template_save']);
		$_REQUEST = $_POST = ['save_component_template' => 1, 'id' => 1, 'name' => 'Changed template', 'graph_template_id_prev' => 1, 'gprint_prefix' => '', 'gprint_format' => 'on', 'graph_type' => '0', 'total' => 2, 'total_type' => 1, 'total_prefix' => 'Combined', 'order_type' => 1, 'agg_color_1' => 0, 'agg_total_1' => 'on'];
	}
	$reject = ['template-row' => '/^INSERT INTO aggregate_graph_templates /', 'child-settings' => '/^UPDATE aggregate_graphs SET gprint_prefix/', 'template-params' => '/^INSERT INTO aggregate_graph_templates_graph /', 'template-items' => '/^INSERT INTO aggregate_graph_templates_item\b/', 'create-local' => '/^INSERT INTO graph_local /', 'create-metadata' => '/^INSERT INTO aggregate_graphs /', 'create-members' => '/^INSERT INTO aggregate_graphs_items /', 'convert-write' => '/^INSERT INTO aggregate_graphs /', 'convert-members' => '/^REPLACE INTO aggregate_graphs_items /', 'convert-rebuild' => '/^INSERT INTO graph_templates_item /'];
	$pdo->reject_query = $reject[$case] ?? null;
	if ($case === 'template-items') {
		$_POST['agg_skip_1'] = 'on';
	}
	if ($case === 'empty-name') {
		$_POST['name'] = $_REQUEST['name'] = '';
	}
	if ($case === 'missing-template') {
		$_POST['id'] = $_REQUEST['id'] = 999999;
	}
	if ($case === 'invalid-width') {
		$_POST['t_width'] = 'on';
		$_POST['width'] = 'invalid';
	}
	if ($case === 'convert-empty') {
		$pdo->exec("DELETE FROM graph_templates_item WHERE local_graph_id=$id");
	}
	$before = snapshot_aggregate_tables();
	if ($case === 'failure') {
		$pdo->exec("CREATE TRIGGER aggregate_fail BEFORE INSERT ON graph_templates_item FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='caller rebuild failure'");
	}
	try {
		if ($mode === 'template-form') {
			aggregate_form_save();
			$result['total'] = (int) $pdo->query("SELECT total FROM aggregate_graphs WHERE id=$agid")->fetchColumn();
			$result['total_type'] = (int) $pdo->query("SELECT total_type FROM aggregate_graphs WHERE id=$agid")->fetchColumn();
		} elseif (str_starts_with($case, 'convert')) {
			$_REQUEST = $_POST = ['aggregate_template_id' => $case === 'convert-missing-template' ? 999999 : 1];
			$result['returned'] = api_aggregate_convert_template([$case === 'convert-missing-graph' ? 999999 : $id]);
		} elseif ($case === 'combine') {
			$result['returned'] = api_aggregate_create('Combined aggregates', [$id]);
		} else {
			$result['returned'] = api_aggregate_create('From template', [102, 101], $case === 'missing' ? 999 : 1);
		}
	} finally {
		if ($case === 'failure') {
			$pdo->exec('DROP TRIGGER aggregate_fail');
		}
	}
	$result['unchanged'] = snapshot_aggregate_tables() === $before;
	$result['transaction_open'] = $pdo->inTransaction();
	$result['graphs'] = (int) $pdo->query('SELECT COUNT(*) FROM aggregate_graphs')->fetchColumn();
	$result['tasks'] = array_column(items((int) $pdo->query('SELECT MAX(local_graph_id) FROM aggregate_graphs')->fetchColumn()), 'task_item_id');
} elseif ($mode === 'invalid-cdef') {
	$case = $argv[2];
	if ($case !== 'missing') {
		$pdo->exec("INSERT INTO cdef (id,name,hash) VALUES (2,'Invalid reference','invalid-reference')");
	}
	if ($case === 'cycle') {
		$pdo->exec("INSERT INTO cdef_items (cdef_id,sequence,type,value) VALUES (2,1,5,'2')");
	}
	$pdo->exec('UPDATE graph_templates_item SET cdef_id=2 WHERE local_graph_id=0');
	$before = snapshot_aggregate_tables();
	$attrs['total'] = AGGREGATE_TOTAL_ALL;
	$result['returned'] = aggregate_create_update($id, $members, $attrs);
	$result['unchanged'] = snapshot_aggregate_tables() === $before;
	$result['transaction_open'] = $pdo->inTransaction();
} elseif ($mode === 'palette-cf') {
	$pdo->exec('DELETE FROM color_template_items');
	if ($argv[2] !== 'empty') {
		$pdo->exec('INSERT INTO color_template_items (color_template_id,color_id,sequence) VALUES (1,10,1),(1,20,2)');
	}
	$pdo->exec('UPDATE graph_templates_item SET consolidation_function_id=3 WHERE local_graph_id=102');
	$attrs['color_templates'] = [1 => 1];
	$attrs['total'] = AGGREGATE_TOTAL_ONLY;
	$pdo->query_counts = [];
	$result['returned'] = aggregate_create_update($id, [102, 101], $attrs);
	$result['colors'] = array_column(items($id), 'color_id');
	$result['cfs'] = array_map('intval', $pdo->query("SELECT consolidation_function_id FROM graph_templates_item WHERE local_graph_id=$id ORDER BY sequence")->fetchAll(PDO::FETCH_COLUMN));
	$result['palette_queries'] = 0;
	foreach ($pdo->query_counts as $sql => $count) {
		if (str_contains($sql, 'FROM color_template_items')) {
			$result['palette_queries'] += $count;
		}
	}
} elseif ($mode === 'overrides') {
	$pdo->exec("INSERT INTO cdef (id,name,hash) VALUES (2,'Multiply by eight','multiply-eight')");
	$pdo->exec("INSERT INTO cdef_items (cdef_id,sequence,type,value) VALUES (2,1,6,'CURRENT_DATA_SOURCE,8,*')");
	$attrs['cdefs'] = [1 => 2];
	$attrs['graph_item_types'] = [1 => GRAPH_ITEM_TYPE_AREA];
	$attrs['total'] = (int) $argv[2];
	$result['returned'] = aggregate_create_update($id, [102, 101], $attrs);
	$result['cdefs'] = array_column(items($id), 'cdef_id');
	$result['types'] = array_column(items($id), 'graph_type_id');
} elseif ($mode === 'concurrent') {
	$pdo->exec('INSERT INTO graph_local (id,graph_template_id,host_id) VALUES (200,1,1)');
	$pdo->exec("INSERT INTO graph_templates_item (local_graph_id,graph_template_id,task_item_id,graph_type_id,color_id,text_format,sequence,consolidation_function_id) VALUES (200,1,101,4,1,'new',1,1)");
	$pdo->beginTransaction();
	$pdo->query("SELECT id FROM graph_local WHERE id=$id FOR UPDATE")->fetchColumn();
	$child = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', __FILE__, 'concurrent-member', (string) $id, '200'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	check(is_resource($child), 'spawn concurrent membership edit');
	$waiting = false;
	try {
		$deadline = microtime(true) + 10;
		do {
			foreach ($pdo->query('SHOW PROCESSLIST')->fetchAll(PDO::FETCH_ASSOC) as $process) {
				if (str_contains($process['Info'] ?? '', 'SELECT id FROM graph_local WHERE id = ') && str_contains($process['Info'], 'FOR UPDATE')) {
					$waiting = true;
				}
			}
			if (!$waiting) {
				usleep(10000);
			}
		} while (!$waiting && microtime(true) < $deadline);
		$result['waited'] = $waiting;
		$result['rebuilt'] = push_out_aggregates(0, $id, false);
		$pdo->commit();
	} finally {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
	}
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	check(proc_close($child) === 0, 'concurrent child: ' . $stderr);
	$result['child'] = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
	$result['members'] = array_map('intval', $pdo->query("SELECT local_graph_id FROM aggregate_graphs_items WHERE aggregate_graph_id=$agid ORDER BY sequence")->fetchAll(PDO::FETCH_COLUMN));
	$result['tasks'] = array_column(items($id), 'task_item_id');
}

function snapshot_aggregate_tables() {
	$tables = ['graph_local', 'graph_templates_graph', 'graph_templates_item', 'aggregate_graphs', 'aggregate_graphs_items', 'aggregate_graphs_graph_item', 'aggregate_graph_templates', 'aggregate_graph_templates_graph', 'aggregate_graph_templates_item', 'cdef', 'cdef_items'];
	$result = [];
	foreach ($tables as $table) {
		$result[$table] = $GLOBALS['pdo']->query("SELECT * FROM $table ORDER BY 1,2")->fetchAll(PDO::FETCH_ASSOC);
	}
	return $result;
}
function csrf_require_post() {
}

print json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
