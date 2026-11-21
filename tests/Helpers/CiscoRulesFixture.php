<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
$root=sys_get_temp_dir().'/cisco-rules-'.bin2hex(random_bytes(6));
mkdir($root); foreach (['cli','include','lib'] as $dir) mkdir($root.'/'.$dir);
foreach (['setup_cisco_health_rules.php','cisco_health_rules.json'] as $name) copy(dirname(__DIR__,2).'/cli/'.$name,$root.'/cli/'.$name);
copy(__DIR__.'/CiscoRulesBootstrap.php',$root.'/include/cli_check.php');
file_put_contents($root.'/lib/api_automation.php','<?php');
register_shutdown_function(function () use ($root) {
 foreach (['cli','include','lib'] as $dir) { foreach (glob($root.'/'.$dir.'/*') as $p) unlink($p); rmdir($root.'/'.$dir); }
 rmdir($root);
});
putenv('CACTI_HEALTH_RULE_TEST='.($argv[1]??'local'));
$plan=($argv[2]??'')==='plan';
$argv=['setup_cisco_health_rules.php'];
if (!$plan) $argv[]='--apply';
require $root.'/cli/setup_cisco_health_rules.php';
