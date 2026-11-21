<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
// Include the actual bundle in an isolated fixture, as Cacti's Script Server does.
$root=sys_get_temp_dir().'/cisco-loader-'.bin2hex(random_bytes(6));
mkdir($root);mkdir($root.'/scripts');mkdir($root.'/lib');
file_put_contents($root.'/lib/snmp.php','<?php');
foreach(['ss_cisco_health.php','ss_memory_combined.php','ss_temperature_slots.php'] as $file)copy(dirname(__DIR__, 2).'/scripts/'.$file,$root.'/scripts/'.$file);
function db_fetch_row_prepared(...$args){return [];}
const SNMP_POLLER='POLLER';
const SNMP_STRING_OUTPUT_GUESS=1;
$called_by_script_server=true;
$config=['base_path'=>$root];
try {
 ob_start();
 require $root.'/scripts/ss_memory_combined.php';
 require $root.'/scripts/ss_temperature_slots.php';
 if(ss_memory_combined(1,'memory','get','used1','m1')!=='U')throw new RuntimeException('Missing host memory');
 if(ss_temperature_slots(1,'get','temperature1','t1_0_0_0_0_0_0_0')!=='U')throw new RuntimeException('Missing host temperature');
 require_once $root.'/scripts/ss_cisco_health.php';
 if(ob_get_clean()!=='')throw new RuntimeException('Collector include wrote unexpected output');
 if(ss_temperature_slots('bad','get','temperature1','t1_0_0_0_0_0_0_0')!=='U')throw new RuntimeException('Invalid host');
 echo "PASS shared collector inclusion: memory, temperature and cellular coexist without output or redeclaration\n";
} finally {
 foreach(glob($root.'/scripts/*') as $file)unlink($file);
 unlink($root.'/lib/snmp.php');rmdir($root.'/scripts');rmdir($root.'/lib');rmdir($root);
}
