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
 * Regression tests for poller, boost, dsstats, data_query, script_server,
 * and cmd_realtime algorithmic and correctness fixes.
 */

$repoRoot = dirname(__DIR__, 3);

if (!function_exists('debug_log_insert')) {
	function debug_log_insert($type, $text) {
	}
}

if (!function_exists('__esc')) {
	function __esc($text) {
		$args = func_get_args();
		array_shift($args);

		return count($args) ? vsprintf($text, $args) : $text;
	}
}

if (!function_exists('cacti_unserialize')) {
	function cacti_unserialize($data) {
		return @unserialize($data, array('allowed_classes' => false));
	}
}

if (!function_exists('rewrite_snmp_enum_value')) {
	require_once $repoRoot . '/lib/data_query.php';
}

function algo_extract_function_body($source, $functionName) {
	$needle = 'function ' . $functionName . '(';
	$start  = strpos($source, $needle);

	if ($start === false) {
		return false;
	}

	$next = strpos($source, "\nfunction ", $start + strlen($needle));

	return $next === false ? substr($source, $start) : substr($source, $start, $next - $start);
}

// =========================================================================
// 1. lib/dsstats.php
// =========================================================================

test('dsstats get_rrdfile_names deduplicates poller_item join and avoids K^2 SUM skew', function () use ($repoRoot) {
	$src  = file_get_contents($repoRoot . '/lib/dsstats.php');
	$body = algo_extract_function_body($src, 'get_rrdfile_names');

	expect($body)->not->toBeFalse();
	expect($body)->toContain('GROUP BY dtd.local_data_id, dtd.data_source_path');
	expect($body)->toContain('MAX(pi.rrd_num) AS dsses');
	expect($body)->not->toContain('SELECT SUM(rrd_num)');
	expect($body)->toContain('isset($newrows[$thread_id])');
});

test('dsstats_poller_output keys ds_types by local_data_id and ds name, batches hourly_last, and guards division by zero', function () use ($repoRoot) {
	$src  = file_get_contents($repoRoot . '/lib/dsstats.php');
	$body = algo_extract_function_body($src, 'dsstats_poller_output');

	expect($body)->not->toBeFalse();

	// Keyed by both local_data_id and data_source_name
	expect($body)->toContain("\$ds_types[(int) \$row['local_data_id']][\$row['data_source_name']]");
	expect($body)->toContain("\$ds_types[\$local_data_id][\$ds]['data_source_type_id']");
	expect($body)->not->toContain("\$ds_types[\$result['rrd_name']]");

	// Batch prefetch instead of per-sample N+1 SELECT
	expect($body)->toContain('array_chunk(array_values($counter_ids), 2000)');
	expect($body)->toContain('WHERE local_data_id IN (');
	expect($body)->not->toContain("SELECT SQL_NO_CACHE `value`\n\t\t\t\t\t\t\t\t\tFROM data_source_stats_hourly_last\n\t\t\t\t\t\t\t\t\tWHERE local_data_id = ?");

	// Guard division by $polling_interval against zero/non-positive values
	expect($body)->toContain('if ($polling_interval > 0)');
	expect($body)->toContain("\$result['output'] != 'NULL' && \$polling_interval > 0");
});

test('dsstats_boost_bottom logs BOOST statistics and omits redundant synchronous daily pass', function () use ($repoRoot) {
	$src  = file_get_contents($repoRoot . '/lib/dsstats.php');
	$body = algo_extract_function_body($src, 'dsstats_boost_bottom');

	expect($body)->not->toBeFalse();
	expect($body)->toContain("dsstats_log_statistics('BOOST');");
	expect($body)->not->toContain("dsstats_log_statistics('DAILY');");
	expect($body)->not->toContain("dsstats_get_and_store_ds_avgpeak_values('daily', 'child', 0);");
});

// =========================================================================
// 2. lib/poller.php
// =========================================================================

test('replicate_out fixes IS NOT NULL SQL syntax and OR predicate for poller_type', function () use ($repoRoot) {
	$src  = file_get_contents($repoRoot . '/lib/poller.php');
	$body = algo_extract_function_body($src, 'replicate_out');

	expect($body)->not->toBeFalse();
	expect($body)->toContain('AND last_updated IS NOT NULL');
	expect($body)->not->toContain('AND last_updated NOT NULL');
	expect($body)->toContain('OR name="poller_type"');
	expect($body)->not->toContain("WHERE name LIKE \"path_spine%\"\n\t\t\tAND name=\"poller_type\"");
});

test('replicate_out_table and replicate_table_to_poller use O(1) skipcols map and separator byte accounting', function () use ($repoRoot) {
	$src       = file_get_contents($repoRoot . '/lib/poller.php');
	$outBody   = algo_extract_function_body($src, 'replicate_out_table');
	$tableBody = algo_extract_function_body($src, 'replicate_table_to_poller');

	expect($outBody)->not->toBeFalse();
	expect($tableBody)->not->toBeFalse();

	expect($outBody)->toContain('$skipcols[$c] = true;');
	expect($outBody)->toContain('if (!isset($skipcols[$col]))');
	expect($outBody)->not->toContain('array_search($col, $skipcols)');

	expect($tableBody)->toContain('$skipcols[$c] = true;');
	expect($tableBody)->toContain('if (!isset($skipcols[$col]))');
	expect($tableBody)->not->toContain('array_search($col, $skipcols)');
	expect($tableBody)->toContain("\$row_sep  = (\$rowcnt > 0 ? ', ' : '');");
	expect($tableBody)->toContain('$row_len  = strlen($row_sep) + strlen($sql_row);');
	expect($tableBody)->toContain('$sqllen + $row_len + 1000 > $max_packet');
});

// =========================================================================
// 3. lib/data_query.php
// =========================================================================

test('get_data_query_array passes $xml_data to calculate_or_set_index_order and derives index_order', function () use ($repoRoot) {
	$src  = file_get_contents($repoRoot . '/lib/data_query.php');
	$body = algo_extract_function_body($src, 'get_data_query_array');

	expect($body)->not->toBeFalse();
	expect($body)->toContain('calculate_or_set_index_order($xml_data);');
	expect($body)->not->toContain('calculate_or_set_index_order($raw_data);');

	$xml = array(
		'fields' => array(
			'ifDescr' => array('source' => 'value'),
			'ifIndex' => array('source' => 'index'),
		),
	);

	calculate_or_set_index_order($xml);
	expect($xml['index_order'])->toBe('ifIndex');
});

test('data_query_rewrite_indexes does not double-quote prepared statement parameter or chain_indexes key', function () use ($repoRoot) {
	$src  = file_get_contents($repoRoot . '/lib/data_query.php');
	$body = algo_extract_function_body($src, 'data_query_rewrite_indexes');

	expect($body)->not->toBeFalse();
	expect($body)->not->toContain('$iv = db_qstr($iv);');
	expect($body)->toContain('array($host_id, $snmp_query_id, $iv)');
	expect($body)->toContain('$chain_indexes[$iv] = $data_query_rewrite_indexes_cache[$hash];');
});

test('rewrite_snmp_enum_value applies case-sensitive REGEXP, case-insensitive REGEXPNC, and literal escaping', function () {
	rewrite_snmp_enum_value(null);

	$map = array(
		array('match' => 'REGEXP:^Eth([0-9]+)$', 'replace' => 'Ethernet-$1'),
		array('match' => 'REGEXPNC:^ge-([0-9/]+)$', 'replace' => 'GigE-$1'),
		array('match' => 'up(1).ok/0', 'replace' => 'UP_OK'),
	);

	// Case-sensitive REGEXP must match exact case and NOT match lowercase
	expect(rewrite_snmp_enum_value('if_test', 'Eth42', $map))->toBe('Ethernet-42');
	expect(rewrite_snmp_enum_value('if_test', 'eth42', $map))->toBe('eth42');

	// Case-insensitive REGEXPNC must match regardless of case
	expect(rewrite_snmp_enum_value('if_test', 'GE-0/0/1', $map))->toBe('GigE-0/0/1');

	// Literal rule with regex metacharacters and slash must match exact literal only
	expect(rewrite_snmp_enum_value('if_test', 'up(1).ok/0', $map))->toBe('UP_OK');
	expect(rewrite_snmp_enum_value('if_test', 'up(1)Xok/0', $map))->toBe('up(1)Xok/0');

	rewrite_snmp_enum_value(null);
});

test('get_ordered_index_type_list fixes numeric/alpha flags, inspects field_value, and continues outer loop', function () use ($repoRoot) {
	$src  = file_get_contents($repoRoot . '/lib/data_query.php');
	$body = algo_extract_function_body($src, 'get_ordered_index_type_list');

	expect($body)->not->toBeFalse();
	expect($body)->toContain('$must_be_numeric = true;');
	expect($body)->toContain('$must_be_alpha = true;');
	expect($body)->not->toContain('$mustbenumeric');
	expect($body)->not->toContain('$mustbealpha');
	expect($body)->toContain("is_numeric(\$value['field_value'])");
	expect($body)->not->toContain('is_numeric($value)');
	expect(substr_count($body, 'continue 2;'))->toBe(2);
});

test('query_snmp_host enables bulk cacti_snmp_session_walk when output_format is default GUESS', function () use ($repoRoot) {
	$src  = file_get_contents($repoRoot . '/lib/data_query.php');
	$body = algo_extract_function_body($src, 'query_snmp_host');

	expect($body)->not->toBeFalse();
	expect($body)->toContain('if ($value_output_format !== SNMP_STRING_OUTPUT_GUESS)');
	expect($body)->not->toContain('if (!empty($value_output_format))');
	expect($body)->toContain('$results = cacti_snmp_session_walk($session, $oids);');
});

test('data_query_remap_indexes uses chunked batched UPDATE JOIN instead of 3N queries', function () use ($repoRoot) {
	$src  = file_get_contents($repoRoot . '/lib/data_query.php');
	$body = algo_extract_function_body($src, 'data_query_remap_indexes');

	expect($body)->not->toBeFalse();
	expect($body)->toContain('array_chunk(array_values($ids), 1000)');
	expect($body)->toContain('UPDATE graph_local AS gl');
	expect($body)->toContain('SET gl.snmp_index = dl.snmp_index');
	expect($body)->toContain('WHERE dtr.local_data_id IN (');
	expect($body)->not->toContain('SELECT snmp_index');
});

// =========================================================================
// 4. poller_boost.php
// =========================================================================

test('boost_time_to_run queries poller table instead of non-existent pollers table', function () use ($repoRoot) {
	$src  = file_get_contents($repoRoot . '/poller_boost.php');
	$body = algo_extract_function_body($src, 'boost_time_to_run');

	expect($body)->not->toBeFalse();
	expect($body)->toContain('FROM poller WHERE disabled = ""');
	expect($body)->not->toContain('FROM pollers');
});

// =========================================================================
// 5. lib/boost.php
// =========================================================================

test('boost_process_poller_output updates $last_item inside loop so duplicate filtering works', function () use ($repoRoot) {
	$src  = file_get_contents($repoRoot . '/lib/boost.php');
	$body = algo_extract_function_body($src, 'boost_process_poller_output');

	expect($body)->not->toBeFalse();

	$checkPos  = strpos($body, "if (\$last_item['timestamp'] == \$item['timestamp'] && \$last_item['rrd_name'] == \$item['rrd_name'])");
	$assignPos = strpos($body, '$last_item = $item;', $checkPos);

	expect($checkPos)->not->toBeFalse();
	expect($assignPos)->not->toBeFalse();
	expect($assignPos > $checkPos)->toBeTrue();
});

// =========================================================================
// 6. poller.php
// =========================================================================

test('poller.php safely normalizes poller_lastrun to avoid PHP 8 TypeError on first run', function () use ($repoRoot) {
	$src = file_get_contents($repoRoot . '/poller.php');

	expect($src)->toContain('$poller_lastrun  = is_numeric($poller_lastrun) ? (float) $poller_lastrun : 0;');
	expect($src)->toContain('if ((int) $poller_lastrun % 14440 < $current_time % 14440 || empty($poller_lastrun))');
	expect($src)->toContain('if ($poller_lastrun > 0) {');
	expect($src)->toContain('if (($poller_lastrun > 0) && (($poller_start - $poller_lastrun - 10) > MAX_POLLER_RUNTIME))');

	// Verify runtime behavior for empty string and false (first run) under PHP 8
	foreach (array('', false, null, '0') as $raw) {
		$lastrun = is_numeric($raw) ? (float) $raw : 0;
		$start   = 1700000123.45;
		$since   = $lastrun > 0 ? round($start - $lastrun, 2) : 'Never';
		expect($since)->toBe('Never');
		expect(($lastrun > 0) && (($start - $lastrun - 10) > 298))->toBeFalse();
	}

	// Verify runtime behavior for numeric timestamp
	$lastrun = is_numeric('1700000000') ? (float) '1700000000' : 0;
	expect(round(1700000060.5 - $lastrun, 2))->toBe(60.5);
});

// =========================================================================
// 7. script_server.php
// =========================================================================

test('script_server.php matches --environ CLI option instead of typo enviorn', function () use ($repoRoot) {
	$src = file_get_contents($repoRoot . '/script_server.php');

	expect($src)->toContain("case 'environ':");
	expect($src)->not->toContain("case 'enviorn':");
});

// =========================================================================
// 8. cmd_realtime.php
// =========================================================================

test('cmd_realtime.php removes dead upfront query and caches host and SNMP session per host', function () use ($repoRoot) {
	$src = file_get_contents($repoRoot . '/cmd_realtime.php');

	expect($src)->not->toContain('$polling_items');
	expect($src)->toContain('$host_cache    = null;');
	expect($src)->toContain('$snmp_sessions = array();');
	expect($src)->toContain('if ($host_cache === null)');
	expect($src)->toContain('if (!array_key_exists($session_key, $snmp_sessions))');
	expect($src)->toContain('foreach ($snmp_sessions as $session)');

	// Verify $session->close() runs outside the per-item switch, after the loop
	$loopPos  = strpos($src, 'foreach($poller_items as $item)');
	$closePos = strpos($src, 'foreach ($snmp_sessions as $session)');
	expect($loopPos)->not->toBeFalse();
	expect($closePos)->not->toBeFalse();
	expect($closePos > $loopPos)->toBeTrue();
});
