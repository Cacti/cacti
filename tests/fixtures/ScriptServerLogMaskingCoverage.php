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

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 2);

require_once $root . '/include/global_constants.php';

$GLOBALS['ss_cov_log']     = [];
$GLOBALS['ss_cov_reply']   = '42';
$GLOBALS['ss_cov_reindex'] = [];

function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['ss_cov_log'][] = $string;
}

function read_config_option($name, $force = false) {
	return ['spine_log_level' => 2][$name] ?? '';
}

function db_fetch_assoc($sql) {
	return [
		['snmp_community' => 'c0mmunity', 'snmp_password' => 'authpass', 'snmp_priv_passphrase' => ''],
	];
}

function db_fetch_row_prepared($sql, $params = []) {
	return ['hostname' => 'host1', 'ping_port' => 0, 'ping_method' => 0, 'ping_retries' => 1, 'ping_timeout' => 400, 'availability_method' => 0];
}

function db_fetch_assoc_prepared($sql, $params = []) {
	return $GLOBALS['ss_cov_reindex'];
}

function db_execute_prepared($sql, $params = [], $log = true, $db_conn = false) {
	return true;
}

function dsv_log(string $message, mixed $data = null, int $level = POLLER_VERBOSITY_LOW) : void {
}

function debug_level(int $host_id, int $level) : int {
	return $level;
}

function exec_poll(string $command, int $timeout = 5) : string {
	return $GLOBALS['ss_cov_reply'] . "\n";
}

function exec_poll_php(string $command, bool $using_proc_function, array $pipes, mixed $proc_fd) : string {
	return $GLOBALS['ss_cov_reply'] . "\n";
}

function exec_into_array(string $command) : array {
	return ['1', '2', '3'];
}

function update_host_status(...$arguments) {
}

function update_system_mibs($host_id) {
}

function open_snmp_session($host_id, &$item) {
	return 'session';
}

function cacti_snmp_session_get($session, $oid, $strip_alpha = false) {
	return '42';
}

function cacti_snmp_session_walk($session, $oid) {
	return [['oid' => $oid . '.1', 'value' => '1']];
}

function cacti_snmp_select_uptime(...$arguments) {
	return '42';
}

class Net_Ping {
	public $host = [];

	public $port = 0;

	public function ping($avail_method, $ping_type, $timeout, $retries) {
		return true;
	}
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
		throw new RuntimeException('Script Server log masking probe failed: ' . $case);
	}
}

function ss_cov_log() : string {
	$log                     = implode("\n", $GLOBALS['ss_cov_log']);
	$GLOBALS['ss_cov_log']   = [];

	return $log;
}

foreach (['cacti_sizeof', 'cacti_count', 'prepare_validate_result', 'is_hexadecimal', 'strip_alpha'] as $function) {
	cacti_fixture_source_function($root . '/lib/functions.php', $function, '');
}

foreach (['parseArgs', 'script_server_log_parse_failure', 'script_server_log_missing_function', 'script_server_call', 'script_server_response'] as $function) {
	cacti_fixture_source_function($root . '/script_server.php', $function, '');
}

foreach (['script_server_redact_params', 'poller_redact_snmp_secrets'] as $function) {
	cacti_fixture_source_function($root . '/lib/poller.php', $function, '');
}

foreach (['collect_device_data', 'ping_and_reindex_check'] as $function) {
	cacti_fixture_source_function($root . '/cmd.php', $function, '');
}

// script_server_call(): printed output is discarded and only its size logged
ob_start();
$result  = script_server_call('ss_cov_echo', 'c0mmunity', ['c0mmunity']);
$printed = ob_get_clean();
$log     = ss_cov_log();
ss_cov_check($printed === '' && $result === 'value:c0mmunity', 'printed output discarded');
ss_cov_check(str_contains($log, 'wrote 20 bytes of output, discarded') && !str_contains($log, 'c0mmunity'), 'stray output logged by size');

$result = script_server_call('ss_cov_quiet', '', []);
ss_cov_check($result === '7' && ss_cov_log() === '', 'quiet function logs nothing');

// script_server_response(): one line per reply
ss_cov_check(script_server_response(script_server_call('ss_cov_multiline', '', [])) === 'a:1 b:2 c:3', 'multipart reply on one line');

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
		$GLOBALS['ss_cov_reply'] = $reply;

		$item = [
			'local_data_id'        => 3,
			'host_id'              => 7,
			'action'               => $action,
			'snmp_community'       => 'c0mmunity',
			'snmp_password'        => 'authpass',
			'snmp_priv_passphrase' => '',
			'arg1'                 => '/x/ss_cov.php ss_cov' . $args,
		];
		$error_ds = [];

		collect_device_data($item, $error_ds, 25);

		$log = ss_cov_log();
		ss_cov_check(!str_contains($log, 'c0mmunity') && !str_contains($log, 'authpass') && substr_count($log, '********') === ($reply === '42' ? 2 : 4), "data source log masked for action $action reply $reply");
	}
}

$GLOBALS['ss_cov_reply'] = '42';

foreach ([POLLER_ACTION_SNMP, POLLER_ACTION_SCRIPT, POLLER_ACTION_SCRIPT_PHP, POLLER_ACTION_SNMP_COUNT, POLLER_ACTION_SCRIPT_COUNT, POLLER_ACTION_SCRIPT_PHP_COUNT] as $action) {
	$GLOBALS['ss_cov_reindex'][] = ['data_query_id' => $action, 'action' => $action, 'op' => '=', 'assert_value' => '42', 'arg1' => '/x/ss_q.php ss_q' . $args];
}

$item = ['host_id' => 7, 'hostname' => 'host1', 'snmp_community' => 'c0mmunity', 'snmp_password' => 'authpass', 'snmp_priv_passphrase' => ''];
ss_cov_check(ping_and_reindex_check($item, false, 25) === false, 'device reported up');

$lines = array_values(array_filter($GLOBALS['ss_cov_log'], function ($line) {
	return str_contains($line, 'ss_q.php');
}));
$log = implode("\n", $lines);
ss_cov_check(count($lines) === 6 && !str_contains($log, 'c0mmunity') && !str_contains($log, 'authpass') && substr_count($log, '********') === 12, 'recache logs masked');

print "PASS: Script Server replies stay on one line and logged arguments mask device credentials\n";
