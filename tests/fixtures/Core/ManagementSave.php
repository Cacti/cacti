<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiManagementSaveFixture;

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';
require $root . '/lib/html_validate.php';
$config = ['base_path' => $root, 'library_path' => $root . '/lib', 'is_web' => false, 'config_options_array' => ['storage_location' => 0, 'extended_paths' => '']];
$_SESSION = [];
$_SERVER['REQUEST_METHOD'] = 'POST';
$db = new \PDO('sqlite::memory:');
$remote = new \PDO('sqlite::memory:');
$scenario = $argv[1];
$settings = $titles = $request = $replicated = [];

// Execute production SQL against isolated databases; only persistence/network and UI boundaries are replaced.
function db_fetch_assoc_prepared($sql, $params = [], $log = true, $db_conn = false) {
	$query = ($db_conn ?: $GLOBALS['db'])->prepare($sql);
	$query->execute($params);

	return $query->fetchAll(\PDO::FETCH_ASSOC);
}
function db_fetch_assoc($sql, $log = true, $db_conn = false) {
	return db_fetch_assoc_prepared($sql, [], $log, $db_conn);
}
function db_fetch_row_prepared($sql, $params = [], $log = true, $db_conn = false) {
	$rows = db_fetch_assoc_prepared($sql, $params, $log, $db_conn);

	return $rows[0] ?? [];
}
function db_fetch_cell_prepared($sql, $params = [], $col_name = '', $log = true, $db_conn = false) {
	// SQLite has no connection advisory locks; concurrency is covered by the MariaDB suite.
	if (str_contains($sql, 'GET_LOCK(') || str_contains($sql, 'RELEASE_LOCK(')) {
		return 1;
	}
	$row = db_fetch_row_prepared($sql, $params, $log, $db_conn);

	return $row ? ($col_name === '' ? reset($row) : $row[$col_name]) : false;
}
function db_execute_prepared($sql, $params = [], $log = true, $db_conn = false) {
	return ($db_conn ?: $GLOBALS['db'])->prepare($sql)->execute($params);
}
function db_execute($sql, $log = true, $db_conn = false) {
	if ($GLOBALS['scenario'] === 'site-delete-failure' && str_starts_with($sql, 'DELETE FROM sites')) {
		return false;
	}

	return db_execute_prepared($sql, [], $log, $db_conn);
}
function sql_save($data, $table, $key = 'id', $autoinc = true, $db_conn = false) {
	if (in_array($GLOBALS['scenario'], ['device-save-failure', 'site-save-failure'], true)) {
		return 0;
	}
	$columns = array_keys($data);
	$updates = array_map(static fn($column) => $column . '=excluded.' . $column, $columns);
	db_execute_prepared('INSERT INTO ' . $table . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ') ON CONFLICT (' . $key . ') DO UPDATE SET ' . implode(',', $updates), array_values($data), true, $db_conn);

	return $data[$key];
}
function get_nfilter_request_var($name) {
	return $GLOBALS['request'][$name] ?? null;
}
function get_filter_request_var($name, $filter = FILTER_VALIDATE_INT, $options = []) {
	return filter_var(get_nfilter_request_var($name), $filter, $options);
}
function isset_request_var($name) {
	return isset($GLOBALS['request'][$name]);
}
function array_to_sql_or($values, $column) {
	return $column . ' IN (' . implode(',', array_map(static fn($value) => $GLOBALS['db']->quote($value), $values)) . ')';
}
function raise_message($id, $message = '', $level = null) {
}
function cacti_log($message, $echo = false, $subsystem = 'SYSTEM', $verbosity = 2) {
}
function set_config_option($name, $value) {
	$GLOBALS['settings'][$name] = $value;
}
function api_plugin_hook_function($name, $data) {
	return $data;
}
function remote_poller_up($id) {
	return true;
}
function poller_connect_to_remote($id) {
	return $GLOBALS['remote'];
}
function poller_push_to_remote_db_connect($id) {
	return $GLOBALS['remote'];
}
function replicate_table_to_poller($conn, &$data, $table, $exclude = false) {
	$GLOBALS['replicated'][] = $table;
	foreach ($data as $row) {
		$columns = array_keys($row);
		db_execute_prepared('INSERT OR REPLACE INTO ' . $table . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')', array_values($row), true, $conn);
	}
}
function push_out_host($host_id, $local_data_id = 0, $data_template_id = 0) {
	// Poller cache construction is covered separately; preserve its save-time assignment contract.
	db_execute_prepared('UPDATE poller_item SET poller_id = (SELECT poller_id FROM host WHERE id = ?) WHERE host_id = ?', [$host_id, $host_id]);
}
function api_device_cache_crc_update($poller_id) {
}
function snmpagent_api_device_new($save) {
}
function automation_execute_device_create_tree($id) {
}
function update_data_source_title_cache($id) {
	$GLOBALS['titles']['data'][] = $id;
}
function update_graph_title_cache($id) {
	$GLOBALS['titles']['graph'][] = $id;
}
class Redirect extends \RuntimeException {
}
function header($value, $replace = true, $response_code = 0) {
	throw new Redirect($value);
}
function check($condition, $message) {
	if (!$condition) {
		throw new \RuntimeException($message);
	}
}

foreach (['form_input_validate', 'is_error_message', 'sanitize_unserialize_selected_items'] as $function) {
	cacti_fixture_source_function($root . '/lib/functions.php', $function, __NAMESPACE__);
}
cacti_fixture_source_function($root . '/include/csrf.php', 'csrf_require_post', __NAMESPACE__);
foreach (['update_data_source_title_cache_from_host', 'update_graph_title_cache_from_host'] as $function) {
	cacti_fixture_source_function($root . '/lib/variables.php', $function, __NAMESPACE__);
}

$host = ['id' => 7, 'host_template_id' => 0, 'poller_id' => 1, 'site_id' => 1, 'description' => 'Device', 'hostname' => 'router.example', 'disabled' => '', 'deleted' => '', 'status' => 3, 'snmp_version' => 2, 'snmp_community' => '', 'snmp_username' => '', 'snmp_password' => '', 'snmp_auth_protocol' => '', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '', 'snmp_context' => '', 'snmp_engine_id' => '', 'snmp_port' => 161, 'snmp_timeout' => 500, 'external_id' => '', 'notes' => '', 'location' => '', 'availability_method' => 1, 'ping_method' => 3, 'ping_port' => 23, 'ping_timeout' => 500, 'ping_retries' => 1, 'max_oids' => 5, 'bulk_walk_size' => -1, 'device_threads' => 1];
foreach ([$db, $remote] as $connection) {
	$connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	$connection->exec('CREATE TABLE host (
		id INTEGER PRIMARY KEY, host_template_id TEXT, poller_id TEXT, site_id TEXT,
		description TEXT, hostname TEXT, disabled TEXT, deleted TEXT, status TEXT,
		snmp_version TEXT, snmp_community TEXT, snmp_username TEXT, snmp_password TEXT,
		snmp_auth_protocol TEXT, snmp_priv_passphrase TEXT, snmp_priv_protocol TEXT,
		snmp_context TEXT, snmp_engine_id TEXT, snmp_port TEXT, snmp_timeout TEXT,
		external_id TEXT, notes TEXT, location TEXT, availability_method TEXT,
		ping_method TEXT, ping_port TEXT, ping_timeout TEXT, ping_retries TEXT,
		max_oids TEXT, bulk_walk_size TEXT, device_threads TEXT)');
	$connection->exec('CREATE TABLE data_local (id INTEGER PRIMARY KEY, host_id INTEGER, snmp_query_id INTEGER)');
	$connection->exec('CREATE TABLE graph_local (id INTEGER PRIMARY KEY, host_id INTEGER, snmp_query_id INTEGER)');
	$connection->exec('CREATE TABLE data_template_rrd (id INTEGER PRIMARY KEY, local_data_id INTEGER)');
	$connection->exec('CREATE TABLE graph_templates_item (id INTEGER PRIMARY KEY, local_graph_id INTEGER, task_item_id INTEGER)');
	$connection->exec('CREATE TABLE data_template_data (id INTEGER PRIMARY KEY, local_data_id INTEGER)');
	$connection->exec('CREATE TABLE data_input_data (id INTEGER PRIMARY KEY, data_template_data_id INTEGER)');
	$connection->exec('CREATE TABLE poller_item (local_data_id INTEGER PRIMARY KEY, host_id INTEGER, poller_id INTEGER, action INTEGER)');
	$connection->exec('CREATE TABLE host_snmp_query (host_id INTEGER, snmp_query_id INTEGER, reindex_method INTEGER)');
	$connection->exec('CREATE TABLE host_snmp_cache (host_id INTEGER, snmp_query_id INTEGER, field_name TEXT, field_value TEXT)');
	$connection->exec('CREATE TABLE poller_reindex (host_id INTEGER, data_query_id INTEGER, action INTEGER)');
	$connection->exec('CREATE TABLE poller (id INTEGER PRIMARY KEY, snmp INTEGER, script INTEGER, server INTEGER)');
}
$db->prepare('INSERT INTO host VALUES (
	:id, :host_template_id, :poller_id, :site_id, :description, :hostname,
	:disabled, :deleted, :status, :snmp_version, :snmp_community, :snmp_username,
	:snmp_password, :snmp_auth_protocol, :snmp_priv_passphrase, :snmp_priv_protocol,
	:snmp_context, :snmp_engine_id, :snmp_port, :snmp_timeout, :external_id,
	:notes, :location, :availability_method, :ping_method, :ping_port,
	:ping_timeout, :ping_retries, :max_oids, :bulk_walk_size, :device_threads)')->execute($host);
$db->exec('INSERT INTO data_local VALUES (101,7,1),(102,7,1),(103,7,2),(104,8,1)');
$db->exec('INSERT INTO graph_local VALUES (201,7,1),(202,7,1),(203,7,2),(204,8,1)');
$db->exec('INSERT INTO data_template_rrd VALUES (301,101),(302,102),(303,103),(304,104)');
$db->exec('INSERT INTO graph_templates_item VALUES (401,201,301),(402,202,302),(403,203,303),(404,204,304)');
$db->exec('INSERT INTO data_template_data VALUES (501,101)');
$db->exec('INSERT INTO data_input_data VALUES (601,501)');
$db->exec('INSERT INTO poller_item VALUES (101,7,1,0)');
$db->exec('INSERT INTO host_snmp_query VALUES (7,1,2)');
$db->exec("INSERT INTO host_snmp_cache VALUES (7,1,'hrStorageAllocationUnits','4096')");
$db->exec('INSERT INTO poller_reindex VALUES (7,1,0)');
$db->exec('INSERT INTO poller VALUES (2,0,0,0)');

if (str_starts_with($scenario, 'device-')) {
	foreach (['api_device_save', 'api_device_quick_save', 'api_device_disable_devices', 'api_device_replicate_out'] as $function) {
		cacti_fixture_source_function($root . '/lib/api_device.php', $function, __NAMESPACE__);
	}
	$poller = $scenario === 'device-migrate' ? 2 : 1;
	$disabled = $scenario === 'device-migrate' || $scenario === 'device-unchanged' ? '' : 'on';
	$result = api_device_save(7, 0, 'Device', 'router.example', '', 2, '', '', 161, 500, $disabled, 1, 3, 23, $scenario === 'device-invalid' ? 'invalid-timeout' : 500, 1, '', '', '', '', '', '', 5, 1, $poller);
	$state = db_fetch_row_prepared('SELECT disabled, status FROM host WHERE id = 7');
	if ($scenario === 'device-invalid' || $scenario === 'device-save-failure') {
		check($result === 0 && $state === ['disabled' => '', 'status' => '3'] && !$titles, 'Rejected save changed the device');
	} elseif ($scenario === 'device-migrate') {
		check($result === 7 && count($replicated) === 11, 'Collector transfer skipped metadata replication');
		check(db_fetch_cell_prepared('SELECT field_value FROM host_snmp_cache', [], '', true, $remote) === '4096', 'Collector lost disk allocation units');
		check(db_fetch_cell_prepared('SELECT reindex_method FROM host_snmp_query', [], '', true, $remote) === 2, 'Collector lost query assignments');
		check(db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_reindex', [], '', true, $remote) === 1, 'Collector lost reindex rows');
		check(db_fetch_cell_prepared('SELECT poller_id FROM poller_item', [], '', true, $remote) === 2, 'Collector received stale poller items');
	} elseif ($scenario === 'device-unchanged') {
		check($result === 7 && !$replicated, 'Ordinary save unnecessarily replicated the device');
	} else {
		check($result === 7 && $state === ['disabled' => 'on', 'status' => '0'], 'Successful save failed to disable the device');
	}
} elseif ($scenario === 'limited-titles') {
	update_data_source_title_cache_from_host(7, 1, ['a' => 101, 'b' => 102, 'c' => 103, 'd' => 104]);
	update_graph_title_cache_from_host(7, 1, ['a' => 101, 'b' => 102, 'c' => 103, 'd' => 104]);
	check($titles === ['data' => [101, 102], 'graph' => [201, 202]], 'Limited refresh skipped IDs or crossed device/query boundaries');
} else {
	foreach (['form_save', 'form_actions'] as $function) {
		cacti_fixture_source_function($root . '/sites.php', $function, __NAMESPACE__);
	}
	foreach (['api_tree_delete_node', 'api_tree_parse_node_data', 'api_tree_acquire_write_lock', 'api_tree_release_write_lock'] as $function) {
		cacti_fixture_source_function($root . '/lib/api_tree.php', $function, __NAMESPACE__);
	}
	$request = ['save_component_site' => 1, 'id' => 1, 'name' => 'New Site', 'address1' => '', 'address2' => '', 'city' => '', 'state' => '', 'postal_code' => '', 'country' => '', 'timezone' => 'UTC', 'latitude' => '', 'longitude' => '', 'zoom' => '12', 'alternate_id' => '', 'notes' => 'Updated'];
	$db->exec('CREATE TABLE sites (
		id INTEGER PRIMARY KEY, name TEXT, address1 TEXT, address2 TEXT, city TEXT,
		state TEXT, postal_code TEXT, country TEXT, timezone TEXT, latitude TEXT,
		longitude TEXT, zoom TEXT, alternate_id TEXT, notes TEXT)');
	$db->exec("INSERT INTO sites (id,name) VALUES (1,'Old Site'),(2,'Other Site')");
	$db->exec('CREATE TABLE graph_tree_items (id INTEGER PRIMARY KEY, graph_tree_id INTEGER, site_id INTEGER, parent INTEGER)');
	$db->exec('INSERT INTO graph_tree_items VALUES (1,1,1,0),(2,2,1,0),(3,1,2,0),(4,1,0,0)');
	$delete = str_starts_with($scenario, 'site-delete');
	if ($delete) {
		$request = ['drp_action' => '1', 'selected_items' => serialize([1])];
	} elseif ($scenario === 'site-notes') {
		$request['name'] = 'Old Site';
	}
	try {
		$delete ? form_actions() : form_save();
	} catch (Redirect $redirect) {
	}
	if ($scenario === 'site-delete') {
		check(db_fetch_cell_prepared('SELECT COUNT(*) FROM sites') === 1 && db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_tree_items') === 2, 'Site delete left tree entries or removed unrelated entries');
		check(db_fetch_cell_prepared('SELECT site_id FROM host WHERE id = 7') === '0' && isset($settings['time_last_change_branch']), 'Site delete left host assignment or tree cache stale');
	} elseif ($scenario === 'site-save-failure' || $scenario === 'site-delete-failure') {
		check(db_fetch_cell_prepared('SELECT name FROM sites WHERE id = 1') === 'Old Site' && !$settings && !$titles, 'Failed site write changed related state');
	} else {
		check(db_fetch_cell_prepared('SELECT name FROM sites WHERE id = 1') === $request['name'], 'Site rename was not saved');
	}
	if ($scenario === 'site-rename' || $scenario === 'site-delete') {
		check($titles === ['data' => [101, 102, 103], 'graph' => [201, 202, 203]], 'Site change skipped dependent titles or refreshed unrelated hosts');
	}
	if ($scenario === 'site-notes') {
		check(!$titles, 'Notes-only save unnecessarily refreshed titles');
	}
	if ($scenario !== 'site-save-failure' && $scenario !== 'site-delete-failure') {
		check(isset($settings['time_last_change_site'], $settings['time_last_change_site_device']), 'Site cache was not invalidated');
	}
}
echo json_encode(['scenario' => $scenario, 'ok' => true], JSON_THROW_ON_ERROR), "\n";
