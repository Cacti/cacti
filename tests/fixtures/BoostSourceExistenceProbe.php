<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
$root     = dirname(__DIR__, 2);
$scenario = $argv[1] ?? 'deleted';
$queries  = 0;
require __DIR__ . '/ProbeLoader.php';

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
