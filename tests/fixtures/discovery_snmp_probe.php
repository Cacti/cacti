<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

namespace DiscoverySnmpFixture;

use RuntimeException;

// Transport fakes belong to this child process, never Pest collection.
const HOST_DOWN                = 1;
const HOST_UP                  = 3;
const CACTI_WEB                = true;
const SNMP_STRING_OUTPUT_GUESS = 1;
const REGEXP_SNMP_TRIM         = '/(counter(32|64)|gauge|gauge(32|64)|float|ipaddress|string|integer):/i';

$banned_snmp_strings = ['End of MIB', 'No Such', 'No more'];
$attempts            = [];
$queries             = [];
$sessions            = [];
$gets                = [];
$scenario            = $argv[1] ?? 'success';
$plans               = match ($scenario) {
	'success'        => ['.1.3.6.1.4.1.9.1.1', '.1.3.6.1.4.1.11.1', '.1.3.6.1.4.1.2636.1'],
	'no-response'    => [null, null, null],
	'recovery'       => [false, 'U', '.1.3.6.1.4.1.9.1.1'],
	'outage'         => [false, false, false],
	'unknown'        => ['U'],
	'empty'          => [''],
	'no-object'      => ['No Such Object available on this agent at this OID'],
	'no-instance'    => ['No Such Instance currently exists at this OID'],
	'error'          => ['Error In packet'],
	'no-credentials' => [],
	default          => ['.1.3.6.1.4.1.9.1.1'],
};

require dirname(__DIR__) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 2);

foreach (['format_snmp_string', 'cacti_snmp_select_uptime'] as $name) {
	\cacti_fixture_source_function($root . '/lib/snmp.php', $name, __NAMESPACE__);
}

foreach (['is_hex_string', 'cacti_strtolower'] as $name) {
	\cacti_fixture_source_function($root . '/lib/functions.php', $name, __NAMESPACE__);
}
\cacti_fixture_source_function($root . '/lib/api_automation.php', 'automation_valid_snmp_device', __NAMESPACE__);

function automation_debug(string $text): void {
}

function cacti_sizeof(mixed $value): int {
	return is_array($value) ? count($value) : 0;
}

function cacti_oid_numeric_format(): void {
}

function db_fetch_assoc_prepared(string $sql, array $params): array {
	global $queries, $plans;
	$queries[] = [$sql, $params];
	$rows      = [];

	foreach ($plans as $index => $plan) {
		$rows[] = [
			'snmp_id'            => 7, 'snmp_version' => (string) ($index + 1),
			'snmp_port'          => 1161, 'snmp_timeout' => 125, 'snmp_retries' => 1,
			'snmp_community'     => 'fixture-community-' . $index,
			'snmp_username'      => 'fixture-user', 'snmp_password' => 'fixture-auth',
			'snmp_auth_protocol' => 'SHA256', 'snmp_priv_passphrase' => 'fixture-privacy',
			'snmp_priv_protocol' => 'AES', 'snmp_context' => 'fixture-context',
			'snmp_engine_id'     => '8000000001', 'max_oids' => 4, 'bulk_walk_size' => 8,
		];
	}

	return $rows;
}

final class DiscoveryProbeSession {
	public bool $closed = false;
	public function __construct(public mixed $objectId) {
	}
	public function close(): void {
		$this->closed = true;
	}
}

function cacti_snmp_session(mixed ...$args): DiscoveryProbeSession|false {
	global $attempts, $sessions, $plans;
	$index      = count($attempts);
	$attempts[] = $args;

	if ($plans[$index] === false) {
		return false;
	}
	$session    = new DiscoveryProbeSession($plans[$index]);
	$sessions[] = $session;

	return $session;
}

function cacti_snmp_session_get(DiscoveryProbeSession $session, string $oid): mixed {
	global $gets, $scenario;
	$gets[] = $oid;
	$value  = match ($oid) {
		'.1.3.6.1.2.1.1.2.0'      => $session->objectId,
		'.1.3.6.1.2.1.1.5.0'      => 'fixture-device',
		'.1.3.6.1.2.1.1.6.0'      => $scenario === 'partial' ? false : 'fixture-location',
		'.1.3.6.1.2.1.1.4.0'      => '',
		'.1.3.6.1.2.1.1.1.0'      => $scenario === 'partial' ? false : 'fixture-description',
		'.1.3.6.1.2.1.1.3.0'      => '0',
		'.1.3.6.1.6.3.10.2.1.3.0' => false,
		default                   => throw new RuntimeException('Unexpected OID'),
	};

	return $value === false || $value === null ? false : format_snmp_string($value, false);
}

$device = ['snmp_id' => 7, 'ip_address' => '192.0.2.8',
	'snmp_sysName'      => '', 'snmp_sysLocation' => '', 'snmp_sysContact' => '',
	'snmp_sysDescr'     => '', 'snmp_sysUptime' => 'previous'];
$up = automation_valid_snmp_device($device);
print json_encode(['up' => $up, 'device' => $device, 'attempts' => $attempts,
	'gets'                 => $gets, 'queries' => $queries,
	'closed'               => array_map(static fn ($s) => $s->closed, $sessions)], JSON_THROW_ON_ERROR);
