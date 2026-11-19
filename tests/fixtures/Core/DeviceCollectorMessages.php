<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiDeviceCollectorMessageFixture;

use RuntimeException;

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';
require $root . '/lib/html.php';

function __(string $format, mixed ...$values) : string {
	return vsprintf($format, $values);
}

function raise_message(string $id, string $text, int $level) : void {
	$GLOBALS['notifications'][] = $id;
	\raise_message($id, $text, $level);
}

function remote_poller_up(int $poller_id) : bool {
	return array_shift($GLOBALS['heartbeats']) ?? false;
}

function poller_push_to_remote_db_connect(int $id, bool $poller = false) : mixed {
	return array_shift($GLOBALS['connections']) ?? false;
}

function db_fetch_cell_prepared(string $sql, array $params, mixed ...$options) : int {
	return str_contains($sql, 'COUNT(') ? 1 : 2;
}

function db_execute_prepared(string $sql, array $params, mixed ...$options) : bool {
	$GLOBALS['queries'][] = [$sql, $params];

	return true;
}

function db_fetch_assoc_prepared(string $sql, array $params) : array {
	return [];
}

function gnrv(string $name) : string {
	return $_REQUEST[$name];
}

function push_out_host(int $device_id) : void {
	$GLOBALS['pushed'][] = $device_id;
}

function run_data_query(int $device_id, int $query_id) : void {
	$GLOBALS['refreshed'][] = [$device_id, $query_id];
}

function poller_warning(string $operation, array $args, array $heartbeats, array $connections = []) : void {
	$GLOBALS['heartbeats']    = $heartbeats;
	$GLOBALS['connections']   = $connections;
	$GLOBALS['queries']       = [];
	$GLOBALS['notifications'] = [];
	$_SESSION                 = [];
	$function                 = __NAMESPACE__ . '\\' . $operation;
	$function(...$args);
	$message = $_SESSION[SESS_MESSAGES]['poller_down_2'] ?? [];

	if ($GLOBALS['notifications'] !== ['poller_down_2'] || !$GLOBALS['queries'] ||
		($message['message'] ?? null) !== 'Remote Poller 2 is Down, you will need to perform a FullSync once it is up again' ||
		($message['level'] ?? null) !== MESSAGE_LEVEL_WARN) {
		throw new RuntimeException("Wrong collector warning or no local write from $operation");
	}
}

$no_http_headers  = true;
$fields_host_edit = ['description' => []];
$_REQUEST         = ['description' => 'North & South'];
$operations       = [
	'api_device_purge_from_remote' => [[17], 2],
	'api_device_disable_devices'   => [[17, 18]],
	'api_device_enable_devices'    => [[17, 18]],
	'api_device_change_options'    => [[17, 18], ['t_description' => true]],
	'api_device_clear_statistics'  => [[17, 18]],
	'api_device_dq_add'            => [17, 4, '1'],
	'api_device_dq_remove'         => [17, 4],
	'api_device_dq_change'         => [17, 4, '1'],
	'api_device_gt_remove'         => [17, 5],
];

foreach ($operations as $operation => $args) {
	cacti_fixture_source_function($root . '/lib/api_device.php', $operation, __NAMESPACE__);
	poller_warning($operation, $args, []);
	poller_warning($operation, $args, [true, true, true, true]);
}

// The device write can reach the collector while its subsequent reindex does not.
poller_warning('api_device_enable_devices', [[17]], [true, false], [1]);
poller_warning('api_device_enable_devices', [[17]], [true, true], [1, false]);

if ($GLOBALS['refreshed'] !== array_fill(0, 4, [17, 4]) || $GLOBALS['pushed'] !== [17, 18, 17, 18]) {
	throw new RuntimeException('Remote failure interrupted the local device or data query update');
}

print "Device collector notifications passed\n";
