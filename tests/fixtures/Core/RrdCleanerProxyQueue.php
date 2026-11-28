<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\RrdCleanerQueue;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';
require $root . '/include/vendor/autoload.php';
require $root . '/lib/functions.php';

function read_config_option(string $name, bool $force = false) : mixed {
	return $GLOBALS['settings'][$name] ?? '';
}

function cacti_log(string $message, mixed ...$details) : void {
	$GLOBALS['logs'][] = $message;
}

function db_execute_prepared(string $sql, array $params = []) : void {
	$GLOBALS['dequeued'][] = $params[0];
}

define('CACTI_PATH_RRA', __DIR__);
define('CACTI_PATH_BASE', $root);

function maint_debug(string $message) : void {
}

function rrd_init() : string {
	return 'pipe';
}

function rrd_close(mixed $pipe) : void {
}

function rrdtool_execute(string $command, mixed ...$arguments) : bool {
	if ($command !== 'setcnn timeout off') {
		$GLOBALS['commands'][] = $command;
	}

	return true;
}

function db_fetch_assoc_prepared(string $sql, array $params) : array {
	return [];
}

function api_data_source_remove(mixed $id) : void {
	$GLOBALS['removed'][] = (int) $id;
}

\cacti_fixture_source_function($root . '/lib/rrd.php', 'rrd_strip_control_chars', __NAMESPACE__);

foreach (['rrdclean_proxy_name_is_allowed', 'remove_files'] as $function) {
	\cacti_fixture_source_function($root . '/poller_maintenance.php', $function, __NAMESPACE__);
}
$GLOBALS['archived'] = $GLOBALS['purged'] = 0;
$GLOBALS['commands'] = [];
$queue               = static function (array $names) : array {
	$rows = [];

	foreach ($names as $index => [$name, $action]) {
		$rows[] = ['id' => $index + 1, 'name' => $name, 'local_data_id' => $index + 1, 'action' => $action];
	}

	return $rows;
};

$proxy = $queue([['plain.rrd', '1'], ['sub/with space.rrd', '3'], ['../outside/other.rrd', '1'], ['sub/./kept.rrd', '3'],
	["plain.rrd\nunlink sub/kept.rrd", '1'], ['', '3'], ['sub\\..\\other.rrd', '1']]);
$GLOBALS['settings']   = ['storage_location' => 1];
$GLOBALS['dequeued']   = $GLOBALS['logs'] = $GLOBALS['removed'] = [];
remove_files($proxy);
$result = [
	'commands' => $GLOBALS['commands'],
	'dequeued' => count($GLOBALS['dequeued']),
	'removed'  => $GLOBALS['removed'],
	'skipped'  => count($GLOBALS['logs']),
	'logs'     => $GLOBALS['logs'],
];
print json_encode($result, JSON_THROW_ON_ERROR) . "\n";
