<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

declare(strict_types = 1);

namespace DiscoveryWorkerAddressFixture;

use RuntimeException;

require dirname(__DIR__, 2) . '/include/global_constants.php';

$address     = $argv[1];
$scenario    = $argv[2] ?? 'probe';
$dryrun      = true;
$running     = [];
$snmp        = [];
$claims      = 0;
$writes      = [];
$host_adds   = [];
$reruns      = [];
$removed     = [];
$matches     = 0;
$up_counts   = 0;
$snmp_counts = 0;
$hosts       = [];

if (in_array($scenario, ['managed-ip', 'managed-dns', 'deleted'], true)) {
	$hosts[] = ['id' => 41, 'hostname' => $scenario              === 'managed-dns' ? 'managed.example' : $address,
		'snmp_version'  => 2, 'status' => 3, 'deleted' => $scenario === 'deleted' ? 'on' : ''];
}

require dirname(__DIR__) . '/Helpers/SourceSlice.php';
\cacti_fixture_source_function(dirname(__DIR__, 2) . '/poller_automation.php', 'discoverDevices', __NAMESPACE__);
\cacti_fixture_source_function(dirname(__DIR__, 2) . '/lib/functions.php', 'cacti_strtolower', __NAMESPACE__);

function db_fetch_row_prepared(string $sql, array $params): array {
	if (str_contains($sql, 'FROM automation_networks')) {
		return ['dns_servers' => '192.0.2.53', 'enable_netbios' => '',
			'snmp_id'            => 7, 'poller_id' => 1, 'site_id' => 0,
			'ping_retries'       => 0, 'ping_port' => 0, 'ping_method' => PING_SNMP,
			'same_sysname'       => $GLOBALS['scenario'] === 'duplicate-allowed' ? 'on' : ''];
	}

	if (str_contains($sql, 'FROM automation_ips')) {
		return ['ip_address' => $GLOBALS['address']];
	}

	if (str_contains($sql, 'FROM host')) {
		foreach ($GLOBALS['hosts'] as $host) {
			if (in_array($host['hostname'], $params, true)) {
				return $host;
			}
		}

		return [];
	}

	throw new RuntimeException('Unexpected database lookup');
}

function db_fetch_assoc(string $sql): array {
	return [];
}

function db_fetch_cell_prepared(string $sql, array $params): string|int {
	if (str_contains($sql, 'SELECT command')) {
		return match ($GLOBALS['scenario']) {
			'cancel' => 'cancel', 'missing-master' => '', default => 'running'
		};
	}

	if (str_contains($sql, 'FROM automation_devices')) {
		// A previously discovered different IP has the exact same sysName.
		return (int) ($params === [7, $GLOBALS['address'], 'shared.example']);
	}

	if (str_contains($sql, 'FROM host')) {
		return 0;
	}

	throw new RuntimeException('Unexpected database count');
}

function db_execute_prepared(string $sql, array $params): bool {
	$GLOBALS['writes'][] = [$sql, $params];

	if (str_contains($sql, 'UPDATE automation_ips') && str_contains($sql, 'SET pid')) {
		$GLOBALS['claims']++;
	}

	return true;
}

function automation_get_dns_from_ip(string $address, string $dns, int $timeout): string {
	return $GLOBALS['scenario'] === 'managed-dns' ? 'managed.example' : $address;
}

function automation_debug(string $message): void {
}

function cacti_log(mixed ...$args): void {
}

function automation_get_pid(): string {
	return 'fixture';
}

function cacti_sizeof(mixed $value): int {
	return is_array($value) ? count($value) : 0;
}

function markIPRunning(string $address, int $network_id): void {
	$GLOBALS['running'][] = $address;
}

function markIPDone(string $address, int $network_id): void {
	throw new RuntimeException('worker-finished');
}

function automation_valid_snmp_device(array &$device): bool {
	$GLOBALS['snmp'][] = $device['ip_address'];

	if (str_starts_with($GLOBALS['scenario'], 'duplicate-')) {
		$device['snmp_sysName'] = 'shared.example';

		return true;
	}

	throw new RuntimeException('worker-reached-snmp');
}

function gethostbyaddr(string $address): string {
	return $address;
}

function is_ipaddress(string $address): bool {
	return filter_var($address, FILTER_VALIDATE_IP) !== false;
}

function db_like_escape(string $value): string {
	return $value;
}

function addUpDevice(int $network_id, int $pid): void {
	$GLOBALS['up_counts']++;
}

function addSNMPDevice(int $network_id, int $pid): void {
	$GLOBALS['snmp_counts']++;
}

function rerunDataQueries(int $host_id, array $network): void {
	$GLOBALS['reruns'][] = $host_id;
}

function removeMyProcess(int $pid, int $network_id): void {
	$GLOBALS['removed'][] = $network_id;
}

function killProcess(int $pid): void {
	throw new RuntimeException('worker-cancelled');
}

function automation_find_os(string $description, string $oid, string $name): array|false {
	$GLOBALS['matches']++;

	throw new RuntimeException('worker-matched');
}

function automation_add_device(array $device): int {
	$GLOBALS['host_adds'][] = $device;

	throw new RuntimeException('unexpected-host-add');
}

final class Net_Ping {
	public array $host  = [];
	public int $retries = 0;
	public int $port    = 0;
}

try {
	discoverDevices(7, 1);
} catch (RuntimeException $error) {
	if (!in_array($error->getMessage(), ['worker-finished', 'worker-reached-snmp', 'worker-cancelled', 'worker-matched'], true)) {
		throw $error;
	}
}
echo json_encode(compact('running', 'snmp', 'claims', 'writes', 'host_adds', 'reruns', 'removed', 'matches', 'up_counts', 'snmp_counts'), JSON_THROW_ON_ERROR), PHP_EOL;
