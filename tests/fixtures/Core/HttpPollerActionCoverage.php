<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace CactiHttpPollerActionFixture;

use RuntimeException;

/*
 * Request-coverage probe for the HTTP poller action.
 *
 * The action is wired into cmd.php, remote_agent.php, the data source test,
 * the poller cache helper, the data template editor and the 1.3.0 upgrade.
 * Those entry points run as CLI or web scripts that Pest cannot include, so
 * each function is loaded in isolation with its database and logging
 * boundaries stubbed, and the top-level poller loops of cmd.php and
 * cmd_realtime.php are compiled from their own lines.  The HTTP client is
 * real: cacti_http_multi() against the loopback test server, or a refused
 * loopback port where a failure is the point.
 */

$root = dirname(__DIR__, 3);

require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/vendor/autoload.php';
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';
require $root . '/lib/CactiHttpPoller.php';
require $root . '/tests/Helpers/HttpTestServer.php';

define('CACTI_SERVER_OS', 'unix');
define('CACTI_PATH_BASE', $root);
define('CACTI_PATH_LIBRARY', $root . '/lib');

// classes do not fall back to the global namespace the way functions do
class_alias(\CactiHttpPoller::class, __NAMESPACE__ . '\CactiHttpPoller');
class_alias(\CactiHttpPollItem::class, __NAMESPACE__ . '\CactiHttpPollItem');

const HTTP_HASH = '5d4a0c2b7f8e41a69b3c1e07d2f6a8b4';
const REFUSED   = 'https://127.0.0.1:1/stats';

$GLOBALS['fixture_rows']     = [];
$GLOBALS['fixture_executes'] = [];
$GLOBALS['fixture_request']  = [];

function check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function http_row(int $local_data_id, string $url, string $pointer, string $options = '') : array {
	return [
		'host_id'       => 2,
		'local_data_id' => $local_data_id,
		'rrd_name'      => 'value',
		'action'        => POLLER_ACTION_HTTP,
		'arg1'          => $url,
		'arg2'          => $pointer,
		'arg3'          => $options,
	];
}

function cacti_log($message, $print = false, $type = '', $level = 0) {
}
function dsv_log($message, $data = null, $level = 0) {
}
function debug_level($host_id, $level) {
	return $level;
}
function read_config_option($name, $force = false) {
	return $name === 'spine_log_level' ? 2 : '4';
}
function cacti_sizeof($value) {
	return is_countable($value) ? count($value) : 0;
}
function array_rekey($array, $key, $key_value) {
	return array_column($array, $key_value, $key);
}
function substitute_host_data($string, $l_escape, $r_escape, $host_id) {
	return str_replace($l_escape . 'host_hostname' . $r_escape, '127.0.0.1', $string);
}
function input_validate_input_number($value, $name = '') {
	check(is_numeric($value), "$name is not numeric");
}
function gnrv($name, $default = '') {
	return $GLOBALS['fixture_request'][$name] ?? $default;
}
function gfrv($name, $filter = FILTER_VALIDATE_INT, $options = []) {
	return $GLOBALS['fixture_request'][$name] ?? 0;
}
function __($text, ...$args) {
	return $args === [] ? $text : vsprintf($text, $args);
}
function htmle($string) {
	return htmlspecialchars((string) $string);
}
function ierv($name) {
	return !isset($GLOBALS['fixture_request'][$name]);
}
function grv($name, $default = '') {
	return $GLOBALS['fixture_request'][$name] ?? $default;
}
function html_start_box($title, $width, $div, $cell_padding, $align, $add_text = '', $add_label = '') {
	$GLOBALS['fixture_boxes'][] = $add_text;
}
function data_template_is_snmp($data_input_id) {
	return false;
}
function data_template_is_stream($data_input_id) {
	return false;
}
function data_input_whitelist_check($data_input_id) {
	return true;
}
function get_data_source_item_name($data_template_rrd_id) {
	return 'value';
}
function api_poller_cache_item_add($host_id, $host_field_override, $local_data_id, $rrd_step, $poller_action_id, $data_source_item_name, $num_rrd_items, $arg1 = '', $arg2 = '', $arg3 = '') {
	return compact('host_id', 'local_data_id', 'poller_action_id', 'data_source_item_name', 'arg1', 'arg2', 'arg3');
}
function exec_poll_php($command, $using_proc_function, $pipes, $proc_fd) {
	return "42\n";
}
// the remote agent starts the script server itself; refusing it takes the fallback path
function proc_open($command, $descriptors, &$pipes) {
	return false;
}
function db_install_execute($sql, $params = [], $log = true) {
	$GLOBALS['fixture_executes'][] = [$sql, $params];

	return true;
}
function db_fetch_row_prepared($sql, $params = [], $log = true) {
	if (str_contains($sql, 'FROM data_template_data AS dtd')) {
		return ['id'        => 13, 'type_id' => DATA_INPUT_TYPE_HTTP, 'data_template_data_id' => 50,
			'data_template_id' => 7, 'active' => 'on', 'rrd_step' => 300, 'name' => 'Get HTTP JSON Data'];
	}

	return ['id' => 2, 'hostname' => '127.0.0.1'];
}
function db_fetch_cell_prepared($sql, $params = [], $col_name = '', $log = true) {
	if (str_contains($sql, 'FROM data_input WHERE hash = ?')) {
		return 13;
	}

	if (str_contains($sql, 'hash = "' . HTTP_HASH . '"')) {
		return $params[0] == 13 ? 13 : '';
	}

	if (str_contains($sql, 'local_data_id = 0')) {
		return $GLOBALS['fixture_template_dtd'];
	}

	return 0;
}
function db_fetch_assoc_prepared($sql, $params = [], $log = true) {
	if (str_contains($sql, 'dif.type_code IN')) {
		return $GLOBALS['fixture_fields'][$params[0]] ?? [];
	}

	if (str_contains($sql, 'AND action = ?')) {
		$ids = array_map('intval', array_slice($params, 2));

		return array_values(array_filter($GLOBALS['fixture_rows'], fn ($row) => $row['action'] == $params[1] && in_array($row['local_data_id'], $ids, true)));
	}

	return array_values(array_filter($GLOBALS['fixture_rows'], fn ($row) => $row['local_data_id'] == $params[1]));
}

cacti_fixture_source_function($root . '/cmd.php', 'collect_device_data', __NAMESPACE__);
cacti_fixture_source_function($root . '/cmd.php', 'http_prefetch_device', __NAMESPACE__);
cacti_fixture_source_function($root . '/remote_agent.php', 'poll_for_data', __NAMESPACE__);
cacti_fixture_source_function($root . '/lib/utility.php', 'get_http_input_fields', __NAMESPACE__);
cacti_fixture_source_function($root . '/lib/functions.php', 'test_data_source', __NAMESPACE__);
cacti_fixture_source_function($root . '/data_templates.php', 'data_template_is_http', __NAMESPACE__);
cacti_fixture_source_function($root . '/install/upgrades/1_3_0.php', 'upgrade_http_data_input', __NAMESPACE__);
cacti_fixture_source_function($root . '/lib/utility.php', 'update_poller_cache', __NAMESPACE__);

/**
 * Compile one file's top-level blocks, each found by its first and last line.
 *
 * @param string                            $file   checked-in source file
 * @param list<array{0: string, 1: string}> $blocks start and end patterns, in file order
 *
 * @return string temporary file for the caller to require in its own scope and unlink
 */
function source_blocks(string $file, array $blocks) : string {
	$ranges = [];

	foreach ($blocks as [$start, $end]) {
		$ranges[] = cacti_fixture_source_range($file, $start, $end);
	}

	return cacti_fixture_source_lines($file, $ranges, __NAMESPACE__);
}

// cmd.php: one request serves both items on /stats, the third endpoint fails
$base   = cacti_http_test_server()['url'];
$seen   = count(cacti_http_test_requests());
$poller = new CactiHttpPoller();

$items = [
	http_row(9, "$base/stats", '/in'),
	http_row(10, "$base/stats", '/out', '{"scale":8}'),
	http_row(11, "$base/down", '/in'),
];

http_prefetch_device($poller, 2, $items);

$error_ds = [];
$outputs  = [];

foreach ($items as $item) {
	$outputs[] = collect_device_data($item, $error_ds, 25);
}

$requests = count(cacti_http_test_requests()) - $seen;

// a script server item in the same walk still reaches its own case
$script = ['host_id' => 2, 'local_data_id' => 8, 'action' => POLLER_ACTION_SCRIPT_PHP, 'arg1' => 'ss_fixture'];

$outputs[] = collect_device_data($script, $error_ds, 25);

check($requests === 2, "cmd.php made $requests requests for two endpoints");
check($outputs === ['5', '56.0', 'U', '42'], 'cmd.php outputs were ' . json_encode($outputs));
check($error_ds === [11 => 11], 'cmd.php error list was ' . json_encode($error_ds));

// an item that was never prefetched reports U instead of reusing stale output
$error_ds = [];
$item     = http_row(12, "$base/stats", '/in');

check(collect_device_data($item, $error_ds, 25) === 'U', 'unfetched item was not U');
check($error_ds === [12 => 12], 'unfetched item was not counted as an error');

// cmd.php's poller loop groups HTTP items by device and prefetches them when it reaches a new, live device
$poller_items = [http_row(9, "$base/stats", '/in'), $script, http_row(10, "$base/stats", '/out')];
$new_host     = true;
$host_down    = false;
$host_id      = 2;
$loop         = source_blocks($root . '/cmd.php', [
	["~^require_once\(CACTI_PATH_LIBRARY \. '/CactiHttpPoller\.php'\);$~", '~~'],
	['~^\$http_outputs = \[\];$~', '~^\$http_reasons = \[\];$~'],
	['~^// one request per device and endpoint~', '~^}$~'],
	['~^\t\$http_poller = ~', '~~'],
	['~^\t\tif \(\$new_host && !\$host_down && \$http_poller~', '~^\t\t}$~'],
]);

try {
	require $loop;
} finally {
	unlink($loop);
}

check(array_keys($http_items) === [2] && count($http_items[2]) === 2, 'cmd.php grouped ' . json_encode($http_items));
check($http_poller instanceof CactiHttpPoller, 'cmd.php built no HTTP poller');
check($http_outputs === ['9:value' => '5', '10:value' => '7'], 'cmd.php prefetched ' . json_encode($http_outputs));

// cmd_realtime.php fetches a device's HTTP rows once, then reads each item's output
$poller_items = [http_row(9, "$base/stats", '/in'), $script];
$item         = $poller_items[0];
$realtime     = source_blocks($root . '/cmd_realtime.php', [
	["~^require_once\(CACTI_PATH_LIBRARY \. '/CactiHttpPoller\.php'\);$~", '~~'],
	['~^\t+\$http_rows = array_filter~', '~^\t+\$http_outputs = cacti_sizeof~'],
	['~^\t+\$output = \$http_outputs\[CactiHttpPoller::rowKey~', '~~'],
]);

try {
	require $realtime;
} finally {
	unlink($realtime);
}

check(array_keys($http_rows) === [0] && $output === '5', 'cmd_realtime.php output was ' . json_encode($output));

// remote agent poll-now: the HTTP rows of every requested data source are fetched up front
$GLOBALS['fixture_rows']    = [http_row(9, "$base/stats", '/in'), http_row(10, REFUSED, '/out'), ['local_data_id' => 11, 'rrd_name' => 'ss', 'action' => POLLER_ACTION_SCRIPT_PHP, 'arg1' => 'ss_fixture']];
$GLOBALS['fixture_request'] = ['local_data_ids' => ['9', '10', '11'], 'host_id' => 2, 'poller_id' => '1'];

ob_start();
$returned = poll_for_data();
$response = json_decode(ob_get_clean(), true, 4, JSON_THROW_ON_ERROR);

check($returned === false, 'poll_for_data returned a value');
check(array_column($response, 'value') === ['5', 'U', 'U'], 'remote agent values were ' . json_encode($response));
check(array_column($response, 'local_data_id') === ['9', '10', '11'], 'remote agent ids were ' . json_encode($response));

// remote_agent.php loads the HTTP poller with its other libraries
$agent = source_blocks($root . '/remote_agent.php', [["~^require_once\(CACTI_PATH_LIBRARY \. '/CactiHttpPoller\.php'\);$~", '~~']]);

try {
	require $agent;
} finally {
	unlink($agent);
}

// poller cache helper: data source values override the data template
$GLOBALS['fixture_template_dtd'] = 60;
$GLOBALS['fixture_fields']       = [
	50 => [['type_code' => 'http_url', 'value' => 'https://local.example/']],
	60 => [['type_code' => 'http_url', 'value' => 'https://template.example/'], ['type_code' => 'http_timeout', 'value' => '2']],
];

$fields = get_http_input_fields(50, 7);

check($fields === ['http_url' => 'https://local.example/', 'http_timeout' => '2'], 'merged fields were ' . json_encode($fields));

// the poller cache writes one HTTP item carrying the URL, pointer and encoded options
$GLOBALS['fixture_template_dtd'] = 60;
$GLOBALS['fixture_fields']       = [
	50 => [['type_code' => 'http_url', 'value' => 'https://|host_hostname|/stats'], ['type_code' => 'http_json_pointer', 'value' => '/in']],
	60 => [['type_code' => 'http_scale', 'value' => '8']],
];

$cache = update_poller_cache(['id' => 21, 'host_id' => 2], false, 1);

$expected = [
	'host_id'               => 2,
	'local_data_id'         => 21,
	'poller_action_id'      => POLLER_ACTION_HTTP,
	'data_source_item_name' => 'value',
	'arg1'                  => 'https://127.0.0.1/stats',
	'arg2'                  => '/in',
	'arg3'                  => '{"scale":8}',
];

check($cache === [$expected], 'poller cache items were ' . json_encode($cache));

// data source test: a refused endpoint fails, so automation does not create the graph
$GLOBALS['fixture_template_dtd'] = 50;
$GLOBALS['fixture_fields']       = [
	50 => [['type_code' => 'http_url', 'value' => 'https://|host_hostname|:1/stats'], ['type_code' => 'http_json_pointer', 'value' => '/in']],
];

check(test_data_source(7, 2) === false, 'test_data_source accepted a refused endpoint');

// data template editor recognises only the HTTP system method
check(data_template_is_http(13) === true, 'data_template_is_http missed the HTTP method');
check(data_template_is_http(1) === false, 'data_template_is_http matched another method');

// and, like SNMP Get, offers no button for a second item on an HTTP template
$editor = source_blocks($root . '/data_templates.php', [
	['~^\t\$isSNMPGet = false;$~', '~^\t\$isStream  = false;$~'],
	['~^\t+\$isSNMPGet = data_template_is_snmp~', '~^\t+\$isStream  = data_template_is_stream~'],
	['~^\t// SNMP and HTTP gets write exactly one item per Data Source$~', '~^\t}$~'],
]);

$GLOBALS['fixture_request'] = ['id' => 7];
$GLOBALS['fixture_boxes']   = [];
$readOnly                   = false;

try {
	foreach ([13, 1] as $data_input_id) {
		$template_data = ['data_input_id' => $data_input_id];

		require $editor;
	}
} finally {
	unlink($editor);
}

check($GLOBALS['fixture_boxes'][0] === '' && is_array($GLOBALS['fixture_boxes'][1]), 'item buttons were ' . json_encode($GLOBALS['fixture_boxes']));

// the global tables name the method and its concurrency setting
$poller_intervals      = [];
$cron_intervals        = [];
$poller_options        = [];
$poller_sync_intervals = [];
$settings              = [];
$globals               = [
	source_blocks($root . '/include/global_arrays.php', [['~^\$input_types = \[$~', '~^\];$~'], ['~^\$hash_system_data_inputs = \[$~', '~^\];$~']]),
	source_blocks($root . '/include/global_settings.php', [['~^\$settings\[\'poller\'\] = \[$~', '~^\];$~']]),
];

try {
	foreach ($globals as $path) {
		require $path;
	}
} finally {
	array_map('unlink', $globals);
}

check($input_types[DATA_INPUT_TYPE_HTTP] === 'HTTP JSON Get', 'input type was ' . json_encode($input_types));
check(in_array(HTTP_HASH, $hash_system_data_inputs, true), 'HTTP method is not a system data input');
check($settings['poller']['http_poller_concurrency']['default'] === (string) CactiHttpPoller::DEFAULT_CONCURRENCY, 'concurrency default differs from the poller');

// upgrade adds the method and its four fields, each guarded by its hash
upgrade_http_data_input();

$executes = $GLOBALS['fixture_executes'];

check(count($executes) === 5, 'upgrade ran ' . count($executes) . ' statements');
check($executes[0][1] === [HTTP_HASH, DATA_INPUT_TYPE_HTTP, HTTP_HASH], 'upgrade method parameters were ' . json_encode($executes[0][1]));
check(array_column(array_column(array_slice($executes, 1), 1), 5) === ['http_url', 'http_json_pointer', 'http_scale', 'http_timeout'], 'upgrade field type codes were wrong');

foreach (array_slice($executes, 1) as [$sql, $params]) {
	check($params[1] === 13 && $params[0] === $params[8] && str_contains($sql, 'WHERE NOT EXISTS'), 'upgrade field insert is not guarded by its hash');
}

// upgrade_to_1_3_0() runs the step
$GLOBALS['fixture_executes'] = [];
$upgrade                     = source_blocks($root . '/install/upgrades/1_3_0.php', [['~^\tupgrade_http_data_input\(\);$~', '~~']]);

try {
	require $upgrade;
} finally {
	unlink($upgrade);
}

check(count($GLOBALS['fixture_executes']) === 5, 'upgrade_to_1_3_0 did not add the HTTP method');

print json_encode(['http_poller_action_scenarios' => 13], JSON_THROW_ON_ERROR) . "\n";
