<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-rjvj-r52f-8v5q. cacti_escapeshellarg_cmd() strips cmd.exe operators
 * (" & | ^ < > ( )) on Windows before quoting, because cmd.exe ignores \" and
 * toggles quote-state on ". Verifies the helper and that the remaining Windows
 * shell sinks route their device/request values through it.
 */

require_once dirname(__DIR__, 4) . '/lib/functions.php';
if (!defined('CACTI_ESCAPE_CHARACTER')) { define('CACTI_ESCAPE_CHARACTER', '"'); }

$functions = file_get_contents(dirname(__DIR__, 4) . '/lib/functions.php');
$ping      = file_get_contents(dirname(__DIR__, 4) . '/lib/ping.php');
$snmpagent = file_get_contents(dirname(__DIR__, 4) . '/lib/snmpagent.php');

test('on win32 the helper strips cmd.exe operators before quoting', function () {
	global $config;
	$config['cacti_server_os'] = 'win32';
	$out = cacti_escapeshellarg_cmd('eth0" & calc.exe & ');
	// the outer wrapping quotes are the (safe) escaping; the value between them
	// must carry no cmd.exe operator that a rogue value tried to inject.
	$inner = trim($out, '"');
	foreach (array('"', '&', '|', '^', '<', '>', '(', ')') as $meta) {
		expect($inner)->not->toContain($meta);
	}
	expect($inner)->toContain('calc.exe');
});

test('on unix the helper is exactly cacti_escapeshellarg', function () {
	global $config;
	$config['cacti_server_os'] = 'unix';
	$value = "eth0' && id";
	expect(cacti_escapeshellarg_cmd($value))->toBe(cacti_escapeshellarg($value));
});

test('the windows ping hostname goes through the cmd helper', function () use ($ping) {
	expect($ping)->toContain('cacti_escapeshellarg_cmd($this->host[' . "'hostname'" . '], true, true)');
});

test('data-input argument builders use the cmd helper', function () use ($functions) {
	expect($functions)->toContain("cacti_escapeshellarg_cmd(\$item['value'])");
	expect($functions)->toContain("cacti_escapeshellarg_cmd(\$host[\$item['data_name']])");
});

test('the snmp trap notification is sent via the argv-based background process', function () use ($snmpagent) {
	// no shell string is built for the notification anymore; snmptrap runs
	// via proc_open bypass_shell, so no per-argument cmd helper is needed.
	expect($snmpagent)->toContain("exec_background_process(\$path_snmptrap, \$args);");
	// no bare escaper left in the trap community/credential args
	$start = strpos($snmpagent, "if (\$notification_manager['snmp_version'] == 1 )");
	$block = substr($snmpagent, $start, 1400);
	expect($block)->not->toContain('cacti_escapeshellarg($notification_manager');
	expect($block)->not->toContain('cacti_escapeshellarg_cmd($notification_manager');
});

test('the strip_env flag removes %VAR% for hostname-class values only', function () {
	// default: percent preserved (community/credentials may contain it)
	expect(cacti_escapeshellarg_cmd('p%ss'))->toBe(cacti_escapeshellarg('p%ss'));
	$src   = file_get_contents(dirname(__DIR__, 4) . '/lib/functions.php');
	$start = strpos($src, 'function cacti_escapeshellarg_cmd(');
	$body  = substr($src, $start, 700);
	expect($body)->toContain('$strip_env');
	expect($body)->toContain("str_replace('%', ''");
});

test('the snmp hostname strip and the ping hostname opt into the percent strip', function () {
	$snmp = file_get_contents(dirname(__DIR__, 4) . '/lib/snmp.php');
	$ping = file_get_contents(dirname(__DIR__, 4) . '/lib/ping.php');
	// snmp hostname inline strip now includes %
	expect($snmp)->toContain("'(', ')', '%'), ''");
	expect($ping)->toContain('cacti_escapeshellarg_cmd($this->host[' . "'hostname'" . '], true, true)');
});
