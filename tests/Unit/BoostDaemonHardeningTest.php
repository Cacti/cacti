<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$boostSource  = file_get_contents(__DIR__ . '/../../lib/boost.php');
$pollerSource = file_get_contents(__DIR__ . '/../../lib/poller.php');
$funcSource   = file_get_contents(__DIR__ . '/../../lib/functions.php');

test('boost_graph_set_file uses umask instead of chmod', function () use ($boostSource) {
	$start = strpos($boostSource, 'function boost_graph_set_file(');
	$body = substr($boostSource, $start, 1500);
	expect($body)->toContain('umask(');
	expect($body)->not->toContain('chmod($cache_file');
});

test('boost_graph_cache_check casts IDs to int', function () use ($boostSource) {
	$start = strpos($boostSource, 'function boost_graph_cache_check(');
	$body = substr($boostSource, $start, 500);
	expect($body)->toContain('$local_graph_id = (int)$local_graph_id');
	expect($body)->toContain('$rra_id         = (int)$rra_id');
});

test('boost_graph_set_file casts IDs to int', function () use ($boostSource) {
	$start = strpos($boostSource, 'function boost_graph_set_file(');
	$body = substr($boostSource, $start, 500);
	expect($body)->toContain('(int)$local_graph_id');
	expect($body)->toContain('(int)$rra_id');
});

test('boost GET_LOCK has retry limit', function () use ($boostSource) {
	expect($boostSource)->toContain('$max_attempts');
	expect($boostSource)->toContain('if (++$lock_attempts >= $max_attempts)');
});

test('exec_with_timeout does not use exec setsid', function () use ($pollerSource) {
	$start = strpos($pollerSource, 'function exec_with_timeout(');
	$body = substr($pollerSource, $start, 1000);
	expect($body)->not->toContain('exec setsid');
	expect($body)->toContain('proc_open($cmd,');
});

test('cacti_unserialize blocks object injection on PHP 5', function () use ($funcSource) {
	$start = strpos($funcSource, 'function cacti_unserialize(');
	$body = substr($funcSource, $start, 600);
	expect($body)->toContain("(O|C):[0-9]+:");
	expect($body)->toContain("'allowed_classes' => false");
});

test('cacti_unserialize rejects null and empty input', function () use ($funcSource) {
	$start = strpos($funcSource, 'function cacti_unserialize(');
	$body = substr($funcSource, $start, 300);
	expect($body)->toContain("\$strobj === null || \$strobj === ''");
});
