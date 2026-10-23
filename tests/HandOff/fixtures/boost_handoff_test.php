<?php
/* Standalone behavioral regression tests: real patched functions, in-memory DB doubles.
 * Run: php tests/HandOff/fixtures/boost_handoff_test.php
 * This does not substitute for testing on MariaDB with live Spine writers.
 */
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
const POLLER_VERBOSITY_HIGH = 3;
const SQL_NO_CACHE = '';
$config = array('poller_id' => 1, 'connection' => 'online', 'library_path' => __DIR__ . '/boost-handoff');
$debug = false;
$remote_db_cnn_id = false;
$lib_path = $argv[1] ?? dirname(__DIR__, 3) . '/lib';
require $lib_path . '/poller.php';
require $lib_path . '/boost.php';
function cacti_sizeof($v) { return is_array($v) ? count($v) : 0; }
function read_config_option($k) { return $GLOBALS['settings'][$k] ?? ''; }
function set_config_option($k, $v) { $GLOBALS['settings'][$k] = $v; }
function db_qstr($v, $conn = false) { return "'" . str_replace("'", "''", $v) . "'"; }
function cacti_log($message, ...$args) { $GLOBALS['logs'][] = $message; }
function array_rekey($rows, $key, $value) { return array(); }
function dsstats_poller_output($rows) { $GLOBALS['stats'] = $rows; }
function dsdebug_poller_output($rows) {}
function api_plugin_hook_function($name, $rows) { $GLOBALS['plugin_rows'] = $rows; }
function rrdtool_function_update($rows, &$pipe, $unused) {
    $GLOBALS['inline_calls']++;
    return count(array_filter($rows, function($r) { return !empty($r['times']); }));
}
function db_fetch_assoc($sql) {
    if (strpos($sql, 'SELECT po.output') !== false) {
        $GLOBALS['process_selects']++;
        if ($GLOBALS['process_select_limit'] && $GLOBALS['process_selects'] > $GLOBALS['process_select_limit']) {
            throw new Exception('processing recursed without progress');
        }
        return array_values(array_filter($GLOBALS['pending'], function($row) {
            return !isset($GLOBALS['deleted_sources'][$row['local_data_id']]);
        }));
    }
    throw new Exception('Unexpected SELECT: ' . $sql);
}
function db_fetch_assoc_prepared($sql, $params = array(), ...$args) {
    if (strpos($sql, 'poller output housekeeping') !== false) {
        $GLOBALS['housekeeping_queries']++;
        if ($GLOBALS['housekeeping_fail']) { return false; }
        $groups = array();
        foreach ($GLOBALS['pending'] as $r) {
            $group = $r['local_data_id'] . ':' . $r['time'];
            $groups[$group]['fields'][$r['rrd_name']] = true;
            $groups[$group]['expected'] = $r['rrd_num'];
        }
        $result = array();
        foreach ($GLOBALS['pending'] as $r) {
            $id = $r['local_data_id'];
            $group = $id . ':' . $r['time'];
            $deleted = isset($GLOBALS['deleted_sources'][$id]);
            if (!$deleted && ($r['poller_id'] ?? 1) != $params[0]) { continue; }
            if (!$deleted && !isset($GLOBALS['missing_poller_sources'][$id]) &&
                count($groups[$group]['fields']) === $groups[$group]['expected']) { continue; }
            if (count($params) > 1) {
                $cursor = array((int) $params[1], $params[3], $params[6]);
                if (compare_keys(array($id, $r['rrd_name'], $r['time']), $cursor) <= 0) { continue; }
            }
            $r['source_exists'] = $deleted ? null : $id;
            $r['template_name'] = $r['template_name'] ?? 'Errors template';
            $result[] = $r;
        }
        usort($result, function($a, $b) {
            return compare_keys(array($a['local_data_id'], $a['rrd_name'], $a['time']),
                array($b['local_data_id'], $b['rrd_name'], $b['time']));
        });
        $result = array_slice($result, 0, 40000);
        if ($GLOBALS['housekeeping_arrival']) {
            $r = $GLOBALS['housekeeping_arrival'];
            $GLOBALS['pending'][sample_key($r)] = $r;
            $GLOBALS['housekeeping_arrival'] = null;
        }
        return $result;
    }

    if (strpos($sql, 'poller_data_template_field_mappings') !== false) { return array(); }
    if (strpos($sql, 'SELECT DISTINCT local_data_id') !== false) {
        return array_map(function($id) { return array('local_data_id' => $id); }, array_slice($params, 1));
    }
    throw new Exception('Unexpected prepared SELECT: ' . $sql);
}
function compare_keys($a, $b) {
    return ($a[0] <=> $b[0]) ?: (strcmp($a[1], $b[1]) ?: strcmp($a[2], $b[2]));
}
function db_fetch_row($sql, ...$args) { return array('Value' => 1024); }
function db_fetch_cell($sql) {
    if (strpos($sql, 'FROM poller_output') !== false) { return count($GLOBALS['pending']); }
    if (strpos($sql, 'FROM poller_time') !== false) { return $GLOBALS['status_fail'] ? false : $GLOBALS['running']; }
    throw new Exception('Unexpected scalar SELECT');
}
function sample_key($row) { return $row['local_data_id'] . '|' . $row['rrd_name'] . '|' . $row['time']; }
function db_execute($sql, ...$args) {
    if (strpos($sql, 'INSERT IGNORE INTO poller_output_boost') === 0) {
        $GLOBALS['events'][] = 'stage';
        $GLOBALS['insert_calls']++;
        if ($GLOBALS['insert_calls'] === $GLOBALS['fail_insert']) { return false; }
        preg_match_all("/\((\d+),'([^']*)','([^']*)','([^']*)'\)/", $sql, $matches, PREG_SET_ORDER);
        $GLOBALS['affected'] = 0;
        foreach ($matches as $m) {
            $r = array('local_data_id' => (int) $m[1], 'rrd_name' => $m[2], 'time' => $m[3], 'output' => $m[4]);
            $key = sample_key($r);
            if (!isset($GLOBALS['boost'][$key])) { $GLOBALS['boost'][$key] = $r; $GLOBALS['affected']++; }
        }
        if ($GLOBALS['arrival']) {
            $r = $GLOBALS['arrival']; $GLOBALS['pending'][sample_key($r)] = $r; $GLOBALS['arrival'] = null;
        }
        return true;
    }
    if (strpos($sql, 'DELETE FROM poller_output WHERE local_data_id IN (') === 0) {
        $GLOBALS['events'][] = 'legacy-delete';
        preg_match('/IN \(([^)]*)\)/', $sql, $m);
        $ids = array_map('intval', explode(',', $m[1]));
        foreach ($GLOBALS['pending'] as $key => $r) {
            if (in_array($r['local_data_id'], $ids, true)) { unset($GLOBALS['pending'][$key]); }
        }
        return true;
    }
    throw new Exception('Unexpected mutation: ' . $sql);
}
function db_execute_prepared($sql, $params = array(), ...$args) {
    if (strpos($sql, 'DELETE FROM poller_output WHERE ') !== 0) { throw new Exception('Unexpected prepared mutation'); }
    $GLOBALS['events'][] = 'delete';
    $GLOBALS['delete_calls']++;
    check(count($params) <= 1000, 'cleanup batch exceeds bound');
    if ($GLOBALS['fail_delete'] || $GLOBALS['fail_delete_at'] === $GLOBALS['delete_calls']) { return false; }
    foreach (array_chunk($params, 4) as $values) {
        $key = implode('|', array_slice($values, 0, 3));
        if (isset($GLOBALS['pending'][$key]) && $GLOBALS['pending'][$key]['output'] === $values[3]) {
            unset($GLOBALS['pending'][$key]);
        }
    }
    return true;
}
function db_affected_rows($conn = false) { return $GLOBALS['affected']; }
function row($field, $time = '2026-10-22 14:55:02', $id = 12238, $expected = 4, $output = '0') {
    return array('local_data_id' => $id, 'rrd_name' => $field, 'time' => $time,
        'unix_time' => strtotime($time), 'output' => $output, 'data_template_id' => 1,
        'rrd_path' => '/rra/' . $id . '.rrd', 'rrd_num' => $expected);
}
function reset_case($rows) {
    $GLOBALS['pending'] = array();
    foreach ($rows as $r) { $GLOBALS['pending'][sample_key($r)] = $r; }
    $GLOBALS['boost'] = $GLOBALS['logs'] = $GLOBALS['events'] = $GLOBALS['stats'] = array();
    $GLOBALS['settings'] = array('boost_rrd_update_enable' => 'on', 'boost_rrd_update_system_enable' => 'on', 'boost_redirect' => '');
    $GLOBALS['fail_insert'] = $GLOBALS['insert_calls'] = $GLOBALS['delete_calls'] = $GLOBALS['inline_calls'] = $GLOBALS['affected'] = 0;
    $GLOBALS['fail_delete'] = false; $GLOBALS['arrival'] = null;
    $GLOBALS['config']['poller_id'] = 1;
    $GLOBALS['running'] = 1;
    $GLOBALS['process_selects'] = $GLOBALS['process_select_limit'] = 0;
    $GLOBALS['status_fail'] = $GLOBALS['housekeeping_fail'] = false;
    $GLOBALS['housekeeping_queries'] = $GLOBALS['fail_delete_at'] = 0;
    $GLOBALS['deleted_sources'] = $GLOBALS['missing_poller_sources'] = array();
    $GLOBALS['housekeeping_arrival'] = null;
}
function check($v, $message) { if (!$v) { throw new Exception($message); } }
function run_pass() { $pipe = null; return process_poller_output($pipe, 1); }
function passed($name) { echo "PASS: $name\n"; }
$fields = array('discards_in', 'discards_out', 'errors_in', 'errors_out');
$full = array_map('row', $fields);
reset_case(array_slice($full, 1));
run_pass(); run_pass();
check(count($pending) === 3 && $insert_calls === 0 && $delete_calls === 0, 'partial batch was staged or deleted');
foreach ($full as $r) { $pending[sample_key($r)] = $r; }
run_pass();
check(count($pending) === 0 && count($boost) === 4, 'completed batch did not drain');
check(count($stats['/rra/12238.rrd']['times'][strtotime('2026-10-22 14:55:02')]) === 4, 'statistics lost fields');
check($events === array('stage', 'delete') && !$inline_calls, 'wrong handoff order');
passed('partial samples wait; completion stages once and preserves statistics');
reset_case($full); $arrival = row('discards_in', '2026-10-22 15:00:02'); run_pass();
check(count($pending) === 1 && isset($pending['12238|discards_in|2026-10-22 15:00:02']), 'new timestamp was deleted');
passed('concurrent arrival with a new timestamp survives cleanup');
reset_case($full); $arrival = row('late_field', '2026-10-22 14:55:02'); run_pass();
check(count($pending) === 1 && isset($pending['12238|late_field|2026-10-22 14:55:02']), 'unselected field was deleted');
passed('unselected field at the same timestamp survives cleanup');
reset_case($full); $arrival = row('discards_in', '2026-10-22 14:55:02', 12238, 4, '9'); run_pass();
check(count($pending) === 1 && $pending['12238|discards_in|2026-10-22 14:55:02']['output'] === '9', 'changed sample value was deleted');
passed('concurrently changed value survives cleanup');
reset_case($full); $fail_insert = 1; run_pass();
check(count($pending) === 4 && !$delete_calls && !$inline_calls, 'failed staging deleted or wrote inline');
$fail_insert = 0; run_pass(); check(!count($pending) && count($boost) === 4, 'retry failed');
passed('failed staging retains all rows and succeeds on retry');
$many = array(); for ($i = 1; $i <= 251; $i++) { $many[] = row('v', '2026-10-22 14:55:02', $i, 1, str_repeat('7', 100)); }
reset_case($many); $fail_insert = 2; run_pass();
check(count($pending) === 251 && count($boost) > 0 && count($boost) < 251 && !$delete_calls, 'partial SQL failure lost retry data');
$fail_insert = 0; run_pass();
check(!count($pending) && count($boost) === 251 && $delete_calls === 2, 'chunked retry did not drain');
passed('partially successful SQL batches retry safely; cleanup is bounded');
reset_case($full); $fail_delete = true; run_pass();
check(count($pending) === 4 && count($boost) === 4 && !$inline_calls, 'cleanup failure lost data');
$fail_delete = false; run_pass(); check(!count($pending), 'cleanup retry failed');
passed('cleanup failure retains source rows for retry');
reset_case($full); $settings['boost_rrd_update_enable'] = ''; $settings['boost_rrd_update_system_enable'] = ''; run_pass();
check(!$insert_calls && $inline_calls === 1 && !count($pending), 'direct RRD path changed');
passed('Boost-disabled path still updates RRD directly');
reset_case($full); $settings['boost_redirect'] = 'on'; run_pass();
check(!$insert_calls && !$inline_calls && !count($pending), 'redirection contract changed');
passed('existing direct-redirection contract preserved');
reset_case($full); $config['poller_id'] = 2; run_pass();
check(count($boost) === 4 && !count($pending), 'remote ownership/handoff failed');
passed('remote-poller ownership validation and staging');
reset_case(array_slice($full, 1)); foreach ($full as $r) { $boost[sample_key($r)] = $r; }
run_pass(); check(count($pending) === 3 && !$insert_calls, 'historical partial rows were restaged');
passed('historical partial samples are retained without repeated staging');


// Cleanup is exercised with the production helper. Each explicit $checked variable
// represents a new poller process, avoiding cross-test static state in the hot path.
function cleanup($protected = array()) {
    $checked = false;
    poller_cleanup_idle_output($checked, $protected);
    return $checked;
}
function has_log($needle) {
    foreach ($GLOBALS['logs'] as $line) { if (strpos($line, $needle) !== false) { return true; } }
    return false;
}
reset_case(array_slice($full, 1));
check(!cleanup() && count($pending) === 3 && !$housekeeping_queries, 'cleanup ran while writer active');
passed('incomplete samples are retained while poller workers are active');
reset_case(array_slice($full, 1)); $running = 0; $status_fail = true;
check(!cleanup() && count($pending) === 3 && !$housekeeping_queries, 'failed status lookup treated as idle');
passed('failed worker-status lookup does not permit destructive cleanup');
reset_case(array_slice($full, 1)); $running = 0;
check(cleanup() && !count($pending), 'idle incomplete rows not purged');
check(has_log('There are 1 Data Sources not returning all data') && has_log("Data Template 'Errors template'"), 'incomplete warning missing');
passed('idle misaligned samples are purged with the incomplete-source warning');
$two_sources = array_slice($full, 1);
foreach (array_slice($full, 1) as $r) { $r['local_data_id'] = 12239; $two_sources[] = $r; }
reset_case($two_sources); $running = 0;
check(cleanup() && !count($pending) && has_log('There are 2 Data Sources'), 'only first source of a template was purged');
passed('every misaligned source of the same template is cleaned');
reset_case(array(row('v', '2026-10-22 14:55:02', 9, 1))); $running = 0; $deleted_sources[9] = true;
check(cleanup() && !count($pending) && !has_log('not returning all data'), 'deleted source was not independently cleaned');
passed('deleted sources are discarded without a false incomplete-source warning');
reset_case($two_sources); $running = 0;
$protected = array('12238:2026-10-22 14:55:02' => true);
check(cleanup($protected) && count($pending) === 3 && has_log('There are 1 Data Sources'), 'failed handoff was purged or unrelated rows kept');
foreach ($pending as $r) { check($r['local_data_id'] === 12238, 'wrong protected source'); }
passed('failed handoff groups survive while unrelated incomplete sources are purged');
$old_partial = array_map(function($r) { $r['time'] = '2026-10-22 14:50:02'; return $r; }, array_slice($full, 1));
reset_case(array_merge($full, $old_partial)); $running = 0;
check(cleanup() && count($pending) === 4, 'cleanup removed complete timestamp or retained incomplete one');
foreach ($pending as $r) { check($r['time'] === '2026-10-22 14:55:02', 'wrong timestamp retained'); }
passed('incomplete old timestamps are purged without touching complete handoffs');
reset_case(array_slice($full, 1)); $running = 0;
$housekeeping_arrival = row('errors_in', '2026-10-22 14:55:02', 12238, 4, '9');
check(cleanup() && count($pending) === 1 && $pending['12238|errors_in|2026-10-22 14:55:02']['output'] === '9', 'cleanup lost concurrent replacement');
passed('idle cleanup also preserves values changed after its SELECT');
reset_case(array_slice($full, 1)); $running = 0; $housekeeping_arrival = row('v', '2026-10-22 15:00:02', 12238, 1);
check(cleanup() && count($pending) === 1 && isset($pending['12238|v|2026-10-22 15:00:02']), 'cleanup removed an unselected arrival');
passed('idle cleanup does not delete rows arriving outside its snapshot');
reset_case(array_slice($full, 1)); $running = 0; $housekeeping_fail = true;
check(!cleanup() && count($pending) === 3 && !$delete_calls, 'failed inspection deleted rows');
passed('failed housekeeping SELECT preserves every queued sample');
reset_case(array_slice($full, 1)); $running = 0; $fail_delete = true;
check(!cleanup() && count($pending) === 3, 'failed housekeeping DELETE lost rows');
$fail_delete = false; check(cleanup() && !count($pending), 'cleanup could not retry');
passed('failed housekeeping DELETE remains retryable');
reset_case(array(row('unknown', '2026-10-22 14:55:02', 8, 1))); $running = 0; $missing_poller_sources[8] = true;
check(cleanup() && !count($pending) && has_log('not returning all data'), 'source with missing poller configuration was retained indefinitely');
passed('misaligned rows with missing poller configuration are cleaned');
$remote_partial = array_slice($full, 1);
foreach ($remote_partial as &$r) { $r['poller_id'] = 2; } unset($r);
reset_case($remote_partial); $running = 0;
check(cleanup() && count($pending) === 3, 'another collector rows were purged');
passed('idle cleanup is scoped to the current collector');
// Keep a group spanning cleanup DELETE chunks safe if a later chunk fails.
$wide = array(); for ($i = 0; $i < 251; $i++) { $wide[] = row('v' . $i, '2026-10-22 14:55:02', 99, 251); }
reset_case($wide); $running = 0; $fail_delete_at = 2; run_pass();
check(count($pending) === 1 && count($boost) === 251, 'failed cleanup tail was purged as incomplete');
check(!has_log('not returning all data'), 'failed cleanup was mislabeled incomplete');
passed('partially deleted acknowledged group is protected from idle misalignment cleanup');
// Full page of excluded failures must advance the cursor instead of looping forever.
$protected = array(); $scale_rows = array();
for ($i = 0; $i < 40001; $i++) {
    $time = date('Y-m-d H:i:s', strtotime('2026-10-20 00:00:00') + $i);
    $scale_rows[] = row('v', $time, 99, 2);
    $protected['99:' . $time] = true;
}
reset_case($scale_rows); $running = 0;
check(cleanup($protected) && count($pending) === 40001 && $housekeeping_queries === 2, 'protected page did not advance cursor');
passed('40,001 protected timestamps paginate without dropping rows or looping');
unset($scale_rows, $protected);
// No prior process-level cleanup ran while workers were active, except the test
// above, which did run cleanup. The following verifies the helper itself with a
// failed-complete source plus an unrelated orphan and partial timestamp.
reset_case(array_merge($full, array(row('v', '2026-10-22 14:55:02', 7, 2), row('v', '2026-10-22 14:55:02', 8, 1))));
$running = 0; $deleted_sources[8] = true;
check(cleanup(array('12238:2026-10-22 14:55:02' => true)) && count($pending) === 4, 'unrelated orphan/partial cleanup affected complete failed handoff');
passed('complete failed handoff coexists with independent orphan and incomplete cleanup');
// A full 40,000-row incomplete page used to recursively select itself forever.
$partial_page = array();
for ($i = 1; $i <= 40000; $i++) { $partial_page[] = row('v', '2026-10-22 14:55:02', $i, 2); }
reset_case($partial_page); $running = 0; $process_select_limit = 1; $pipe = null;
process_poller_output($pipe);
check($process_selects === 1 && !$insert_calls, 'incomplete full page recursed or staged');
check(cleanup() && !count($pending), 'full incomplete page could not be purged');
passed('40,000 incomplete rows terminate processing and remain eligible for idle cleanup');
echo "All 28 scenarios passed.\n";
