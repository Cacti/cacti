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

/*
 * Shared runtime for the automation and template propagation probes. It loads the
 * production graph, tree and template libraries against a disposable database
 * that was created from cacti.sql, and fakes only logging, settings and plugin
 * hooks so the probes can observe them.
 */

$root   = dirname(__DIR__, 2);
$config = ['is_web' => false, 'poller_id' => 1, 'cacti_server_os' => 'unix'];

$no_http_headers = true;
$_SESSION        = [];
$messages        = [];
$review_logs     = [];
$review_options  = ['automation_tree_enabled' => 'on', 'poller_interval' => '300', 'rrdtool_version' => '1.8.0'];

require_once dirname(__DIR__) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';
require_once "$root/include/vendor/autoload.php";
require_once "$root/include/global_path.php";

function load_actual_functions(string $path, array $names) : void {
	foreach ($names as $name) {
		if (!function_exists($name)) {
			cacti_fixture_source_function($path, $name, '');
		}
	}
}

function __(mixed $text, mixed ...$args) : string {
	return $args ? vsprintf((string) $text, $args) : (string) $text;
}

function __x(mixed ...$args) : string {
	array_shift($args);

	return __(...$args);
}

function __esc(mixed $text, mixed ...$args) : string {
	return __($text, ...$args);
}

function cacti_log(mixed $string, bool $output = false, string $environ = 'CMDPHP', mixed $level = '') : bool {
	if ($level === '' || str_contains((string) $string, 'ERROR:') || str_contains((string) $string, 'WARNING:')) {
		$GLOBALS['review_logs'][] = (string) $string;
	}

	return true;
}

function cacti_debug_backtrace(mixed ...$args) : void {
}

function read_config_option(string $name, bool $force = false) : mixed {
	return $GLOBALS['review_options'][$name] ?? '';
}

function set_config_option(string $name, mixed $value, bool $remote = false) : void {
	$GLOBALS['review_options'][$name] = $value;
}

require "$root/include/global_constants.php";

define('POLLER_ID', 1);
define('CACTI_CLI', true);
define('CACTI_WEB', false);
define('CACTI_SERVER_OS', 'unix');

load_actual_functions("$root/lib/functions.php", [
	'cacti_sizeof', 'cacti_count', 'array_rekey', 'cacti_object_hash', 'clean_up_lines',
	'sanitize_sql_column', 'form_input_validate', 'is_error_message', 'raise_message',
	'get_message_level', 'test_data_sources', 'get_graph_title', 'get_data_source_title',
	'cacti_version_compare', 'version_to_decimal', 'version_to_bits', 'get_rrdtool_version',
	'format_cacti_version', 'get_message_title'
]);
load_actual_functions("$root/lib/html_validate.php", ['input_validate_input_number']);
load_actual_functions("$root/lib/auth.php", ['get_auth_realms']);

// The probes run as the poller, which is not subject to per-user tree permissions.
function is_tree_allowed(int $tree_id, int $user_id = 0) : bool {
	return true;
}

/**
 * Counts prepared statements and lets a probe interrupt or slow one down.
 */
class ReviewPDO extends PDO {
	public array $query_counts = [];

	public ?Closure $before = null;

	public function prepare(string $query, array $options = []) : PDOStatement|false {
		$key = preg_replace('/\s+/', ' ', trim($query));

		$this->query_counts[$key] = ($this->query_counts[$key] ?? 0) + 1;

		if ($this->before) {
			($this->before)($query);
		}

		return parent::prepare($query, $options);
	}
}

/**
 * The probes delete and rewrite whole tables, so refuse anything but a local,
 * disposable database named cacti_automation_test or cacti_automation_<name>_test.
 */
$dsn = (string) getenv('CACTI_AUTOMATION_TEST_DSN');

if (!preg_match('/^mysql:(?:host=127\.0\.0\.1;port=([0-9]+)|unix_socket=[^;]+);dbname=(cacti_automation(?:_[a-z0-9]+)*_test)(?:;|$)/', $dsn, $connection)) {
	throw new RuntimeException('Use an isolated local cacti_automation_test database for this destructive fixture.');
}

$pdo = new ReviewPDO($dsn, getenv('CACTI_AUTOMATION_TEST_USER') ?: 'root', getenv('CACTI_AUTOMATION_TEST_PASSWORD') ?: '', [
	PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_EMULATE_PREPARES => false,
]);

$database_hostname      = '127.0.0.1';
$database_port          = $connection[1] !== '' ? $connection[1] : '3306';
$database_default       = $connection[2];
$database_sessions      = ["$database_hostname:$database_port:$database_default" => $pdo];
$database_total_queries = 0;
$database_last_error    = '';
$data_query_id_cache    = [];

require "$root/lib/database.php";
require "$root/lib/api_automation.php";
require "$root/lib/api_tree.php";
require "$root/lib/data_query.php";
require "$root/lib/template.php";
require "$root/lib/variables.php";
require "$root/lib/api_graph.php";
require "$root/lib/api_data_source.php";

function check(bool $condition, string $description) : void {
	if (!$condition) {
		throw new RuntimeException("Review assertion failed: $description");
	}
}

function emit_result(string $name, array $result) : void {
	print json_encode(['scenario' => $name] + $result, JSON_THROW_ON_ERROR) . PHP_EOL;
}

function clear_fixture() : void {
	global $pdo;

	foreach (['graph_tree_items', 'graph_tree', 'graph_templates_item', 'graph_templates_graph', 'graph_local',
		'data_local', 'data_template', 'data_template_data', 'data_template_rrd', 'host', 'host_template',
		'graph_templates', 'automation_tree_rules', 'automation_tree_rule_items', 'automation_match_rule_items',
		'automation_graph_rules', 'automation_graph_rule_items', 'automation_templates_rules', 'host_snmp_cache',
		'host_snmp_query', 'snmp_query_graph', 'snmp_query_graph_rrd', 'snmp_query_graph_rrd_sv',
		'snmp_query_graph_sv', 'data_input_data', 'snmp_query', 'host_graph'] as $table) {
		$pdo->exec("DELETE FROM $table");
	}

	$_SESSION                = [];
	$GLOBALS['review_logs']  = [];
	$GLOBALS['review_hooks'] = [];
}

$review_hooks = [];

function api_plugin_hook_function(string $name, mixed $data = '') : mixed {
	$GLOBALS['review_hooks'][] = [$name, $GLOBALS['pdo']->inTransaction()];

	return $data;
}

function api_plugin_hook(string $name) : void {
	$GLOBALS['review_hooks'][] = [$name, $GLOBALS['pdo']->inTransaction()];
}

function push_out_host(int $host_id, int $local_data_id = 0, int $data_template_id = 0) : void {
	$GLOBALS['review_hooks'][] = ['push_out_host', $GLOBALS['pdo']->inTransaction()];
}

function object_cache_get_totals(mixed ...$args) : void {
}

function object_cache_update_totals(mixed ...$args) : void {
}

// Include the maintained definitions directly, after the isolated database is ready.
require "$root/include/global_arrays.php";
require "$root/include/global_form.php";

// Bootstrap hooks are outside the graph and template operations measured by the probes.
$review_hooks = [];

function seed_graph_template() : void {
	global $pdo;

	$pdo->exec("INSERT INTO host (id,hostname,description) VALUES (1,'router-a','router-a'),(2,'server-b','server-b')");
	$pdo->exec("INSERT INTO graph_templates (id,name,test_source) VALUES (1,'Fixture','')");
	$pdo->exec("INSERT INTO graph_templates_graph (id,graph_template_id,local_graph_id,title,height,width) VALUES (1,1,0,'Fixture graph',120,500)");
	$pdo->exec("INSERT INTO data_template (id,hash,name) VALUES (1,'fixture-template','Fixture data')");
	$pdo->exec("INSERT INTO data_template_data (id,data_template_id,data_input_id,name,active,rrd_step) VALUES (1,1,1,'Fixture data','on',300)");
	$pdo->exec("INSERT INTO data_template_rrd (id,hash,data_template_id,data_source_name,data_source_type_id,rrd_heartbeat) VALUES (1,'fixture-source',1,'traffic',2,600)");
	$pdo->exec("INSERT INTO graph_templates_item (id,hash,graph_template_id,task_item_id,graph_type_id,consolidation_function_id,sequence) VALUES (1,'fixture-item',1,1,4,1,1)");
}
