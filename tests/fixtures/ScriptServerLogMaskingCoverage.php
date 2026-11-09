<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Request-coverage probe for the Script Server response and credential masking
// changes in script_server.php, lib/poller.php and cmd.php. The unit tests load
// the same functions into a fixture namespace from temporary files, which the
// unit coverage report cannot attribute to the real sources. This probe loads
// the unchanged functions at their original line numbers, runs them against
// minimal stand-ins, checks each result and lets RequestCoverage remap the hits
// to the real files. The Script Server main loop and cmd.php's top level need a
// full poller environment and are covered by the unit tests' source contracts.

namespace CactiCmdDeviceFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__) . '/Helpers/CmdDeviceStubs.php';

$root = dirname(__DIR__, 2);

require_once $root . '/include/global_constants.php';

if (!defined('CACTI_SERVER_OS')) {
	define('CACTI_SERVER_OS', 'unix');
}

$GLOBALS['ss_cov_log'] = [];

function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['ss_cov_log'][] = $string;
}

function db_fetch_assoc($sql) {
	return [
		['snmp_community' => 'c0mmunity', 'snmp_password' => 'authpass', 'snmp_priv_passphrase' => ''],
	];
}

function dsv_log(string $message, mixed $data = null, int $level = POLLER_VERBOSITY_LOW) : void {
}

function ss_cov_echo($a) {
	print "community $a\n";

	return "value:$a";
}

function ss_cov_multiline() {
	return "a:1\r\nb:2\nc:3";
}

function ss_cov_quiet() {
	return '7';
}

function ss_cov_check(bool $passed, string $case) : void {
	if (!$passed) {
		throw new \RuntimeException('Script Server log masking probe failed: ' . $case);
	}
}

function ss_cov_log() : string {
	$log                     = implode("\n", $GLOBALS['ss_cov_log']);
	$GLOBALS['ss_cov_log']   = [];

	return $log;
}

foreach (['cacti_sizeof', 'cacti_count', 'prepare_validate_result', 'is_hexadecimal', 'strip_alpha'] as $function) {
	cacti_fixture_source_function($root . '/lib/functions.php', $function, __NAMESPACE__);
}

foreach (['parseArgs', 'script_server_log_parse_failure', 'script_server_log_missing_function', 'script_server_call', 'script_server_response'] as $function) {
	cacti_fixture_source_function($root . '/script_server.php', $function, __NAMESPACE__);
}

foreach (['script_server_redact_params', 'poller_redact_snmp_secrets', 'exec_poll', 'exec_with_timeout'] as $function) {
	cacti_fixture_source_function($root . '/lib/poller.php', $function, __NAMESPACE__);
}

foreach (['collect_device_data', 'ping_and_reindex_check'] as $function) {
	cacti_fixture_source_function($root . '/cmd.php', $function, __NAMESPACE__);
}

// script_server_call(): printed output is discarded and only its size logged
ob_start();
$result  = script_server_call(__NAMESPACE__ . '\\ss_cov_echo', 'c0mmunity', ['c0mmunity']);
$printed = ob_get_clean();
$log     = ss_cov_log();
ss_cov_check($printed === '' && $result === 'value:c0mmunity', 'printed output discarded');
ss_cov_check(str_contains($log, 'wrote 20 bytes of output, discarded') && !str_contains($log, 'c0mmunity'), 'stray output logged by size');

$result = script_server_call(__NAMESPACE__ . '\\ss_cov_quiet', '', []);
ss_cov_check($result === '7' && ss_cov_log() === '', 'quiet function logs nothing');

// script_server_response(): one line per reply
ss_cov_check(script_server_response(script_server_call(__NAMESPACE__ . '\\ss_cov_multiline', '', [])) === 'a:1 b:2 c:3', 'multipart reply on one line');

// parseArgs(): a parse error is printed only when debugging
$parsed = [];
ob_start();
$ok      = parseArgs("'host1 'c0mmunity'", $parsed);
$printed = ob_get_clean();
ss_cov_check($ok === false && $printed === '', 'parse error silent without debug');

ob_start();
parseArgs("'host1 'c0mmunity'", $parsed, true);
$printed = ob_get_clean();
ss_cov_check(str_contains($printed, 'ERROR: '), 'parse error printed when debugging');

// the parse failure and missing function warnings never log a credential
script_server_log_parse_failure('ss_cov_quiet', "'host1 'c0mmunity'");
$log = ss_cov_log();
ss_cov_check(str_contains($log, '18 byte argument list') && !str_contains($log, 'c0mmunity'), 'parse failure logged by size');

script_server_log_missing_function('/x/ss_cov.php', 'ss_cov_missing', ['host1', 'c0mmunity', '3:161:500:1:10::user:authpass:SHA']);
$log = ss_cov_log();
ss_cov_check(str_contains($log, "PARMS: 'host1', '********', '3:161:500:1:10::user:********:SHA'"), 'missing function arguments masked');

// poller_redact_snmp_secrets(): every escaped form, longest credential first
$item = ['snmp_community' => 'pub', 'snmp_password' => "pubp'ass\"x", 'snmp_priv_passphrase' => ''];
ss_cov_check(poller_redact_snmp_secrets("f 'pub' 'pubp'\\''ass\"x' \"pubp'ass\\\"x\" pubp'assx", $item) === "f '********' '********' \"********\" ********", 'escaped forms masked');
ss_cov_check(poller_redact_snmp_secrets('no secrets here', ['snmp_community' => '']) === 'no secrets here', 'no credentials leaves the string');

// exec_poll(): a failing script is logged with the device credentials masked,
// and its first line of output is the result
$failing = PHP_BINARY . ' -r ' . escapeshellarg('fwrite(STDOUT, "out " . $argv[1] . "\n"); fwrite(STDERR, "err " . $argv[1] . "\n"); exit(3);') . ' ' . escapeshellarg('c0mmunity');
ss_cov_check(exec_poll($failing, 5, ['snmp_community' => 'c0mmunity']) === 'out c0mmunity', 'failing script first line');
$log = ss_cov_log();
ss_cov_check(str_contains($log, 'ErrorCode:3') && str_contains($log, 'exited with code 3') && !str_contains($log, 'c0mmunity'), 'failing script log masked');
ss_cov_check(exec_poll(PHP_BINARY . ' -r ' . escapeshellarg('print "7\n";'), 5) === '7', 'newline terminated output');

// cmd.php data source and recache logs mask the device credentials
$GLOBALS['print_data_to_stdout'] = false;
$GLOBALS['using_proc_function']  = true;
$GLOBALS['pipes']                = [];
$GLOBALS['cactiphp']             = null;
$GLOBALS['poller_id']            = 1;
$GLOBALS['set_spike_kill']       = false;

$args = " 'c0mmunity' 'authpass'";

foreach ([POLLER_ACTION_SCRIPT, POLLER_ACTION_SCRIPT_PHP] as $action) {
	foreach (['U', 'not a number', '42'] as $reply) {
		$GLOBALS['cmd_device_reply'] = $reply;

		// the script runs for real; the Script Server reply is canned
		$script = PHP_BINARY . ' -r ' . escapeshellarg('print "' . $reply . '\n";') . ' /x/ss_cov.php ss_cov';

		$item = [
			'local_data_id'        => 3,
			'host_id'              => 7,
			'action'               => $action,
			'snmp_community'       => 'c0mmunity',
			'snmp_password'        => 'authpass',
			'snmp_priv_passphrase' => '',
			'arg1'                 => $script . $args,
		];
		$error_ds = [];

		collect_device_data($item, $error_ds, 25);

		$log = ss_cov_log();
		ss_cov_check(!str_contains($log, 'c0mmunity') && !str_contains($log, 'authpass') && substr_count($log, '********') === ($reply === '42' ? 2 : 4), "data source log masked for action $action reply $reply");
	}
}

$GLOBALS['cmd_device_reply'] = '42';
$script                      = PHP_BINARY . ' -r ' . escapeshellarg('print "42\n";') . ' /x/ss_q.php ss_q';

foreach ([POLLER_ACTION_SNMP, POLLER_ACTION_SCRIPT, POLLER_ACTION_SCRIPT_PHP, POLLER_ACTION_SNMP_COUNT, POLLER_ACTION_SCRIPT_COUNT, POLLER_ACTION_SCRIPT_PHP_COUNT] as $action) {
	$GLOBALS['cmd_device_reindex'][] = ['data_query_id' => $action, 'action' => $action, 'op' => '=', 'assert_value' => '42', 'arg1' => $script . $args];
}

$item = ['host_id' => 7, 'hostname' => 'host1', 'snmp_community' => 'c0mmunity', 'snmp_password' => 'authpass', 'snmp_priv_passphrase' => ''];
ss_cov_check(ping_and_reindex_check($item, false, 25) === false, 'device reported up');

$lines = array_values(array_filter($GLOBALS['ss_cov_log'], function ($line) {
	return str_contains($line, 'ss_q.php');
}));
$log = implode("\n", $lines);
ss_cov_check(count($lines) === 6 && !str_contains($log, 'c0mmunity') && !str_contains($log, 'authpass') && substr_count($log, '********') === 12, 'recache logs masked');

print "PASS: Script Server replies stay on one line and logged arguments mask device credentials\n";
