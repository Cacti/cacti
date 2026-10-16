<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 3) . '/Helpers/RequestCoverage.php';
$root = dirname(__DIR__, 4);
require $root . '/include/global_constants.php';
if (!defined('CACTI_LOCALE')) {
	define('CACTI_LOCALE', 'en-US');
}
require $root . '/lib/rrd.php';
$config = array(
	'base_path' => $root,
	'url_path' => '/cacti/',
	'cacti_server_os' => 'unix',
	'library_path' => $root . '/lib',
	'include_path' => $root . '/include',
	'poller_id' => 2,
	'is_web' => false,
	'connection' => 'online',
);

/** Translate fixture labels without loading the web application. */
function __($text, ...$args) {
	return $text;
}

/** Compare fixture versions using PHP's ordinary version comparison. */
function cacti_version_compare($left, $right, $operator) {
	return version_compare($left, $right, $operator);
}

/** Supply an installed RRDtool version without probing a binary. */
function get_rrdtool_version() {
	return '1.7.0';
}

/** Keep unrelated authentication menus empty in the isolated renderer fixture. */
function get_auth_realms() {
	return array();
}

/** Translate contextual fixture labels without loading gettext. */
function __x($context, $text) {
	return $text;
}

/** No installed plugins participate in the isolated renderer fixture. */
function api_plugin_hook($name) {
}

/** Model a stale session option and the current database value. */
function read_config_option($name, $force = false) {
	if ($name === 'poller_lastrun_1') {
		$GLOBALS['poll_time_reads'][] = $force;
		return $force ? $GLOBALS['poll_time_database'] : time() - 1800;
	}
	return $name === 'poller_interval' ? '300' : '0';
}

/** This boundary is never reached because the database stub stops first. */
function array_rekey($rows, $key, $value) {
	throw new RuntimeException('Renderer continued beyond the time-selection boundary');
}

/** Stop after the real renderer has selected its time range, before database or RRD access. */
function db_fetch_assoc_prepared($sql, $params) {
	foreach (debug_backtrace() as $frame) {
		if (($frame['function'] ?? '') === 'rrdtool_function_graph') {
			$GLOBALS['poll_time_result'] = $frame['args'][2];
			throw new RuntimeException('poll-time-fixture-stop');
		}
	}
	throw new RuntimeException('Unexpected database access');
}

$cases = array(
	'earlier' => array(-900, array()),
	'automatic' => array(-300, array()),
	'historical' => array(-300, array('graph_start' => 1700000000, 'graph_end' => 1700003600)),
	'missing' => array(null, array()),
	'future' => array(300, array()),
	'custom_start' => array(-300, array('graph_start' => -3600)),
);
$results = array();
foreach ($cases as $name => [$offset, $input]) {
	$GLOBALS['poll_time_database'] = $offset === null ? '' : (string) (time() + $offset);
	$GLOBALS['poll_time_reads'] = array();
	unset($GLOBALS['poll_time_result']);
	$before = time();
	try {
		rrdtool_function_graph(1, null, $input);
		throw new RuntimeException('Renderer did not reach the time-selection boundary');
	} catch (RuntimeException $error) {
		if ($error->getMessage() !== 'poll-time-fixture-stop') {
			throw $error;
		}
	}
	$results[$name] = array(
		'times' => $GLOBALS['poll_time_result'],
		'reads' => $GLOBALS['poll_time_reads'],
		'database' => $GLOBALS['poll_time_database'],
		'before' => $before,
		'after' => time(),
	);
}
print json_encode($results, JSON_THROW_ON_ERROR) . PHP_EOL;
