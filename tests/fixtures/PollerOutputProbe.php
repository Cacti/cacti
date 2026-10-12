<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
// Isolated behavioral probe: production parsing, batching, staging and update bodies.
if (PHP_SAPI !== 'cli') {
	exit(1);
}
$root = getenv('CACTI_POLLER_SOURCE_ROOT') ?: dirname(__DIR__, 2);
// The maintenance probe uses no Composer classes.
$scenario = $argv[1] ?? 'partial';
if (str_starts_with($scenario, 'integration-')) {
	require __DIR__ . '/PollerHandoffDatabase.php';
}
$state    = ['rows' => [], 'queries' => 0, 'mapping_queries' => 0, 'commands' => [], 'rrd_calls' => 0, 'create_calls' => 0, 'boost_calls' => 0, 'delete_calls' => 0, 'arrived' => false];
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
	if (isset($GLOBALS['handoff_pdo'])) return handoff_fetch($sql, $params);

	if (str_contains($sql, 'SELECT value FROM settings')) {
		$state['generation_queries'] = ($state['generation_queries'] ?? 0) + 1;
		if ($state['generation_queries'] === 1) {
			if ($scenario === 'cache-pending') return [['value' => '']];
			if ($scenario === 'cache-missing') return [];
		}
		return [['value' => 'probe']];
	}
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

		if (in_array($scenario, ['metadata', 'metadata-empty'], true) && $state['mapping_queries'] === 1) {
			return false;
		}

		if (in_array($scenario, ['empty-mappings', 'metadata-empty'], true)) {
			return [];
		}
		return $scenario === 'fanout' || $scenario === 'fanout-orphan' ? [['keyname' => '1_value', 'data_source_name' => 'in,out']] : [['keyname' => '1_in', 'data_source_name' => 'in'], ['keyname' => '1_out', 'data_source_name' => 'out']];
	}

	if (str_contains($sql, 'WHERE poller_id = ?')) {
		return $scenario === 'boost-owner-query-failure' ? false : ($scenario === 'boost-owner-mismatch' ? [] : [['local_data_id' => 1]]);
	}
	if (str_contains($sql, 'LEFT JOIN graph_templates_item')) {
		$state['orphan_queries'] = ($state['orphan_queries'] ?? 0) + 1;
		if ($scenario === 'orphan-query-failure' && $state['orphan_queries'] === 1) return false;
		return $scenario === 'fanout-orphan' ? [['local_data_id' => 1, 'data_source_name' => 'out']] : [];
	}
	if (str_contains($sql, 'INNER JOIN data_input_fields')) {
		$state['manual_queries'] = ($state['manual_queries'] ?? 0) + 1;
		if ($scenario === 'manual-query-failure' && $state['manual_queries'] === 1) return false;
		return [['local_data_id' => 1, 'data_name' => 'value', 'data_source_name' => 'in']];
	}
	return [];
}
function db_fetch_assoc($sql) : array|false {
	global $scenario;
	if ($scenario === 'cache-read-failure' && str_contains($sql, 'FROM poller_data_source_unused') && empty($GLOBALS['state']['cache_failed'])) {
		$GLOBALS['state']['cache_failed'] = true;
		return false;
	}
	if (str_contains($sql, 'FROM poller_data_source_unused')) $GLOBALS['state']['cache_reads'] = ($GLOBALS['state']['cache_reads'] ?? 0) + 1;
	if (str_contains($sql, 'FROM poller_data_source_unused')) return $scenario === 'fanout-orphan' ? [['local_data_id' => 1, 'data_source_name' => 'out']] : [];
	return db_fetch_assoc_prepared($sql);
}
function db_fetch_cell($sql) : int {
	return str_contains($sql, 'poller_time') ? 0 : count($GLOBALS['state']['rows']);
}
function db_execute_prepared($sql, $params = []) : bool {
	global $state, $scenario;
	if (isset($GLOBALS['handoff_pdo'])) return handoff_execute($sql, $params);
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
	if (isset($GLOBALS['handoff_pdo'])) return handoff_execute($sql, [], $args[1] ?? false);

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
	if (isset($GLOBALS['handoff_pdo'])) return handoff_fetch($args[0], [], $args[2] ?? false)[0] ?? [];
	return ['Value' => 1048576];
}
function db_affected_rows(...$args) : int {
	return $GLOBALS['handoff_affected'] ?? 100000;
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
	if ($key === 'path_rrdtool') return $GLOBALS['probe_rrd_binary'] ?? '';
	if ($key === 'storage_location') return isset($GLOBALS['handoff_pdo']) ? '' : '1';
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
function cacti_rrdtool_valid_path($s) : bool { return true; }
function cacti_rrdtool_valid_ds_name($s) : bool { return true; }
function cacti_rrdtool_valid_ds_template($s) : bool { return true; }
function db_fetch_cell_prepared($sql, $params = []) : int { return isset($GLOBALS['handoff_pdo']) ? (int) array_values(handoff_fetch($sql, $params)[0])[0] : 1; }
function db_fetch_row_prepared($sql, $params = []) : array { return handoff_fetch($sql, $params)[0] ?? []; }
function get_data_source_path(...$args) : string { return $GLOBALS['handoff_rrd']; }
function get_data_source_item_name(...$args) : string { return 'in'; }
function rrdtool_function_interface_speed(...$args) : int { return 0; }
function cacti_rrdtool_valid_bound($s) : bool { return true; }
if (!isset($handoff_pdo)) {
function rrdtool_function_create(...$args) : bool {
	if ($GLOBALS['scenario'] === 'local-create') {
		if ($args[3] !== true) {
			throw new RuntimeException('Creation still uses the asynchronous write-only pipe');
		}
		if (!rrdtool_execute_acknowledged('create source-1', $args[2])) {
			return false;
		}
	}
	$GLOBALS['state']['create_calls']++;
	return true;
}
} else {
	probe_load($root . '/lib/rrd.php', 'rrdtool_function_create');
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
function rrdtool_execute_path_command(...$args) : bool { return $GLOBALS['scenario'] !== 'local-create'; }
function rrdtool_execute($command, ...$args) : string|bool|null {
	global $state, $scenario;
	$state['rrd_calls']++;
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

	if ($scenario === 'proxy-null') {
		return null;
	}

	return $args[1] === RRDTOOL_OUTPUT_BOOLEAN ? true : '';
}

foreach (['SQL_NO_CACHE' => '', 'POLLER_VERBOSITY_HIGH' => 4, 'POLLER_VERBOSITY_NONE' => 0, 'POLLER_ID' => 1, 'CACTI_CONNECTION' => 'online', 'RRDTOOL_OUTPUT_STDOUT' => 1, 'RRDTOOL_OUTPUT_BOOLEAN' => 2, 'CACTI_PATH_LIBRARY' => dirname(__DIR__) . '/performance/fixtures'] as $name => $value) {
	define($name, $value);
}

define('RRD_NL', " \\\n");
foreach (['cacti_sizeof', 'array_rekey', 'is_hexadecimal', 'cacti_has_control_chars'] as $name) {
	probe_load($root . '/lib/functions.php', $name);
}
foreach (['rrdtool_function_update', 'rrdtool_execute_acknowledged', 'rrdtool_acknowledged_close'] as $name) {
	probe_load($root . '/lib/rrd.php', $name);
}

foreach (['boost_load_unused_data_source_names', 'boost_flush_output_batch', 'boost_validate_poller_ownership', 'boost_poller_on_demand'] as $name) {
	probe_load($root . '/lib/boost.php', $name);
}
require $root . '/lib/poller.php';
$config = ['library_path' => dirname(__DIR__) . '/performance/fixtures', 'poller_id' => 1, 'connection' => 'online', 'cacti_server_os' => 'unix'];
$debug = false;
$time  = 1700000000;

if (str_starts_with($scenario, 'boost-owner-')) {
	$config['poller_id'] = 2;
}

if (str_starts_with($scenario, 'local-')) {
	$config['force_storage_location_local'] = true;
	$probe_rrd_binary = tempnam(sys_get_temp_dir(), 'rrd acknowledgement ');
	$probe_rrd_starts = tempnam(sys_get_temp_dir(), 'rrd starts ');
	$reply = match ($scenario) {
		'local-error' => "ERROR: update rejected\n",
		'local-empty' => '',
		'local-partial' => 'OK u:0.00 s:0.00 r:0.00',
		default => "OK u:0.00 s:0.00 r:0.00\n"
	};
	file_put_contents($probe_rrd_binary, '#!' . PHP_BINARY . "\n<?php\n"
		. 'file_put_contents(' . var_export($probe_rrd_starts, true) . ', "start\n", FILE_APPEND);'
		. '$reply = ' . var_export($reply, true) . ';'
		. ($scenario === 'local-retry' ? 'if (substr_count(file_get_contents(' . var_export($probe_rrd_starts, true) . '), "start\n") === 1) $reply = "ERROR: rejected\n";' : '')
		. 'while (fgets(STDIN) !== false) {' . ($scenario === 'local-timeout' ? 'sleep(60);' : '')
		. 'fwrite(STDOUT, $reply); fflush(STDOUT);'
		. (in_array($scenario, ['local-success', 'local-create'], true) ? '' : 'exit(1);') . '}');
	chmod($probe_rrd_binary, 0700);
	register_shutdown_function(static function () use ($probe_rrd_binary, $probe_rrd_starts) {
		rrdtool_acknowledged_close();
		unlink($probe_rrd_binary);
		unlink($probe_rrd_starts);
	});
}

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

if (isset($handoff_pdo)) {
	$time = time();
	handoff_seed($config, $state, $time);
}

if ($scenario === 'local-success') {
	$state['rows'][] = probe_row(1, $time + 60, 'in', '43');
}
if ($scenario === 'manual-query-failure') {
	$state['rows'] = [probe_row(1, $time, '', 'value:42')];
	$state['rows'][0]['data_template_id'] = 0;
}
$pipe   = $scenario === 'local-create' ? fopen('php://temp', 'w+') : null;
$first  = process_poller_output($pipe);
$second = null;

if (in_array($scenario, ['boundary', 'boundary-partial', 'boundary-failure', 'backlog', 'metadata', 'metadata-empty', 'empty-mappings', 'retry-failed', 'local-retry', 'cache-pending', 'cache-missing', 'cache-read-failure', 'manual-query-failure', 'orphan-query-failure', 'integration-manual-retry', 'integration-stage-retry', 'integration-delete-retry'], true)) {
	$second = process_poller_output($pipe);
}

if ($scenario === 'metadata-empty') {
	process_poller_output($pipe);
}

if ($scenario === 'retry') {
	// Requeue a new timestamp after the first was stored.
	$state['rows'][] = probe_row(1, $time + 60, 'in', '43');
	$second          = process_poller_output($pipe, 1);
}
if (isset($handoff_pdo)) {
	$state['rows'] = handoff_fetch('SELECT local_data_id, rrd_name, time, output FROM poller_output');
	$state['handoff'] = handoff_verdict();
}
print json_encode(['cache_reads' => ($state['cache_reads'] ?? 0), 'handoff' => ($state['handoff'] ?? null), 'orphan_queries' => ($state['orphan_queries'] ?? 0), 'manual_queries' => ($state['manual_queries'] ?? 0), 'first' => $first, 'second' => $second, 'remaining' => count($state['rows']), 'rows' => array_slice($state['rows'], 0, 3), 'queries' => $state['queries'], 'mapping_queries' => $state['mapping_queries'], 'rrd_calls' => $state['rrd_calls'], 'create_calls' => $state['create_calls'], 'commands' => $state['commands'], 'boost_calls' => $state['boost_calls'], 'delete_calls' => $state['delete_calls'], 'local_starts' => isset($probe_rrd_starts) ? substr_count(file_get_contents($probe_rrd_starts), "start\n") : 0], JSON_THROW_ON_ERROR) . "\n";
