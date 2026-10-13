<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
$root     = dirname(__DIR__, 2);
$scenario = $argv[1] ?? 'integration-drain';
require __DIR__ . '/PollerHandoffDatabase.php';
$state = ['rows' => [], 'queries' => 0, 'mapping_queries' => 0, 'delete_calls' => 0, 'boost_calls' => 0];
function probe_load(string $path, string $name) : void {
	$tokens  = token_get_all(file_get_contents($path));
	$code    = '';
	$capture = false;
	$depth   = 0;
	$started = false;

	foreach ($tokens as $i => $token) {
		if (!$capture && is_array($token) && $token[0] === T_FUNCTION) {
			$j = $i + 1;

			while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
				$j++;
			}
			$capture = is_array($tokens[$j]) && $tokens[$j][1] === $name;
		}

		if (!$capture) {
			continue;
		}
		$code .= is_array($token) ? $token[1] : $token;

		if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
			$depth++;
			$started = true;
		}

		if ($token === '}' && --$depth === 0 && $started) {
			eval($code);

			return;
		}
	}

	throw new RuntimeException('Missing production function: ' . $name);
}

function db_fetch_assoc_prepared($sql, $params = []) {
	return handoff_fetch($sql, $params);
}
function db_fetch_cell($sql) {
	return 0;
}
function db_fetch_cell_prepared($sql, $params = []) {
	return array_values(handoff_fetch($sql, $params)[0] ?? [0])[0];
}
function db_fetch_row_prepared($sql, $params = []) {
	return handoff_fetch($sql, $params)[0] ?? [];
}
function db_execute($sql, ...$args) {
	return handoff_execute($sql);
}
function db_execute_prepared($sql, $params = [], ...$args) {
	return handoff_execute($sql, $params);
}
function cacti_log(...$args) {
}
function cacti_system_zone_set() {
}
function boost_error_handler(...$args) {
	return false;
}
function boost_timer(...$args) {
}
function boost_get_arch_table_names(...$args) {
	return str_starts_with($GLOBALS['scenario'], 'integration-scheduled-') ? ['poller_output_boost_arch_1'] : [];
}
function boost_debug(...$args) {
}
function get_installed_rrdtool_version(...$args) {
	return '1.8';
}
function rrd_init(...$args) {
	return null;
}
function rrd_close(...$args) {
	rrdtool_acknowledged_close();
}
function get_rrdtool_version(...$args) {
	return '1.8';
}
function cacti_version_compare($a, $b, $op) {
	return version_compare($a, $b, $op);
}
function read_config_option($key) {
	return match ($key) {
		'path_rrdtool'                            => $GLOBALS['probe_rrd_binary'],
		'boost_rrd_update_max_records_per_select' => '100',
		'boost_rrd_update_string_length'          => $GLOBALS['scenario'] === 'integration-drain-middle-failure' ? '1' : '10000',
		default                                   => ''
	};
}
function cacti_rrdtool_valid_path($path) {
	return true;
}
function cacti_rrdtool_valid_ds_template($template) {
	return true;
}
function cacti_escapeshellarg($value) {
	return escapeshellarg($value);
}
function cacti_session_close() {
}
function rrdtool_set_language(...$args) {
}
function rrdtool_reset_language() {
}
function rrdtool_command_log_context($command) {
	return $command;
}
function rrdtool_execute(...$args) {
	return __rrd_execute(...$args);
}

foreach (['SQL_NO_CACHE'=>'', 'BOOST_TIMER_START'=>0, 'BOOST_TIMER_END'=>1, 'POLLER_VERBOSITY_DEBUG'=>5, 'POLLER_VERBOSITY_MEDIUM'=>3, 'POLLER_VERBOSITY_HIGH'=>4, 'POLLER_VERBOSITY_NONE'=>0, 'RRDTOOL_OUTPUT_STDOUT'=>1, 'RRDTOOL_OUTPUT_BOOLEAN'=>2, 'RRDTOOL_OUTPUT_STDERR'=>3, 'RRDTOOL_OUTPUT_RETURN_STDERR'=>4, 'RRDTOOL_OUTPUT_GRAPH_DATA'=>5, 'RRDTOOL_OUTPUT_NULL'=>6] as $name=>$value) {
	define($name,$value);
}
define('RRD_NL', " \
");

foreach (['cacti_sizeof', 'cacti_count', 'array_rekey', 'is_hexadecimal', 'cacti_has_control_chars'] as $name) {
	probe_load($root . '/lib/functions.php',$name);
}

foreach (['__rrd_execute', 'rrdtool_trim_output', 'escape_command', 'rrdtool_execute_acknowledged', 'rrdtool_acknowledged_close'] as $name) {
	probe_load($root . '/lib/rrd.php',$name);
}

foreach (['boost_rrdtool_function_update', 'boost_process_poller_output', 'boost_get_rrd_filename_and_template', 'boost_get_unused_data_source_names', 'boost_get_input_field_names'] as $name) {
	probe_load($root . '/lib/boost.php',$name);
}
probe_load($root . '/lib/boost.php', 'boost_limit_complete_timestamp_page');
probe_load($root . '/poller_boost.php', 'boost_process_output');
probe_load($root . '/poller_boost.php', 'boost_process_local_data_ids');
$config = ['library_path'=>__DIR__ . '/../performance/fixtures', 'cacti_server_os'=>'unix', 'is_web'=>false];
$debug  = $get_memory = false;
$time   = time() - 60;
handoff_seed($config,$state,$time);
handoff_execute('INSERT INTO poller_output_boost SELECT * FROM poller_output');
handoff_execute('DELETE FROM poller_output');
$state['delete_calls'] = 0;
$create_args           = [getenv('RRDTOOL_TEST_BINARY'), 'create', $handoff_rrd, '--start', (string)($time - 60), '--step', '60', 'DS:in:GAUGE:120:U:U', 'RRA:AVERAGE:0.5:1:10'];

if ($scenario === 'integration-drain-multi-field') {
	handoff_execute('INSERT INTO data_template_rrd VALUES (2,1,2,"out",2,120,"U","U",1)');
	handoff_execute('INSERT INTO graph_templates_item VALUES (2)');
	handoff_execute('INSERT INTO poller_item VALUES (1,"out",?,2,1)', [$handoff_rrd]);
	handoff_execute('INSERT INTO poller_output_boost VALUES (1,"in",FROM_UNIXTIME(?),"43"),(1,"out",FROM_UNIXTIME(?),"44")', [$time + 10, $time + 10]);
	array_splice($create_args, -1, 0, ['DS:out:GAUGE:120:U:U']);
}
$process               = proc_open($create_args,[1=>['pipe', 'w'], 2=>['redirect', 1]],$pipes);
$output                = stream_get_contents($pipes[1]);
fclose($pipes[1]);

if (proc_close($process) !== 0) {
	throw new RuntimeException($output);
}

if ($scenario === 'integration-drain-middle-failure') {
	handoff_execute('INSERT INTO poller_output_boost VALUES (1,"in",FROM_UNIXTIME(?),"43")',[$time + 10]);
	handoff_execute('INSERT INTO poller_output_boost VALUES (1,"in",FROM_UNIXTIME(?),"44")',[$time + 20]);
}
$crash = $handoff_dir . '/crashed-rrd';
file_put_contents($crash, '#!' . PHP_BINARY . "\n<?php fgets(STDIN); exit(1);\n");
chmod($crash,0700);

if ($scenario === 'integration-drain-middle-failure') {
	$script = '#!' . PHP_BINARY . "\n<?php\n" . '$real = ' . var_export(getenv('RRDTOOL_TEST_BINARY'), true) . ';'
		. '$process = proc_open([$real,"-"], [0=>["pipe","r"],1=>["pipe","w"],2=>["redirect",1]],$pipes);'
		. '$count=0; while ($command=fgets(STDIN)) { if (++$count===2) exit(1); fwrite($pipes[0],$command); fflush($pipes[0]); echo fgets($pipes[1]); fflush(STDOUT); }';
	file_put_contents($crash, $script);
}

if (in_array($scenario,['integration-drain-crash', 'integration-drain-middle-failure'],true)) {
	$probe_rrd_binary = $crash;
}
$scheduled = str_starts_with($scenario, 'integration-scheduled-');

if ($scheduled) {
	handoff_execute('CREATE TABLE poller_output_boost_arch_1 LIKE poller_output_boost');
	handoff_execute('CREATE TABLE poller_output_boost_local_data_ids (local_data_id INT PRIMARY KEY, run_id VARCHAR(64), process_handler INT, cursor_time DATETIME NULL, cursor_rrd_name VARCHAR(30)) ENGINE=InnoDB');
	handoff_execute('INSERT INTO poller_output_boost_arch_1 SELECT * FROM poller_output_boost');
	handoff_execute('DELETE FROM poller_output_boost');
	handoff_execute('INSERT INTO poller_output_boost_local_data_ids VALUES (1, "test-run", 1, NULL, "")');

	if ($scenario === 'integration-scheduled-mapping-retry') {
		handoff_execute('DELETE FROM poller_data_template_field_mappings');
	}
	$run_id           = 'test-run';
	$max_run_duration = 30;
	$archive_table    = false;
	$current_lock     = false;

	if ($scenario === 'integration-scheduled-crash') {
		$probe_rrd_binary = $crash;
	}
}
$first            = $scheduled ? boost_process_local_data_ids(1, null, 2) : boost_process_poller_output(1);
$first_cursor     = $scheduled ? handoff_fetch('SELECT cursor_time FROM poller_output_boost_local_data_ids')[0]['cursor_time'] : null;
$after            = handoff_fetch('SELECT output FROM poller_output_boost');
$first_last       = handoff_verdict()['lastupdate'];
$probe_rrd_binary = getenv('RRDTOOL_TEST_BINARY');

if ($scenario === 'integration-drain-multi-field') {
	handoff_execute('INSERT INTO poller_output_boost VALUES (1,"out",FROM_UNIXTIME(?),"41")', [$time]);
}
$second           = $scheduled ? boost_process_local_data_ids(1, null, 2) : boost_process_poller_output(1);
$second_cursor    = $scheduled ? handoff_fetch('SELECT cursor_time FROM poller_output_boost_local_data_ids')[0]['cursor_time'] : null;

if ($scheduled) {
	boost_process_local_data_ids(1, null, 2);
}
print json_encode(['first_cursor'=>$first_cursor, 'second_cursor'=>$second_cursor, 'mapping_queries'=>$state['mapping_queries'], 'first'=>$first, 'second'=>$second, 'retained'=>count($after), 'first_last'=>$first_last, 'handoff'=>handoff_verdict()],JSON_THROW_ON_ERROR);
