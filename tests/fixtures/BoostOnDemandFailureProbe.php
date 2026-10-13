<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
$root     = getenv('CACTI_BOOST_SOURCE_ROOT') ?: dirname(__DIR__, 2);
$scenario = $argv[1] ?? 'first';
$writes   = $deletes = 0;
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
	if (!str_contains($sql, 'AS po')) {
		return [];
	}
	$rows = [];

	for ($i = 0; $i < 4; $i++) {
		$rows[] = ['local_data_id'=>1, 'data_template_id'=>1, 'timestamp'=>1700000000 + $i * 60, 'rrd_name'=>'in', 'output'=>'42'];
	}

	return $rows;
}
function db_fetch_cell(...$args) {
	return 0;
}
function db_execute($sql, ...$args) {
	return !($GLOBALS['scenario'] === 'temp-create' && str_starts_with($sql, 'CREATE TEMPORARY TABLE'));
}
function db_execute_prepared(...$args) {
	if ($GLOBALS['scenario'] === 'archive-copy' && str_starts_with($args[0], 'INSERT IGNORE INTO')) {
		return false;
	}

	if (str_starts_with($args[0], 'DELETE')) {
		$GLOBALS['deletes']++;
	}

	return true;
}
function boost_get_arch_table_names(...$args) {
	return in_array($GLOBALS['scenario'], ['archive-copy', 'temp-create'], true) ? ['poller_output_boost_arch_1'] : [];
}
function boost_get_unused_data_source_names(...$args) {
	return [];
}
function boost_get_rrd_filename_and_template(...$args) {
	return ['rrd_path'=>'source.rrd', 'rrd_template'=>'in'];
}
function boost_rrdtool_function_update(...$args) {
	$call = ++$GLOBALS['writes'];

	return $call === ($GLOBALS['scenario'] === 'first' ? 1 : 2) ? 'ERROR: rejected' : 'OK';
}
function read_config_option($key) {
	return $key === 'boost_rrd_update_string_length' ? '1' : '100';
}
function boost_error_handler(...$args) {
	return false;
}
function cacti_system_zone_set() {
}
function cacti_log(...$args) {
}
function boost_timer(...$args) {
}
function rrd_init(...$args) {
	return [];
}
function rrd_close(...$args) {
}
function get_rrdtool_version(...$args) {
	return '1.8';
}
function cacti_version_compare($a,$b,$op) {
	return version_compare($a,$b,$op);
}

foreach (['BOOST_TIMER_START'=>0, 'BOOST_TIMER_END'=>1, 'POLLER_VERBOSITY_MEDIUM'=>3, 'POLLER_VERBOSITY_HIGH'=>4] as $name=>$value) {
	define($name,$value);
}
define('CACTI_PATH_LIBRARY',__DIR__ . '/../performance/fixtures');

foreach (['cacti_sizeof', 'cacti_count', 'array_rekey'] as $name) {
	probe_load($root . '/lib/functions.php',$name);
}
probe_load($root . '/lib/boost.php','boost_process_poller_output');
$config     = ['library_path'=>CACTI_PATH_LIBRARY];
$get_memory = false;
$result     = boost_process_poller_output(1);
print json_encode(['result'=>$result, 'writes'=>$writes, 'deletes'=>$deletes],JSON_THROW_ON_ERROR);
