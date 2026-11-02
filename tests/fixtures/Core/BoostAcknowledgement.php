<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/include/global_constants.php';
if (!defined('CACTI_LOCALE')) { define('CACTI_LOCALE', 'en-US'); }
$GLOBALS['config'] = ['cacti_server_os' => PHP_OS_FAMILY === 'Windows' ? 'win32' : 'unix', 'library_path' => dirname(__DIR__, 3) . '/lib', 'include_path' => dirname(__DIR__, 3) . '/include', 'is_web' => false];
$GLOBALS['ack_binary'] = getenv('RRDTOOL_BINARY') ?: (is_executable('/usr/bin/rrdtool') ? '/usr/bin/rrdtool' : '/opt/homebrew/bin/rrdtool');
function read_config_option($name) { if ($name === 'path_rrdtool') { return $GLOBALS['ack_binary']; } if ($name === 'boost_rrd_update_string_length') { return 10000; } if ($name === 'extended_paths') { return $GLOBALS['ack_extended_paths'] ?? ''; } return ''; }
function __($message) { return $message; }
function cacti_log(...$args) {}
function boost_timer(...$args) {}
function get_rrdtool_version() { return '1.7'; }
function cacti_version_compare(...$args) { return version_compare(...$args); }
function cacti_sizeof($value) { return is_array($value) ? count($value) : 0; }
require_once dirname(__DIR__, 3) . '/lib/rrd.php';
foreach (['cacti_has_control_chars', 'cacti_rrdtool_valid_path', 'cacti_rrdtool_valid_ds_name', 'cacti_rrdtool_valid_ds_template', 'cacti_rrdtool_valid_bound'] as $function) {
    \cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/functions.php', $function, __NAMESPACE__);
}
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/boost.php', 'boost_rrdtool_function_create', __NAMESPACE__);
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/boost.php', 'boost_rrdtool_function_update', __NAMESPACE__);
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/poller_boost.php', 'boost_process_output', __NAMESPACE__);
function cacti_count($value) { return count($value); }
function cacti_system_zone_set() {}
function boost_error_handler(...$arguments) { return false; }
function boost_get_arch_table_names(...$arguments) { return $GLOBALS['ack_archives']; }
function boost_get_unused_data_source_names(...$arguments) { return []; }
function boost_get_rrd_filename_and_template($local_data_id) { return ['rrd_path' => $GLOBALS['ack_rrd_path'], 'rrd_template' => $GLOBALS['ack_templates'][$local_data_id] ?? $GLOBALS['ack_template']]; }
function get_data_source_path($local_data_id, $expand = false) { return $GLOBALS['ack_create_path']; }
function get_data_source_item_name($id) { return 'value'; }
function get_installed_rrdtool_version() { return '1.7'; }
function array_rekey($rows, $key, $value) { $result = []; foreach ($rows as $row) { $result[$row[$key]] = is_array($value) ? array_intersect_key($row, array_flip($value)) : $row[$value]; } return $result; }
function db_fetch_assoc_prepared($sql, $params = []) { $statement = $GLOBALS['ack_db']->prepare($sql); $statement->execute($params); return $statement->fetchAll(\PDO::FETCH_ASSOC); }
function db_fetch_cell($sql) { if (str_contains($sql, 'poller_time')) { return 1800000000; } return $GLOBALS['ack_db']->query($sql)->fetchColumn(); }
function db_fetch_cell_prepared($sql, $params = []) { $statement = $GLOBALS['ack_db']->prepare($sql); $statement->execute($params); return $statement->fetchColumn(); }
function db_execute_prepared($sql, $params = [], ...$arguments) { $statement = $GLOBALS['ack_db']->prepare($sql); return $statement->execute($params); }
if (!defined('SQL_NO_CACHE')) { define('SQL_NO_CACHE', ''); }
foreach (['boost_process_poller_output', 'boost_limit_complete_timestamp_page'] as $function) { cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/boost.php', $function, ''); }
cacti_fixture_source_function(dirname(__DIR__, 3) . '/poller_boost.php', 'boost_process_local_data_ids', '');
$GLOBALS['ack_db'] = new \PDO('sqlite::memory:');
$GLOBALS['ack_db']->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$GLOBALS['ack_db']->sqliteCreateFunction('CONCAT', fn (...$parts) => implode('', $parts), -1);
$GLOBALS['ack_db']->sqliteCreateFunction('UNIX_TIMESTAMP', fn($value) => (int) $value);
$GLOBALS['ack_db']->sqliteCreateFunction('FROM_UNIXTIME', fn($value) => (string) $value);
$GLOBALS['ack_db']->exec('CREATE TABLE poller_output_boost (local_data_id INTEGER, time TEXT, rrd_name TEXT, output TEXT); CREATE TABLE data_local (id INTEGER, data_template_id INTEGER); INSERT INTO data_local VALUES (1,1),(2,1); CREATE TABLE data_template_data (local_data_id INTEGER, data_source_profile_id INTEGER, rrd_step INTEGER); INSERT INTO data_template_data VALUES (1,1,60); CREATE TABLE data_source_profiles (id INTEGER, x_files_factor REAL); INSERT INTO data_source_profiles VALUES (1,0.5); CREATE TABLE data_source_profiles_rra (data_source_profile_id INTEGER, steps INTEGER, rows INTEGER); INSERT INTO data_source_profiles_rra VALUES (1,1,100); CREATE TABLE data_source_profiles_cf (data_source_profile_id INTEGER, consolidation_function_id INTEGER); INSERT INTO data_source_profiles_cf VALUES (1,1); CREATE TABLE data_template_rrd (id INTEGER, data_source_name TEXT, rrd_heartbeat INTEGER, rrd_minimum TEXT, rrd_maximum TEXT, data_source_type_id INTEGER, local_data_id INTEGER, local_data_template_rrd_id INTEGER); INSERT INTO data_template_rrd VALUES (1,"value",120,"0","U",1,1,1); CREATE TABLE graph_templates_item (task_item_id INTEGER); INSERT INTO graph_templates_item VALUES (1); CREATE TABLE poller_output_boost_local_data_ids (local_data_id INTEGER, run_id TEXT, process_handler INTEGER, cursor_time TEXT, cursor_rrd_name TEXT); INSERT INTO poller_output_boost_local_data_ids VALUES (1,"fixture",1,NULL,NULL); CREATE TABLE poller_data_template_field_mappings (data_template_id INTEGER, data_name TEXT, data_source_names TEXT); INSERT INTO poller_data_template_field_mappings VALUES (1,"value","value")');
$GLOBALS['ack_archives'] = ['poller_output_boost'];
$GLOBALS['run_id'] = 'fixture';
$GLOBALS['current_lock'] = false;
$file = tempnam(sys_get_temp_dir(), 'boost-ack-');
unlink($file);
$create_directory = sys_get_temp_dir() . '/boost-create-' . bin2hex(random_bytes(8));
mkdir($create_directory, 0775);
$fixture_include = $create_directory . '/include';
mkdir($fixture_include, 0775);
file_put_contents($fixture_include . '/global_arrays.php', '<?php $data_source_types = array(1 => "GAUGE"); $consolidation_functions = array(1 => "AVERAGE");');
$GLOBALS['config']['rra_path'] = $create_directory;
$GLOBALS['config']['include_path'] = $fixture_include;
$pipe = rrd_init(true, true);
if (!is_resource($pipe)) { throw new \RuntimeException('RRDtool worker did not start.'); }
$context = stream_context_get_options($pipe)['cacti'];
$pid = proc_get_status($context['rrd_process'])['pid'];
try {
	$GLOBALS['ack_extended_paths'] = 'on';
	$GLOBALS['ack_create_path'] = $create_directory . '/created/1.rrd';
	$create_sample = '1700000001:1';
	$create_result = boost_rrdtool_function_update(1, $GLOBALS['ack_create_path'], 'value', $create_sample, $pipe);
	if ($create_result !== 'OK' || !is_file($GLOBALS['ack_create_path'])) { throw new \RuntimeException('Boost did not create and update a missing structured RRD.'); }
	$GLOBALS['ack_extended_paths'] = '';
	$GLOBALS['ack_create_path'] = $create_directory . '/missing/1.rrd';
	$create_error_sample = '1700000002:2';
	$create_error = boost_rrdtool_function_update(1, $GLOBALS['ack_create_path'], 'value', $create_error_sample, $pipe);
	if (!str_starts_with($create_error, 'ERROR:') || $create_error === 'ERROR: Unable to create RRD file') { throw new \RuntimeException('Boost hid the RRDtool create failure: ' . $create_error); }
    $create = rrdtool_execute('create ' . $file . ' --start 1700000000 --step 1 DS:value:GAUGE:600:U:U RRA:AVERAGE:0.5:1:2000', false, RRDTOOL_OUTPUT_RETURN_STDERR, $pipe);
    if (!str_starts_with($create, 'OK ')) { throw new \RuntimeException('RRD fixture creation failed: ' . $create); }
    $missing = false;
    $sample = '1700000001:1';
    if (!str_starts_with(boost_rrdtool_function_update(1, $file, 'value', $sample, $missing), 'ERROR:')) { throw new \RuntimeException('Missing worker used a per-update process fallback.'); }
    $bad = '1700000001:1';
    $refused = boost_rrdtool_function_update(1, $file, 'unknown', $bad, $pipe);
    if (!str_starts_with($refused, 'ERROR:')) { throw new \RuntimeException('An RRDtool refusal was acknowledged.'); }
    if (boost_process_output(1, [[1700000001, 1]], $file, ['unknown' => 0], $pipe)) { throw new \RuntimeException('Scheduled flush accepted an RRDtool refusal.'); }
    $start = hrtime(true);
    for ($i = 1; $i <= 1000; $i++) {
        $value = (1700000000 + $i) . ':1';
        if (boost_rrdtool_function_update(1, $file, 'value', $value, $pipe) !== 'OK') { throw new \RuntimeException('Valid update was refused.'); }
    }
    if (rrdtool_execute('last ' . $file, false, RRDTOOL_OUTPUT_BOOLEAN, $pipe) !== true) { throw new \RuntimeException('Boolean response mode lost acknowledgement.'); }
    rrdtool_execute('last ' . $file, false, RRDTOOL_OUTPUT_NULL, $pipe);
    if (trim(rrdtool_execute('last ' . $file, false, RRDTOOL_OUTPUT_STDOUT, $pipe)) !== '1700001000') { throw new \RuntimeException('Output modes desynchronized the worker.'); }
    $elapsed = (hrtime(true) - $start) / 1e9;
    if (proc_get_status($context['rrd_process'])['pid'] !== $pid) { throw new \RuntimeException('RRDtool worker was replaced during updates.'); }
    if (!boost_process_output(1, [[1700001001, 2]], $file, ['value' => 0], $pipe)) { throw new \RuntimeException('Scheduled valid flush failed.'); }
    $GLOBALS['ack_rrd_path'] = $file;
    foreach (['unknown', 'value'] as $template) {
        $GLOBALS['ack_template'] = $template;
        $GLOBALS['ack_db']->exec('DELETE FROM poller_output_boost');
        db_execute_prepared('INSERT INTO poller_output_boost VALUES (1,?,?,?)', ['1700001002', $template, '3']);
        $result = boost_process_local_data_ids(1, $pipe, 100);
        $cursor = $GLOBALS['ack_db']->query('SELECT cursor_time FROM poller_output_boost_local_data_ids')->fetchColumn();
        if (($template === 'unknown' && ($result !== -1 || $cursor !== null)) || ($template === 'value' && ($result !== 1 || $cursor !== '1700001002'))) { throw new \RuntimeException('Scheduled cursor acknowledgement boundary failed.'); }
        $GLOBALS['ack_db']->exec('DELETE FROM poller_output_boost');
        db_execute_prepared('INSERT INTO poller_output_boost VALUES (1,?,?,?)', ['1700001003', $template, '4']);
        $GLOBALS['ack_archives'] = [];
        $result = boost_process_poller_output(1, $template === 'value' ? false : $pipe);
        $remaining = (int) $GLOBALS['ack_db']->query('SELECT COUNT(*) FROM poller_output_boost')->fetchColumn();
        if (($template === 'unknown' && ($result !== -1 || $remaining !== 1)) || ($template === 'value' && ($result !== 1 || $remaining !== 0))) { throw new \RuntimeException('On-demand queue acknowledgement boundary failed.'); }
    }
	$GLOBALS['ack_db']->exec('DELETE FROM poller_output_boost; DELETE FROM poller_output_boost_local_data_ids; INSERT INTO poller_output_boost_local_data_ids VALUES (1,"fixture",1,NULL,NULL),(2,"fixture",1,NULL,NULL)');
	$GLOBALS['ack_templates'] = [1 => 'unknown', 2 => 'value'];
	db_execute_prepared('INSERT INTO poller_output_boost VALUES (1,?,?,?),(2,?,?,?)', ['1700001010', 'unknown', '5', '1700001011', 'value', '6']);
	$result = boost_process_local_data_ids(1, $pipe, 100);
	$cursors = $GLOBALS['ack_db']->query('SELECT local_data_id, cursor_time FROM poller_output_boost_local_data_ids ORDER BY local_data_id')->fetchAll(\PDO::FETCH_KEY_PAIR);
	$partial_progress = $result === -1 && $cursors[1] === null && $cursors[2] === '1700001011';
	if (!$partial_progress) { throw new \RuntimeException('A failed data source prevented an acknowledged peer cursor from advancing.'); }
} finally {
    rrd_close($pipe);
    if (is_file($file)) { unlink($file); }
	if (is_file($create_directory . '/created/1.rrd')) { unlink($create_directory . '/created/1.rrd'); }
	if (is_dir($create_directory . '/created')) { rmdir($create_directory . '/created'); }
	if (is_file($fixture_include . '/global_arrays.php')) { unlink($fixture_include . '/global_arrays.php'); }
	if (is_dir($fixture_include)) { rmdir($fixture_include); }
	if (is_dir($create_directory)) { rmdir($create_directory); }
}
// A live worker that never answers must fail closed and remain failed.
$stalled = rrd_init(true, true);
try {
    if (__rrd_read_acknowledgement($stalled, 0.01) !== false || rrdtool_execute('info unused', false, RRDTOOL_OUTPUT_RETURN_STDERR, $stalled) !== false) { throw new \RuntimeException('Missing acknowledgement did not fail closed.'); }
} finally { rrd_close($stalled); }
print json_encode(['refused' => true, 'updates' => 1000, 'worker_processes' => 1, 'create_error' => $create_error, 'partial_progress' => $partial_progress, 'timeout_closed' => true]);
