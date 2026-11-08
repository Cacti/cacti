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
$n = (int)($argv[1] ?? 1000);
$mixed = ($argv[3] ?? '') == 'mixed';
$fields = (int)($argv[2] ?? 8);
clear_fixture();
if ($mixed) seed_graph_template();
else $pdo->exec("INSERT INTO host (id,hostname,description) VALUES (1,'router-a','router-a')");
$pdo->exec("INSERT INTO host_snmp_query (host_id,snmp_query_id,sort_field) VALUES (1,1,'field0')");
$pdo->exec("INSERT INTO snmp_query_graph (id,snmp_query_id,graph_template_id,name) VALUES (1,1,1,'traffic')");
$pdo->exec("INSERT INTO automation_graph_rules (id,name,snmp_query_id,graph_type_id,enabled) VALUES (1,'allinterfaces',1,1,'on')");
$s=$pdo->prepare('INSERT INTO automation_graph_rule_items (rule_id,sequence,operation,field,operator,pattern) VALUES (1,1,0,?,?,?)');
$s->execute(['field0',AUTOMATION_OP_BEGINS,'if']);
$pdo->beginTransaction();
$cache=$pdo->prepare('INSERT INTO host_snmp_cache (host_id,snmp_query_id,field_name,field_value,snmp_index,oid) VALUES (1,1,?,?,?,?)');
$graph=$pdo->prepare('INSERT INTO graph_local (host_id,graph_template_id,snmp_query_id,snmp_query_graph_id,snmp_index) VALUES (1,1,1,1,?)');
for($i=1;$i<=$n;$i++) {
	for($f=0;$f<$fields;$f++) $cache->execute(['field'.$f,'if'.$i,(string)$i,'']);
	$graph->execute([(string)$i]);
	$pdo->exec('INSERT INTO graph_templates_graph (local_graph_id,graph_template_id) VALUES (' . $pdo->lastInsertId() . ',1)');
}
$pdo->commit();
if ($mixed) {
	// Use the real graph/source construction path for missing string-valued indexes.
	$pdo->exec('UPDATE data_template_data SET data_input_id=2 WHERE id=1');
	$pdo->exec("INSERT INTO snmp_query (id,name,data_input_id) VALUES (1,'fixture',2)");
	$pdo->exec("INSERT INTO snmp_query_graph_rrd VALUES (1,1,1,'counterA')");
	foreach (array('01','port-A','port-B') as $index) {
		$cache->execute(array('field0','if'.$index,$index,''));
	}
	$cache->execute(array('field0','excluded','port-C',''));
}
$pdo->query_counts=[];
$start=hrtime(true);
create_dq_graphs(1,1,['id'=>1,'name'=>'allinterfaces','snmp_query_id'=>1,'graph_type_id'=>1]);
$elapsed=(hrtime(true)-$start)/1e6;
$duplicates=0;
foreach($pdo->query_counts as $sql=>$count) if(str_starts_with($sql,'SELECT DISTINCT gl.id')) $duplicates+=$count;
check($duplicates===0,'existing graphs must be filtered in bulk');
$count=(int)$pdo->query('SELECT COUNT(*) FROM graph_local')->fetchColumn();
check($count===$n+($mixed?3:0),'discovery creates only missing matching indexes: ' . json_encode($review_logs));
if ($mixed) {
	$pdo->exec("INSERT INTO automation_match_rule_items (rule_id,rule_type,sequence,field,operator,pattern) VALUES (1," . AUTOMATION_RULE_TYPE_GRAPH_MATCH . ",1,'h.hostname'," . AUTOMATION_OP_MATCHES . ",'router-a')");
	automation_update_device(1);
	check((int)$pdo->query('SELECT COUNT(*) FROM graph_local')->fetchColumn()===$count, 'retry preserves graph identity');
	check((int)$pdo->query('SELECT COUNT(*) FROM data_local')->fetchColumn()===3, 'three distinct string indexes have three sources');
	check(!array_filter($review_hooks, fn($hook)=>$hook[1]),'DQ hooks run after commit');
}

emit_result('unchanged data-query automation', ['indexes'=>$n,'fields'=>$fields,'milliseconds'=>$elapsed,'database_calls'=>array_sum($pdo->query_counts),'duplicate_checks'=>$duplicates,'peak_mib'=>memory_get_peak_usage(true)/1048576]);
