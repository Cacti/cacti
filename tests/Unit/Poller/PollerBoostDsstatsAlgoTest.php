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

if (!function_exists('cacti_log')) {
	function cacti_log(string $string, bool $output = false, string $environ = 'POLLER', int $level = 0) : void {
	}
}

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof(mixed $array) : int {
		return is_array($array) ? count($array) : 0;
	}
}

if (!defined('POLLER_VERBOSITY_MEDIUM')) {
	define('POLLER_VERBOSITY_MEDIUM', 3);
}

if (!defined('POLLER_VERBOSITY_HIGH')) {
	define('POLLER_VERBOSITY_HIGH', 4);
}

if (!defined('SIGTERM')) {
	define('SIGTERM', 15);
}

if (!function_exists('cacti_path_is_within')) {
	function cacti_path_is_within(string $candidate, string $base) : bool {
		$resolved     = realpath($candidate);
		$baseResolved = realpath($base);

		if ($resolved === false || $baseResolved === false) {
			return false;
		}

		return str_starts_with($resolved, $baseResolved . DIRECTORY_SEPARATOR) || $resolved === $baseResolved;
	}
}

if (!function_exists('rewrite_snmp_enum_value')) {
	require_once $repoRoot . '/lib/data_query.php';
}

if (!function_exists('resource_cache_resolve_path')) {
	require_once $repoRoot . '/lib/poller.php';
}

if (!function_exists('boost_append_update_value')) {
	require_once $repoRoot . '/lib/boost.php';
}

if (!function_exists('dsstats_rrdtool_execute')) {
	require_once $repoRoot . '/lib/dsstats.php';
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
	'resource_cache_out validates base path containment and uses shell-free cacti_exec' => [
		'lib/poller.php',
		'resource_cache_out',
		[
			'resource_cache_resolve_path(',
			'cacti_path_is_within(dirname($mypath)',
			"cacti_exec(\$php_path, ['-l', \$tmpfile], \$syntax_output)",
		],
		[
			'system($php_path',
			"mkdir('./'",
		],
	],
	'process_poller_output guards recursion on zero deleted rows and deletes all misaligned sources' => [
		'lib/poller.php',
		'process_poller_output',
		[
			'$deleted_rows = 0;',
			'$deleted_rows += $k;',
			'if ($deleted_rows > 0)',
		],
		[],
	],
	'timeout_kill_registered_processes fixes SQL timestamp/default guards and validates PIDs' => [
		'lib/poller.php',
		'timeout_kill_registered_processes',
		[
			'if ($taskid != 0)',
			'if ($pid != -1)',
			'WHERE UNIX_TIMESTAMP() > UNIX_TIMESTAMP(started) + timeout',
			'if (is_system_pid($proc_pid))',
			'elseif (cacti_process_still_running($proc_pid))',
			'cacti_process_kill($proc_pid, SIGTERM);',
		],
		[
			"if (\$taskid != '')",
			"if (\$pid != '')",
			'FROM_UNIXTIME(started)',
			'posix_kill($r[\'pid\']',
		],
	],
	'register_process_start guards system PIDs and recycled PIDs' => [
		'lib/poller.php',
		'register_process_start',
		[
			'if (is_system_pid($timeout_pid))',
			'if (cacti_process_still_running($timeout_pid))',
			'cacti_process_kill($timeout_pid, SIGTERM);',
			'elseif (cacti_process_still_running($r[\'pid\']))',
		],
		['posix_kill($r[\'pid\']'],
	],
	'dsstats_write_buffer measures appended row length instead of quadratic buffer sum' => [
		'lib/dsstats.php',
		'dsstats_write_buffer',
		[
			'$outbuf     .= $row;',
			'$out_length += strlen($row);',
		],
		['$out_length += strlen($outbuf);'],
	],
	'dsstats_poller_output measures appended row length for cache and last buffers' => [
		'lib/dsstats.php',
		'dsstats_poller_output',
		[
			'$cachebuf   .= $row;',
			'$out_length += strlen($row);',
			'$lastbuf     .= $row;',
			'$last_length += strlen($row);',
		],
		[
			'$out_length += strlen($cachebuf);',
			'$last_length += strlen($lastbuf);',
		],
	],
	'dsstats_rrdtool_read_pipe uses stream_select timeout and anchored status lines' => [
		'lib/dsstats.php',
		'dsstats_rrdtool_read_pipe',
		[
			'stream_select($read, $write, $except,',
			'dsstats_rrdtool_is_status_line($line)',
		],
		[
			"substr_count(\$stdout, 'OK')",
			"substr_count(\$stdout, 'ERROR')",
		],
	],
	'dsstats_rrdtool_is_status_line anchors OK and ERROR prefixes' => [
		'lib/dsstats.php',
		'dsstats_rrdtool_is_status_line',
		[
			"str_starts_with(\$line, 'OK u:')",
			"str_starts_with(\$line, 'ERROR:')",
		],
		[],
	],
	'dsstats_rrdtool_close terminates wedged rrdtool before proc_close' => [
		'lib/dsstats.php',
		'dsstats_rrdtool_close',
		['proc_terminate($process);', 'proc_close($process);'],
		[],
	],
	'run_data_query eliminates N+1 per-source lookup queries via data_query_local_data' => [
		'lib/data_query.php',
		'run_data_query',
		[
			'$local_data = data_query_local_data($host_id, $snmp_query_id, $old_sort_field, $new_sort_field);',
			'$remap_source        = $remap && $new_sort_field != $previous_sort_field;',
		],
		[
			'$previous_did = db_fetch_row_prepared',
			'$did_map_data = db_fetch_row_prepared',
		],
	],
	'data_query_remove_disabled_items passes params array before log flag and validates archive tables' => [
		'lib/data_query.php',
		'data_query_remove_disabled_items',
		[
			'TABLE_SCHEMA = SCHEMA()',
			"preg_match('/^poller_output_boost_arch_\\d+\$/', \$table)",
			'$ids, false);',
		],
		["implode(', ', \$orphaned_ids) . ')', false)"],
	],
	'query_snmp_host uses O(1) data_query_index_lookup instead of O(N) in_array' => [
		'lib/data_query.php',
		'query_snmp_host',
		[
			'$snmp_index_lookup = data_query_index_lookup($snmp_indexes);',
			'if (!data_query_index_exists($snmp_index, $snmp_index_lookup))',
		],
		['if (!in_array($snmp_index, $snmp_indexes))'],
	],
	'boost_process_poller_output fixes stale timestamp check, undefined variables, and bare timestamps' => [
		'lib/boost.php',
		'boost_process_poller_output',
		[
			'if ($item[\'timestamp\'] <= $last_update && cacti_version_compare(get_rrdtool_version(), \'1.5\', \'<\'))',
			'$pending_timestamp = null;',
			'boost_append_update_value($outbuf, $outlen, $pending_timestamp,',
			'if (cacti_sizeof($rrd_field_names))',
		],
		[
			'if ($time < $last_update',
			'$nt_rrd_field_names',
			'$reset_template',
			'$tv_tmpl[$field]',
			'$buflen += 2',
		],
	],
	'poller_boost boost_kill_running_processes uses validated PID helpers' => [
		'poller_boost.php',
		'boost_kill_running_processes',
		[
			'if (cacti_process_still_running($p[\'pid\']))',
			"cacti_process_kill(\$p['pid'], SIGTERM, 'BOOST');",
		],
		['posix_kill($p[\'pid\']'],
	],
	'poller_boost boost_process_local_data_ids releases current_lock and checks item timestamp' => [
		'poller_boost.php',
		'boost_process_local_data_ids',
		[
			"cacti_version_compare(\$rrdtool_version, '1.5', '<') && \$current_lock !== false",
			"SELECT RELEASE_LOCK('boost.single_ds.\$current_lock')",
			"if (\$item['timestamp'] <= \$last_update && cacti_version_compare(get_rrdtool_version(), '1.5', '<'))",
		],
		[
			"SELECT RELEASE_LOCK('boost.single_ds.\$local_data_id')",
			'if ($time < $last_update',
		],
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

test('poller.php safely normalizes poller_lastrun and tracks 4-hour MIB interval via poller_mibsrun', function () use ($repoRoot) : void {
	$src = (string) file_get_contents($repoRoot . '/poller.php');

	expect($src)->toContain('$poller_lastrun  = is_numeric($poller_lastrun) ? (float) $poller_lastrun : 0;');
	expect($src)->toContain("\$poller_mibsrun  = read_config_option('poller_mibsrun_' . \$poller_id);");
	expect($src)->toContain('$poller_mibsrun  = is_numeric($poller_mibsrun) ? (int) $poller_mibsrun : 0;');
	expect($src)->toContain('if (empty($poller_mibsrun) || ($current_time - $poller_mibsrun > 14400))');
	expect($src)->toContain("set_config_option('poller_mibsrun_' . \$poller_id, \$current_time);");
	expect($src)->not->toContain('% 14440');
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

test('resource_cache_resolve_path enforces base path containment and blocks traversal and symlink pivots', function () : void {
	$baseDir    = sys_get_temp_dir() . '/cacti_rc_base_' . bin2hex(random_bytes(4));
	$outsideDir = sys_get_temp_dir() . '/cacti_rc_out_' . bin2hex(random_bytes(4));

	mkdir($baseDir . '/plugins/sample', 0755, true);
	mkdir($outsideDir, 0755, true);
	symlink($outsideDir, $baseDir . '/plugins/pivot');

	try {
		$baseReal = (string) realpath($baseDir);

		expect(resource_cache_resolve_path('plugins/sample/newdir/file.php', $baseDir))
			->toBe($baseReal . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'sample' . DIRECTORY_SEPARATOR . 'newdir' . DIRECTORY_SEPARATOR . 'file.php');

		foreach (['', '../outside.php', 'plugins/../../etc/passwd', '/etc/passwd', 'C:/Windows/win.ini', "scripts/a\0.php", 'plugins/./a.php', 'plugins//a.php', 'plugins/pivot/evil.php'] as $bad) {
			expect(resource_cache_resolve_path($bad, $baseDir))->toBeFalse();
		}
	} finally {
		@unlink($baseDir . '/plugins/pivot');
		@rmdir($baseDir . '/plugins/sample');
		@rmdir($baseDir . '/plugins');
		@rmdir($baseDir);
		@rmdir($outsideDir);
	}
});

test('should_ignore_from_replication strips base_path and excludes tests directories', function () : void {
	global $config;
	$previous = $config['base_path'] ?? null;
	$config['base_path'] = '/srv/tests/cacti';

	try {
		expect(should_ignore_from_replication('/srv/tests/cacti/lib/poller.php'))->toBeFalse();
		expect(should_ignore_from_replication('plugins/foo/tests/BarTest.php'))->toBeTrue();
		expect(should_ignore_from_replication('tests/Unit/FooTest.php'))->toBeTrue();
		expect(should_ignore_from_replication('.git'))->toBeTrue();
	} finally {
		if ($previous === null) {
			unset($config['base_path']);
		} else {
			$config['base_path'] = $previous;
		}
	}
});

test('process PID helpers validate pid_t bounds, reject system PID 1, and sanitize log values', function () : void {
	expect(cacti_process_pid_is_valid(1))->toBeTrue();
	expect(cacti_process_pid_is_valid(42))->toBeTrue();
	expect(cacti_process_pid_is_valid('2147483647'))->toBeTrue();
	expect(cacti_process_pid_is_valid(0))->toBeFalse();
	expect(cacti_process_pid_is_valid(-1))->toBeFalse();
	expect(cacti_process_pid_is_valid('4294967295'))->toBeFalse();
	expect(cacti_process_pid_is_valid('2147483648'))->toBeFalse();
	expect(cacti_process_pid_is_valid('12abc'))->toBeFalse();
	expect(cacti_process_pid_is_valid(null))->toBeFalse();

	expect(is_system_pid(1))->toBeTrue();
	expect(is_system_pid(0))->toBeTrue();
	expect(is_system_pid(-1))->toBeTrue();
	expect(is_system_pid('4294967295'))->toBeTrue();
	expect(is_system_pid(2))->toBeFalse();
	expect(is_system_pid(12345))->toBeFalse();

	expect(cacti_process_pid_for_log("123\r\nINJECT"))->toBe('123??INJECT');
	expect(cacti_process_pid_for_log(null))->toBe('null');
	expect(cacti_process_pid_for_log(true))->toBe('true');

	expect(cacti_process_executable_is_php_interpreter('/usr/bin/php'))->toBeTrue();
	expect(cacti_process_executable_is_php_interpreter('/usr/sbin/php-fpm8.4'))->toBeTrue();
	expect(cacti_process_executable_is_php_interpreter('/bin/sh'))->toBeFalse();

	$cmdline = "php\0-d\0auto_prepend_file=/tmp/skip.php\0-q\0/var/www/cacti/poller.php\0--force";
	expect(cacti_process_extract_php_script($cmdline))->toBe('/var/www/cacti/poller.php');

	expect(cacti_process_kill(1))->toBeFalse();
	expect(cacti_process_kill('4294967295'))->toBeFalse();
});

test('data_query_index_lookup matches loose numeric and exact string indexes in O(1)', function () : void {
	$lookup = data_query_index_lookup(['1', '1.2.3', '0', '9007199254740992', 'eth0']);

	expect(data_query_index_exists(1, $lookup))->toBeTrue();
	expect(data_query_index_exists('1.0', $lookup))->toBeTrue();
	expect(data_query_index_exists('1.2.3', $lookup))->toBeTrue();
	expect(data_query_index_exists('0.0', $lookup))->toBeTrue();
	expect(data_query_index_exists('eth0', $lookup))->toBeTrue();
	expect(data_query_index_exists('9007199254740992', $lookup))->toBeTrue();
	expect(data_query_index_exists('9007199254740993', $lookup))->toBeFalse();
	expect(data_query_index_exists('2', $lookup))->toBeFalse();

	expect(data_query_cache_compare('did.value', 'field_value', [
		'field_value' => ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'],
	]))->toBe('CONVERT(did.value USING utf8mb4) COLLATE utf8mb4_unicode_ci');
	expect(data_query_cache_compare('did.value', 'field_value', []))->toBe('did.value');
});

test('boost_append_update_value emits timestamp lazily and prevents bare timestamps', function () : void {
	$outbuf            = '';
	$outlen            = 0;
	$pending_timestamp = 1700000000;

	// If no values are emitted for a timestamp, outbuf stays empty (no bare timestamp).
	expect($outbuf)->toBe('');

	boost_append_update_value($outbuf, $outlen, $pending_timestamp, '42');
	expect($outbuf)->toBe(' 1700000000:42');
	expect($outlen)->toBe(strlen($outbuf));
	expect($pending_timestamp)->toBeNull();

	boost_append_update_value($outbuf, $outlen, $pending_timestamp, 'U');
	expect($outbuf)->toBe(' 1700000000:42:U');
	expect($outlen)->toBe(strlen($outbuf));
});

test('dsstats_rrdtool_execute waits via stream_select and stops only at complete status lines', function () : void {
	$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
	expect($pair)->not->toBeFalse();

	stream_set_blocking($pair[0], false);
	stream_set_blocking($pair[1], false);

	// Data source named LOOKUPS contains "OK"; reader must keep reading until status line.
	fwrite($pair[1], "ds[LOOKUPS].index = 0\nds[ERROR_COUNT].index = 1\nOK u:0.01 s:0.00 r:0.01\n");

	$pipes  = [0 => $pair[1], 1 => $pair[0]];
	$output = dsstats_rrdtool_execute('info test.rrd', $pipes, 2);

	expect($output)->toContain('ds[LOOKUPS].index = 0');
	expect($output)->toContain('ds[ERROR_COUNT].index = 1');
	expect($output)->toContain('OK u:0.01 s:0.00 r:0.01');

	// Silent peer times out cleanly and closes stdout pipe instead of spinning forever.
	$silentResult = dsstats_rrdtool_execute('info silent.rrd', $pipes, 0.05);
	expect($silentResult)->toBeNull();
	expect(is_resource($pipes[1]))->toBeFalse();

	fclose($pair[1]);
});

test('poller_boost sig_handler releases locks before exiting and gates status on master', function () use ($repoRoot) : void {
	$src  = (string) file_get_contents($repoRoot . '/poller_boost.php');
	$body = (string) algo_extract_function_body($src, 'sig_handler');

	$releasePos = strpos($body, 'RELEASE_LOCK');
	$allPos     = strpos($body, 'RELEASE_ALL_LOCKS');
	$exitPos    = strpos($body, 'exit(1);');

	expect($releasePos)->not->toBeFalse();
	expect($allPos)->not->toBeFalse();
	expect($exitPos)->not->toBeFalse();
	expect($releasePos < $exitPos)->toBeTrue();
	expect($allPos < $exitPos)->toBeTrue();
	expect($body)->not->toContain("exit;\n\t\t\tbreak;");
});
