<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace RrdInterfaceSpeedQueryTest;

$GLOBALS['interface_speed_queries'] = [];
$GLOBALS['interface_speed_rows']    = [];
$GLOBALS['default_interface_speed'] = 0;

function db_fetch_assoc_prepared(string $sql, array $params = []) : array {
	$GLOBALS['interface_speed_queries'][] = [$sql, $params];

	return array_shift($GLOBALS['interface_speed_rows']);
}

function array_rekey(array $rows, string $key, string $value) : array {
	$result = [];

	foreach ($rows as $row) {
		$result[$row[$key]] = $row[$value];
	}

	return $result;
}

function read_config_option(string $name) : int {
	return $GLOBALS['default_interface_speed'];
}

$source = file_get_contents(CACTI_PATH_LIBRARY . '/rrd.php');

if ($source === false) {
	throw new \RuntimeException('Unable to read lib/rrd.php for interface speed tests.');
}

if (preg_match('/function rrdtool_function_interface_speed\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract rrdtool_function_interface_speed() for query tests.');
}

$function = str_replace('function rrdtool_function_interface_speed(', 'function rrdtool_function_interface_speed_under_test(', $matches[0]);
eval('namespace RrdInterfaceSpeedQueryTest;' . $function);

test('interface speed fields are fetched together and cached per interface', function () {
	$GLOBALS['interface_speed_rows'] = [
		[
			['field_name' => 'ifHighSpeed', 'field_value' => '1000'],
			['field_name' => 'ifSpeed', 'field_value' => '100000000'],
		],
	];
	$interface = ['host_id' => 1, 'snmp_query_id' => 2, 'snmp_index' => 'eth0'];

	expect(rrdtool_function_interface_speed_under_test($interface))->toBe('1000000000')
		->and(rrdtool_function_interface_speed_under_test($interface))->toBe('1000000000')
		->and($GLOBALS['interface_speed_queries'])->toHaveCount(1)
		->and($GLOBALS['interface_speed_queries'][0][0])->toContain('field_name IN (?, ?)')
		->and($GLOBALS['interface_speed_queries'][0][1])->toBe([1, 2, 'eth0', 'ifHighSpeed', 'ifSpeed']);
});

test('interface speed falls back through ifSpeed and the configured default', function () {
	$GLOBALS['interface_speed_rows'] = [
		[['field_name' => 'ifSpeed', 'field_value' => '123456']],
		[],
		[],
	];
	$GLOBALS['default_interface_speed'] = 456;

	expect(rrdtool_function_interface_speed_under_test(['host_id' => 1, 'snmp_query_id' => 2, 'snmp_index' => 'eth1']))->toBe('123456')
		->and(rrdtool_function_interface_speed_under_test(['host_id' => 1, 'snmp_query_id' => 2, 'snmp_index' => 'eth2']))->toBe('456000000');

	$GLOBALS['default_interface_speed'] = 0;

	expect(rrdtool_function_interface_speed_under_test(['host_id' => 1, 'snmp_query_id' => 2, 'snmp_index' => 'eth3']))->toBe('10000000000000');
});
