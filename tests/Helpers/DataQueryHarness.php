<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

namespace Cacti\Test\DataQuery;

$root = getenv('CACTI_DQ_SOURCE') ?: dirname(__DIR__, 2);
$GLOBALS['dq_source_root'] = $root;
require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__) . '/Helpers/FixtureCoverage.php';
require_once $root . '/include/global_constants.php';
require_once $root . '/lib/database.php';

foreach (array('cacti_sizeof', 'cacti_count', 'cacti_object_hash', 'clean_up_lines') as $function) {
	if (!function_exists($function)) {
		\cacti_fixture_source_function($root . '/lib/functions.php', $function, '');
	}
}

function load_functions($file, $names) {
	$source = file_get_contents($file);
	foreach ($names as $name) {
		if (preg_match('/^function ' . preg_quote($name, '/') . '\(/m', $source)) {
			\cacti_fixture_source_function($file, $name, __NAMESPACE__);
		}
	}
}
load_functions($root . '/lib/data_query.php', [
	'run_data_query', 'query_script_host', 'query_snmp_host', 'data_query_format_record',
	'data_query_update_host_cache_from_buffer', 'query_check_suitable',
	'get_best_data_query_index_type', 'get_ordered_index_type_list',
	'update_data_query_sort_cache', 'verify_index_order', 'data_query_remap_indexes',
	'data_query_remove_disabled_items', 'rewrite_snmp_enum_value',
	'data_query_local_data', 'data_query_identity_complete', 'data_query_index_key',
	'data_query_index_lookup', 'data_query_index_exists',
	'data_query_cache_collations', 'data_query_cache_compare',
]);
load_functions($root . '/lib/api_device.php', ['api_device_dq_remove']);
load_functions($root . '/lib/poller.php', ['update_reindex_cache', 'poller_update_poller_reindex_from_buffer']);
load_functions($root . '/lib/functions.php', ['cacti_sizeof', 'cacti_count', 'array_rekey']);

$GLOBALS['config'] = array('poller_id' => 1, 'library_path' => $root . '/lib', 'php_snmp_support' => false);
$GLOBALS['input_types'] = array();

function record_query($query) {
	$GLOBALS['dq_calls']++;
	if (!empty($GLOBALS['dq_capture'])) $GLOBALS['dq_queries'][] = $query;
	if (!empty($GLOBALS['dq_failure']) && preg_match($GLOBALS['dq_failure'], $query)) {
		$GLOBALS['dq_failure_hits']++;
		if ($GLOBALS['dq_failure_hits'] === $GLOBALS['dq_failure_at']) return false;
	}
	return true;
}
function db_fetch_assoc_prepared($query, $params = array(), $log = true, $db_conn = false) {
	if (!record_query($query)) return false;
	$rows = \db_fetch_assoc_prepared($query, $params, $log, $db_conn);
	if ($GLOBALS['reverse'] && str_contains($query, 'dl.id AS local_data_id')) $rows = array_reverse($rows);
	return $rows;
}
function db_fetch_row_prepared($query, $params = array(), $log = true, $db_conn = false) {
	if (!record_query($query)) return false;
	return \db_fetch_row_prepared($query, $params, $log, $db_conn);
}
function db_fetch_cell_prepared($query, $params = array(), $col_name = '', $log = true, $db_conn = false) {
	if (!record_query($query)) return false;
	return \db_fetch_cell_prepared($query, $params, $col_name, $log, $db_conn);
}
function db_execute_prepared($query, $params = array(), $log = true, $db_conn = false, $execute_name = 'Exec', $default_value = true, $return_func = 'no_return_function', $return_params = array()) {
	if (!record_query($query)) return false;
	$result = \db_execute_prepared($query, $params, $log, $db_conn, $execute_name, $default_value, $return_func, $return_params);
	if (!empty($GLOBALS['dq_pause_after_mark']) && str_contains($query, 'UPDATE host_snmp_cache SET present = 0')) {
		fwrite(STDOUT, "marked\n");
		fflush(STDOUT);
		fgets(STDIN);
	}
	return $result;
}
function db_execute($query, $log = true, $db_conn = false) {
	if (!record_query($query)) return false;
	return \db_execute($query, $log, $db_conn);
}
function db_begin_transaction($db_conn = false) {
	if (!empty($GLOBALS['dq_begin_failure'])) return false;
	return \db_begin_transaction($db_conn);
}
function db_commit_transaction($db_conn = false) {
	if (!empty($GLOBALS['dq_commit_failure'])) return false;
	return \db_commit_transaction($db_conn);
}
function sql($query, $params = array()) {
	$statement = $GLOBALS['db']->prepare($query);
	$statement->execute($params);
	return $statement;
}
function get_data_query_array($id) { return $GLOBALS['xml']; }
function call_remote_data_collector($poller_id, $url) { return $GLOBALS['dq_remote_response']; }
function get_script_query_path($args, $script_path, $host_id) { return $args; }
function exec_into_array($command_line) {
	if (!array_key_exists($command_line, $GLOBALS['script'])) throw new \RuntimeException('Unexpected script ' . $command_line);
	return $GLOBALS['script'][$command_line];
}
function read_config_option($name, $force = false) { return $name === 'boost_rrd_update_enable' ? 'on' : ''; }
function __esc(...$args) { return vsprintf(array_shift($args), $args); }
function __( ...$args) { return __esc(...$args); }
function query_debug_timer_start() {}
function query_debug_timer_offset($section, $message) {}
function debug_log_insert($type, $text) {}
function debug_log_insert_section_start($type, $text, $allowcopy = false) {}
function debug_log_insert_section_end($type) {}
function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') { $GLOBALS['dq_logs'][] = $string; }
function update_data_source_title_cache_from_host($host_id, $query_id = 0, $ids = []) {}
function update_graph_title_cache_from_host($host_id, $query_id = 0, $ids = []) {}
function automation_execute_data_query($host_id, $snmp_query_id) {
	if (isset($GLOBALS['dq_automation_callback'])) ($GLOBALS['dq_automation_callback'])();
}
function api_device_purge_from_remote($device_id, $poller_id) { $GLOBALS['dq_purges'][] = $device_id; }
function api_plugin_hook_function($name, $parm = null) {}
function api_data_source_cache_crc_update($poller_id, $variable = 'poller_replicate_data_source_cache_crc') {}
function poller_push_reindex_only_data_to_main($host_id, $query_id) { $GLOBALS['dq_replicated'][] = array($host_id, $query_id); }
function update_poller_cache_from_query($host_id, $query_id, $local_data_ids) { $GLOBALS['poller_updates'][] = $local_data_ids; }
function push_out_host($host_id, $local_data_id = 0, $data_template_id = 0) {
	$GLOBALS['pushes'][] = array($host_id, $local_data_id, $data_template_id);
	if ($local_data_id) sql('INSERT INTO poller_item (local_data_id, poller_id, rrd_name) VALUES (?,1,?) ON DUPLICATE KEY UPDATE poller_id=1', array($local_data_id, 'fixture'));
}
function microtime($as_float = false) {
	if (!empty($GLOBALS['clock_samples'])) return array_shift($GLOBALS['clock_samples']);
	return \microtime($as_float);
}
function cacti_snmp_session($hostname, $community, $version, $auth_user = '', $auth_pass = '', $auth_proto = '', $priv_pass = '', $priv_proto = '', $context = '', $engineid = '', $port = 161, $timeout_ms = 500, $retries = 0, $max_oids = 10, $bulk_walk_size = 10) {
	if (($GLOBALS['dq_session_failure_size'] ?? null) === $bulk_walk_size) return false;
	$session = new class($bulk_walk_size) {
		public $bulk_walk_size;
		public $closed = false;
		public function __construct($size) { $this->bulk_walk_size = $size; }
		public function close() { $this->closed = true; }
	};
	$GLOBALS['dq_sessions'][] = $session;
	return $session;
}
function cacti_snmp_session_walk($session, $oid, $dummy = false, $max_repetitions = null, $non_repeaters = null, $value_output_format = SNMP_STRING_OUTPUT_GUESS) {
	$GLOBALS['walks'][] = ['oid' => $oid, 'size' => $session->bulk_walk_size];
	if (isset($GLOBALS['benchmark_walks'])) return $GLOBALS['benchmark_walks'][$oid];
	if ($oid === '.1.2.1') {
		if ($session->bulk_walk_size === 5 && $GLOBALS['fail_second_walk']) return [];
		return ['.1.2.1.1' => '1', '.1.2.1.2' => '2'];
	}
	if ($oid === '.1.2.2') return ['.1.2.2.1' => 'port1', '.1.2.2.2' => 'port2'];
	throw new \RuntimeException('Unexpected walk ' . $oid);
}
function connect_fixture() {
	global $database_sessions, $database_default, $database_hostname, $database_port;
	$database_default = getenv('CACTI_DQ_DB_NAME') ?: 'cacti_dq_test';
	if (!preg_match('/^cacti_dq_test(?:_[a-z0-9]+)?$/D', $database_default)) {
		throw new \RuntimeException('Use a disposable cacti_dq_test database');
	}
	$database_hostname = getenv('CACTI_DQ_DB_HOST') ?: '127.0.0.1';
	$database_port = getenv('CACTI_DQ_DB_PORT') ?: '3306';
	$GLOBALS['db'] = new \PDO("mysql:host=$database_hostname;port=$database_port;dbname=$database_default;charset=utf8mb4",
		getenv('CACTI_DQ_DB_USER') ?: 'root', getenv('CACTI_DQ_DB_PASSWORD') ?: '',
		array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));
	$database_sessions["$database_hostname:$database_port:$database_default"] = $GLOBALS['db'];
}

function reset_fixture($count = 2) {
	$root = $GLOBALS['dq_source_root'];
	connect_fixture();
	$schema = file_get_contents($root . '/cacti.sql');
	$tables = array('host', 'host_snmp_query', 'data_input', 'snmp_query', 'data_local',
		'data_template_data', 'data_input_fields', 'data_input_data', 'host_snmp_cache',
		'poller_reindex', 'poller_item', 'poller_output_boost', 'graph_templates_item',
		'data_template_rrd', 'graph_local', 'poller_command', 'poller');
	foreach ($tables as $table) {
		if (!preg_match('/CREATE TABLE `?' . $table . '`? \(.*?;\n/s', $schema, $match)) {
			throw new \RuntimeException('Missing production schema for ' . $table);
		}
		$GLOBALS['db']->exec(str_replace('CREATE TABLE ', 'CREATE TABLE IF NOT EXISTS ', $match[0]));
		$GLOBALS['db']->exec('TRUNCATE TABLE ' . $table);
	}
	sql("INSERT INTO host (id,status,disabled,poller_id,hostname,snmp_version,bulk_walk_size) VALUES (1,3,'',1,'fixture.invalid',2,10)");
	sql("INSERT INTO host_snmp_query (host_id,snmp_query_id,sort_field,reindex_method) VALUES (1,1,'ifName',0)");
	sql('INSERT INTO data_input (id,type_id) VALUES (1,4)');
	sql('INSERT INTO snmp_query (id,data_input_id) VALUES (1,1)');
	sql("INSERT INTO data_input_fields (id,data_input_id,data_name) VALUES (1,1,'index_value'),(2,1,'index_type')");
	$GLOBALS['xml'] = array('script_path' => 'fixture', 'arg_index' => 'index', 'arg_num_indexes' => 'count', 'arg_query' => 'query', 'output_delimiter' => '!', 'index_order' => 'ifName:ifDescr', 'index_order_type' => 'alphanumeric', 'fields' => array(
		'ifName' => array('direction' => 'input', 'query_name' => 'ifName'),
		'ifDescr' => array('direction' => 'input', 'query_name' => 'ifDescr'),
	));
	$GLOBALS['script'] = array('index' => array(), 'count' => array((string) $count), 'query ifName' => array(), 'query ifDescr' => array());
	$GLOBALS['pushes'] = $GLOBALS['poller_updates'] = $GLOBALS['dq_logs'] = $GLOBALS['dq_queries'] = $GLOBALS['dq_replicated'] = array();
	$GLOBALS['dq_begin_failure'] = $GLOBALS['dq_commit_failure'] = $GLOBALS['reverse'] = false;
	$GLOBALS['dq_failure'] = null;
	$GLOBALS['dq_failure_hits'] = 0;
	$GLOBALS['dq_failure_at'] = 1;
	$GLOBALS['dq_capture'] = false;
	$GLOBALS['dq_remote_response'] = '';
	$GLOBALS['config']['url_path'] = '/cacti/';
	$GLOBALS['data_query_rewrite_indexes_cache'] = array();
	$GLOBALS['clock_samples'] = array();
	$GLOBALS['walks'] = array();
	$GLOBALS['fail_second_walk'] = false;
	$GLOBALS['dq_sessions'] = $GLOBALS['dq_purges'] = array();
	unset($GLOBALS['dq_session_failure_size'], $GLOBALS['dq_automation_callback']);
	unset($GLOBALS['benchmark_walks']);
	$inserts = array(
		'data_local (id,host_id,snmp_query_id,snmp_index,orphan,data_template_id)' => array(),
		'data_template_data (id,local_data_id,data_input_id)' => array(),
		'data_input_data (data_template_data_id,data_input_field_id,value)' => array(),
		'poller_item (local_data_id,poller_id,rrd_name)' => array(),
		'host_snmp_cache (host_id,snmp_query_id,field_name,field_value,snmp_index,oid,present)' => array(),
	);
	foreach (array_chunk(range(1, max(1, $count)), 1000) as $chunk) {
		foreach ($chunk as $i) {
			if ($count === 0) break;
			$inserts['data_local (id,host_id,snmp_query_id,snmp_index,orphan,data_template_id)'][] = "($i,1,1,'$i',0,1)";
			$inserts['data_template_data (id,local_data_id,data_input_id)'][] = "($i,$i,1)";
			$inserts['data_input_data (data_template_data_id,data_input_field_id,value)'][] = "($i,1,'port$i'),($i,2,'ifName')";
			$inserts['poller_item (local_data_id,poller_id,rrd_name)'][] = "($i,1,'fixture')";
			foreach (array('ifName' => 'port', 'ifDescr' => 'description') as $field => $prefix) {
				$inserts['host_snmp_cache (host_id,snmp_query_id,field_name,field_value,snmp_index,oid,present)'][] = "(1,1,'$field','$prefix$i','$i','',1)";
				$GLOBALS['script']['query ' . $field][] = $i . '!' . $prefix . $i;
			}
			$GLOBALS['script']['index'][] = (string) $i;
		}
		foreach ($inserts as $table => $rows) {
			if ($rows) sql('INSERT INTO ' . $table . ' VALUES ' . implode(',', $rows));
			$inserts[$table] = array();
		}
	}
	// A production cache carries index statistics; a freshly bulk-loaded table has none until
	// InnoDB samples it, so the optimizer would plan the identity lookups against an empty table.
	sql('ANALYZE TABLE host_snmp_cache')->fetchAll();
	$GLOBALS['dq_calls'] = $GLOBALS['database_total_queries'] = 0;
}

function worker_fixture($started = null) {
	$source = file_get_contents($GLOBALS['dq_source_root'] . '/poller_commands.php');
	$offset = strpos($source, "\tif (cacti_sizeof(\$poller_commands)) {", strpos($source, 'Register the child process'));
	$end = strpos($source, "\n\tunregister_process('commands', 'child'", $offset);
	if ($offset === false || $end === false) throw new \RuntimeException('Cannot locate command worker');
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-query-worker-');
	$line = substr_count(substr($source, 0, $offset), "\n");
	file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", $line) . substr($source, $offset, $end - $offset));
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($GLOBALS['dq_source_root'] . '/poller_commands.php');
	$poller_commands = sql('SELECT action,command,time,last_updated FROM poller_command ORDER BY command')->fetchAll(\PDO::FETCH_ASSOC);
	$poller_id = $host_id = 1;
	$last_host_id = $verbosity = 0;
	$max_updated = 2000000000;
	$poller_db_cnn_id = $GLOBALS['db'];
	$start = $started ?? \microtime(true);
	if (!defined('MAX_RECACHE_RUNTIME')) define('MAX_RECACHE_RUNTIME', 600);
	try {
		require $temporary;
	} finally {
		unlink($temporary);
	}
}
