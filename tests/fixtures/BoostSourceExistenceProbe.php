<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
$root     = dirname(__DIR__, 2);
$scenario = $argv[1] ?? 'deleted';
$queries  = 0;
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

function db_fetch_assoc_prepared(...$args) {
	$GLOBALS['queries']++;

	return $GLOBALS['scenario'] === 'deleted' ? [] : false;
}
function db_fetch_cell_prepared(...$args) {
	$GLOBALS['queries']++;

	return false;
}
function read_config_option(...$args) {
	return '';
}
function cacti_rrdtool_valid_path(...$args) {
	return true;
}
function cacti_log(...$args) {
}
function boost_rrdtool_function_create(...$args) {
	throw new RuntimeException('Unverified or deleted data source must not create an RRD.');
}
probe_load($root . '/lib/boost.php','boost_rrdtool_function_update');
$values = '1700000000:42';
$pipe   = null;
$debug  = false;
$result = boost_rrdtool_function_update(1,sys_get_temp_dir() . '/missing-' . bin2hex(random_bytes(12)) . '.rrd','in',$values,$pipe);
print json_encode(['result'=>$result, 'queries'=>$queries],JSON_THROW_ON_ERROR);
