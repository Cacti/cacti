<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/include/global_constants.php';
if (!defined('CACTI_LOCALE')) { define('CACTI_LOCALE', 'en-US'); }
$GLOBALS['config'] = ['cacti_server_os' => PHP_OS_FAMILY === 'Windows' ? 'win32' : 'unix', 'library_path' => dirname(__DIR__, 3) . '/lib'];
$GLOBALS['ack_binary'] = getenv('RRDTOOL_BINARY') ?: (is_executable('/usr/bin/rrdtool') ? '/usr/bin/rrdtool' : '/opt/homebrew/bin/rrdtool');
function read_config_option($name) { return $name === 'path_rrdtool' ? $GLOBALS['ack_binary'] : ($name === 'boost_rrd_update_string_length' ? 10000 : ''); }
function cacti_log(...$args) {}
function boost_timer(...$args) {}
function get_rrdtool_version() { return '1.7'; }
function cacti_version_compare(...$args) { return version_compare(...$args); }
function cacti_sizeof($value) { return is_array($value) ? count($value) : 0; }
require_once dirname(__DIR__, 3) . '/lib/rrd.php';
foreach (['cacti_has_control_chars', 'cacti_rrdtool_valid_path', 'cacti_rrdtool_valid_ds_name', 'cacti_rrdtool_valid_ds_template'] as $function) {
    \cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/functions.php', $function, __NAMESPACE__);
}
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/boost.php', 'boost_rrdtool_function_update', __NAMESPACE__);
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/poller_boost.php', 'boost_process_output', __NAMESPACE__);
function cacti_count($value) { return count($value); }
function cacti_system_zone_set() {}
function boost_error_handler(...$arguments) { return false; }
function boost_get_arch_table_names(...$arguments) { return $GLOBALS['ack_archives']; }
function boost_get_unused_data_source_names(...$arguments) { return []; }
function boost_get_rrd_filename_and_template(...$arguments) { return ['rrd_path' => $GLOBALS['ack_rrd_path'], 'rrd_template' => $GLOBALS['ack_template']]; }
function get_installed_rrdtool_version() { return '1.7'; }
function array_rekey($rows, $key, $value) { $result = []; foreach ($rows as $row) { $result[$row[$key]] = is_array($value) ? array_intersect_key($row, array_flip($value)) : $row[$value]; } return $result; }
function db_fetch_assoc_prepared($sql, $params = []) { $statement = $GLOBALS['ack_db']->prepare($sql); $statement->execute($params); return $statement->fetchAll(\PDO::FETCH_ASSOC); }
function db_fetch_cell($sql) { if (str_contains($sql, 'poller_time')) { return 1800000000; } return $GLOBALS['ack_db']->query($sql)->fetchColumn(); }
function db_execute_prepared($sql, $params = [], ...$arguments) { $statement = $GLOBALS['ack_db']->prepare($sql); return $statement->execute($params); }
if (!defined('SQL_NO_CACHE')) { define('SQL_NO_CACHE', ''); }
foreach (['boost_process_poller_output', 'boost_limit_complete_timestamp_page'] as $function) { cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/boost.php', $function, ''); }
cacti_fixture_source_function(dirname(__DIR__, 3) . '/poller_boost.php', 'boost_process_local_data_ids', '');
$GLOBALS['ack_db'] = new \PDO('sqlite::memory:');
$GLOBALS['ack_db']->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$GLOBALS['ack_db']->sqliteCreateFunction('CONCAT', fn (...$parts) => implode('', $parts), -1);
$GLOBALS['ack_db']->sqliteCreateFunction('UNIX_TIMESTAMP', fn($value) => (int) $value);
$GLOBALS['ack_db']->sqliteCreateFunction('FROM_UNIXTIME', fn($value) => (string) $value);
$GLOBALS['ack_db']->exec('CREATE TABLE poller_output_boost (local_data_id INTEGER, time TEXT, rrd_name TEXT, output TEXT); CREATE TABLE data_local (id INTEGER, data_template_id INTEGER); INSERT INTO data_local VALUES (1,1); CREATE TABLE poller_output_boost_local_data_ids (local_data_id INTEGER, run_id TEXT, process_handler INTEGER, cursor_time TEXT, cursor_rrd_name TEXT); INSERT INTO poller_output_boost_local_data_ids VALUES (1,"fixture",1,NULL,NULL); CREATE TABLE poller_data_template_field_mappings (data_template_id INTEGER, data_name TEXT, data_source_names TEXT); INSERT INTO poller_data_template_field_mappings VALUES (1,"value","value")');
$GLOBALS['ack_archives'] = ['poller_output_boost'];
$GLOBALS['run_id'] = 'fixture';
$GLOBALS['current_lock'] = false;
$file = tempnam(sys_get_temp_dir(), 'boost-ack-');
unlink($file);
$pipe = rrd_init(true, true);
if (!is_resource($pipe)) { throw new \RuntimeException('RRDtool worker did not start.'); }
$context = stream_context_get_options($pipe)['cacti'];
$pid = proc_get_status($context['rrd_process'])['pid'];
try {
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
} finally {
    rrd_close($pipe);
    if (is_file($file)) { unlink($file); }
}
// A live worker that never answers must fail closed and remain failed.
$stalled = rrd_init(true, true);
try {
    if (__rrd_read_acknowledgement($stalled, 0.01) !== false || rrdtool_execute('info unused', false, RRDTOOL_OUTPUT_RETURN_STDERR, $stalled) !== false) { throw new \RuntimeException('Missing acknowledgement did not fail closed.'); }
} finally { rrd_close($stalled); }
print json_encode(['refused' => true, 'updates' => 1000, 'worker_processes' => 1, 'elapsed_seconds' => $elapsed, 'timeout_closed' => true]);
