<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Exercises the script server request handling, the poller writer limit and
 * credential masking, and the hardened poller scripts in a separate process,
 * so request coverage can be recorded for code that cannot be loaded into
 * the test runner (script_server.php runs its main loop on include).
 * Database, SNMP and ping calls are replaced at the namespace boundary.
 *
 * Usage: php ScriptServerHardening.php io|bytes
 */

namespace CactiScriptServerHardeningFixture;

use RuntimeException;

/* standard output carries the JSON result only */
ini_set('display_errors', 'stderr');

require dirname(__DIR__) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 2);
$mode = ($argv[1] ?? 'io') === 'bytes' ? 'bytes' : 'io';
$base = \sys_get_temp_dir() . '/cacti-ss-hardening-' . getmypid() . '-' . bin2hex(random_bytes(4));

mkdir($base, 0700);

require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';

$config = [
	'base_path'            => $base,
	'library_path'         => $root . '/lib',
	'is_web'               => false,
	'poller_id'            => 1,
	'cacti_server_os'      => 'unix',
	'php_snmp_support'     => false,
	'config_options_array' => ['storage_location' => '', 'path_cactilog' => $base . '/cacti.log'],
];

$environ   = 'cmd';
$poller_id = 1;
$logged    = [];
$failures  = [];
$checks    = 0;
$poll      = 0;

/* the state helpers come from the real script, loaded the way the script
 * server loads it; only one script per run so its own copies are defined */
$called_by_script_server = true;
require $root . '/scripts/ss_net_snmp_disk_' . $mode . '.php';

function check($label, $condition) {
	$GLOBALS['checks']++;

	if (!$condition) {
		$GLOBALS['failures'][] = $label;
	}
}

// Limit the controlled boundary to logging, database, SNMP and ping access.
function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['logged'][] = $string;
}

function db_fetch_assoc($sql, $log = true, $db_conn = false) {
	if (strpos($sql, 'SHOW GLOBAL STATUS') !== false) {
		return $GLOBALS['sql_status'];
	}

	if (strpos($sql, 'FROM host') !== false) {
		return [['snmp_community' => 'c0mmunity', 'snmp_password' => 'authpass', 'snmp_priv_passphrase' => '']];
	}

	if (strpos($sql, 'plugin_webseer_urls') !== false) {
		return [['id' => 1, 'name' => "line\r\nbreak"]];
	}

	return [];
}

function db_fetch_row_prepared($sql, $params = []) {
	$GLOBALS['db_row_reads']++;

	return $GLOBALS['host_row'];
}

function db_fetch_cell_prepared($sql, $params = []) {
	return ($params[0] ?? '') === 'known.example' ? 7 : false;
}

function db_table_exists($table) {
	return true;
}

function read_config_option($name) {
	return ['script_timeout' => 25, 'ping_timeout' => 400, 'snmp_retries' => 1, 'spine_log_level' => 2][$name] ?? '';
}

function exec_poll($command) {
	return $GLOBALS['exec_reply'] . "\n";
}

function debug_level($host_id, $level) {
	return POLLER_VERBOSITY_NONE;
}

function sys_get_temp_dir() {
	return $GLOBALS['base'];
}

function cacti_snmp_get(...$arguments) {
	return (string) (1000 + 100 * $GLOBALS['poll']);
}

function cacti_snmp_walk($hostname, $community, $oid, ...$arguments) {
	if ($oid === '.1.3.6.1.4.1.2021.13.15.1.1.2') {
		return [
			['oid' => '.1.3.6.1.4.1.2021.13.15.1.1.2.1', 'value' => 'sda'],
			['oid' => '.1.3.6.1.4.1.2021.13.15.1.1.2.2', 'value' => 'sda1'],
			['oid' => '.1.3.6.1.4.1.2021.13.15.1.1.2.3', 'value' => 'nvme0n1'],
		];
	}

	if ($oid === '.1.3.6.1.2.1.25.3.3.1.2') {
		return $GLOBALS['cpu_loads'];
	}

	/* disk counters: index 3 reports a value that is not an unsigned integer */
	return [
		['oid' => $oid . '.1', 'value' => (string) (5000 + 10 * $GLOBALS['poll'])],
		['oid' => $oid . '.3', 'value' => $GLOBALS['poll'] > 1 ? '12abc' : '40'],
	];
}

class Net_Ping {
	public $host        = [];
	public $retries     = 0;
	public $port        = 0;
	public $ping_status = 0;

	public function ping($avail_method, $ping_type, $timeout, $retries) {
		$GLOBALS['ping_calls']++;
		$this->ping_status = 1.5;

		return true;
	}
}

function ss_hardening_fixture_throws() {
	throw new RuntimeException('script failure');
}

function ss_hardening_fixture_array() {
	return [1, 2];
}

function ss_hardening_fixture_ok($a = 'x', $b = 'y') {
	return "$a:$b";
}

foreach (['parseArgs', 'script_server_read_request', 'script_server_drain_line', 'script_server_call', 'script_server_redact_params', 'sig_handler'] as $function) {
	cacti_fixture_source_function($root . '/script_server.php', $function, __NAMESPACE__);
}

foreach (['exec_poll_php', 'poller_redact_snmp_secrets'] as $function) {
	cacti_fixture_source_function($root . '/lib/poller.php', $function, __NAMESPACE__);
}

cacti_fixture_source_function($root . '/cmd.php', 'collect_device_data', __NAMESPACE__);
cacti_fixture_source_function($root . '/scripts/ss_fping.php', 'ss_fping', __NAMESPACE__);
cacti_fixture_source_function($root . '/scripts/ss_fping.php', 'ss_fping_sweeps', __NAMESPACE__);
cacti_fixture_source_function($root . '/scripts/ss_count_oids.php', 'ss_count_oids', __NAMESPACE__);
cacti_fixture_source_function($root . '/scripts/ss_sql.php', 'ss_sql', __NAMESPACE__);
cacti_fixture_source_function($root . '/scripts/ss_multicpu_avg.php', 'ss_multicpu_avg', __NAMESPACE__);
cacti_fixture_source_function($root . '/scripts/ss_webseer.php', 'ss_webseer', __NAMESPACE__);
cacti_fixture_source_function($root . '/scripts/ss_net_snmp_disk_' . $mode . '.php', 'ss_net_snmp_disk_' . $mode, __NAMESPACE__);

function memory_stream($contents) {
	$stream = fopen('php://memory', 'w+');
	fwrite($stream, $contents);
	rewind($stream);

	return $stream;
}

function spine_pipe($written) {
	$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

	/* what spine's php_cmd() writes: snprintf(command, 1024, "%s\r\n") */
	fwrite($pair[0], substr($written . "\r\n", 0, 1023));

	return $pair;
}

// Request lines: at the limit, over it, truncated by spine, and drained in pieces.
$limit   = str_repeat('a', SCRIPT_SERVER_MAX_REQUEST_LENGTH);
$stream  = memory_stream($limit . "\r\n" . str_repeat('b', 3000) . "\r\nnext\r\n" . str_repeat('c', 1022) . "\n");
check('request at the limit', script_server_read_request($stream, 0.1) === $limit . "\r\n");
check('over-length request discarded', script_server_read_request($stream, 0.1) === null);
check('request after the discarded one', script_server_read_request($stream, 0.1) === "next\r\n");
check('one byte over with a bare newline', script_server_read_request($stream, 0.1) === null);
check('end of input', script_server_read_request($stream, 0.1) === false);
check('unterminated tail at end of input', script_server_read_request(memory_stream(str_repeat('d', 2000)), 0.1) === null);

list($spine, $server) = spine_pipe($limit);
check('full length spine request', script_server_read_request($server, 0.1) === $limit . "\r\n");

list($spine, $server) = spine_pipe(str_repeat('e', 1500));
$start = microtime(true);
check('truncated spine request refused', script_server_read_request($server, 0.1) === null);
check('truncated spine request does not block', microtime(true) - $start < 1.0);
fwrite($spine, "after\r\n");
check('request after a truncated one', script_server_read_request($server, 0.1) === "after\r\n");

list($spine, $server) = spine_pipe(str_repeat('f', 1500));
fwrite($spine, str_repeat('g', 500));
fwrite($spine, "tail\r\nnext\r\n");
check('pieces of an over-length line drained', script_server_read_request($server, 0.1) === null);
check('next request after drained pieces', script_server_read_request($server, 0.1) === "next\r\n");
fclose($spine);
check('closed pipe reports end of input', script_server_read_request($server, 0.1) === false);

list($spine, $server) = spine_pipe(str_repeat('h', 1500));
fclose($spine);
check('truncated request before the writer exits', script_server_read_request($server, 0.1) === null);

// Argument parsing of cacti_escapeshellarg() output and its error paths.
$values = ['plain', "it's", 'with space', 'double"quote', '', "'"];
check('escaped values round trip', parseArgs(implode(' ', array_map('escapeshellarg', $values)), $parsed) && $parsed === $values);
check('unquoted arguments split on spaces', parseArgs('a b  c', $parsed) && $parsed === ['a', 'b', '', 'c']);
check('escaped quote outside a quoted section', parseArgs("\\'a 'b'", $parsed) && $parsed === ["'a", 'b']);
check('escaped backslash outside a quoted section', parseArgs("'x' \\\\y", $parsed) && $parsed === ['x', '\\y']);
check('backslash inside a quoted section', parseArgs("'a\\b' c", $parsed) && $parsed === ['a\\b', 'c']);
ob_start();
$results = [parseArgs("'open", $parsed), parseArgs("'a' \\ b", $parsed), parseArgs("'a' \\x", $parsed), parseArgs("'a' `b`", $parsed)];
$printed = ob_get_clean();
check('parse errors fail without output', $results === [false, false, false, false] && $printed === '');
ob_start();
$debug_ok = parseArgs("'open", $parsed, true);
$printed  = ob_get_clean();
check('parse errors are printed only in debug mode', $debug_ok === false && strpos($printed, 'ERROR:') !== false);

// Dispatch containment.
check('a throwing script answers U', script_server_call(__NAMESPACE__ . '\\ss_hardening_fixture_throws', 'secret-value', ['secret-value']) === 'U');
check('the failure log leaves out the parameters', strpos(implode("\n", $logged), 'secret-value') === false);
check('an array result answers U', script_server_call(__NAMESPACE__ . '\\ss_hardening_fixture_array', '', []) === 'U');
check('a call without parameters', script_server_call(__NAMESPACE__ . '\\ss_hardening_fixture_ok', '', []) === 'x:y');
check('a call with parameters', script_server_call(__NAMESPACE__ . '\\ss_hardening_fixture_ok', 'a b', ['a', 'b']) === 'a:b');

// Credential masking in the script server and cmd.php logs.
check('script server masks loaded credentials', script_server_redact_params(['c0mmunity', 'h', '3:161::user:authpass:SHA']) === ['********', 'h', '3:161::user:********:SHA']);
check('script server masks supplied credentials', script_server_redact_params(['x', 'y:z'], ['x' => true]) === ['********', 'y:z']);
$parameter_array = ['c0mmunity'];
$include_file    = '/opt/cacti/scripts/ss_x.php';
$function        = 'ss_x';
sig_handler(defined('SIGUSR1') ? SIGUSR1 : 10);
check('signal log has the argument count only', strpos(end($logged), '1 argument(s)') !== false && strpos(end($logged), 'c0mmunity') === false);

$secrets = ["it's'secret", 'say"secret"', 'a&b|c^d<e>(f)%g'];

foreach ($secrets as $secret) {
	foreach ([['unix', false], ['win32', false], ['win32', true]] as $form) {
		$config['cacti_server_os'] = $form[0];
		$escaped = ($form[1] ? \cacti_escapeshellarg_cmd($secret) : \cacti_escapeshellarg($secret));
		$config = array_replace($config, ['cacti_server_os' => 'unix']);
		$masked = poller_redact_snmp_secrets('/x/ss.php f ' . $escaped, ['snmp_community' => '', 'snmp_password' => $secret]);

		check("escaped credential masked ($form[0])", substr_count($masked, '********') === 1);
	}
}

check('contained credential masked whole', poller_redact_snmp_secrets("f 'pubpubpass' 'pub'", ['snmp_community' => 'pub', 'snmp_password' => 'pubpubpass']) === "f '********' '********'");
check('no credentials leaves the command alone', poller_redact_snmp_secrets("f 'x'", ['snmp_community' => '']) === "f 'x'");

// The poller refuses commands the script server would split.
foreach ([str_repeat('a', SCRIPT_SERVER_MAX_REQUEST_LENGTH + 1), "a\nb", "a\r"] as $command) {
	$to_server = fopen('php://memory', 'w+');
	check('poller refuses a command it cannot send whole', exec_poll_php($command, 1, [$to_server, memory_stream("42\n")], fopen('php://memory', 'r')) === 'U');
	rewind($to_server);
	check('nothing was sent', stream_get_contents($to_server) === '');
}

$to_server = fopen('php://memory', 'w+');
check('poller sends a command at the limit', exec_poll_php($limit, 1, [$to_server, memory_stream("42\n")], fopen('php://memory', 'r')) === "42\n");

// Script argument bounds.
$ping_calls = 0;
check('ss_fping clamps the sweep count', strpos(ss_fping('127.0.0.1', '500', 'ICMP'), 'loss:0.0000') !== false && $ping_calls === 20);
$ping_calls = 0;
check('ss_fping defaults an unknown ping type and a non-numeric count', strpos(ss_fping('127.0.0.1', 'six', 'BOGUS'), 'loss:0.0000') !== false && $ping_calls === 6);
check('ss_fping_sweeps lower bound', ss_fping_sweeps('-3') === 1);

$db_row_reads = 0;
$host_row     = [];
check('ss_count_oids refuses an option-like OID', ss_count_oids(1, '-Cc') === '0' && $db_row_reads === 0);
$host_row = ['hostname' => 'h', 'snmp_community' => 'c', 'snmp_version' => 2, 'snmp_username' => '', 'snmp_password' => '',
	'snmp_auth_protocol' => '', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '', 'snmp_context' => '',
	'snmp_port' => 161, 'snmp_timeout' => 500, 'max_oids' => 10, 'snmp_engine_id' => '', 'ping_retries' => 1];
check('ss_count_oids walks a valid OID', ss_count_oids(1, '.1.3.6.1.2.1.2.2.1.1') === 2);

// Status read over Cacti's own connection.
$sql_status = [];
foreach (['Uptime' => '5', 'Threads_connected' => '1', 'Questions' => '3', 'Slow_queries' => '0', 'Opened_tables' => '17', 'Open_tables' => '10'] as $name => $value) {
	$sql_status[] = ['Variable_name' => $name, 'Value' => $value];
}
check('ss_sql matches MariaDB mysqladmin status', ss_sql() === 'Uptime:5 Threads:1 Questions:3 SlowQueries:0 Opens:17 OpenTables:10 QPS:0.600');
$sql_status = [['Variable_name' => 'Uptime', 'Value' => '0'], ['Variable_name' => 'Questions', 'Value' => '9']];
check('ss_sql at zero uptime', ss_sql() === 'Uptime:0 Questions:9 QPS:0.000');
$sql_status = [['Variable_name' => 'Threads_connected', 'Value' => '1']];
check('ss_sql without uptime', ss_sql() === 'U');
$sql_status = [];
check('ss_sql without status', ss_sql() === 'U');

// Non-numeric processor loads are skipped.
$cpu_loads = [['value' => '10'], ['value' => 'n/a'], ['value' => '30']];
check('ss_multicpu_avg skips a non-numeric load', ss_multicpu_avg(1) === "load:20\n");
$cpu_loads = [['value' => 'n/a']];
check('ss_multicpu_avg with no numeric load', ss_multicpu_avg(1) === "load:0\n");

ob_start();
ss_webseer('query', 'webseerName');
check('ss_webseer strips line breaks from names', ob_get_clean() === '1!linebreak' . PHP_EOL);

// Disk counters kept in a private state directory between polls.
$disk     = __NAMESPACE__ . '\\ss_net_snmp_disk_' . $mode;
$zero     = ($mode === 'io' ? 'reads:0 writes:0' : 'bytesread:0 byteswritten:0');
$host_row = array_merge($host_row, ['id' => 7]);

check('disk script refuses an unknown device', $disk('unknown.example') === $zero);
check('disk script refuses a device id of zero', $disk('0.0') === $zero);

foreach (['cmd', 'realtime'] as $environ) {
	$poll = 1;
	check("first $environ poll has no previous state", $disk('known.example') === $zero);
	$poll = 2;
	check("second $environ poll reports a delta", $disk(7) !== $zero);
}

$state = $base . '/cacti/net-snmp-devio';
check('state directory is private', (fileperms($state) & 0777) === 0700 && (fileperms($base . '/cacti') & 0777) === 0700);
check('state file written without leftovers', is_file("$state/7_$mode") && glob("$state/state*") === []);

chmod($state, 0770);
clearstatcache();
check('the state directory is open to its group before the check', (fileperms($state) & 0777) === 0770);
$tightened = ss_net_snmp_state_dir($state);
clearstatcache();
check('an open state directory is tightened', $tightened === $state && (fileperms($state) & 0777) === 0700);

mkdir($base . '/elsewhere', 0700);
symlink($base . '/elsewhere', $base . '/linked');
check('a linked state directory is refused', ss_net_snmp_state_dir($base . '/linked/net-snmp-devio') === false);
symlink($base . '/elsewhere', $base . '/cacti/linked');
check('a linked state leaf is refused', ss_net_snmp_state_dir($base . '/cacti/linked') === false);
file_put_contents($base . '/plain', '');
check('a file in place of the directory is refused', ss_net_snmp_state_dir($base . '/plain/net-snmp-devio') === false);
symlink($base . '/plain', "$state/9_$mode");
check('a linked state file is not read', ss_net_snmp_state_read("$state/9_$mode") === false);
mkdir("$state/occupied/entry", 0700, true);
check('a state write that cannot replace its target fails cleanly', ss_net_snmp_state_write($state, "$state/occupied", '{}') === false && glob("$state/state*") === []);
check('a state write outside the directory is refused',ss_net_snmp_state_write($base . '/missing', $base . '/missing/x', '{}') === false);

$environ = 'cmd';
chmod($state, 0500);
$poll = 3;
check('an unwritable state directory still answers', is_string($disk(7)));
chmod($state, 0700);

check('counter step refuses a non-integer counter', ss_counter_step('U', '12abc', '1', '4294967296') === 'U');

// cmd.php logs script and script server commands with the device credentials masked.
$print_data_to_stdout = false;
$using_proc_function  = 1;
$cactiphp             = fopen('php://memory', 'r');

$item = ['local_data_id' => 3, 'host_id' => 7, 'snmp_community' => 'c0mm', 'snmp_password' => "zq'xw", 'snmp_priv_passphrase' => ''];

foreach ([POLLER_ACTION_SCRIPT, POLLER_ACTION_SCRIPT_PHP] as $action) {
	foreach (['U', 'not a number', '42'] as $reply) {
		$logged     = [];
		$exec_reply = $reply;
		$pipes      = [fopen('php://memory', 'w+'), memory_stream($reply . "\n")];
		$error_ds   = [];

		$item['action'] = $action;
		$item['arg1']   = '/opt/cacti/scripts/ss_x.php ss_x ' . \cacti_escapeshellarg('c0mm') . ' ' . \cacti_escapeshellarg("zq'xw");

		$value = collect_device_data($item, $error_ds);
		$log   = implode("\n", $logged);

		check("cmd.php logs action $action reply '$reply'", $log !== '' && strpos($log, 'c0mm') === false && strpos($log, 'zq') === false && strpos($log, 'xw') === false && strpos($log, '********') !== false);
		check("cmd.php result for action $action reply '$reply'", $reply === '42' ? $value === '42' : $value === 'U');
	}
}

// Leave nothing behind in the system temporary directory.
function remove_tree($path) {
	if (is_link($path) || is_file($path)) {
		unlink($path);
	} elseif (is_dir($path)) {
		chmod($path, 0700);

		foreach (scandir($path) as $entry) {
			if ($entry != '.' && $entry != '..') {
				remove_tree("$path/$entry");
			}
		}

		rmdir($path);
	}
}

remove_tree($base);

print json_encode(['mode' => $mode, 'checks' => $checks, 'failures' => $failures], JSON_THROW_ON_ERROR) . "\n";

exit($failures === [] ? 0 : 1);
