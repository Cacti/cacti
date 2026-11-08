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

$root = dirname(__DIR__, 2);
$config = ['base_path' => $root, 'library_path' => "$root/lib", 'is_web' => false, 'poller_id' => 1];
$no_http_headers = true;
$_SESSION = [];
$messages = [];
$review_logs = [];
$review_options = ['automation_tree_enabled' => 'on'];

function load_actual_functions($path, $names) {
	$tokens = token_get_all(file_get_contents($path));
	for ($i = 0; $i < count($tokens); $i++) {
		if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
		$j = $i + 1;
		while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
		if (!is_array($tokens[$j]) || !in_array($tokens[$j][1], $names, true)) continue;
		$name = $tokens[$j][1];
		$code = '';
		$depth = 0;
		$started = false;
		for (; $i < count($tokens); $i++) {
			$t = $tokens[$i];
			$code .= is_array($t) ? $t[1] : $t;
			if ($t === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) { $depth++; $started = true; }
			if ($t === '}' && --$depth === 0 && $started) break;
		}
		eval($code);
	}
	foreach ($names as $name) if (!function_exists($name)) throw new RuntimeException("Missing actual function $name");
}

function __($text, ...$args) { return $args ? vsprintf($text, $args) : $text; }
function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') {
	if ($level === '' || str_contains($string, 'ERROR:') || str_contains($string, 'WARNING:')) $GLOBALS['review_logs'][] = $string;
}
function cacti_debug_backtrace($message = '', $output = false, $log = true, $level = 0, $skip = 0) {}
function read_config_option($name, $force = false) { return $GLOBALS['review_options'][$name] ?? ''; }
function set_config_option($name, $value, $remote = false) { $GLOBALS['review_options'][$name] = $value; }
require "$root/include/global_constants.php";
load_actual_functions("$root/lib/functions.php", ['cacti_sizeof', 'cacti_count', 'array_rekey', 'cacti_object_hash', 'clean_up_lines', 'sanitize_sql_column', 'form_input_validate', 'is_error_message', 'raise_message', 'get_message_level', 'test_data_sources', 'get_graph_title', 'get_data_source_title']);
load_actual_functions("$root/lib/html_validate.php", ['input_validate_input_number']);
$arrays = file_get_contents("$root/include/global_arrays.php");
$begin = strpos($arrays, '$automation_op_array =');
if ($begin === false) $begin = strpos($arrays, '$automation_op_array');
$end = strpos($arrays, '$automation_tree_item_types', $begin);
eval(substr($arrays, $begin, $end - $begin));
$automation_tree_header_types = [AUTOMATION_TREE_ITEM_TYPE_STRING => 'Fixed String'];

class ReviewPDO extends PDO {
	public array $query_counts = [];
	public ?Closure $before = null;
	public function prepare(string $query, array $options = []): PDOStatement|false {
		$key = preg_replace('/\s+/', ' ', trim($query));
		$this->query_counts[$key] = ($this->query_counts[$key] ?? 0) + 1;
		if ($this->before) ($this->before)($query);
		return parent::prepare($query, $options);
	}
}
$dsn = getenv('CACTI_AUTOMATION_TEST_DSN');
if (!$dsn || !preg_match('/^mysql:host=127\.0\.0\.1;port=([0-9]+);dbname=cacti_automation_test(?:;|$)/', $dsn, $connection)) {
	throw new RuntimeException('Use an isolated local cacti_automation_test database for this destructive fixture.');
}
$pdo = new ReviewPDO($dsn, getenv('CACTI_AUTOMATION_TEST_USER') ?: 'root', getenv('CACTI_AUTOMATION_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$database_hostname = '127.0.0.1';
$database_port = $connection[1];
$database_default = 'cacti_automation_test';
$database_sessions = ["$database_hostname:$database_port:$database_default" => $pdo];
$database_total_queries = 0;
$database_last_error = '';
$data_query_id_cache = array();
require "$root/lib/database.php";
require "$root/lib/api_automation.php";
require "$root/lib/api_tree.php";
require "$root/lib/data_query.php";

function check($condition, $description) {
	if (!$condition) throw new RuntimeException("Review assertion failed: $description");
}
function emit_result($name, $result) {
	print json_encode(['scenario' => $name] + $result, JSON_THROW_ON_ERROR) . PHP_EOL;
}
function clear_fixture() {
	global $pdo;
	foreach (['graph_tree_items', 'graph_tree', 'graph_templates_item', 'graph_templates_graph', 'graph_local', 'data_local', 'data_template', 'data_template_data', 'data_template_rrd', 'host', 'host_template', 'graph_templates', 'automation_tree_rules', 'automation_tree_rule_items', 'automation_match_rule_items', 'automation_graph_rules', 'automation_graph_rule_items', 'host_snmp_cache', 'host_snmp_query', 'snmp_query_graph', 'snmp_query_graph_rrd', 'snmp_query_graph_rrd_sv', 'snmp_query_graph_sv', 'data_input_data', 'snmp_query', 'host_graph'] as $table) $pdo->exec("DELETE FROM $table");
	$_SESSION = [];
	$GLOBALS['review_logs'] = [];
}

require "$root/lib/template.php";
require "$root/lib/variables.php";
require "$root/lib/api_graph.php";
require "$root/lib/api_data_source.php";

$review_hooks = array();
function api_plugin_hook_function($name, $data) {
	$GLOBALS['review_hooks'][] = array($name, $GLOBALS['pdo']->inTransaction());
	return $data;
}
function push_out_host($host_id, $local_data_id = 0) {
	$GLOBALS['review_hooks'][] = array('push_out_host', $GLOBALS['pdo']->inTransaction());
}
function __esc($text, ...$args) { return __($text, ...$args); }

// Load the real field definitions; dropdown contents do not affect copying template fields.
$forms = file_get_contents("$root/include/global_form.php");
foreach (array('struct_data_source', 'struct_data_source_item', 'struct_graph', 'struct_graph_item') as $name) {
	$start = strpos($forms, '$' . $name . ' =');
	$end = strpos($forms, "\n\t);", $start) + 4;
	$definition = substr($forms, $start, $end - $start);
	preg_match_all('/=> (\$[a-z_]+)/', $definition, $references);
	foreach ($references[1] as $reference) {
		$variable = substr($reference, 1);
		if (!isset($$variable)) $$variable = array();
	}
	eval($definition);
}

function seed_graph_template() {
	global $pdo;
	$pdo->exec("INSERT INTO host (id,hostname,description) VALUES (1,'router-a','router-a'),(2,'server-b','server-b')");
	$pdo->exec("INSERT INTO graph_templates (id,name,test_source) VALUES (1,'Fixture','')");
	$pdo->exec("INSERT INTO graph_templates_graph (id,graph_template_id,local_graph_id,title,height,width) VALUES (1,1,0,'Fixture graph',120,500)");
	$pdo->exec("INSERT INTO data_template (id,hash,name) VALUES (1,'fixture-template','Fixture data')");
	$pdo->exec("INSERT INTO data_template_data (id,data_template_id,data_input_id,name,active,rrd_step) VALUES (1,1,1,'Fixture data','on',300)");
	$pdo->exec("INSERT INTO data_template_rrd (id,hash,data_template_id,data_source_name,data_source_type_id,rrd_heartbeat) VALUES (1,'fixture-source',1,'traffic',2,600)");
	$pdo->exec("INSERT INTO graph_templates_item (id,hash,graph_template_id,task_item_id,graph_type_id,consolidation_function_id,sequence) VALUES (1,'fixture-item',1,1,4,1,1)");
}
