<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
const SNMP_POLLER = 'POLLER';
const SNMP_STRING_OUTPUT_GUESS = 1;
$root = sys_get_temp_dir() . '/cisco-discovery-' . bin2hex(random_bytes(6));
mkdir($root); mkdir($root . '/scripts'); mkdir($root . '/lib');
file_put_contents($root . '/lib/snmp.php', '<?php');
foreach (['ss_cisco_health.php','ss_memory_combined.php','ss_temperature_slots.php'] as $name) {
 copy(dirname(__DIR__, 2) . '/scripts/' . $name, $root . '/scripts/' . $name);
}
register_shutdown_function(function () use ($root) {
 foreach (glob($root . '/scripts/*') as $p) unlink($p);
 unlink($root . '/lib/snmp.php'); rmdir($root . '/scripts'); rmdir($root . '/lib'); rmdir($root);
});
$config = ['base_path'=>$root];
$saved = true;
$walks = []; $gets = [];
function db_fetch_assoc_prepared($sql, $args) {
 global $saved;
 if (!$saved) return [];
 return [['binding'=>$args[1] === '59bb7c19c1f17052ccb33a1937e40155' ? 't10_20_0_0_0_0_0_0' : 'm7.1_7.2']];
}
function db_fetch_row_prepared(...$args) {
 return ['hostname'=>'fixture','snmp_community'=>'fixture','snmp_version'=>'2','snmp_username'=>'','snmp_password'=>'','snmp_auth_protocol'=>'','snmp_priv_passphrase'=>'','snmp_priv_protocol'=>'','snmp_context'=>'','snmp_port'=>161,'snmp_timeout'=>100,'bulk_walk_size'=>10];
}
function cacti_snmp_walk($host,$community,$oid,...$args) {
 global $walks;
 $walks[]=$oid;
 $index = str_contains($oid,'.99.') ? '30' : '7.3';
 return [['oid'=>$oid.'.'.$index,'value'=>str_contains($oid,'.99.') ? '8' : 'Processor']];
}
function cacti_snmp_get($host,$community,$oid,...$args) {
 global $gets;
 $gets[]=$oid;
 return str_contains($oid,'.221.') ? 'Processor' : 'Entity';
}
require $root . '/scripts/ss_memory_combined.php';
require $root . '/scripts/ss_temperature_slots.php';
function verify($value,$expected,$label) {
 if ($value !== $expected) throw new RuntimeException($label . ': ' . json_encode($value));
}
foreach (['index','num_indexes'] as $cmd) {
 ss_memory_combined(1,'memory',$cmd); ss_memory_combined(1,'qfp_memory',$cmd); ss_temperature_slots(1,$cmd);
}
verify($walks,[],'Saved index/count must not walk'); verify($gets,[],'Saved index/count must not GET');
ss_memory_combined(1,'memory','query','pool1Name');
verify(count($gets),2,'One enhanced pool uses two label GETs');
verify($gets[0],'.1.3.6.1.4.1.9.9.221.1.1.1.1.3.7.1','Bound pool only');
$gets=[]; ss_memory_combined(1,'qfp_memory','query','pool2Name');
verify(count($gets),1,'One QFP entity GET');
$gets=[]; ss_temperature_slots(1,'query','sensor2Name');
verify($gets,['.1.3.6.1.2.1.47.1.1.1.1.7.20'],'Bound sensor only');
$gets=[]; ss_temperature_slots(1,'query','sensor8Name'); ss_memory_combined(1,'memory','query','pool8Name');
verify($gets,[],'Unused slots do not GET'); verify($walks,[],'Saved labels do not walk');
ss_memory_combined(1,'memory','query','newPools'); ss_temperature_slots(1,'query','newSensors');
if (!$walks) throw new RuntimeException('New resource checks must still discover');
$walks=[]; $saved=false;
ss_memory_combined(1,'memory','index'); ss_temperature_slots(1,'index');
if (!$walks) throw new RuntimeException('Fresh groups must still discover');
echo "PASS saved bindings skip walks; targeted and unused labels; new resources still discovered\n";
