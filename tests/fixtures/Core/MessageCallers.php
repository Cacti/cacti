<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiMessageCallersFixture;

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';
require $root . '/lib/html.php';
require $root . '/lib/html_validate.php';
require __DIR__ . '/MessagePluginHooks.php';
$config = ['base_path' => $root, 'library_path' => $root . '/lib', 'is_web' => false, 'config_options_array' => ['storage_location' => 0]];
$no_http_headers = true;
$messages = [1 => ['message' => 'Save Successful.', 'level' => MESSAGE_LEVEL_INFO]];
$_SESSION = ['sess_user_id' => 1];
$_SERVER['REQUEST_METHOD'] = 'POST';
$request = [];
$connections = $heartbeats = [];
$checks = 0;
$name = 'R&D <b>x</b> "q"';

// Real SQL and message storage; only remote connectivity and expensive poller work are substituted.
function query($sql, $params = [], $connection = false) {
	$statement = ($connection ?: $GLOBALS['db'])->prepare($sql);
	$statement->execute($params);

	return $statement;
}
function db_fetch_cell_prepared($sql, $params = [], $col_name = '', $log = true, $db_conn = false) {
	$row = query($sql, $params, $db_conn)->fetch(\PDO::FETCH_ASSOC);

	return $row ? ($col_name === '' ? reset($row) : $row[$col_name]) : false;
}
function db_fetch_assoc_prepared($sql, $params = [], $log = true, $db_conn = false) {
	return query($sql, $params, $db_conn)->fetchAll(\PDO::FETCH_ASSOC);
}
function db_fetch_row_prepared($sql, $params = [], $log = true, $db_conn = false) {
	return query($sql, $params, $db_conn)->fetch(\PDO::FETCH_ASSOC) ?: [];
}
function db_execute_prepared($sql, $params = [], $log = true, $db_conn = false, $execute_name = 'Exec', $default_value = true, $return_func = 'no_return_function', $return_params = []) {
	query($sql, $params, $db_conn);

	return true;
}
function db_execute($sql, $log = true, $db_conn = false) {
	return db_execute_prepared($sql, [], $log, $db_conn);
}
function db_fetch_cell($sql, $col_name = '', $log = true, $db_conn = false) {
	return db_fetch_cell_prepared($sql, [], $col_name, $log, $db_conn);
}
function remote_poller_up($id) {
	return array_shift($GLOBALS['heartbeats']) ?? false;
}
function poller_push_to_remote_db_connect($id, $poller = false) {
	return array_shift($GLOBALS['connections']) ? $GLOBALS['db'] : false;
}
function __($text, ...$args) {
	return $args ? sprintf($text, ...$args) : $text;
}
function __esc($text, ...$args) {
	return \html_escape(__($text, ...$args));
}
function get_nfilter_request_var($key, $default = '') {
	return $GLOBALS['request'][$key] ?? $default;
}
function get_request_var($key, $default = '') {
	return get_nfilter_request_var($key, $default);
}
function get_filter_request_var($key, $filter = FILTER_VALIDATE_INT, $options = []) {
	return filter_var(get_nfilter_request_var($key), $filter, $options);
}
function isset_request_var($key) {
	return isset($GLOBALS['request'][$key]);
}
function read_config_option($name, $force = false) {
	return ['reindex_method' => 1, 'poller_id' => 1][$name] ?? '';
}
function set_config_option($name, $value, $remote = false) {
	query('REPLACE INTO settings (name, value) VALUES (?, ?)', [$name, $value]);
}
function run_data_query($host_id, $query_id, $automation = false, $force = false) {
}
function push_out_host($id, $local_data_id = 0, $data_template_id = 0) {
}
function api_plugin_hook_function($name, $data = null) {
	return $data;
}
function automation_hook_graph_template($host_id, $template_id) {
}
function reports_log($message, $output = false, $environ = 'REPORTS', $level = POLLER_VERBOSITY_NONE) {
}
function api_plugin_get_dependencies($plugin) {
	return [];
}
function api_plugin_can_install($plugin, &$message) {
	$message = $GLOBALS['name'];

	return $plugin !== 'message_dependency';
}
function cacti_plugin_path($plugin, $relative = '') {
	return false;
}
function api_plugin_disable_hooks_all($plugin) {
	query('UPDATE plugin_hooks SET status = 0 WHERE name = ?', [$plugin]);
}
function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = '') {
}
class Redirect extends \RuntimeException {
}
function header($value, $replace = true, $response_code = 0) {
	throw new Redirect($value);
}
function cacti_authorize_resource($user_id, $id, $type) {
	return true;
}
function get_graph_title($id) {
	return $GLOBALS['name'];
}
function sql_save($data, $table, $key = 'id', $autoinc = true, $db_conn = false) {
	if ($GLOBALS['save_failure'] ?? false) {
		return false;
	}
	$id = $data[$key] ?? 0;
	unset($data[$key]);
	if ($id) {
		query('UPDATE ' . $table . ' SET `' . implode('` = ?, `', array_keys($data)) . '` = ? WHERE ' . $key . ' = ?', [...array_values($data), $id], $db_conn);

		return $id;
	}
	query('INSERT INTO ' . $table . ' (`' . implode('`,`', array_keys($data)) . '`) VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')', array_values($data), $db_conn);

	return (int) $GLOBALS['db']->lastInsertId();
}
function api_device_cache_crc_update($poller_id, $variable = 'poller_replicate_device_cache_crc') {
}
function update_data_source_title_cache_from_host($host_id, $query_id = 0, $ids = []) {
}
function update_graph_title_cache_from_host($host_id, $query_id = 0, $ids = []) {
}
function snmpagent_api_device_new($save) {
}
function automation_execute_device_create_tree($id) {
}
function check($condition, $message) {
	$GLOBALS['checks']++;
	if (!$condition) {
		throw new \RuntimeException($message);
	}
}
function message($id, $fragment, $level, $dynamic = false) {
	$stored = $_SESSION['sess_messages'][$id] ?? [];
	check(($stored['level'] ?? null) === $level, 'Wrong severity for ' . $id);
	check(str_contains($stored['message'] ?? '', $fragment), 'Wrong message for ' . $id);
	if ($dynamic) {
		check(str_contains($stored['message'], \html_escape($GLOBALS['name'])), 'Name was lost or escaped twice');
		check(!str_contains($stored['message'], $GLOBALS['name']), 'Name was emitted as markup');
	}
}

$db = new \PDO(
	'mysql:host=' . (getenv('BOOST_DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('BOOST_DB_PORT') ?: '3306') . ';charset=utf8mb4',
	getenv('BOOST_DB_USER') ?: 'root', getenv('BOOST_DB_PASSWORD') ?: '',
	[\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
// A process-owned identifier permits concurrent probes without sharing tables.
$database = 'cacti_message_probe_' . getmypid();
$db->exec('CREATE DATABASE `' . $database . '`');
$db->exec('USE `' . $database . '`');
$db->exec("SET SESSION sql_mode = ''");

try {
	preg_match_all('/^CREATE TABLE .*?;$/ms', file_get_contents($root . '/cacti.sql'), $schema);
	foreach ($schema[0] as $statement) {
		$db->exec($statement);
	}
	query('INSERT INTO host (id, poller_id, description) VALUES (7, 2, ?)', [$name]);
	query('INSERT INTO reports (id, user_id, name, font_size) VALUES (1, 1, ?, 10)', [$name]);
	query('INSERT INTO graph_local (id, host_id, graph_template_id) VALUES (1, 7, 1)');

	if (($argv[1] ?? 'api') !== 'api') {
		require __DIR__ . '/MessagePages.php';
	} else {
		foreach (['api_device_purge_from_remote', 'api_device_disable_devices', 'api_device_enable_devices', 'api_device_change_options', 'api_device_clear_statistics', 'api_device_dq_add', 'api_device_dq_remove', 'api_device_dq_change', 'api_device_gt_remove'] as $function) {
			cacti_fixture_source_function($root . '/lib/api_device.php', $function, __NAMESPACE__);
		}
		$fields_host_edit = ['description' => []];
		$request = ['description' => $name];
		$actions = [
			'api_device_purge_from_remote' => [7, 2],
			'api_device_disable_devices' => [[7, 7]],
			'api_device_enable_devices' => [[7, 7]],
			'api_device_change_options' => [[7, 7], ['t_description' => 'on']],
			'api_device_clear_statistics' => [[7, 7]],
			'api_device_dq_add' => [7, 1, 1],
			'api_device_dq_remove' => [7, 1],
			'api_device_dq_change' => [7, 1, 2],
			'api_device_gt_remove' => [7, 1],
		];
		foreach ($actions as $function => $args) {
			foreach ([false, true] as $up) {
				$_SESSION['sess_messages'] = [];
				$heartbeats = array_fill(0, 10, $up);
				$connections = [];
				(__NAMESPACE__ . '\\' . $function)(...$args);
				message('poller_down_2', 'FullSync', MESSAGE_LEVEL_WARN);
				check(count($_SESSION['sess_messages']) === 1, 'Repeated devices must share a collector warning');
			}
		}
		check(db_fetch_cell_prepared('SELECT description FROM host WHERE id = 7') === $name, 'Device action failed to preserve its plain-text name');
		foreach ([[true, false], [true, true]] as $heartbeats) {
			$_SESSION['sess_messages'] = [];
			$connections = [true, false];
			api_device_enable_devices([7]);
			message('poller_down_2', 'FullSync', MESSAGE_LEVEL_WARN);
		}
		foreach (['api_device_save', 'api_device_quick_save', 'api_device_update_host_template'] as $function) {
			cacti_fixture_source_function($root . '/lib/api_device.php', $function, __NAMESPACE__);
		}
		foreach ([[1, [false], []], [2, [false], []], [2, [true], []], [2, [true, false], [true]], [2, [true, true], [true, false]]] as [$poller, $heartbeats, $connections]) {
			$_SESSION = ['sess_user_id' => 1];
			query('UPDATE host SET poller_id = 2, disabled = ?, snmp_version = 0 WHERE id = 7', ['']);
			$result = api_device_save(7, 0, $name, 'router.example.test', '', 0, '', '', 161, 500, '', 1, 3, 23, 500, 1, '', '', '', '', '', '', 5, 1, $poller);
			check($result === 7, 'Collector failure must not reject the local save');
			message('poller_down_7', 'FullSync', MESSAGE_LEVEL_WARN);
			check(db_fetch_cell_prepared('SELECT description FROM host WHERE id = 7') === $name, 'Save altered the device name');
		}
		query('INSERT INTO host_template_snmp_query (host_template_id, snmp_query_id) VALUES (1, 1)');
		query('INSERT INTO host_template_graph (host_template_id, graph_template_id) VALUES (1, 1)');
		query('INSERT INTO graph_templates (id, name) VALUES (2, ?)', [$name]);
		$poller = 10;
		foreach ([
			[[false], []], [[true, true], [false, false]],
			[[true, false], [true]],
			[[true, true, false], [true, true]], [[true, true, true], [true, true, false]],
			[[true, true, true, false], [true, true, true]], [[true, true, true, true], [true, true, true, false]],
		] as [$heartbeats, $connections]) {
			$poller++;
			$_SESSION['sess_messages'] = [];
			query('UPDATE host SET poller_id = ? WHERE id = 7', [$poller]);
			query('DELETE FROM host_graph WHERE host_id = 7');
			query('INSERT INTO host_graph (host_id, graph_template_id) VALUES (7, 2)');
			api_device_update_host_template(7, 1);
			message('poller_down_' . $poller, 'FullSync', MESSAGE_LEVEL_WARN);
			check((int) db_fetch_cell_prepared('SELECT host_template_id FROM host WHERE id = 7') === 1, 'Collector failure must not reject the local template');
		}

		foreach (['reports_add_devices', 'reports_graphs_action_execute'] as $function) {
			cacti_fixture_source_function($root . '/lib/reports.php', $function, __NAMESPACE__);
		}
		cacti_fixture_source_function($root . '/lib/html_reports.php', 'reports_send', __NAMESPACE__);
		check(reports_add_devices(1, [7], 0, 'left'), 'Adding a device should succeed');
		message('reports_add_device_7', 'successfully added', MESSAGE_LEVEL_INFO, true);
		check(!reports_add_devices(1, [7], 0, 'left'), 'Duplicate report device should be rejected');
		message('reports_no_add_device_7', 'already exists', MESSAGE_LEVEL_WARN, true);
		$request = ['selected_items' => serialize([1]), 'reports_id' => 1, 'timespan' => 0, 'alignment' => 'left'];
		foreach ([true, false, false] as $save_failure) {
			reports_graphs_action_execute('reports');
			message('report_send', '<i>' . \html_escape($name) . '</i>', MESSAGE_LEVEL_INFO, true);
		}
		foreach (['email' => 'destination', 'from_name' => 'From Name', 'from_email' => 'from address'] as $missing => $fragment) {
			query('UPDATE reports SET email = ?, subject = ?, from_name = ?, from_email = ? WHERE id = 1', ['to@example.test', 'Subject', 'Sender', 'from@example.test']);
			query('UPDATE reports SET ' . $missing . ' = ? WHERE id = 1', ['']);
			reports_send(1);
			message('report_message', $fragment, MESSAGE_LEVEL_ERROR, true);
		}
		cacti_fixture_source_function($root . '/lib/utility.php', 'poller_update_poller_cache_from_buffer', __NAMESPACE__);
		foreach (['mark', 'chunk', 'tail', 'cleanup'] as $phase) {
			foreach ([false, true] as $up) {
				$_SESSION['sess_messages'] = [];
				$ids = $phase === 'mark' || $phase === 'cleanup' ? [101] : [];
				$heartbeats = $phase === 'cleanup' ? [true, $up] : [$up];
				$connections = $phase === 'cleanup' ? [true, false] : [false];
				$records = [];
				for ($i = 0; $i < ($phase === 'chunk' ? 260 : ($phase === 'tail' ? 1 : 0)); $i++) {
					$values = [101, 2, 7, 0, 'router.example.test', '', 2, 500, '', '', '', '', '', '', '', 161, 'ds' . $i, str_repeat('r', 500), 1, 300, 0, str_repeat('a', 500), '', '', 1];
					$records[] = '(' . implode(',', array_map([$db, 'quote'], $values)) . ')';
				}
				poller_update_poller_cache_from_buffer($ids, $records, 2);
				message('poller_down_2', 'FullSync', MESSAGE_LEVEL_WARN);
				check(db_fetch_cell_prepared('SELECT value FROM settings WHERE name = ?', ['time_last_change_poller_item']) !== false, 'Failed collector flush must still invalidate the local cache');
			}
		}
		cacti_fixture_source_function($root . '/lib/plugins.php', 'api_plugin_install', __NAMESPACE__);
		try {
			api_plugin_install('message_dependency');
			check(false, 'Unsatisfied dependency must redirect');
		} catch (Redirect $e) {
			check($e->getMessage() === 'Location: plugins.php', 'Dependency redirect changed');
		}
		message('dependency_check', \html_escape($name) . '<br><br>', MESSAGE_LEVEL_ERROR, true);
		foreach (['plugin_wrong' => 'directory_error', 'message_missing_version' => 'version_error', 'message_failure' => 'install_error', 'message_no_install' => 'install_error'] as $plugin => $id) {
			check(api_plugin_install($plugin) === false, 'Invalid plugin installation must fail');
			message($id, $plugin, MESSAGE_LEVEL_ERROR);
		}
		check((int) db_fetch_cell_prepared('SELECT status FROM plugin_config WHERE directory = ?', ['message_failure']) === 2, 'Failed plugin must require configuration');
	}
	print json_encode(['ok' => true, 'checks' => $checks], JSON_THROW_ON_ERROR);
} finally {
	$db->exec('DROP DATABASE `' . $database . '`');
}
