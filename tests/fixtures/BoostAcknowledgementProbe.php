<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
$root     = dirname(__DIR__, 2);
$scenario = $argv[1] ?? 'success';
$calls    = [];
$rows     = [];

for ($i = 0; $i < 999; $i++) {
	$rows[] = ['timestamp'=>1700000000 + $i, 'rrd_name'=>'in'];
}
$rows[] = ['timestamp'=>1700001000, 'rrd_name'=>'in'];
$rows[] = ['timestamp'=>1700001000, 'rrd_name'=>'out'];
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

function db_execute_prepared($sql, $params, ...$args) {
	$GLOBALS['calls'][] = $params;

	if ($GLOBALS['scenario'] === 'failed-delete' && count($GLOBALS['calls']) === 2) {
		return false;
	}
	$keys = [];

	for ($i = 1; $i < count($params); $i += 2) {
		$keys[$params[$i + 1] . ':' . $params[$i]] = true;
	}
	$GLOBALS['rows'] = array_values(array_filter($GLOBALS['rows'],fn ($row) =>!isset($keys[$row['timestamp'] . ':' . $row['rrd_name']])));

	return true;
}
probe_load($root . '/lib/boost.php','boost_delete_output_keys');
$result = boost_delete_output_keys(1,$rows);
print json_encode(['result'=>$result, 'remaining'=>$rows, 'batch_sizes'=>array_map(fn ($params) =>(count($params) - 1) / 2,$calls)],JSON_THROW_ON_ERROR);
