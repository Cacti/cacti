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

	print call_user_func_array('ss_juniper_operating', $_SERVER['argv']);
} else {
	include_once(__DIR__ . '/../lib/snmp.php');
}
/** @codeCoverageIgnoreEnd */

/**
 * Base OIDs for the JUNIPER-MIB jnxOperatingTable (enterprise .2636).
 *
 * The table reports the health of each operating subject (Routing Engine, FPC,
 * PSU, fan). It is multi-indexed by jnxContentsContainerIndex.L1Index.L2Index.
 * L3Index, so the collector keys rows on the full OID suffix rather than a
 * single component. The signature Juniper metrics beyond the IF-MIB and
 * HOST-RESOURCES standards are the per-engine CPU, temperature and buffer use.
 *
 * @return array<string,string> Symbolic name to numeric base OID.
 */
function ss_juniper_operating_oids() : array {
	return [
		'descr'  => '.1.3.6.1.4.1.2636.3.1.13.1.5',  // jnxOperatingDescr
		'state'  => '.1.3.6.1.4.1.2636.3.1.13.1.6',  // jnxOperatingState
		'temp'   => '.1.3.6.1.4.1.2636.3.1.13.1.7',  // jnxOperatingTemp (celsius)
		'cpu'    => '.1.3.6.1.4.1.2636.3.1.13.1.8',  // jnxOperatingCPU (percent)
		'buffer' => '.1.3.6.1.4.1.2636.3.1.13.1.11', // jnxOperatingBuffer (percent)
	];
}

/**
 * ss_juniper_operating - collect JUNIPER-MIB jnxOperatingTable health metrics.
 *
 * Implements Cacti's script-server data-query contract: 'index' lists the
 * operating-subject indexes, 'num_indexes' counts them, 'query' returns
 * index!value pairs for a named field, and 'get' returns one field for one
 * index. The final $walker parameter is a seam for testing; the script server
 * never passes it, so production runs fall back to cacti_snmp_walk().
 *
 * @param string        $hostname  Device hostname.
 * @param int           $host_id   Cacti host id.
 * @param mixed         $snmp_auth Colon-joined SNMP auth string from the query.
 * @param string        $cmd       One of index, num_indexes, query, get.
 * @param string        $arg1      Field name (query and get).
 * @param string        $arg2      Operating index (get only).
 * @param callable|null $walker    Optional walk override: fn(string $oid): array.
 *
 * @return mixed Printed lines for index/query, a count for num_indexes, or a
 *               scalar for get.
 */
function ss_juniper_operating(string $hostname = '', int $host_id = 0, mixed $snmp_auth = '', string $cmd = 'index', string $arg1 = '', string $arg2 = '', ?callable $walker = null) : mixed {
	$oids = ss_juniper_operating_oids();

	if ($walker === null) {
		$walker = ss_juniper_operating_snmp_walker($hostname, explode(':', (string) $snmp_auth));
	}

	if ($cmd == 'index') {
		foreach (array_keys($walker($oids['descr'])) as $index) {
			print $index . "\n";
		}

		return '';
	}

	if ($cmd == 'num_indexes') {
		return cacti_count($walker($oids['descr']));
	}

	if ($cmd == 'query') {
		foreach (ss_juniper_operating_field($walker, $oids, $arg1) as $index => $value) {
			print $index . '!' . $value . "\n";
		}

		return '';
	}

	if ($cmd == 'get') {
		$rows = ss_juniper_operating_field($walker, $oids, $arg1);

		return $rows[$arg2] ?? 'U';
	}

	return '';
}

/**
 * Build the production SNMP walk callable for ss_juniper_operating().
 *
 * The returned closure is the boundary to lib/snmp.php; its body performs live
 * network I/O and is exercised by integration runs, not unit tests.
 *
 * @param string            $hostname Device hostname.
 * @param array<int,string> $snmp     The colon-split SNMP auth fields.
 *
 * @return callable fn(string $oid): array<string,string>
 */
function ss_juniper_operating_snmp_walker(string $hostname, array $snmp) : callable {
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

		return ss_juniper_operating_flatten($oid, $results);
		// @codeCoverageIgnoreEnd
	};
}

/**
 * Normalise a walk result into a jnxOperating-index-keyed map.
 *
 * jnxOperatingTable is multi-indexed, so the key is the full OID suffix that
 * follows the walked base OID, not just its final component.
 *
 * @param string                                      $base    The walked base OID.
 * @param array<int,array{oid?:string,value?:string}> $results Rows from cacti_snmp_walk().
 *
 * @return array<string,string> Composite index suffix to value.
 */
function ss_juniper_operating_flatten(string $base, array $results) : array {
	$prefix = rtrim($base, '.') . '.';
	$map    = [];

	foreach ($results as $row) {
		$oid = '.' . ltrim(trim($row['oid'] ?? ''), '.');

		if (str_starts_with($oid, $prefix)) {
			$index = substr($oid, strlen($prefix));

			if ($index !== '') {
				$map[$index] = trim((string) ($row['value'] ?? ''));
			}
		}
	}

	return $map;
}

/**
 * Resolve one query field into an index-keyed map.
 *
 * Every field is a direct walk of a jnxOperatingTable column; an unknown field
 * yields an empty map so the data query degrades cleanly.
 *
 * @param callable             $walker Walk callable: fn(string $oid): array.
 * @param array<string,string> $oids   The base OID map from ss_juniper_operating_oids().
 * @param string               $field  Requested field name.
 *
 * @return array<string,string> Composite index to field value.
 */
function ss_juniper_operating_field(callable $walker, array $oids, string $field) : array {
	if ($field == 'index') {
		$rows = $walker($oids['descr']);

		return array_combine(array_keys($rows), array_keys($rows));
	}

	if (isset($oids[$field])) {
		return $walker($oids[$field]);
	}

	return [];
}
