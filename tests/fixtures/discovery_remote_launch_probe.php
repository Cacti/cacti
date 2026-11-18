<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

declare(strict_types = 1);

namespace DiscoveryRemoteLaunchFixture;

/*
 * Manual discovery of a network owned by a remote collector, one side per run.
 * "main" reports the remote agent URL the main server requests; "remote"
 * replays that URL's query into the remote agent and reports what it launches.
 */

$side = $argv[1];

define('POLLER_ID', $side === 'main' ? 1 : 2);
define('CACTI_PATH_URL', '/cacti/');

$launches = [];
$urls     = [];

require dirname(__DIR__) . '/Helpers/SourceSlice.php';

function db_fetch_cell_prepared(string $sql, array $params = []) : mixed {
	return match (true) {
		str_contains($sql, 'SELECT enabled')   => 'on',
		str_contains($sql, 'count(*)')         => 0,
		str_contains($sql, 'SELECT name')      => 'Remote Network',
		str_contains($sql, 'SELECT poller_id') => 2,
	};
}

function call_remote_data_collector(int $poller_id, string $url, string $logtype = '') : string {
	$GLOBALS['urls'][] = [$poller_id, $url];

	return '';
}

function read_config_option(string $name) : string {
	return match ($name) {
		'path_php_binary' => '/usr/bin/php',
		'path_webroot'    => '/var/www/cacti',
	};
}

function exec_background(string $filename, string|array $args = '') : void {
	$GLOBALS['launches'][] = [$filename, $args];
}

function raise_message(mixed ...$args) : void {
	throw new \RuntimeException('Unexpected discovery refusal');
}

function __esc(string $text, mixed ...$args) : string {
	return $text;
}

function force_session_data() : void {
}

function cacti_escapeshellarg(string $value) : string {
	return "'" . $value . "'";
}

function cacti_escapeshellcmd(string $value) : string {
	return $value;
}

function gfrv(string $name) : string {
	return (string) $_REQUEST[$name];
}

function isrv(string $name) : bool {
	return isset($_REQUEST[$name]);
}

function sleep(int $seconds) : int {
	return 0;
}

if ($side === 'main') {
	$scenario = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);

	\cacti_fixture_source_function(dirname(__DIR__, 2) . '/automation_networks.php', 'api_networks_discover', __NAMESPACE__);
	api_networks_discover(7, $scenario['debug'], $scenario['dryrun']);
} else {
	parse_str((string) parse_url($argv[2], PHP_URL_QUERY), $_REQUEST);

	\cacti_fixture_source_function(dirname(__DIR__, 2) . '/remote_agent.php', 'run_remote_discovery', __NAMESPACE__);
	run_remote_discovery();
}

echo json_encode(['launches' => $launches, 'urls' => $urls], JSON_THROW_ON_ERROR), PHP_EOL;
