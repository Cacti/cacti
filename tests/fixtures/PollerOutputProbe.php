<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
// Isolated behavioral probe: production parsing, batching, staging and update bodies.
if (PHP_SAPI !== 'cli') {
	exit(1);
}
$root = getenv('CACTI_POLLER_SOURCE_ROOT') ?: dirname(__DIR__, 2);
require dirname(__DIR__, 2) . '/include/vendor/autoload.php';
define('DATA_INPUT_TYPE_SCRIPT', 1);
define('DATA_INPUT_TYPE_PHP_SCRIPT_SERVER', 5);
$scenario = $argv[1] ?? 'partial';
$state    = ['rows' => [], 'queries' => 0, 'mapping_queries' => 0, 'commands' => [], 'rrd_calls' => 0, 'boost_calls' => 0, 'delete_calls' => 0, 'arrived' => false];
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
function probe_row(int $id, int $time, string $field, string $value, int $fields = 1) : array {
	return ['local_data_id' => $id, 'unix_time' => $time, 'time' => gmdate('Y-m-d H:i:s', $time), 'rrd_name' => $field, 'output' => $value, 'rrd_num' => $fields, 'rrd_path' => 'source-' . $id, 'data_template_id' => 1];
}
function db_fetch_assoc_prepared($sql, $params = []) : array|false {
	global $state, $scenario;

	if (str_contains($sql, 'FROM poller_output AS po')) {
		$state['queries']++;

		if ($state['queries'] > 8) {
			throw new RuntimeException('Poller repeated a backlog without progress');
		}
		$rows = array_values(array_filter($state['rows'], static fn ($row) => $params === [] || $row['local_data_id'] > $params[0] || ($row['local_data_id'] == $params[1] && $row['time'] > $params[2])));
		usort($rows, static fn ($a, $b) => [$a['local_data_id'], $a['time'], $a['rrd_name']] <=> [$b['local_data_id'], $b['time'], $b['rrd_name']]);

		return str_contains($sql, 'LIMIT 40001') ? array_slice($rows, 0, 40001) : $rows;
	}

	if (str_contains($sql, 'poller_data_template_field_mappings')) {
		$state['mapping_queries']++;

		if ($scenario === 'metadata' && $state['mapping_queries'] === 1) {
			return false;
		}

		if ($scenario === 'orphan-query-failure') {
			return [['keyname' => '1_value', 'data_source_name' => 'in']];
		}

		return $scenario === 'fanout' || $scenario === 'fanout-orphan' ? [['keyname' => '1_value', 'data_source_name' => 'in,out']] : [['keyname' => '1_in', 'data_source_name' => 'in'], ['keyname' => '1_out', 'data_source_name' => 'out']];
	}

	if (str_contains($sql, 'FROM data_template_rrd') || str_contains($sql, 'FROM data_input AS di')) {
		$state['source_queries'] = ($state['source_queries'] ?? 0) + 1;

		if (in_array($scenario, ['manual-query-failure', 'orphan-query-failure'], true) && $state['source_queries'] === 1) {
			return false;
		}

		if ($scenario === 'manual-query-failure') {
			return [['data_name' => 'value', 'data_source_name' => 'in']];
		}
	}

	return $scenario === 'fanout-orphan' ? [['local_data_id' => 1, 'data_source_name' => 'out']] : [];
}
function db_fetch_assoc($sql) : array|false {
	return str_contains($sql, 'FROM poller_output AS po') ? db_fetch_assoc_prepared($sql) : [];
}
function db_fetch_cell($sql) : int {
	return str_contains($sql, 'poller_time') ? 0 : count($GLOBALS['state']['rows']);
}
function db_execute_prepared($sql, $params = []) : bool {
	global $state, $scenario;
	$state['delete_calls']++;

	if ($scenario === 'delete-failure' || ($scenario === 'delete-group' && $state['delete_calls'] === 2)) {
		return false;
	}
	$keys = [];

	for ($i = 0; $i < count($params); $i += 3) {
		$keys[$params[$i] . '|' . $params[$i + 1] . '|' . $params[$i + 2]] = true;
	}
	$state['rows'] = array_values(array_filter($state['rows'], static fn ($row) => !isset($keys[$row['local_data_id'] . '|' . $row['rrd_name'] . '|' . $row['time']])));

	return true;
}
function db_execute($sql, ...$args) : bool {
	global $state, $scenario;

	if (str_contains($sql, 'INSERT IGNORE INTO poller_output_boost')) {
		$state['boost_calls']++;

		return $scenario !== 'boost-failure';
	}

	if (preg_match('/local_data_id IN\s*\(([^)]+)\)/', $sql, $matches)) {
		$ids           = array_flip(explode(',', $matches[1]));
		$state['rows'] = array_values(array_filter($state['rows'], static fn ($row) => !isset($ids[$row['local_data_id']])));
	}

	return true;
}
function db_in_clause($column, $ids) : string {
	return $column . ' IN (' . implode(',', $ids) . ')';
}
function db_fetch_row(...$args) : array {
	return ['Value' => 1048576];
}
function db_affected_rows(...$args) : int {
	return 100000;
}
function db_qstr($s, ...$args) : string {
	return "'" . str_replace("'", "''", $s) . "'";
}
function cacti_log(...$args) : void {
}
function dsstats_poller_output(...$args) : void {
}
function dsdebug_poller_output(...$args) : void {
}
function api_plugin_hook_function(...$args) : void {
}
function read_config_option($key) : string {
	if ($key === 'boost_rrd_update_enable') {
		return str_starts_with($GLOBALS['scenario'], 'boost-') ? 'on' : '';
	}

	return $key === 'boost_redirect' && $GLOBALS['scenario'] === 'boost-redirect' ? 'on' : '';
}
function set_config_option(...$args) : void {
}
function boost_check_correct_enabled() : bool {
	return true;
}
function boost_error_handler(...$args) : bool {
	return false;
}
function boost_log_ignored_sample_keys(...$args) : void {
}
function rrdtool_file_exists(...$args) : bool {
	return true;
}
function get_rrdtool_version() : string {
	return '1.8';
}
function cacti_version_compare($a, $b, $op) : bool {
	return version_compare($a, $b, $op);
}
function cacti_escapeshellarg($s) : string {
	return escapeshellarg($s);
}
function rrdtool_command_log_context($s) : string {
	return $s;
}
function rrdtool_execute($command, ...$args) : string|false {
	global $state, $scenario;
	$state['rrd_calls']++;

	if ($command instanceof \Cacti\Rrd\RrdCommand) {
		$command = [$command->operation, ...$command->arguments];
	}
	$command = is_array($command) ? implode(' ', $command) : $command;

	if (count($state['commands']) < 5) {
		$state['commands'][] = $command;
	}

	if ($scenario === 'arrival' && !$state['arrived']) {
		$state['rows'][]  = probe_row(1, 1700000060, 'in', '43');
		$state['arrived'] = true;
	}

	if (($scenario === 'retry-failed' && $state['rrd_calls'] === 1) || ($scenario === 'boundary-failure' && str_contains($command, 'source-40000') && str_contains($command, '1700000000:')) || in_array($scenario, ['failed', 'boost-failure'], true) || ($scenario === 'mixed' && str_contains($command, 'source-1'))) {
		return false;
	}

	return '';
}

foreach (['SQL_NO_CACHE' => '', 'POLLER_VERBOSITY_HIGH' => 4, 'POLLER_VERBOSITY_NONE' => 0, 'POLLER_ID' => 1, 'CACTI_CONNECTION' => 'online', 'RRDTOOL_OUTPUT_STDOUT' => 1, 'CACTI_PATH_LIBRARY' => dirname(__DIR__) . '/performance/fixtures'] as $name => $value) {
	define($name, $value);
}

foreach (['cacti_sizeof', 'array_rekey', 'is_hexadecimal'] as $name) {
	probe_load($root . '/lib/functions.php', $name);
}
probe_load($root . '/lib/rrd.php', 'rrdtool_function_update');

foreach (['boost_flush_output_batch', 'boost_poller_on_demand'] as $name) {
	probe_load($root . '/lib/boost.php', $name);
}
require $root . '/lib/poller.php';
$debug = false;
$time  = 1700000000;

if ($scenario === 'partial') {
	$state['rows'] = [probe_row(1, $time, 'in', '42', 2), probe_row(1, $time, 'out', '43', 2), probe_row(1, $time + 60, 'in', '44', 2)];
} elseif ($scenario === 'early-partial') {
	$state['rows'] = [probe_row(1, $time, 'in', '42', 2), probe_row(1, $time + 60, 'in', '43', 2), probe_row(1, $time + 60, 'out', '44', 2)];
} elseif ($scenario === 'delete-group') {
	for ($i = 1; $i <= 999; $i++) {
		$state['rows'][] = probe_row($i, $time, 'in', '42');
	}
	$state['rows'][] = probe_row(1000, $time, 'in', '42', 2);
	$state['rows'][] = probe_row(1000, $time, 'out', '43', 2);
	$state['rows'][] = probe_row(1001, $time, 'in', '44');
} elseif ($scenario === 'mixed') {
	$state['rows'] = [probe_row(1, $time, 'in', '42'), probe_row(1, $time + 60, 'in', '43'), probe_row(2, $time, 'in', '44')];
} elseif (str_starts_with($scenario, 'fanout')) {
	$state['rows'] = [probe_row(1, $time, '', 'value:42', $scenario === 'fanout' ? 2 : 1)];
} elseif ($scenario === 'boundary' || $scenario === 'boundary-partial' || $scenario === 'boundary-failure' || $scenario === 'backlog') {
	for ($i = 1; $i <= ($scenario !== 'backlog' ? 39999 : 40001); $i++) {
		$state['rows'][] = probe_row($i, $time, 'in', '42', $scenario === 'backlog' ? 2 : 1);
	}

	if ($scenario === 'boundary-failure') {
		$state['rows'][] = probe_row(40000, $time, 'in', '42');
		$state['rows'][] = probe_row(40000, $time + 60, 'in', '43');
	} elseif ($scenario === 'boundary-partial') {
		$state['rows'][] = probe_row(40000, $time, 'in', '42', 2);
		$state['rows'][] = probe_row(40000, $time + 60, 'in', '43', 2);
		$state['rows'][] = probe_row(40000, $time + 60, 'out', '44', 2);
	} elseif ($scenario === 'boundary') {
		$state['rows'][] = probe_row(40000, $time, 'in', '42', 2);
		$state['rows'][] = probe_row(40000, $time, 'out', '43', 2);
	}
} else {
	$state['rows'] = [probe_row(1, $time, 'in', '42')];
}

if (in_array($scenario, ['manual-query-failure', 'orphan-query-failure'], true)) {
	$state['rows']                        = [probe_row(1, $time, '', 'value:42')];
	$state['rows'][0]['data_template_id'] = $scenario === 'manual-query-failure' ? 0 : 1;
}
$pipe   = null;
$first  = process_poller_output($pipe);
$second = null;

if (in_array($scenario, ['boundary', 'boundary-partial', 'boundary-failure', 'backlog', 'metadata', 'retry-failed', 'manual-query-failure', 'orphan-query-failure'], true)) {
	$second = process_poller_output($pipe);
}

if ($scenario === 'retry') {
	// Requeue a new timestamp after the first was stored.
	$state['rows'][] = probe_row(1, $time + 60, 'in', '43');
	$second          = process_poller_output($pipe, 1);
}
print json_encode(['source_queries' => ($state['source_queries'] ?? 0), 'first' => $first, 'second' => $second, 'remaining' => count($state['rows']), 'rows' => array_slice($state['rows'], 0, 3), 'queries' => $state['queries'], 'mapping_queries' => $state['mapping_queries'], 'rrd_calls' => $state['rrd_calls'], 'commands' => $state['commands'], 'boost_calls' => $state['boost_calls'], 'delete_calls' => $state['delete_calls']], JSON_THROW_ON_ERROR) . "\n";
