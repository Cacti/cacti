<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-rjvj-r52f-8v5q (forward-port to develop). cacti_escapeshellarg_cmd()
 * strips cmd.exe operators (" & | ^ < > ( )) on Windows before quoting, and the
 * Windows shell sinks route their device/request values through it or the
 * equivalent snmp_escape_string strip.
 */

if (!defined('CACTI_SERVER_OS')) {
	define('CACTI_SERVER_OS', 'unix');
}

if (!defined('SNMP_ESCAPE_CHARACTER')) {
	define('SNMP_ESCAPE_CHARACTER', '"');
}
require_once dirname(__DIR__, 4) . '/lib/functions.php';

$functions = file_get_contents(dirname(__DIR__, 4) . '/lib/functions.php');
$snmp      = file_get_contents(dirname(__DIR__, 4) . '/lib/snmp.php');
$dataquery = file_get_contents(dirname(__DIR__, 4) . '/lib/data_query.php');
$ping      = file_get_contents(dirname(__DIR__, 4) . '/lib/ping.php');
$snmpagent = file_get_contents(dirname(__DIR__, 4) . '/lib/snmpagent.php');

test('cacti_escapeshellarg_cmd exists and strips on win32 by contract', function () use ($functions) {
	expect(function_exists('cacti_escapeshellarg_cmd'))->toBeTrue();
	$start = strpos($functions, 'function cacti_escapeshellarg_cmd(');
	$body  = substr($functions, $start, 500);
	expect($body)->toContain("CACTI_SERVER_OS == 'win32'");
	expect($body)->toContain("'^', '<', '>', '('");
});

test('on unix (test default) the helper is a pass-through to cacti_escapeshellarg', function () {
	$v = "eth0' && id";
	expect(cacti_escapeshellarg_cmd($v))->toBe(cacti_escapeshellarg($v));
});

test('snmp_escape_string strips cmd.exe operators on win32', function () use ($snmp) {
	$start = strpos($snmp, 'function snmp_escape_string(');
	$body  = substr($snmp, $start, 700);
	expect($body)->toContain("'^', '<', '>', '('");
});

test('the snmp binary hostname uses the cmd helper', function () use ($snmp) {
	expect($snmp)->toContain('cacti_escapeshellarg_cmd($hostname, true, true)');
});

test('get_script_query_path strips device values on win32', function () use ($dataquery) {
	$start = strpos($dataquery, 'function get_script_query_path(');
	$body  = substr($dataquery, $start, 1200);
	expect($body)->toContain("CACTI_SERVER_OS == 'win32'");
	expect($body)->toContain("'^', '<', '>', '('");
});

test('ping hostname and snmp trap args use the cmd helper', function () use ($ping, $snmpagent) {
	expect($ping)->toContain('cacti_escapeshellarg_cmd($this->host[' . "'hostname'" . '], true, true)');
	expect($snmpagent)->toContain('cacti_escapeshellarg_cmd($arg)');
});

test('the strip_env flag removes %VAR% for hostname-class values only', function () {
	// default: percent is preserved (credentials may contain it)
	expect(cacti_escapeshellarg_cmd('p%ss'))->toBe(cacti_escapeshellarg('p%ss'));
	// opt-in: a hostname never contains a percent, so it is stripped on win32.
	// on unix (test default) the flag is a no-op, so assert the source wiring.
	$src   = file_get_contents(dirname(__DIR__, 4) . '/lib/functions.php');
	$start = strpos($src, 'function cacti_escapeshellarg_cmd(');
	$body  = substr($src, $start, 700);
	expect($body)->toContain('$strip_env');
	expect($body)->toContain("str_replace('%', ''");
});

test('the hostname sinks opt into the percent strip', function () {
	$snmp = file_get_contents(dirname(__DIR__, 4) . '/lib/snmp.php');
	$ping = file_get_contents(dirname(__DIR__, 4) . '/lib/ping.php');
	expect($snmp)->toContain('cacti_escapeshellarg_cmd($hostname, true, true)');
	expect($ping)->toContain('cacti_escapeshellarg_cmd($this->host[' . "'hostname'" . '], true, true)');
});
