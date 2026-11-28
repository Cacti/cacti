<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiScriptQueryKnownResults;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$mode = $argv[1] ?? 'known';

if ($mode === 'lmsensors') {
	require dirname(__DIR__, 2) . '/bootstrap-unit.php';
	require_once dirname(__DIR__, 3) . '/lib/snmp.php';
}

$GLOBALS['cacti_fixture_contract'] = ['name' => 'script-query-known-results', 'complete' => false, 'assertions' => 0,
	'producers'                                 => ['scripts/ss_apache_stats.php', 'scripts/ss_fortigate_virus.php', 'scripts/ss_nimble_alletra_volumes.php', 'scripts/ss_fping.php', 'scripts/aruba_instant_wlan_client.php', 'scripts/query_akcp_temperature.php', 'scripts/ss_netsnmp_lmsensors.php']];
require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/include/global_constants.php';
// Match lib/snmp.php's compatibility default without loading its native driver.
const SNMP_STRING_OUTPUT_GUESS = 1;

$root = dirname(__DIR__, 3);
$host = array_fill_keys(['snmp_username', 'snmp_password', 'snmp_auth_protocol', 'snmp_priv_passphrase',
	'snmp_priv_protocol', 'snmp_context', 'snmp_engine_id'], '');
$host += ['hostname' => '127.0.0.1', 'snmp_community' => 'fixture', 'snmp_version' => 2,
	'snmp_port'         => 161, 'snmp_timeout' => 500, 'snmp_retries' => 0, 'max_oids' => 10];
$mode  = $argv[1] ?? 'known';
$unit  = 'F';
$gets  = [];
$walks = [];

// PHP callback strings refer to global symbols. Keep those real signatures
// callable, but route the two external SNMP boundaries into this fixture.
function call_user_func_array(callable $callback, array $args): mixed {
	if (is_string($callback) && in_array($callback, ['cacti_snmp_get', 'cacti_snmp_walk'], true)) {
		$callback = __NAMESPACE__ . '\\' . $callback;
	}

	return \call_user_func_array($callback, $args);
}

function db_fetch_row_prepared(string $sql, array $params = [], bool $log = true, mixed $db_conn = false, float $timeout = 0): bool|array {
	global $host;

	if ($params !== [7] || !str_contains($sql, 'WHERE id = ?')) {
		throw new \RuntimeException('The device lookup must bind the requested ID.');
	}

	return $host;
}

function cacti_snmp_get(string $hostname, mixed $community, string $oid, mixed $version, mixed $auth_user = '', mixed $auth_pass = '',
	mixed $auth_proto = '', mixed $priv_pass = '', mixed $priv_proto = '', mixed $context = '',
	mixed $port = 161, mixed $timeout_ms = 500, mixed $retries = 0, mixed $environ = 'SNMP',
	mixed $engineid = '', int $value_output_format = SNMP_STRING_OUTPUT_GUESS, ?callable $native_get = null): string {
	global $gets, $mode, $unit;
	$gets[] = $oid;

	if ($mode === 'lmsensors') {
		return "rack\r\nsensor";
	}

	if ($mode === 'akcp') {
		if ($oid === '.1.3.6.1.4.1.3854.1.1.8.0') {
			return 'securityProbe';
		}

		if (str_contains($oid, '.2.3.2.1.5.')) {
			return $unit;
		}

		if (str_contains($oid, '.2.3.2.1.3.')) {
			return '1';
		}

		return '100';
	}

	return '2';
}

function cacti_snmp_walk(string $hostname, mixed $community, string $oid, mixed $version, mixed $auth_user = '', mixed $auth_pass = '',
	mixed $auth_proto = '', mixed $priv_pass = '', mixed $priv_proto = '', mixed $context = '',
	mixed $port = 161, mixed $timeout_ms = 500, mixed $retries = 0, mixed $bulk_walk_size = 10, mixed $environ = 'SNMP',
	mixed $engineid = '', int $value_output_format = SNMP_STRING_OUTPUT_GUESS): array {
	global $walks, $mode;
	$walks[] = $oid;

	if ($mode === 'lmsensors') {
		return [['oid' => $oid . '.1', 'value' => '1']];
	}

	if ($mode === 'akcp') {
		return [['oid' => $oid . '.1', 'value' => str_ends_with($oid, '.1') ? '1' : '100']];
	}

	return [['oid' => $oid . '.1', 'value' => $mode === 'aruba' ? 'volname' : "vol\r\nname"]];
}

function ss_apache_stats_fetch(string $url): string|false {
	if ($url !== 'http://127.0.0.1:8080/server-status?auto') {
		throw new \RuntimeException('The Apache request must preserve the validated authority.');
	}

	return "Total Accesses: 12\nTotal kBytes: 34\n";
}

function cacti_log(mixed $string, bool $output = false, string $environ = 'CMDPHP', mixed $level = ''): bool {
	throw new \RuntimeException('Successful query fixtures must not log an error.');
}

function read_config_option(string $config_name, bool $force = false): mixed {
	return $config_name === 'script_timeout' ? 30 : 500;
}

function db_fetch_cell_prepared(string $sql, array $params = [], string $col_name = '', bool $log = true, mixed $db_conn = false, float $timeout = 0): mixed {
	return 500;
}

class Net_Ping {
	public array $host        = [];
	public int $retries       = 0;
	public int $port          = 0;
	public float $ping_status = 2.0;
	public static int $calls  = 0;

	public function ping(int $avail_method = AVAIL_SNMP_AND_PING, int $ping_type = PING_ICMP, int $timeout = 500, int $retries = 3): bool {
		self::$calls++;

		return true;
	}
}

foreach (['cacti_sizeof', 'cacti_count', 'cacti_strtoupper', 'cacti_strtolower'] as $function) {
	\cacti_fixture_source_function($root . '/lib/functions.php', $function, __NAMESPACE__);
}

foreach (['ss_apache_stats', 'ss_apache_stats_authority', 'ss_apache_stats_parse'] as $function) {
	\cacti_fixture_source_function($root . '/scripts/ss_apache_stats.php', $function, __NAMESPACE__);
}
\cacti_fixture_source_function($root . '/scripts/ss_fping.php', 'ss_fping', __NAMESPACE__);
\cacti_fixture_source_function($root . '/scripts/ss_fortigate_virus.php', 'ss_fortigate_virus', __NAMESPACE__);
\cacti_fixture_source_function($root . '/scripts/ss_nimble_alletra_volumes.php', 'ss_nimble_alletra_volumes', __NAMESPACE__);

if ($mode === 'known') {
	if (ss_apache_stats('127.0.0.1:8080', 'hits') !== 'apache_total_hits:12'
		|| ss_apache_stats_authority('[1.2.3.4]') !== false) {
		throw new \RuntimeException('Apache results must parse a validated endpoint and reject bracketed IPv4.');
	}

	if (ss_fortigate_virus(7) !== 'vir_detected:2 vir_blocked:2 vir_det_web:2 vir_det_smb:2 vir_det_mail:6' . PHP_EOL
		|| count($gets) !== 7 || count(array_unique($gets)) !== 7) {
		throw new \RuntimeException('Mail totals must sum exactly the three independent protocol counters.');
	}
	ob_start();
	ss_nimble_alletra_volumes(7, 'query', 'name');
	$result = ob_get_clean();

	if ($result !== '1:volname' . PHP_EOL || $walks !== ['.1.3.6.1.4.1.37447.1.2.1.3']) {
		throw new \RuntimeException('A volume name must produce one row with its original index and no injected line breaks.');
	}

	foreach ([-5 => 1, 3 => 3, 1000 => 100] as $requested => $expected) {
		Net_Ping::$calls = 0;

		if (ss_fping('127.0.0.1', $requested, 'TCP', 8080) !== 'min:2.0000 avg:2.0000 max:2.0000 dev:0.0000 loss:0.0000'
			|| Net_Ping::$calls !== $expected) {
			throw new \RuntimeException('Ping sweep bounds must preserve one sample, ordinary counts, and the maximum without network I/O.');
		}
	}
} elseif ($mode === 'lmsensors') {
	\cacti_fixture_source_function($root . '/scripts/ss_netsnmp_lmsensors.php', 'ss_netsnmp_lmsensors', __NAMESPACE__);
	ob_start();
	ss_netsnmp_lmsensors(7, 'fan', 'query', 'sensorname');
	$result = ob_get_clean();

	if ($result !== '1:racksensor' . PHP_EOL || count($walks) !== 1 || count($gets) !== 1) {
		throw new \RuntimeException('Sensor names must preserve their index and remove device-supplied line breaks.');
	}
} else {
	query_entrypoint_cases($root, $mode);
}
$assertions                                      = ['known' => 7, 'aruba' => 4, 'akcp' => 5, 'lmsensors' => 1][$mode];
$GLOBALS['cacti_fixture_contract']['assertions'] = $assertions;
$GLOBALS['cacti_fixture_contract']['complete']   = true;
printf("PASS: %d known query result contracts (%s)\n", $assertions, $mode);

/** Run a complete unchanged CLI body, leaving includes at their real boundaries. */
function query_entrypoint(string $file, string $start, string $end, array $arguments): string {
	$source = file_get_contents($file);
	$begin  = strpos($source, $start);
	$finish = strpos($source, $end);

	if ($begin === false || $finish === false || $finish <= $begin) {
		throw new \RuntimeException('Could not locate the complete query entrypoint.');
	}
	$line      = substr_count(substr($source, 0, $begin), "\n") + 1;
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-query-entrypoint-');
	file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", $line - 1) . substr($source, $begin, $finish - $begin));
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);
	$original                                        = $_SERVER['argv'];
	$_SERVER['argv']                                 = $arguments;
	ob_start();

	try {
		require $temporary;

		return ob_get_contents();
	} finally {
		ob_end_clean();
		$_SERVER['argv'] = $original;
		unlink($temporary);
	}
}

function query_entrypoint_cases(string $root, string $mode): void {
	global $unit;

	if ($mode === 'aruba') {
		$file = $root . '/scripts/aruba_instant_wlan_client.php';
		\cacti_fixture_source_function($file, 'reindex', __NAMESPACE__);

		foreach ([['index', '', "1\n"], ['query', 'name', "1!volname\n"], ['query', 'count', "1!volname\n"], ['query', 'index', "1!1\n"]] as [$command, $field, $expected]) {
			$result = query_entrypoint($file, '$oids = [', 'function reindex(', [$file, '7', $command, $field, '1']);

			if ($result !== $expected) {
				throw new \RuntimeException('Aruba query must preserve its command, delimiter, and one-based index.');
			}
		}
	} elseif ($mode === 'akcp') {
		$file = $root . '/scripts/query_akcp_temperature.php';
		\cacti_fixture_source_function($file, 'reindex', __NAMESPACE__);
		\cacti_fixture_source_function($file, 'akcp_fahrenheit_tenths', __NAMESPACE__);

		foreach ([['index', '', 'F', "1\n"], ['query', 'sensorProbeTempDegreeRaw', 'F', "1!500\n"], ['get', 'sensorProbeTempDegreeRaw', 'F', '500'], ['query', 'sensorProbeTempDegreeRaw', 'C', "1!100\n"], ['get', 'sensorProbeTempDegreeRaw', 'C', '100']] as [$command, $field, $unit, $expected]) {
			$arguments = [$file, '127.0.0.1', 'fixture', '2', '161', '500', '10', '', '', '', '', '', '', $command, $field, '1'];
			$result    = query_entrypoint($file, '$xml_delimiter', "/**\n * akcp_fahrenheit_tenths", $arguments);

			if ($result !== $expected) {
				throw new \RuntimeException('AKCP query must retain its index and convert only Fahrenheit sensors in tenths.');
			}
		}
	} else {
		throw new \RuntimeException('Unknown query entrypoint fixture mode.');
	}
}
