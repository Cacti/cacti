<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
namespace CactiHttpPollerActionFixture;

use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/*
 * Request-coverage probe for the HTTP poller action.
 *
 * The action is wired into cmd.php, remote_agent.php, the data source test,
 * the poller cache helper, the data template editor and the 1.3.0 upgrade.
 * Those entry points run as CLI or web scripts that Pest cannot include, so
 * each function is loaded in isolation with its database and logging
 * boundaries stubbed.  The HTTP client is real: Symfony's MockHttpClient where
 * the function accepts a poller, and a refused loopback port where the
 * function builds its own.
 */

$root = dirname(__DIR__, 3);

require $root . '/tests/Helpers/RequestCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/vendor/autoload.php';
require $root . '/include/global_constants.php';
require $root . '/lib/CactiHttpPoller.php';

define('CACTI_PATH_LIBRARY', $root . '/lib');

// classes do not fall back to the global namespace the way functions do
class_alias(\CactiHttpPoller::class, __NAMESPACE__ . '\CactiHttpPoller');
class_alias(\CactiHttpPollItem::class, __NAMESPACE__ . '\CactiHttpPollItem');

const HTTP_HASH = '5d4a0c2b7f8e41a69b3c1e07d2f6a8b4';
const REFUSED   = 'http://127.0.0.1:1/stats';

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
		return array_values(array_filter($GLOBALS['fixture_rows'], fn ($row) => in_array($row['local_data_id'], array_slice($params, 2), true)));
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

// cmd.php: one request serves both items on /stats, the third endpoint fails
$requests = 0;
$poller   = new CactiHttpPoller(new MockHttpClient(function (string $method, string $url) use (&$requests) {
	$requests++;

	return str_ends_with($url, '/down') ? new MockResponse('', ['http_code' => 503]) : new MockResponse('{"in":5,"out":7}');
}));

$items = [
	http_row(9, 'https://device.example/stats', '/in'),
	http_row(10, 'https://device.example/stats', '/out', '{"scale":8}'),
	http_row(11, 'https://device.example/down', '/in'),
];

http_prefetch_device($poller, 2, $items);

$error_ds = [];
$outputs  = [];

foreach ($items as $item) {
	$outputs[] = collect_device_data($item, $error_ds, 25);
}

check($requests === 2, "cmd.php made $requests requests for two endpoints");
check($outputs === ['5', '56.0', 'U'], 'cmd.php outputs were ' . json_encode($outputs));
check($error_ds === [11 => 11], 'cmd.php error list was ' . json_encode($error_ds));

// an item that was never prefetched reports U instead of reusing stale output
$error_ds = [];
$item     = http_row(12, 'https://device.example/stats', '/in');

check(collect_device_data($item, $error_ds, 25) === 'U', 'unfetched item was not U');
check($error_ds === [12 => 12], 'unfetched item was not counted as an error');

// remote agent poll-now: the HTTP rows of every requested data source are fetched up front
$GLOBALS['fixture_rows']    = [http_row(9, REFUSED, '/in'), http_row(10, REFUSED, '/out')];
$GLOBALS['fixture_request'] = ['local_data_ids' => ['9', '10'], 'host_id' => 2, 'poller_id' => '1'];

ob_start();
$returned = poll_for_data();
$response = json_decode(ob_get_clean(), true, 4, JSON_THROW_ON_ERROR);

check($returned === false, 'poll_for_data returned a value');
check(array_column($response, 'value') === ['U', 'U'], 'remote agent values were ' . json_encode($response));
check(array_column($response, 'local_data_id') === ['9', '10'], 'remote agent ids were ' . json_encode($response));

// poller cache helper: data source values override the data template
$GLOBALS['fixture_template_dtd'] = 60;
$GLOBALS['fixture_fields']       = [
	50 => [['type_code' => 'http_url', 'value' => 'https://local.example/']],
	60 => [['type_code' => 'http_url', 'value' => 'https://template.example/'], ['type_code' => 'http_timeout', 'value' => '2']],
];

$fields = get_http_input_fields(50, 7);

check($fields === ['http_url' => 'https://local.example/', 'http_timeout' => '2'], 'merged fields were ' . json_encode($fields));

// data source test: a refused endpoint fails, so automation does not create the graph
$GLOBALS['fixture_template_dtd'] = 50;
$GLOBALS['fixture_fields']       = [
	50 => [['type_code' => 'http_url', 'value' => 'http://|host_hostname|:1/stats'], ['type_code' => 'http_json_pointer', 'value' => '/in']],
];

check(test_data_source(7, 2) === false, 'test_data_source accepted a refused endpoint');

// data template editor recognises only the HTTP system method
check(data_template_is_http(13) === true, 'data_template_is_http missed the HTTP method');
check(data_template_is_http(1) === false, 'data_template_is_http matched another method');

// upgrade adds the method and its four fields, each guarded by its hash
upgrade_http_data_input();

$executes = $GLOBALS['fixture_executes'];

check(count($executes) === 5, 'upgrade ran ' . count($executes) . ' statements');
check($executes[0][1] === [HTTP_HASH, DATA_INPUT_TYPE_HTTP, HTTP_HASH], 'upgrade method parameters were ' . json_encode($executes[0][1]));
check(array_column(array_column(array_slice($executes, 1), 1), 5) === ['http_url', 'http_json_pointer', 'http_scale', 'http_timeout'], 'upgrade field type codes were wrong');

foreach (array_slice($executes, 1) as [$sql, $params]) {
	check($params[1] === 13 && $params[0] === $params[8] && str_contains($sql, 'WHERE NOT EXISTS'), 'upgrade field insert is not guarded by its hash');
}

print json_encode(['http_poller_action_scenarios' => 7], JSON_THROW_ON_ERROR) . "\n";
