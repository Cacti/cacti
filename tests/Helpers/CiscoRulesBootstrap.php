<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
// Isolated fake databases for exercising the real CLI entry point.
const AUTOMATION_OP_MATCHES = 1;
const AUTOMATION_RULE_TYPE_GRAPH_MATCH = 1;
$scenario = getenv('CACTI_HEALTH_RULE_TEST');
$config = ['poller_id'=>str_starts_with($scenario, 'remote') ? 2 : 1];
$remote_db_cnn_id = $scenario === 'remote-offline' ? null : new stdClass();
$main = $config['poller_id'] === 1;
$writes = 0;
$spec = json_decode(file_get_contents(dirname(__DIR__) . '/cli/cisco_health_rules.json'), true);
register_shutdown_function(function () { global $main,$writes; echo 'RESULT main='.(int)$main.' writes='.$writes.PHP_EOL; });
function db_switch_remote_to_main() { global $main; $main=true; return true; }
function assert_main() { global $main; if (!$main) throw new RuntimeException('Accessed collector-local database'); }
function db_table_exists($table) { global $scenario; assert_main(); return str_starts_with($scenario,'linked') || $scenario==='empty-template'; }
function db_fetch_cell($sql) { assert_main(); return 1; }
function db_fetch_cell_prepared($sql,$args) {
 assert_main();
 if (str_contains($sql,'information_schema')) return 'InnoDB';
 if (str_contains($sql,'host_template_snmp_query')) return 1;
 if (str_contains($sql,'host_template')) return 42;
 if (str_contains($sql,'snmp_query')) return 10;
 throw new RuntimeException('Unexpected cell query');
}
function db_fetch_assoc_prepared($sql,$args) {
 global $scenario,$spec;
 assert_main();
 if (str_contains($sql,'automation_templates_rules')) {
  return $scenario==='linked-all' ? array_map(fn($i)=>['rule_id'=>$i+100],range(1,27)) : ($scenario==='empty-template' ? [] : [['rule_id'=>999]]);
 }
 if (str_contains($sql,'FROM snmp_query_graph')) {
  foreach ($spec as $i=>$rule) if ($rule['variant_hash']===$args[1]) return [['id'=>$i+1]];
  return [];
 }
 if (str_contains($sql,'FROM automation_graph_rules')) {
  if (!in_array($scenario,['linked-all','linked-unlinked'],true)) return [];
  return [['id'=>$args[1]+100,'name'=>$args[2],'enabled'=>'on','snmp_query_id'=>10,'graph_type_id'=>$args[1]]];
 }
 if (str_contains($sql,'FROM automation_match_rule_items')) {
  return [['sequence'=>'1','operation'=>'0','field'=>'h.host_template_id','operator'=>'1','pattern'=>'42']];
 }
 if (str_contains($sql,'FROM automation_graph_rule_items')) {
  $r=$spec[$args[0]-101];
  return $r['field']===null ? [] : [['sequence'=>'1','operation'=>'0','field'=>$r['field'],'operator'=>'1','pattern'=>(string)$r['matches']]];
 }
 throw new RuntimeException('Unexpected list query');
}
function db_execute($sql) { assert_main(); return true; }
function sql_save($row,$table) { global $writes; assert_main(); return ++$writes; }
function read_config_option($name) { return 'on'; }
