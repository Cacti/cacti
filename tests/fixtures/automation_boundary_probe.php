<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
define('CACTI_TEST_REAL_HOST_PROPAGATION', true);
require __DIR__ . '/automation_creation_bootstrap.php';
require_once $root . '/lib/utility.php';
load_actual_functions($root . '/lib/functions.php', ['get_data_source_item_name', 'get_data_source_path', 'clean_up_path', 'array_to_sql_or']);
clear_fixture();
$pdo->exec('DELETE FROM data_input_fields WHERE id=900010');
seed_graph_template();
$mode   = $argv[1];
$values = [];

if ($mode === 'query-failure') {
	// An incomplete upgraded table makes the real identity query fail at execute.
	$pdo->exec('CREATE TEMPORARY TABLE graph_templates_graph (local_graph_id INT)');
	$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
	check(automation_create_graph(1, 1, [], $values) === false, 'identity query failure rejects creation');
	check((int)$pdo->query('SELECT COUNT(*) FROM graph_local')->fetchColumn() === 0, 'failed identity query leaves no shell');
} elseif ($mode === 'defaults' || $mode === 'template-race') {
	$pdo->exec("INSERT INTO data_input_fields (id,data_input_id,name,data_name,input_output) VALUES (900010,1,'Input','fixture_input','in')");
	$pdo->exec("INSERT INTO data_input_data (data_input_field_id,data_template_data_id,t_value,value) VALUES (900010,1,'on','fixture-default')");

	if ($mode === 'template-race') {
		$competitor  = new PDO($dsn, getenv('CACTI_AUTOMATION_TEST_USER') ?: 'root', getenv('CACTI_AUTOMATION_TEST_PASSWORD') ?: '');
		$pdo->before = static function ($sql) use ($pdo, $competitor) {
			if (str_contains($sql, "GET_LOCK(CONCAT('cacti.auto.'")) {
				$pdo->before = null;
				$competitor->exec('INSERT INTO graph_local (id,host_id,graph_template_id) VALUES (900001,1,1)');
				$competitor->exec("INSERT INTO graph_templates_graph (local_graph_id,graph_template_id,title) VALUES (900001,1,'Concurrent graph')");
			}
		};
	}
	automation_execute_graph_template(1, 1);
	check((int)$pdo->query('SELECT COUNT(*) FROM graph_local')->fetchColumn() === 1, 'template automation creates only one graph');

	if ($mode === 'defaults') {
		check($pdo->query('SELECT value FROM data_input_data WHERE data_input_field_id=900010 AND data_template_data_id<>1')->fetchColumn() === 'fixture-default', 'input default reaches the created source');
		automation_execute_graph_template(1, 1);
		check((int)$pdo->query('SELECT COUNT(*) FROM graph_local')->fetchColumn() === 1, 'existing graph is reused');
	} else {
		check((int)$pdo->query('SELECT COUNT(*) FROM data_local')->fetchColumn() === 0, 'concurrent graph does not create unused sources');
		check((int)$pdo->query('SELECT id FROM graph_local')->fetchColumn() === 900001, 'concurrent graph identity is preserved');
	}
} elseif ($mode === 'device-template') {
	$pdo->exec('INSERT INTO host_graph (host_id,graph_template_id) VALUES (1,1)');
	automation_update_device(1);
	check((int)$pdo->query('SELECT COUNT(*) FROM graph_local')->fetchColumn() === 1, 'device update creates its associated template graph');
} elseif ($mode === 'dq-empty' || $mode === 'dq-unmatched') {
	if ($mode === 'dq-unmatched') {
		$pdo->exec('DELETE FROM automation_templates');
		$pdo->exec('INSERT INTO automation_templates (id,host_template) VALUES (1,1)');
		$pdo->exec('UPDATE host SET host_template_id=1 WHERE id=1');
		$pdo->exec('INSERT INTO automation_templates_rules (template_id,rule_id,rule_type) VALUES (1,1,1)');
		$pdo->exec('INSERT INTO host_snmp_query (host_id,snmp_query_id) VALUES (1,1)');
		$pdo->exec("INSERT INTO automation_graph_rules (id,name,snmp_query_id,graph_type_id,enabled) VALUES (1,'Unmatched',1,1,'on')");
		$pdo->exec('INSERT INTO automation_match_rule_items (rule_id,rule_type,sequence,field,operator,pattern) VALUES (1,' . AUTOMATION_RULE_TYPE_GRAPH_MATCH . ",1,'h.hostname'," . AUTOMATION_OP_MATCHES . ",'missing-host')");
	}
	automation_execute_data_query(1, 1);
	check((int)$pdo->query('SELECT COUNT(*) FROM graph_local')->fetchColumn() === 0, 'empty or unmatched data-query rules create no graphs');
} elseif ($mode === 'dq-race') {
	$pdo->exec("INSERT INTO host_snmp_query (host_id,snmp_query_id,sort_field) VALUES (1,1,'field0')");
	$pdo->exec("INSERT INTO snmp_query_graph (id,snmp_query_id,graph_template_id,name) VALUES (1,1,1,'Traffic')");
	$pdo->exec("INSERT INTO automation_graph_rules (id,name,snmp_query_id,graph_type_id,enabled) VALUES (1,'Interfaces',1,1,'on')");
	$pdo->exec('INSERT INTO automation_graph_rule_items (rule_id,sequence,operation,field,operator,pattern) VALUES (1,1,0,\'field0\',' . AUTOMATION_OP_BEGINS . ",'if')");
	$pdo->exec("INSERT INTO host_snmp_cache (host_id,snmp_query_id,field_name,field_value,snmp_index,oid) VALUES (1,1,'field0','if1','1','')");
	$competitor  = new PDO($dsn, getenv('CACTI_AUTOMATION_TEST_USER') ?: 'root', getenv('CACTI_AUTOMATION_TEST_PASSWORD') ?: '');
	$pdo->before = static function ($sql) use ($pdo, $competitor) {
		if (str_contains($sql, "GET_LOCK(CONCAT('cacti.auto.'")) {
			$pdo->before = null;
			$competitor->exec("INSERT INTO graph_local (id,host_id,graph_template_id,snmp_query_id,snmp_query_graph_id,snmp_index) VALUES (900001,1,1,1,1,'1')");
			$competitor->exec("INSERT INTO graph_templates_graph (local_graph_id,graph_template_id,title) VALUES (900001,1,'Concurrent query graph')");
		}
	};
	create_dq_graphs(1, 1, ['id' => 1, 'name' => 'Interfaces', 'snmp_query_id' => 1, 'graph_type_id' => 1]);
	check($pdo->query('SELECT id FROM graph_local')->fetchAll(PDO::FETCH_COLUMN) === [900001], 'concurrent query graph identity is preserved');
	check((int)$pdo->query('SELECT COUNT(*) FROM data_local')->fetchColumn() === 0, 'concurrent query does not create unused sources');
} elseif ($mode === 'device-header-failure') {
	$pdo->exec("INSERT INTO graph_tree (id,name,sort_type) VALUES (1,'Tree',2)");
	$pdo->exec("INSERT INTO automation_tree_rules (id,name,tree_id,tree_item_id,leaf_type,host_grouping_type,enabled) VALUES (1,'Device',1,0," . TREE_ITEM_TYPE_HOST . ",1,'on')");
	$pdo->exec('INSERT INTO automation_match_rule_items (rule_id,rule_type,sequence,field,operator,pattern) VALUES (1,' . AUTOMATION_RULE_TYPE_TREE_MATCH . ",1,'h.hostname'," . AUTOMATION_OP_MATCHES . ",'router-a')");
	$pdo->exec("INSERT INTO automation_tree_rule_items (rule_id,sequence,field,search_pattern,replace_pattern,sort_type,propagate_changes) VALUES (1,1,'0','Required Header','',2,'')");
	$pdo->exec("CREATE TRIGGER automation_fail_header BEFORE INSERT ON graph_tree_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture required header failure'");

	try {
		automation_execute_device_create_tree(1);
	} finally {
		$pdo->exec('DROP TRIGGER automation_fail_header');
	}
	check((int)$pdo->query('SELECT COUNT(*) FROM graph_tree_items')->fetchColumn() === 0, 'failed required header does not place a device at root');
	automation_execute_device_create_tree(1);
	check((int)$pdo->query('SELECT COUNT(*) FROM graph_tree_items WHERE host_id=1 AND parent>0')->fetchColumn() === 1, 'retry places the device beneath its required header');
} elseif ($mode === 'multi-header' || $mode === 'multi-header-failure') {
	$pdo->exec("INSERT INTO graph_tree (id,name,sort_type) VALUES (1,'Tree',2)");

	if ($mode === 'multi-header-failure') {
		$pdo->exec("CREATE TRIGGER automation_fail_header BEFORE INSERT ON graph_tree_items FOR EACH ROW BEGIN IF NEW.title='Child' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture header failure'; END IF; END");
	}

	try {
		$result = create_multi_header_node('router-a', ['tree_id' => 1, 'host_grouping_type' => 1], ['field' => 'h.hostname', 'search_pattern' => '^router-a$', 'replace_pattern' => 'Parent\\nChild', 'sort_type' => 2, 'propagate_changes' => ''], 0);
	} finally {
		if ($mode === 'multi-header-failure') {
			$pdo->exec('DROP TRIGGER automation_fail_header');
		}
	}

	if ($mode === 'multi-header-failure') {
		check($result === false, 'failed replacement header propagates failure');
		check((int)$pdo->query('SELECT COUNT(*) FROM graph_tree_items')->fetchColumn() === 1, 'failed child creates no root fallback');
	} else {
		check($result > 0, 'replacement headers are created');
		check($pdo->query('SELECT parent.title FROM graph_tree_items AS child JOIN graph_tree_items AS parent ON parent.id=child.parent WHERE child.id=' . $result)->fetchColumn() === 'Parent', 'replacement headers retain their hierarchy');
	}
}
emit_result($mode, ['verified' => true]);
