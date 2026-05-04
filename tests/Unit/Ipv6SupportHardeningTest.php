<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$snmpSource = file_get_contents(__DIR__ . '/../../lib/snmp.php');
$pingSource = file_get_contents(__DIR__ . '/../../lib/ping.php');
$funcSource = file_get_contents(__DIR__ . '/../../lib/functions.php');

test('cacti_snmp_session brackets IPv6 before port append', function () use ($snmpSource) {
	$start = strpos($snmpSource, 'function cacti_snmp_session(');
	$body = substr($snmpSource, $start, 1500);
	expect($body)->toContain("snmp_hostname = '[' . \$snmp_hostname . ']'");
});

test('snmp_format_target function exists', function () use ($snmpSource) {
	expect($snmpSource)->toContain('function snmp_format_target($hostname, $port)');
});

test('snmp_format_target forces udp6 for IPv6', function () use ($snmpSource) {
	$start = strpos($snmpSource, 'function snmp_format_target(');
	$body = substr($snmpSource, $start, 500);
	expect($body)->toContain("'udp6:[' . \$clean . ']:'");
});

test('binary SNMP commands use snmp_format_target', function () use ($snmpSource) {
	$count = substr_count($snmpSource, 'snmp_format_target($hostname, $port)');
	expect($count)->toBeGreaterThanOrEqual(5);
});

test('ping.php is_ipaddress strips zone index', function () use ($pingSource) {
	$start = strpos($pingSource, 'function is_ipaddress(');
	$body = substr($pingSource, $start, 500);
	expect($body)->toContain("explode('%', \$clean_ip, 2)");
});

test('functions.php is_ipaddress strips zone index', function () use ($funcSource) {
	$start = strpos($funcSource, 'function is_ipaddress(');
	$body = substr($funcSource, $start, 500);
	expect($body)->toContain("explode('%', \$clean_ip, 2)");
});
