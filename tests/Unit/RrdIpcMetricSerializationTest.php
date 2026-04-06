<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$rrdSource = file_get_contents(__DIR__ . '/../../lib/rrd.php');

test('rrdtool_function_update trims string values before is_numeric', function () use ($rrdSource) {
	$start = strpos($rrdSource, 'function rrdtool_function_update(');
	$body = substr($rrdSource, $start, 3000);
	expect($body)->toContain('trim($value)');
});

test('rrdtool_function_update rejects empty strings as U', function () use ($rrdSource) {
	$start = strpos($rrdSource, 'function rrdtool_function_update(');
	$body = substr($rrdSource, $start, 3000);
	expect($body)->toContain("\$value === ''");
});

test('rrdtool_function_update uses locale-safe decimal replacement', function () use ($rrdSource) {
	$start = strpos($rrdSource, 'function rrdtool_function_update(');
	$body = substr($rrdSource, $start, 3000);
	expect($body)->toContain("str_replace(',', '.', (string)\$value)");
});

test('rrdtool_function_update does not directly append raw value', function () use ($rrdSource) {
	$start = strpos($rrdSource, 'function rrdtool_function_update(');
	$body = substr($rrdSource, $start, 3000);
	expect($body)->not->toContain('$rrd_update_values .= $value;');
});
