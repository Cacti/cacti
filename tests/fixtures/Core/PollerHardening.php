<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiPollerHardeningFixture;

use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);
require $root . '/include/global_constants.php';
define('CACTI_PATH_LIBRARY', $root . '/lib');
define('CACTI_SERVER_OS', 'unix');
const CACTI_PATH_BASE              = "/fixture/path with ' quote";
$GLOBALS['config']                 = ['base_path' => $root, 'cacti_server_os' => 'unix'];
$GLOBALS['cacti_fixture_contract'] = ['name' => 'poller-hardening', 'producers' => ['poller.php', 'poller_commands.php'], 'complete' => false, 'assertions' => 0];

function read_config_option($name, $force = false) {
	return $name === 'poller_sync_interval' ? $GLOBALS['interval_control'] : '/fixture/php';
}
function db_fetch_assoc_prepared($sql, $params = [], $log = true, $db_conn = false, $timeout = 0) : array {
	$GLOBALS['captured_query'] = [$sql, $params];

	return [];
}
function cacti_sizeof($value) {
	return count($value);
}
function commands_debug($message) : void {
}
function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') : bool {
	return true;
}
function exec_background($filename, $args = '', $redirect_args = '') : void {
	$GLOBALS['captured_launches'][] = [$filename, $args];
}

cacti_fixture_source_function($root . '/poller_commands.php', 'commands_launch_child', __NAMESPACE__);
cacti_fixture_source_function($root . '/poller.php', 'poller_replicate_check', __NAMESPACE__);
cacti_fixture_source_function($root . '/lib/functions.php', 'cacti_escapeshellarg', __NAMESPACE__);

foreach ([[7, false], [0, true], [-1, false]] as [$host, $debug]) {
	$GLOBALS['seebug']            = $debug;
	$GLOBALS['captured_launches'] = [];
	commands_launch_child($host);
	$expected = ['/fixture/php', escapeshellarg(CACTI_PATH_BASE . '/poller_commands.php') . ' --child=' . $host . ($debug ? ' --debug' : '')];

	if ($GLOBALS['captured_launches'] !== [$expected]) {
		throw new RuntimeException('The child launch did not preserve its path and argument boundaries.');
	}
	$GLOBALS['cacti_fixture_contract']['assertions']++;
}

foreach ([['', 7200], [3600, 3600], ['literal interval control', 'literal interval control']] as [$interval, $expected]) {
	$GLOBALS['interval_control']  = $interval;
	$GLOBALS['captured_launches'] = [];
	poller_replicate_check();
	[$sql, $params] = $GLOBALS['captured_query'];

	if (!str_contains($sql, 'IFNULL(sync_interval, ?)') || $params !== [$expected] || $GLOBALS['captured_launches'] !== []) {
		throw new RuntimeException('The synchronization fallback was not bound or an empty result launched a child.');
	}
	$GLOBALS['cacti_fixture_contract']['assertions']++;
}
$GLOBALS['cacti_fixture_contract']['complete'] = true;
print json_encode(['passed' => $GLOBALS['cacti_fixture_contract']['assertions']], JSON_THROW_ON_ERROR) . PHP_EOL;
