#!/usr/bin/env php
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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

error_reporting(0);

// @codeCoverageIgnoreStart
// Entry-point glue: CLI invocation versus in-process script-server dispatch.
if (!isset($called_by_script_server)) {
	include_once(__DIR__ . '/../include/cli_check.php');
	include_once(__DIR__ . '/../lib/snmp.php');

	array_shift($_SERVER['argv']);

	print call_user_func_array('ss_entity_sensor', $_SERVER['argv']);
} else {
	include_once(__DIR__ . '/../lib/snmp.php');
}
// @codeCoverageIgnoreEnd

/**
 * Base OIDs for the vendor-neutral ENTITY-SENSOR / ENTITY MIB collector.
 *
 * ENTITY-SENSOR-MIB (RFC 3433) exposes physical sensors (temperature, fan,
 * PSU, voltage, current) on most modern network gear. The sensor table is
 * indexed by ENTITY-MIB entPhysicalIndex, so the labels come from the same
 * index in the ENTITY-MIB physical table.
 *
 * @return array<string,string> Symbolic name to numeric base OID.
 */
function ss_entity_sensor_oids() : array {
	return [
		'type'      => '.1.3.6.1.2.1.99.1.1.1.1', // entPhySensorType
		'scale'     => '.1.3.6.1.2.1.99.1.1.1.2', // entPhySensorScale
		'precision' => '.1.3.6.1.2.1.99.1.1.1.3', // entPhySensorPrecision
		'value'     => '.1.3.6.1.2.1.99.1.1.1.4', // entPhySensorValue
		'status'    => '.1.3.6.1.2.1.99.1.1.1.5', // entPhySensorOperStatus
		'units'     => '.1.3.6.1.2.1.99.1.1.1.6', // entPhySensorUnitsDisplay
		'descr'     => '.1.3.6.1.2.1.47.1.1.1.1.2', // entPhysicalDescr
		'name'      => '.1.3.6.1.2.1.47.1.1.1.1.7', // entPhysicalName
	];
}

/**
 * Resolve a raw entPhySensorValue into its real-world magnitude.
 *
 * Per RFC 3433 the displayed value is entPhySensorValue divided by ten to the
 * entPhySensorPrecision, expressed in the SI magnitude named by
 * entPhySensorScale. entPhySensorScale is an IANA enumeration where units(9)
 * is the base and each neighbouring step is a factor of one thousand, so the
 * scale multiplier is 1000 raised to (scale - 9). Out-of-range enumerations
 * are treated as units(9) and non-positive precision leaves the value intact,
 * matching how LibreNMS and Observium normalise the same table.
 *
 * @param int|float $value     The raw entPhySensorValue.
 * @param int       $scale     The entPhySensorScale enumeration (1..17).
 * @param int       $precision The entPhySensorPrecision (decimal places).
 *
 * @return int|float The scaled value.
 */
function ss_entity_sensor_scale(int|float $value, int $scale, int $precision) : int|float {
	$result = $value;

	if ($scale >= 1 && $scale <= 17 && $scale != 9) {
		$result *= 1000 ** ($scale - 9);
	}

	if ($precision > 0) {
		$result /= 10 ** $precision;
	}

	return $result;
}

/**
 * ss_entity_sensor - collect ENTITY-SENSOR-MIB physical sensor readings.
 *
 * Implements Cacti's script-server data-query contract: 'index' lists the
 * sensor indexes, 'num_indexes' counts them, 'query' returns index!value pairs
 * for a named field, and 'get' returns one field for one index. Output values
 * are scaled through ss_entity_sensor_scale().
 *
 * The final $walker parameter is a seam for testing; the script server never
 * passes it, so production runs fall back to cacti_snmp_walk().
 *
 * @param string        $hostname  Device hostname.
 * @param int           $host_id   Cacti host id.
 * @param mixed         $snmp_auth Colon-joined SNMP auth string from the query.
 * @param string        $cmd       One of index, num_indexes, query, get.
 * @param string        $arg1      Field name (query) or field name (get).
 * @param string        $arg2      Sensor index (get only).
 * @param callable|null $walker    Optional walk override: fn(string $oid): array.
 *
 * @return mixed Printed lines for index/query, a count for num_indexes, or a
 *               scalar for get.
 */
function ss_entity_sensor(string $hostname = '', int $host_id = 0, mixed $snmp_auth = '', string $cmd = 'index', string $arg1 = '', string $arg2 = '', ?callable $walker = null) : mixed {
	$oids = ss_entity_sensor_oids();

	if ($walker === null) {
		$walker = ss_entity_sensor_snmp_walker($hostname, explode(':', (string) $snmp_auth));
	}

	if ($cmd == 'index') {
		$rows = $walker($oids['type']);

		foreach (array_keys($rows) as $index) {
			print $index . "\n";
		}

		return '';
	}

	if ($cmd == 'num_indexes') {
		return cacti_count($walker($oids['type']));
	}

	if ($cmd == 'query') {
		$rows = ss_entity_sensor_field($walker, $oids, $arg1);

		foreach ($rows as $index => $value) {
			print $index . '!' . $value . "\n";
		}

		return '';
	}

	if ($cmd == 'get') {
		$rows = ss_entity_sensor_field($walker, $oids, $arg1);

		return $rows[$arg2] ?? 'U';
	}

	return '';
}

/**
 * Build the production SNMP walk callable for ss_entity_sensor().
 *
 * The returned closure is the boundary to lib/snmp.php; its body performs live
 * network I/O and is exercised by integration runs, not unit tests.
 *
 * @param string            $hostname Device hostname.
 * @param array<int,string> $snmp     The colon-split SNMP auth fields.
 *
 * @return callable fn(string $oid): array<string,string>
 */
function ss_entity_sensor_snmp_walker(string $hostname, array $snmp) : callable {
	return static function (string $oid) use ($hostname, $snmp) : array {
		/** @codeCoverageIgnoreStart */
		$version = $snmp[0] ?? '1';
		$results = cacti_snmp_walk(
			$hostname,
			$version == 3 ? '' : ($snmp[5] ?? ''),
			$oid,
			$version,
			$snmp[6] ?? '', $snmp[7] ?? '', $snmp[8] ?? '', $snmp[9] ?? '', $snmp[10] ?? '', $snmp[11] ?? '',
			$snmp[1] ?? 161, $snmp[2] ?? 500, $snmp[3] ?? 0, $snmp[4] ?? 10, SNMP_POLLER
		);

		return ss_entity_sensor_flatten($oid, $results);
		// @codeCoverageIgnoreEnd
	};
}

/**
 * Normalise a walk result into an entPhysicalIndex-keyed map.
 *
 * cacti_snmp_walk() returns rows carrying the fully qualified OID; the sensor
 * table is single-indexed by entPhysicalIndex, so the map key is the final OID
 * component.
 *
 * @param string                                      $base    The walked base OID.
 * @param array<int,array{oid?:string,value?:string}> $results Rows from cacti_snmp_walk().
 *
 * @return array<string,string> entPhysicalIndex to value.
 */
function ss_entity_sensor_flatten(string $base, array $results) : array {
	$map = [];

	foreach ($results as $row) {
		$oid   = $row['oid'] ?? '';
		$parts = explode('.', trim($oid, '.'));
		$index = end($parts);

		if ($index !== '') {
			$map[$index] = trim((string) ($row['value'] ?? ''));
		}
	}

	return $map;
}

/**
 * Resolve one query field into an index-keyed map, scaling sensor values.
 *
 * The 'value' field is the only computed one: it multiplies the raw reading
 * through ss_entity_sensor_scale() using the per-sensor scale and precision.
 * Every other field is a direct walk of the ENTITY or ENTITY-SENSOR column.
 *
 * @param callable             $walker Walk callable: fn(string $oid): array.
 * @param array<string,string> $oids   The base OID map from ss_entity_sensor_oids().
 * @param string               $field  Requested field name.
 *
 * @return array<string,string> entPhysicalIndex to field value.
 */
function ss_entity_sensor_field(callable $walker, array $oids, string $field) : array {
	if ($field == 'value') {
		$values     = $walker($oids['value']);
		$scales     = $walker($oids['scale']);
		$precisions = $walker($oids['precision']);
		$out        = [];

		foreach ($values as $index => $raw) {
			$out[$index] = (string) ss_entity_sensor_scale(
				is_numeric($raw) ? $raw + 0 : 0,
				(int) ($scales[$index] ?? 9),
				(int) ($precisions[$index] ?? 0)
			);
		}

		return $out;
	}

	if ($field == 'index') {
		$rows = $walker($oids['type']);

		return array_combine(array_keys($rows), array_keys($rows));
	}

	if (isset($oids[$field])) {
		return $walker($oids[$field]);
	}

	return [];
}
