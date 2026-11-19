<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\GraphDebugOutput;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 4);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';
define('RRDTOOL_MAX_COMMAND_BYTES', 16 * 1024 * 1024);

foreach (['rrdtool_prepare_stdin_command', 'rrdtool_format_result', 'rrdtool_resolve_graph_text'] as $function) {
	\cacti_fixture_source_function($root . '/lib/rrd.php', $function, __NAMESPACE__);
}

foreach (['strip_credential_variables', 'null_out_substitutions', 'expand_title', 'substitute_host_data'] as $function) {
	\cacti_fixture_source_function($root . '/lib/variables.php', $function, __NAMESPACE__);
}

foreach (['htmle', 'html_escape'] as $function) {
	\cacti_fixture_source_function($root . '/lib/html.php', $function, __NAMESPACE__);
}
\cacti_fixture_source_function($root . '/lib/boost.php', 'boost_error_handler', '');

define('CACTI_PATH_LIBRARY', $root . '/lib');

define('POLLER_ID', 1);
define('VALID_HOST_FIELDS', '(hostname|description)');

function db_fetch_row_prepared(string $sql, array $params) : array {
	$columns = [
		'id', 'description', 'site_name', 'notes', 'location', 'poller_id', 'polling_time',
		'avg_time', 'cur_time', 'availability', 'snmp_community', 'snmp_version', 'snmp_username',
		'snmp_password', 'snmp_auth_protocol', 'snmp_priv_passphrase', 'snmp_priv_protocol',
		'snmp_context', 'snmp_engine_id', 'snmp_port', 'snmp_timeout', 'snmp_sysDescr',
		'snmp_sysObjectID', 'snmp_sysContact', 'snmp_sysLocation', 'snmp_sysName',
		'snmp_sysUpTimeInstance', 'ping_retries', 'max_oids', 'external_id'
	];

	return ['hostname' => 'router.example'] + array_fill_keys($columns, '');
}

function boost_poller_id_check() : bool {
	return true;
}

function cacti_sizeof(array $values) : int {
	return count($values);
}

function get_uptime(array $host) : string {
	return '0';
}

function api_plugin_hook_function(string $hook, array $data) : array {
	return $data;
}

function __(string $text) : string {
	return $text;
}

function grv(string $name) : int {
	return 17;
}

function isrv(string $name) : bool {
	return $name === 'id';
}

function rrdtool_function_graph(int $id, int $rra, array $options, mixed $pipe, array &$meta, int $user) : string {
	if ($id !== 17 || $user !== 1 || $options['output_flag'] !== RRDTOOL_OUTPUT_RETURN_STDERR) {
		throw new \RuntimeException('Unexpected graph or debug output mode');
	}

	return isset($options['print_source']) ? 'graph A & B' : 'sample A & B';
}

function check(mixed $actual, mixed $expected) : void {
	if ($actual !== $expected) {
		throw new \RuntimeException('Unexpected graph debug result: ' . var_export($actual, true));
	}
}

// Run each view's conditional debug panel, including its markup and both RRDtool calls.
function check_debug_view(string $path) : void {
	$source = file_get_contents($path);

	if (!preg_match('/^\t\tif \([^\n]*graph_debug_mode[^\n]*isrv[^\n]*\) \{.*?^\t\t\}/ms', $source, $matches, PREG_OFFSET_CAPTURE)) {
		throw new \RuntimeException('Missing graph debug panel in ' . $path);
	}

	[$panel, $offset] = $matches[0];
	$line_padding     = str_repeat("\n", substr_count(substr($source, 0, $offset), "\n"));
	$temporary        = tempnam(sys_get_temp_dir(), 'cacti-graph-debug-');
	file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . $line_padding . $panel);
	$GLOBALS['cacti_fixture_source_map'][$temporary] = $path;

	$_SESSION    = ['graph_debug_mode' => true, SESS_USER_ID => 1];
	$graph_start = 0;
	$graph_end   = 300;

	ob_start();

	try {
		require $temporary;
	} finally {
		$output = ob_get_clean();
		unlink($temporary);
	}

	check(str_contains($output, 'graph A &amp; B'), true);
	check(str_contains($output, 'sample A &amp; B'), true);
}

foreach (['graphs.php', 'aggregate_graphs.php'] as $view) {
	check_debug_view($root . '/' . $view);
}

\cacti_fixture_source_function($root . '/lib/boost.php', 'boost_graph_cache_check', __NAMESPACE__);
$debug_options = ['output_flag' => RRDTOOL_OUTPUT_RETURN_STDERR];
check(boost_graph_cache_check(17, 1, null, $debug_options), false);

check(expand_title(1, 0, '', '|host_hostname| traffic'), 'router.example traffic');
check(rrdtool_resolve_graph_text('router |host_snmp_username|traffic', [], [], static fn ($text) => $text), 'router traffic');
check(rrdtool_prepare_stdin_command("graph 'Router A'"), 'graph "Router A"');

foreach ([
	['graph', 'no samples', 'no samples'],
	["\x89PNG", '', 'OK'],
	['<?xml version="1.0"?>', '', 'SVG/XML Output OK'],
	['123x456', '', '123x456']
] as [$output, $error, $expected]) {
	check(rrdtool_format_result(['success' => true, 'output' => $output, 'error' => $error], RRDTOOL_OUTPUT_RETURN_STDERR), $expected);
}

print "PASS: graph debug views, titles and RRDtool output\n";
