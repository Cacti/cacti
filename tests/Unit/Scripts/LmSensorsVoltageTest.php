<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace CactiLmSensorsVoltageFixture;

require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

function db_fetch_row_prepared(string $sql, array $params = [], bool $log = true, mixed $db_conn = false, float $timeout = 0) : bool|array {
	return array_fill_keys([
		'hostname', 'snmp_community', 'snmp_version', 'snmp_username',
		'snmp_password', 'snmp_auth_protocol', 'snmp_priv_passphrase',
		'snmp_priv_protocol', 'snmp_context', 'snmp_port', 'snmp_timeout',
		'snmp_retries', 'snmp_engine_id', 'max_oids'
	], '1');
}

function ss_netsnmp_lmsensors_is_vhost() : bool {
	return false;
}

// The script dispatches SNMP by global function name. Intercept that boundary
// inside this fixture namespace without replacing the process-wide SNMP API.
function call_user_func_array(callable|string $callback, array $args) : mixed {
	$GLOBALS['lmsensors_voltage_calls'][] = $callback;

	if ($callback === 'cacti_snmp_walk') {
		return [['oid' => $args[2] . '.1', 'value' => '1']];
	}

	expect($callback)->toBe('cacti_snmp_get');

	return $GLOBALS['lmsensors_voltage_raw'];
}

\cacti_fixture_source_function(dirname(__DIR__, 3) . '/scripts/ss_netsnmp_lmsensors.php', 'ss_netsnmp_lmsensors', __NAMESPACE__);

test('lm-sensors preserves signed voltage readings from issue 7403', function (string $type, string $raw, float $expected, string $request) {
	$GLOBALS['lmsensors_voltage_raw']   = $raw;
	$GLOBALS['lmsensors_voltage_calls'] = [];

	if ($request === 'get') {
		$get = ss_netsnmp_lmsensors(1, $type, 'get', 'sensorreading', '1');

		expect((float) $get)->toBe($expected)
			->and($GLOBALS['lmsensors_voltage_calls'])->toBe(['cacti_snmp_get']);

		return;
	}

	ob_start();

	try {
		ss_netsnmp_lmsensors(1, $type, 'query', 'sensorreading');
		$query = ob_get_contents();
	} finally {
		ob_end_clean();
	}

	expect($query)->toBe('1:' . $expected . PHP_EOL)
		->and($GLOBALS['lmsensors_voltage_calls'])->toBe(['cacti_snmp_walk', 'cacti_snmp_get']);
})->with([
	'negative rail'              => ['voltage', '4294955296', -12.0],
	'lowest signed value'        => ['voltage', '2147483648', -2147483.648],
	'minus one millivolt'        => ['voltage', '4294967295', -0.001],
	'already signed voltage'     => ['voltage', '-12000', -12.0],
	'zero voltage'               => ['voltage', '0', 0.0],
	'positive voltage'           => ['voltage', '12000', 12.0],
	'temperature scaling'        => ['temperature', '45000', 45.0],
	'fan speed remains unscaled' => ['fan', '1500', 1500.0]
])->with(['get', 'query']);

test('lm-sensors query leaves failed readings empty without arithmetic', function (string $type, string $raw) {
	$GLOBALS['lmsensors_voltage_raw']   = $raw;
	$GLOBALS['lmsensors_voltage_calls'] = [];
	ob_start();

	try {
		ss_netsnmp_lmsensors(1, $type, 'query', 'sensorreading');
		$query = ob_get_contents();
	} finally {
		ob_end_clean();
	}

	expect($query)->toBe('1:' . PHP_EOL)
		->and($GLOBALS['lmsensors_voltage_calls'])->toBe(['cacti_snmp_walk', 'cacti_snmp_get']);
})->with(['voltage', 'temperature', 'fan'])->with(['', 'No Such Instance currently exists at this OID', 'not numeric']);
