<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
// Exercise the actual bundled raw-read helper with a fake SNMP executable.
const SNMP_POLLER='POLLER';
const SNMP_STRING_OUTPUT_GUESS=1;
const SNMP_STRING_OUTPUT_HEX=2;
const SNMP_METHOD_PHP=1;
function cacti_snmp_options_sanitize(&$v,&$c,&$p,&$t,&$r,&$m){return in_array($v,['1','2','3'],true);}
if (($argv[1] ?? '') === 'array') {
 function snmp_get_method($type='walk', $version=1, $context='', $engineid='', $value_output_format=1, $auth_proto='', $priv_proto='') {
  if ($auth_proto !== 'SHA' || $priv_proto !== 'AES') throw new RuntimeException('Misplaced 1.2 selector protocols');
  return 2;
 }
} else {
 function snmp_get_method($type='walk', $version=1, $context='', $engineid='', $value_output_format=1, $php_snmp=true, $auth_proto='', $priv_proto='') {
  if ($php_snmp !== true || $auth_proto !== 'SHA' || $priv_proto !== 'AES') throw new RuntimeException('Misplaced develop selector protocols');
  return 2;
 }
}
function cacti_format_ipv6_colon($h){return $h;}
function snmp_escape_string($s){return escapeshellarg($s);}
function cacti_get_snmpv3_auth(...$a){return '-u '.escapeshellarg($a[1]).' -l authPriv -A '.escapeshellarg($a[2]);}
function cacti_escapeshellcmd($s){return escapeshellcmd($s);}
function cacti_escapeshellarg_cmd($s,...$a){return escapeshellarg($s);}
function cacti_escapeshellarg($s){return escapeshellarg($s);}
function read_config_option($s){global $binary;return $binary;}
function cacti_log(...$a){}
if(($argv[1]??'')==='array'){
 function cacti_get_snmp_auth_args(&$v,$community,...$a){$v=$v==='2'?'2c':$v;return ['-c',$community];}
 function snmp_format_target_arg($h,$p){return $h.':'.$p;}
 function cacti_snmp_debug_command(...$a){}
 function cacti_snmp_command_timeout(...$a){return 2;}
}
$root=sys_get_temp_dir().'/cisco-snmp-loader-'.bin2hex(random_bytes(6));
mkdir($root);mkdir($root.'/scripts');mkdir($root.'/lib');
file_put_contents($root.'/lib/snmp.php','<?php');
copy(dirname(__DIR__, 2).'/scripts/ss_cisco_health.php',$root.'/scripts/ss_cisco_health.php');
require $root.'/scripts/ss_cisco_health.php';
$binary=tempnam(sys_get_temp_dir(),'cisco-snmp-test-');
file_put_contents($binary,'#!'.PHP_BINARY."\n<?php array_shift(\$argv);echo json_encode(\$argv);\n");
chmod($binary,0700);
try {
 $n=0;
 foreach(['1','2','3'] as $v)foreach([false,true] as $numeric){
  $community='test ; $(false) `false` " quoted';
  $a=json_decode(cisco_health_snmp_get_raw('127.0.0.1',$community,'.1.3.6.1',$v,'test user','quoted pass',auth_proto:'SHA',priv_proto:'AES',numeric_value:$numeric),true,512,JSON_THROW_ON_ERROR);
  if($v!=='3' || function_exists('cacti_get_snmp_auth_args')){
   $i=array_search('-c',$a,true);if($i===false||$a[$i+1]!==$community)throw new RuntimeException('Credential argument split');
  }
  $i=array_search('-v',$a,true);if($a[$i+1]!==($v==='2'?'2c':$v))throw new RuntimeException('Version mapping');
  if($numeric){$i=array_search('-m',$a,true);if($i===false||$a[$i+1]!==''||!in_array('-OU',$a,true))throw new RuntimeException('Numeric wire options');}
  elseif(in_array('-m',$a,true))throw new RuntimeException('Text reads changed');
  if(end($a)!=='.1.3.6.1')throw new RuntimeException('OID argument');
  $n++;
 }
 if(cisco_health_snmp_get_raw('127.0.0.1','test','.1','9')!=='U')throw new RuntimeException('Invalid SNMP version');
 // Real child processes exercise stderr and nonzero exit handling in both APIs.
 $missing = "Error in packet\nReason: (noSuchName) There is no such variable name in this MIB.\nFailed object: .1.2.3\n";
 $cases = [
  ['', $missing, 2, '1', true, 'No Such Object'],
  ['', $missing, 2, '1', false, 'U'],
  ['', $missing, 2, '2', true, 'U'],
  ['', $missing, 2, '3', true, 'U'],
  ['', $missing, 1, '1', true, 'U'],
  ['', 'Timeout: No Response from router', 1, '1', true, 'U'],
  ['', 'Authentication failure', 1, '1', true, 'U'],
  ['', $missing . 'Authentication failure', 2, '1', true, 'U'],
  ['', '', 1, '1', true, 'U'],
  ['', 'Reason: (authorizationError)', 2, '1', true, 'U'],
  ['0', '', 0, '1', true, '0'],
  ['No Such Object available on this agent at this OID', '', 0, '2', true, 'No Such Object available on this agent at this OID'],
 ];
 foreach ($cases as [$out, $err, $code, $v, $numeric, $expected]) {
  file_put_contents($binary, '#!'.PHP_BINARY."\n<?php echo ".var_export($out,true).';fwrite(STDERR,'.var_export($err,true).');exit('.$code.');');
  $actual=cisco_health_snmp_get_raw('127.0.0.1','test','.1.2.3',$v,auth_proto:'SHA',priv_proto:'AES',numeric_value:$numeric);
  if($actual!==$expected)throw new RuntimeException('Binary diagnostic case '.json_encode([$v,$out,$err,$actual,$expected]));
  $n++;
 }
 // Verify unsupported HC/high columns really reach the readable low gauge.
 file_put_contents($binary, '#!'.PHP_BINARY."\n<?php ".'$oid=end($argv); if($oid===".1.3.6.1.4.1.9.9.221.1.1.1.1.6.1.1") {echo "1";exit;} if(str_ends_with($oid,".7.1.1") || $oid===".3.1") {echo "123";exit;} fwrite(STDERR,'.var_export($missing,true).');exit(2);');
 $get=fn($oid)=>cisco_health_snmp_get_raw('127.0.0.1','test',$oid,'1',auth_proto:'SHA',priv_proto:'AES',numeric_value:true);
 if(cisco_health_query('memory','get','used','1.1',$get,fn()=>[])!=='123')throw new RuntimeException('Enhanced memory fallback failed');
 if(cisco_health_qfp_memory('.', '1', 'used', $get)!==123)throw new RuntimeException('QFP fallback failed');
 $n+=2;
 foreach (['Timeout: No Response from router', 'Authentication failure'] as $error) {
  file_put_contents($binary, '#!'.PHP_BINARY."\n<?php ".'$oid=end($argv); if(str_ends_with($oid,".6.1.1")) {echo "1";exit;} if(str_ends_with($oid,".7.1.1") || $oid===".3.1") {echo "123";exit;} fwrite(STDERR,'.var_export($error,true).');exit(1);');
  if(cisco_health_query('memory','get','used','1.1',$get,fn()=>[])!=='U')throw new RuntimeException('Memory used a fallback on a failed request');
  if(cisco_health_qfp_memory('.', '1', 'used', $get)!==null)throw new RuntimeException('QFP used a fallback on a failed request');
  $n+=2;
 }
 // A stalled or excessively verbose executable must not be treated as unsupported.
 foreach (['fwrite(STDERR,'.var_export($missing,true).');sleep(5);', 'echo str_repeat("x", 1100000);'] as $body) {
  file_put_contents($binary, '#!'.PHP_BINARY."\n<?php ".$body);
  $command=($argv[1]??'')==='array'?[$binary]:'exec '.escapeshellarg($binary);
  if(cisco_health_binary_get($command,1,'1',true)!=='U')throw new RuntimeException('Execution limit failed');
  $n++;
 }
 echo 'PASS '.($argv[1]??'string').' API: '.($n+1).' compatibility cases'.PHP_EOL;
} finally {unlink($binary);unlink($root.'/scripts/ss_cisco_health.php');unlink($root.'/lib/snmp.php');rmdir($root.'/scripts');rmdir($root.'/lib');rmdir($root);}
