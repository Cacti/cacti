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

declare(strict_types=1);

/*
 * Regression tests for poller, boost, dsstats, data_query, script_server,
 * and cmd_realtime algorithmic and correctness fixes.
 */

$repoRoot = dirname(__DIR__, 3);

if (!function_exists('debug_log_insert')) {
	function debug_log_insert(string $type, string $text) : void {
	}
}

if (!function_exists('__esc')) {
	function __esc(string $text, mixed ...$args) : string {
		return $args !== [] ? vsprintf($text, $args) : $text;
	}
}

if (!function_exists('cacti_unserialize')) {
	function cacti_unserialize(string $data) : mixed {
		return @unserialize($data, ['allowed_classes' => false]);
	}
}

if (!function_exists('rewrite_snmp_enum_value')) {
	require_once $repoRoot . '/lib/data_query.php';
}

function algo_extract_function_body(string $source, string $functionName) : string|false {
	$needle = 'function ' . $functionName . '(';
	$start  = strpos($source, $needle);

	if ($start === false) {
		return false;
	}

	$next = strpos($source, "\nfunction ", $start + strlen($needle));

	return $next === false ? substr($source, $start) : substr($source, $start, $next - $start);
}

test('function regression contracts hold', function (string $relPath, string $functionName, array $mustContain, array $mustNotContain) use ($repoRoot) : void {
	$src  = (string) file_get_contents($repoRoot . '/' . $relPath);
	$body = algo_extract_function_body($src, $functionName);

	expect($body)->not->toBeFalse();

	foreach ($mustContain as $needle) {
		expect($body)->toContain($needle);
	}

	foreach ($mustNotContain as $forbidden) {
		expect($body)->not->toContain($forbidden);
	}
})->with([
	'dsstats get_rrdfile_names deduplicates poller_item join' => [
		'lib/dsstats.php',
		'get_rrdfile_names',
		[
			'GROUP BY dtd.local_data_id, dtd.data_source_path',
			'MAX(pi.rrd_num) AS dsses',
			'$newrows[$thread_id] ?? []',
		],
		['SELECT SUM(rrd_num)'],
	],
	'dsstats_needs_last_value detects COUNTER and DERIVE data sources' => [
		'lib/dsstats.php',
		'dsstats_needs_last_value',
		[
			'in_array((int) $ds_info[\'data_source_type_id\'], [2, 3, 6, 7], true)',
		],
		[],
	],
	'dsstats_prefetch_last_values batches hourly_last lookups' => [
		'lib/dsstats.php',
		'dsstats_prefetch_last_values',
		[
			'dsstats_needs_last_value($ds_types, $ldid)',
			'array_chunk(array_values($counter_ids), 2000)',
			'WHERE local_data_id IN (',
		],
		[],
	],
	'dsstats_poller_output keys ds_types, uses prefetched hourly_last, and guards division' => [
		'lib/dsstats.php',
		'dsstats_poller_output',
		[
			"\$ds_types[(int) \$row['local_data_id']][\$row['data_source_name']]",
			"\$ds_types[\$local_data_id][\$ds]['data_source_type_id']",
			'$last_values = dsstats_prefetch_last_values($rrd_update_array, $ds_types);',
			'if ($polling_interval > 0)',
			"\$result['output'] != 'NULL' && \$polling_interval > 0",
		],
		[
			"\$ds_types[\$result['rrd_name']]",
			"SELECT SQL_NO_CACHE `value`\n\t\t\t\t\t\t\t\t\tFROM data_source_stats_hourly_last\n\t\t\t\t\t\t\t\t\tWHERE local_data_id = ?",
		],
	],
	'dsstats_boost_bottom logs BOOST stats and drops redundant daily pass' => [
		'lib/dsstats.php',
		'dsstats_boost_bottom',
		["dsstats_log_statistics('BOOST');"],
		[
			"dsstats_log_statistics('DAILY');",
			"dsstats_get_and_store_ds_avgpeak_values('daily', 'child', 0);",
		],
	],
	'replicate_out fixes IS NOT NULL SQL and OR predicate' => [
		'lib/poller.php',
		'replicate_out',
		['AND last_updated IS NOT NULL', 'OR name="poller_type"'],
		[
			'AND last_updated NOT NULL',
			"WHERE name LIKE \"path_spine%\"\n\t\t\tAND name=\"poller_type\"",
		],
	],
	'replicate_out_table uses O(1) skipcols lookup' => [
		'lib/poller.php',
		'replicate_out_table',
		['$skipcols[$c] = true;', 'if (!isset($skipcols[$col]))'],
		['array_search($col, $skipcols)'],
	],
	'replicate_table_to_poller uses O(1) skipcols and separator byte accounting' => [
		'lib/poller.php',
		'replicate_table_to_poller',
		[
			'$skipcols[$c] = true;',
			'if (!isset($skipcols[$col]))',
			"\$row_sep  = (\$rowcnt > 0 ? ', ' : '');",
			'$row_len  = strlen($row_sep) + strlen($sql_row);',
			'$sqllen + $row_len + 1000 > $max_packet',
		],
		['array_search($col, $skipcols)'],
	],
	'data_query_rewrite_indexes keeps unquoted parameter and chain_indexes key' => [
		'lib/data_query.php',
		'data_query_rewrite_indexes',
		[
			'array($host_id, $snmp_query_id, $iv)',
			'$chain_indexes[$iv] = $data_query_rewrite_indexes_cache[$hash];',
		],
		['$iv = db_qstr($iv);'],
	],
	'query_snmp_host uses bulk cacti_snmp_session_walk for default GUESS format' => [
		'lib/data_query.php',
		'query_snmp_host',
		[
			'if ($value_output_format !== SNMP_STRING_OUTPUT_GUESS)',
			'$results = cacti_snmp_session_walk($session, $oids);',
		],
		['if (!empty($value_output_format))'],
	],
	'data_query_remap_indexes batches remapping via chunked UPDATE JOIN' => [
		'lib/data_query.php',
		'data_query_remap_indexes',
		[
			'array_chunk($ids, 1000)',
			'UPDATE graph_local AS gl',
			'SET gl.snmp_index = dl.snmp_index',
			'WHERE dtr.local_data_id IN (',
		],
		['SELECT snmp_index'],
	],
	'boost_time_to_run queries poller table instead of pollers' => [
		'poller_boost.php',
		'boost_time_to_run',
		['FROM poller WHERE disabled = ""'],
		['FROM pollers'],
	],
]);

test('get_data_query_array passes $xml_data to calculate_or_set_index_order and derives index_order', function () use ($repoRoot) : void {
	$src  = (string) file_get_contents($repoRoot . '/lib/data_query.php');
	$body = algo_extract_function_body($src, 'get_data_query_array');

	expect($body)->toContain('calculate_or_set_index_order($xml_data);');
	expect($body)->not->toContain('calculate_or_set_index_order($raw_data);');

	$xml = [
		'fields' => [
			'ifDescr' => ['source' => 'value'],
			'ifIndex' => ['source' => 'index'],
		],
	];

	calculate_or_set_index_order($xml);
	expect($xml['index_order'])->toBe('ifIndex');
});

test('rewrite_snmp_enum_value applies case-sensitive REGEXP, case-insensitive REGEXPNC, and literal escaping', function () : void {
	rewrite_snmp_enum_value(null);

	$map = [
		['match' => 'REGEXP:^Eth([0-9]+)$', 'replace' => 'Ethernet-$1'],
		['match' => 'REGEXPNC:^ge-([0-9/]+)$', 'replace' => 'GigE-$1'],
		['match' => 'up(1).ok/0', 'replace' => 'UP_OK'],
	];

	expect(rewrite_snmp_enum_value('if_test', 'Eth42', $map))->toBe('Ethernet-42');
	expect(rewrite_snmp_enum_value('if_test', 'eth42', $map))->toBe('eth42');
	expect(rewrite_snmp_enum_value('if_test', 'GE-0/0/1', $map))->toBe('GigE-0/0/1');
	expect(rewrite_snmp_enum_value('if_test', 'up(1).ok/0', $map))->toBe('UP_OK');
	expect(rewrite_snmp_enum_value('if_test', 'up(1)Xok/0', $map))->toBe('up(1)Xok/0');

	rewrite_snmp_enum_value(null);
});

test('get_ordered_index_type_list fixes numeric/alpha flags, inspects field_value, and continues outer loop', function () use ($repoRoot) : void {
	$src  = (string) file_get_contents($repoRoot . '/lib/data_query.php');
	$body = algo_extract_function_body($src, 'get_ordered_index_type_list');

	expect($body)->toContain('$must_be_numeric = true;');
	expect($body)->toContain('$must_be_alpha = true;');
	expect($body)->not->toContain('$mustbenumeric');
	expect($body)->not->toContain('$mustbealpha');
	expect($body)->toContain("is_numeric(\$value['field_value'])");
	expect($body)->not->toContain('is_numeric($value)');
	expect(substr_count((string) $body, 'continue 2;'))->toBe(2);
});

test('boost_process_poller_output updates $last_item inside loop so duplicate filtering works', function () use ($repoRoot) : void {
	$src       = (string) file_get_contents($repoRoot . '/lib/boost.php');
	$body      = (string) algo_extract_function_body($src, 'boost_process_poller_output');
	$checkPos  = strpos($body, "if (\$last_item['timestamp'] == \$item['timestamp'] && \$last_item['rrd_name'] == \$item['rrd_name'])");
	$assignPos = strpos($body, '$last_item = $item;', (int) $checkPos);

	expect($checkPos)->not->toBeFalse();
	expect($assignPos)->not->toBeFalse();
	expect($assignPos > $checkPos)->toBeTrue();
});

test('poller.php safely normalizes poller_lastrun to avoid PHP 8 TypeError on first run', function () use ($repoRoot) : void {
	$src = (string) file_get_contents($repoRoot . '/poller.php');

	expect($src)->toContain('$poller_lastrun  = is_numeric($poller_lastrun) ? (float) $poller_lastrun : 0;');
	expect($src)->toContain('if ((int) $poller_lastrun % 14440 < $current_time % 14440 || empty($poller_lastrun))');
	expect($src)->toContain('if ($poller_lastrun > 0) {');
	expect($src)->toContain('if (($poller_lastrun > 0) && (($poller_start - $poller_lastrun - 10) > MAX_POLLER_RUNTIME))');

	foreach (['', false, null, '0'] as $raw) {
		$lastrun = is_numeric($raw) ? (float) $raw : 0.0;
		$start   = 1700000123.45;
		$since   = $lastrun > 0 ? round($start - $lastrun, 2) : 'Never';
		expect($since)->toBe('Never');
		expect(($lastrun > 0) && (($start - $lastrun - 10) > 298))->toBeFalse();
	}

	$lastrun = is_numeric('1700000000') ? (float) '1700000000' : 0.0;
	expect(round(1700000060.5 - $lastrun, 2))->toBe(60.5);
});

test('script_server.php matches --environ CLI option instead of typo enviorn', function () use ($repoRoot) : void {
	$src = (string) file_get_contents($repoRoot . '/script_server.php');

	expect($src)->toContain("case 'environ':");
	expect($src)->not->toContain("case 'enviorn':");
});

test('cmd_realtime.php removes dead upfront query and caches host and SNMP session per host', function () use ($repoRoot) : void {
	$src      = (string) file_get_contents($repoRoot . '/cmd_realtime.php');
	$loopPos  = strpos($src, 'foreach($poller_items as $item)');
	$closePos = strpos($src, 'foreach ($snmp_sessions as $session)');

	expect($src)->not->toContain('$polling_items');
	expect($src)->toContain('$host_cache    = null;');
	expect($src)->toContain('$snmp_sessions = [];');
	expect($src)->toContain('if ($host_cache === null)');
	expect($src)->toContain('if (!array_key_exists($session_key, $snmp_sessions))');
	expect($loopPos)->not->toBeFalse();
	expect($closePos)->not->toBeFalse();
	expect($closePos > $loopPos)->toBeTrue();
});
