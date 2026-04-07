<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$boostSource  = file_get_contents(__DIR__ . '/../../lib/boost.php');
$pollerSource = file_get_contents(__DIR__ . '/../../poller.php');
$pollerLib    = file_get_contents(__DIR__ . '/../../lib/poller.php');

test('boost.php escapes poller path with cacti_escapeshellarg', function () use ($boostSource) {
	expect($boostSource)->toContain("cacti_escapeshellarg(\$config['base_path'] . '/poller_boost.php')");
});

test('boost.php escapes log path with cacti_escapeshellarg', function () use ($boostSource) {
	expect($boostSource)->toContain('cacti_escapeshellarg($boost_log)');
});

test('poller.php casts poller_id to int', function () use ($pollerSource) {
	expect($pollerSource)->toContain('$poller_id = (int)$value');
});

test('poller.php escapes stderrlog path', function () use ($pollerSource) {
	expect($pollerSource)->toContain("cacti_escapeshellarg(read_config_option('path_stderrlog'))");
});

test('exec_background strips shell operators from string args', function () use ($pollerLib) {
	$start = strpos($pollerLib, 'function exec_background(');
	$body = substr($pollerLib, $start, 800);
	expect($body)->toContain("preg_replace('/[&;|]+/', '', \$args)");
});
