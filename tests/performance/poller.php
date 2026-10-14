<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Isolated CLI harness: execute production function bodies with instrumented I/O.
// Never included by Pest's collection process or a production entry point.
$root = getenv('CACTI_POLLER_SOURCE_ROOT') ?: dirname(__DIR__, 2);
define('DATA_INPUT_TYPE_SCRIPT', 1);
define('DATA_INPUT_TYPE_PHP_SCRIPT_SERVER', 5);
$acknowledgements_in_third_argument = !str_contains(file_get_contents($root . '/lib/poller.php'), '$unused_cache, $successful_updates');
$bounded                            = str_contains(file_get_contents($root . '/lib/poller.php'), 'function process_poller_output_batch(');
$mode                               = $argv[1] ?? 'quick';

if (!in_array($mode, ['quick', 'scale', 'empty', 'recovery', 'recovery-empty', 'exec'], true)) {
	throw new RuntimeException('Unknown performance scenario');
}
$state = ['rows' => [], 'queries' => [], 'writes' => [], 'updates' => [], 'boost' => false];

function perf_assert(bool $condition, string $message) : void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function perf_load(string $path, string $name) : bool {
	$source = file_get_contents($path);
	$start  = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return false;
	}
	$end      = strpos($source, "\nfunction ", $start + 1);
	$function = substr($source, $start, $end === false ? null : $end - $start);
	$map      = [
		'process_poller_output', 'process_poller_output_batch', 'poller_acknowledge_output', 'poller_prefetch_source_fields', 'boost_load_unused_data_source_names', 'poller_get_unused_data_source_names', 'poller_get_nt_rrd_field_names',
		'db_fetch_assoc', 'db_fetch_assoc_prepared', 'db_fetch_cell', 'db_execute', 'db_execute_prepared', 'db_in_clause', 'array_rekey',
		'cacti_sizeof', 'is_hexadecimal', 'cacti_log', 'dsstats_poller_output', 'dsdebug_poller_output',
		'api_plugin_hook_function', 'boost_poller_on_demand', 'rrdtool_function_update',
		'cacti_exec', 'cacti_exec_log_describe',
	];
	// Only rename identifiers, retaining the production statements and static caches.
	$tokens   = token_get_all('<?php ' . $function);
	$compiled = '';

	foreach ($tokens as $token) {
		if (is_array($token)) {
			if ($token[0] === T_OPEN_TAG) {
				continue;
			}
			$compiled .= $token[0] === T_STRING && in_array($token[1], $map, true) ? 'perf_' . $token[1] : $token[1];
		} else {
			$compiled .= $token;
		}
	}
	eval($compiled);

	return true;
}

function perf_cacti_log(...$args) : void {
}
function perf_cacti_exec_log_describe($binary, $args) : string {
	return $binary;
}
function perf_cacti_sizeof($value) : int {
	return is_countable($value) ? count($value) : 0;
}
function perf_dsstats_poller_output(...$args) : void {
}
function perf_dsdebug_poller_output(...$args) : void {
}
function perf_api_plugin_hook_function(...$args) : void {
}
function perf_boost_poller_on_demand($results, &$staged = null) : bool {
	$staged = $GLOBALS['state']['boost'];

	return !$GLOBALS['state']['boost'];
}
function perf_rrdtool_function_update($updates, &$pipe, &$metadata = [], &$successful_updates = []) : int {
	$successful_updates = [];

	foreach ($updates as $path => $rrd) {
		foreach ($rrd['times'] ?? [] as $time => $fields) {
			$successful_updates[$path][$time] = true;
		}
	}

	if ($GLOBALS['bounded'] && $GLOBALS['acknowledgements_in_third_argument']) {
		$metadata = $successful_updates;
	}
	$GLOBALS['state']['updates'] = $updates;

	return count($updates);
}
function perf_array_rekey($rows, $key, $value) : array {
	$result = [];

	foreach ($rows as $row) {
		$result[$row[$key]] = is_array($value) ? array_intersect_key($row, array_flip($value)) : $row[$value];
	}

	return $result;
}
function perf_db_fetch_assoc($sql, ...$args) : array {
	$GLOBALS['state']['queries'][] = ['sql' => $sql, 'params' => []];

	if (str_contains($sql, 'FROM poller_data_source_unused')) {
		return [['local_data_id' => 2, 'data_source_name' => 'b']];
	}
	perf_assert(str_contains($sql, 'FROM poller_output AS po'), 'Unexpected main query: ' . $sql);

	return $GLOBALS['state']['rows'];
}
function perf_db_fetch_assoc_prepared($sql, $params = [], ...$args) : array|false {
	$GLOBALS['state']['queries'][] = ['sql' => $sql, 'params' => $params];

	if (str_contains($sql, 'FROM poller_output AS po')) {
		return $GLOBALS['state']['rows'];
	}

	if (str_contains($sql, 'FROM settings WHERE name = ?')) {
		perf_assert($params === ['poller_unused_cache_generation'], 'Unexpected generation query');

		return [['value' => 'fixture-generation']];
	}

	if (str_contains($sql, 'FROM data_input AS di')) {
		perf_assert($params === [1, 5], 'Orphan preload must be restricted to script methods');

		return [['local_data_id' => 2, 'data_source_name' => 'b']];
	}

	if (str_contains($sql, 'dtr.local_data_id IN (')) {
		perf_assert(count($params) <= 1000, 'Manual metadata query exceeds the source bound');
		$fields = [];

		foreach ($params as $id) {
			foreach (['a', 'b'] as $field) {
				$fields[] = ['local_data_id' => $id, 'data_name' => $field, 'data_source_name' => $field];
			}
		}

		return $fields;
	}

	if (str_contains($sql, 'FROM poller_data_template_field_mappings')) {
		if (in_array($GLOBALS['mode'], ['recovery', 'recovery-empty'], true) && !isset($GLOBALS['state']['mapping_failed'])) {
			$GLOBALS['state']['mapping_failed'] = true;

			return false;
		}

		return in_array($GLOBALS['mode'], ['empty', 'recovery-empty'], true) ? [] : [['keyname' => '1_a', 'data_source_name' => 'a'], ['keyname' => '1_b', 'data_source_name' => 'b']];
	}
	perf_assert(count($params) === 1 && str_contains($sql, 'dtr.local_data_id = ?'), 'Metadata lookup must be bounded to one source');

	if (str_contains($sql, 'gti.task_item_id IS NULL')) {
		// Source 2 shares a template with source 1 but has a different graph binding.
		return $params[0] === 2 ? [['data_source_name' => 'b']] : [];
	}

	return [['data_name' => 'a', 'data_source_name' => 'a'], ['data_name' => 'b', 'data_source_name' => 'b']];
}
function perf_db_fetch_cell($sql, ...$args) : int {
	$GLOBALS['state']['queries'][] = ['sql' => $sql, 'params' => []];

	return 0; // No remaining backlog in this isolated batch.
}
function perf_db_execute($sql, ...$args) : bool {
	$GLOBALS['state']['writes'][] = $sql;

	return true;
}
function perf_db_execute_prepared($sql, $params = []) : bool {
	perf_assert(str_starts_with($sql, 'DELETE FROM poller_output WHERE'), 'Unexpected prepared write');
	perf_assert(count($params) <= 3000, 'Acknowledgement exceeds 1000 keys');
	$GLOBALS['state']['writes'][] = $sql;

	return true;
}
function perf_mapping_count() : int {
	return count(array_filter($GLOBALS['state']['queries'], fn ($query) => str_contains($query['sql'], 'FROM poller_data_template_field_mappings')));
}
function perf_db_in_clause($column, $ids) : string {
	return $column . ' IN (' . implode(',', $ids) . ')';
}
function perf_row(int $id, string $output, int $template = 1, int $fields = 1) : array {
	return ['output' => $output, 'time' => '2026-01-01 00:00:00', 'unix_time' => 1767225600,
		'local_data_id' => $id, 'data_template_id' => $template, 'rrd_path' => 'source-' . $id,
		'rrd_name'      => 'a', 'rrd_num' => $fields];
}
function perf_metadata_count() : int {
	return count(array_filter($GLOBALS['state']['queries'], fn ($query) => !str_contains($query['sql'], 'poller_output') && !str_contains($query['sql'], 'FROM settings WHERE name = ?')));
}
function perf_sample(int $id) : array {
	return $GLOBALS['state']['updates']['source-' . $id]['times'][1767225600] ?? [];
}

if ($mode === 'exec') {
	if (!perf_load($root . '/lib/functions.php', 'cacti_exec')) {
		// develop uses different execution contracts; do not test copied 1.2.x code.
		print json_encode(['scenario' => 'exec', 'applicable' => false]) . "\n";
		exit(0);
	}
	$measure = function (bool $wrapper) : float {
		$start = hrtime(true);

		for ($i = 0; $i < 20; $i++) {
			$out = [];

			if ($wrapper) {
				perf_assert(perf_cacti_exec(PHP_BINARY, ['-r', 'echo "ok"; exit(7);'], $out) === 7, 'Exit status changed');
				perf_assert($out === ['ok'], 'Output changed');
			} else {
				$p = proc_open([PHP_BINARY, '-r', 'echo "ok"; exit(7);'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
				fclose($pipes[0]);
				stream_get_contents($pipes[1]);
				stream_get_contents($pipes[2]);
				fclose($pipes[1]);
				fclose($pipes[2]);
				perf_assert(proc_close($p) === 7, 'Control process failed');
			}
		}

		return (hrtime(true) - $start) / 1e9;
	};
	$overheads = [];

	for ($round = 0; $round < 3; $round++) {
		// Alternate order to reduce drift in shared-runner load.
		if ($round % 2 === 0) {
			$control = $measure(false);
			$wrapper = $measure(true);
		} else {
			$wrapper = $measure(true);
			$control = $measure(false);
		}
		$overheads[] = ($wrapper - $control) / 20;
	}
	sort($overheads);
	perf_assert($overheads[1] < 0.025, 'Median wrapper overhead exceeds 25ms per call; investigate fixed sleeps');
	$out = [];
	perf_assert(perf_cacti_exec(PHP_BINARY, ['-r', 'for($i=0;$i<6;$i++){echo "tick\n";usleep(100000);}'], $out, 0.3) === 0 && count($out) === 6, 'Active streaming child timed out');
	$start = hrtime(true);
	perf_assert(perf_cacti_exec(PHP_BINARY, ['-r', 'usleep(3000000);'], $out, 0.2) === 1, 'Silent child did not time out');
	perf_assert((hrtime(true) - $start) / 1e9 < 2, 'Silent-child timeout was not bounded');
	print json_encode(['scenario' => 'exec', 'applicable' => true, 'median_overhead_seconds' => $overheads[1]]) . "\n";
	exit(0);
}

foreach (['SQL_NO_CACHE' => '', 'POLLER_VERBOSITY_HIGH' => 4, 'POLLER_VERBOSITY_NONE' => 0, 'CACTI_PATH_LIBRARY' => __DIR__ . '/fixtures'] as $name => $value) {
	define($name, $value);
}
$config = ['library_path' => __DIR__ . '/fixtures'];
$debug  = false;
perf_assert(perf_load($root . '/lib/functions.php', 'is_hexadecimal'), 'Production hex parser is missing');

perf_load($root . '/lib/boost.php', 'boost_load_unused_data_source_names');

foreach (['poller_prefetch_source_fields', 'process_poller_output_batch', 'poller_acknowledge_output', 'poller_get_unused_data_source_names', 'poller_get_nt_rrd_field_names', 'process_poller_output'] as $name) {
	$loaded = perf_load($root . '/lib/poller.php', $name);
	perf_assert($loaded || $name !== 'process_poller_output', 'Production poller function is missing');
}
$pipe  = null;
$start = hrtime(true);

if (in_array($mode, ['recovery', 'recovery-empty'], true)) {
	// Fail with an empty batch, then recover; an empty successful read is cached too.
	perf_process_poller_output($pipe);
	perf_assert(perf_mapping_count() === 1, 'First mapping read must fail once');
	perf_process_poller_output($pipe);
	perf_assert(perf_mapping_count() === 2, 'Failed mapping read was cached');

	for ($pass = 0; $pass < 5; $pass++) {
		perf_process_poller_output($pipe);
		perf_assert(perf_mapping_count() === 2, 'Successful mapping read was not cached');
	}

	if ($mode === 'recovery') {
		$state['rows'] = [perf_row(1, 'a:10 b:20', 1, 2)];
		perf_process_poller_output($pipe);
		perf_assert(perf_sample(1) === ['a' => '10', 'b' => '20'], 'MULTI output did not recover');
	}
} elseif ($mode === 'empty') {
	for ($i = 0; $i < 5; $i++) {
		perf_process_poller_output($pipe);
	}
	perf_assert(perf_mapping_count() === 1, 'Empty template map was reloaded on repeated passes');
} elseif ($mode === 'quick') {
	$state['rows'] = [perf_row(1, 'a:10 b:20', 1, 2), perf_row(2, 'a:30 b:40'), perf_row(3, 'a:50 b:60', 0, 2), perf_row(4, 'invalid', 1, 2), perf_row(5, 'FF'), perf_row(6, 'U')];

	$cold_metadata_queries = null;

	for ($pass = 0; $pass < 5; $pass++) {
		perf_process_poller_output($pipe);
		perf_assert(perf_sample(1) === ['a' => '10', 'b' => '20'], 'Templated MULTI output changed');
		perf_assert(perf_sample(2) === ['a' => '30'], 'Per-instance orphan filtering changed');
		perf_assert(perf_sample(3) === ['a' => '50', 'b' => '60'], 'Manual-source output changed');
		perf_assert(perf_sample(4) === ['a' => 'U', 'b' => 'U'], 'Invalid output must remain unknown');
		perf_assert(perf_sample(5) === ['a' => 255], 'Hex conversion changed');
		perf_assert(perf_sample(6) === ['a' => 'U'], 'Unknown value changed');
		$cold_metadata_queries ??= perf_metadata_count();
		perf_assert($cold_metadata_queries <= 5, 'Metadata lookups grew beyond the cold-cache budget');
		perf_assert(perf_metadata_count() === $cold_metadata_queries, 'Metadata lookups grew across repeated passes');
	}
	$state['rows'] = [perf_row(7, 'a:1', 1, 2)];
	perf_process_poller_output($pipe);
	perf_assert(perf_sample(7) === [], 'Incomplete source was emitted');
	$state['boost']   = true;
	$state['updates'] = [];
	$state['rows']    = [perf_row(1, 'a:10 b:20', 1, 2)];
	perf_process_poller_output($pipe);
	perf_assert($state['updates'] === [], 'Boost staging unexpectedly wrote inline RRD updates');
} else {
	$total = (int) ($argv[2] ?? 150000);
	perf_assert(in_array($total, [150000, 1000000, 2500000], true), 'Unsupported synthetic source count');
	$processed = 0;

	for ($offset = 0; $offset < $total; $offset += 40000) {
		$state['rows'] = [];

		for ($id = $offset + 1; $id <= min($total, $offset + 40000); $id++) {
			$state['rows'][] = perf_row($id, '42');
		}
		$state['writes']  = [];
		$state['queries'] = [];
		$processed += perf_process_poller_output($pipe);
		perf_assert(perf_metadata_count() === ($offset === 0 ? (function_exists('perf_boost_load_unused_data_source_names') || function_exists('perf_poller_prefetch_source_fields') ? 2 : 1) : 0), 'Single-value processing added metadata queries');
	}
	perf_assert($processed === $total, 'Scale run lost or duplicated source updates');
}
print json_encode(['scenario' => $mode, 'sources' => $total ?? count($state['rows']), 'elapsed_seconds' => (hrtime(true) - $start) / 1e9, 'peak_bytes' => memory_get_peak_usage(true), 'php' => PHP_VERSION, 'kind' => 'synthetic PHP processing; instrumented database and RRD boundaries']) . "\n";
