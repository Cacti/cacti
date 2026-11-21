<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
declare(strict_types = 1);

namespace DiscoveryWorkerPingCoverage;

use RuntimeException;

require dirname(__DIR__, 2) . '/include/global_constants.php';
require dirname(__DIR__) . '/Helpers/SourceSlice.php';
\cacti_fixture_source_function(dirname(__DIR__, 2) . '/poller_automation.php', 'discoverDevices', __NAMESPACE__);

$alive      = ($argv[1] ?? '') === 'alive';
$dryrun     = false;
$dequeued   = false;
$snmp_calls = 0;
$up         = 0;
$down       = [];
$completed  = [];
$found      = [];

function db_fetch_row_prepared(string $sql, array $parameters): array {
	if (str_contains($sql, 'FROM automation_networks')) {
		return ['name'    => 'Isolated ping discovery', 'dns_servers' => '192.0.2.53',
			'enable_netbios' => '', 'snmp_id' => 7, 'poller_id' => 1, 'site_id' => 0,
			'ping_method'    => PING_TCP, 'ping_timeout' => 100, 'ping_retries' => 0, 'ping_port' => 80];
	}

	if (str_contains($sql, 'FROM automation_ips')) {
		if ($GLOBALS['dequeued']) {
			return [];
		}
		$GLOBALS['dequeued'] = true;

		return ['ip_address' => '192.0.2.255'];
	}

	if (str_contains($sql, 'FROM host')) {
		return [];
	}

	throw new RuntimeException('Unexpected discovery lookup');
}
function db_fetch_cell_prepared(string $sql, array $parameters): string {
	if (!str_contains($sql, 'SELECT command')) {
		throw new RuntimeException('Unexpected master lookup');
	}

	return 'running';
}
function db_execute_prepared(string $sql, array $parameters): bool {
	if (!str_contains($sql, 'UPDATE automation_ips')) {
		throw new RuntimeException('Unexpected discovery write');
	}

	return true;
}
function db_execute(string $sql): bool {
	if (!str_starts_with($sql, 'REPLACE INTO automation_devices')) {
		throw new RuntimeException('Unexpected discovered-device write');
	}
	$GLOBALS['found'][] = $sql;

	return true;
}
function db_qstr(string|int $value): string {
	return "'" . str_replace("'", "''", (string) $value) . "'";
}
function cacti_sizeof(mixed $value): int {
	return is_array($value) ? count($value) : 0;
}
function cacti_log(mixed ...$arguments): void {
}
function automation_debug(string $message): void {
}
function automation_get_pid(): string {
	return 'isolated-coverage';
}
function automation_get_dns_from_ip(string $address, string $dns, int $timeout): string {
	return $address;
}
function markIPRunning(string $address, int $network): void {
}
function markIPDone(string $address, int $network): void {
	$GLOBALS['completed'][] = $address;
}
function addUpDevice(int $network, int $pid): void {
	$GLOBALS['up']++;
}
function updateDownDevice(int $network, string $address): void {
	$GLOBALS['down'][] = $address;
}
function automation_valid_snmp_device(array &$device): bool {
	$GLOBALS['snmp_calls']++;

	return false;
}
final class Net_Ping {
	public array $host  = [];
	public int $retries = 0;
	public int $port    = 0;
	public function ping(int $availability, int $method, int $timeout, int $attempts): bool {
		if ($this->host !== ['hostname' => '192.0.2.255'] || $availability !== AVAIL_PING || $method !== PING_TCP || $timeout !== 100 || $attempts !== 1) {
			throw new RuntimeException('Unexpected discovery ping arguments');
		}

		return $GLOBALS['alive'];
	}
}

$finished  = discoverDevices(7, 1);
$ping_only = count($found) === 1 && str_contains($found[0], '"", 0, 1,');
echo json_encode(compact('finished', 'snmp_calls', 'up', 'down', 'completed', 'ping_only'), JSON_THROW_ON_ERROR), PHP_EOL;
