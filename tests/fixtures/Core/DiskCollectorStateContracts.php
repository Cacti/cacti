<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiDiskCollectorStateContracts;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/bootstrap-unit.php';
$called_by_script_server = true;
require_once dirname(__DIR__, 3) . '/scripts/ss_net_snmp_disk_bytes.php';
require_once dirname(__DIR__, 3) . '/scripts/ss_net_snmp_disk_io.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$GLOBALS['cacti_fixture_contract'] = ['name' => 'disk-collector-state', 'complete' => false, 'assertions' => 0,
	'producers'                                 => ['scripts/ss_net_snmp_disk_bytes.php', 'scripts/ss_net_snmp_disk_io.php']];
require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
class_alias(\CactiSnmpStateStore::class, __NAMESPACE__ . '\\CactiSnmpStateStore');

$base = \sys_get_temp_dir() . '/cacti-disk-collector-' . bin2hex(random_bytes(8));
mkdir($base, 0700);
$uptime  = '200';
$read    = '100';
$written = '200';
$host    = array_fill_keys(['snmp_username', 'snmp_password', 'snmp_auth_protocol', 'snmp_priv_passphrase',
	'snmp_priv_protocol', 'snmp_context', 'snmp_engine_id'], '');
$host += ['hostname' => '127.0.0.1', 'snmp_community' => 'fixture', 'snmp_version' => 2,
	'snmp_port'         => 161, 'snmp_timeout' => 500, 'snmp_retries' => 0, 'max_oids' => 10];

function sys_get_temp_dir(): string {
	return $GLOBALS['base'];
}
function db_table_exists(string $table, bool $log = true, mixed $db_conn = false): bool {
	if ($table !== 'host_value_cache') {
		throw new \RuntimeException('Unexpected cache availability lookup.');
	}

	return false;
}
function db_fetch_row_prepared(string $sql, array $params = [], bool $log = true, mixed $db_conn = false, float $timeout = 0): bool|array {
	if ($params !== [7]) {
		throw new \RuntimeException('Device lookup must bind the requested ID.');
	}

	return $GLOBALS['host'];
}
function cacti_snmp_get(string $hostname, mixed $community, string $oid, mixed $version, mixed $auth_user = '', mixed $auth_pass = '',
	mixed $auth_proto = '', mixed $priv_pass = '', mixed $priv_proto = '', mixed $context = '',
	mixed $port = 161, mixed $timeout_ms = 500, mixed $retries = 0, mixed $environ = 'SNMP',
	mixed $engineid = '', int $value_output_format = SNMP_STRING_OUTPUT_GUESS, ?callable $native_get = null): string {
	return $GLOBALS['uptime'];
}
function cacti_snmp_walk(string $hostname, mixed $community, string $oid, mixed $version, mixed $auth_user = '', mixed $auth_pass = '',
	mixed $auth_proto = '', mixed $priv_pass = '', mixed $priv_proto = '', mixed $context = '',
	mixed $port = 161, mixed $timeout_ms = 500, mixed $retries = 0, mixed $bulk_walk_size = 10, mixed $environ = 'SNMP',
	mixed $engineid = '', int $value_output_format = SNMP_STRING_OUTPUT_GUESS): array {
	$value = match (substr($oid, strrpos($oid, '.') + 1)) {
		'2'       => 'sda',
		'5', '12' => $GLOBALS['read'],
		'6', '13' => $GLOBALS['written'],
		default   => throw new \RuntimeException('Unexpected disk metric OID.'),
	};

	return [['oid' => $oid . '.1', 'value' => $value]];
}
function remove_fixture_directory(string $path): void {
	foreach (scandir($path) as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}
		$child = $path . '/' . $entry;

		if (is_dir($child) && !is_link($child)) {
			remove_fixture_directory($child);
		} else {
			unlink($child);
		}
	}
	rmdir($path);
}
function verify(bool $condition, string $message): void {
	if (!$condition) {
		throw new \RuntimeException($message);
	}
	$GLOBALS['cacti_fixture_contract']['assertions']++;
}

try {
	foreach (['bytes' => ['bytesread', 'byteswritten', 'br1', 'bw1'], 'io' => ['reads', 'writes', 'dr1', 'dw1']] as $suffix => [$readName, $writeName, $readKey, $writeKey]) {
		$function = 'ss_net_snmp_disk_' . $suffix;
		\cacti_fixture_source_function(dirname(__DIR__, 3) . '/scripts/' . $function . '.php', $function, __NAMESPACE__);
		$function  = __NAMESPACE__ . '\\' . $function;
		$environ   = 'realtime';
		$poller_id = '../invalid';
		verify($function(7) === "$readName:U $writeName:U", 'Unsafe realtime identifiers must fail before a state path is built.');
		$environ = 'cmd';
		$uptime  = '200';
		$read    = '100';
		$written = '200';
		verify($function(7) === "$readName:0 $writeName:0", 'The first sample must establish a baseline without reporting a rate.');
		$file = $base . '/cacti/net-snmp-devio/7_' . $suffix;
		verify(CactiSnmpStateStore::read($file) === ['uptime' => '200', $readKey => '100', $writeKey => '200'], 'The baseline must store all counters in the private state file.');
		$uptime  = '300';
		$read    = '150';
		$written = '275';
		verify($function(7) === "$readName:50 $writeName:75", 'The next sample must read the prior state and report exact deltas.');
		$written = 'invalid';
		verify($function(7) === "$readName:0 $writeName:U", 'A malformed write counter must be unknown without invalidating the numeric read counter.');
		$written   = '300';
		$environ   = 'realtime';
		$poller_id = str_repeat('a', 64);
		verify($function(7) === "$readName:0 $writeName:0", 'Each valid realtime request must start its own baseline.');
		$rtFile = $base . '/cacti-rt/net-snmp-devio/7_' . $poller_id . '_' . $suffix . '_rt';
		verify(is_file($rtFile) && (fileperms($rtFile) & 0777) === 0600, 'Realtime state must use a private file bound to its validated request ID.');
		verify($function(7) === "$readName:0 $writeName:0", 'A second realtime sample must read its own state without reusing normal poller state.');
	}
	$GLOBALS['cacti_fixture_contract']['complete'] = true;
	printf("PASS: %d disk collector state contracts\n", $GLOBALS['cacti_fixture_contract']['assertions']);
} finally {
	remove_fixture_directory($base);
}
