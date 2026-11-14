<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

namespace Cacti\Test\DataQuery;

require dirname(__DIR__, 2) . '/Helpers/DataQueryHarness.php';

$mode = $argv[1] ?? 'unchanged';
$size = (int) ($argv[2] ?? 1000);
$repetitions = (int) ($argv[3] ?? 3);
if (!in_array($mode, array('unchanged', 'filtered'), true) || $size < 1 || $size > 100000 || $repetitions < 1 || $repetitions > 10) {
	throw new \RuntimeException('Usage: DataQueryProfile.php unchanged|filtered 1..100000 [1..10 repetitions]');
}

$results = array();
for ($sample = 0; $sample < $repetitions; $sample++) {
	reset_fixture($mode === 'unchanged' ? $size : 0);
	if ($mode === 'filtered') {
		$GLOBALS['xml'] = array('oid_index' => '.1.2.1', 'index_order' => 'ifName', 'value_index_parse' => 'VALUE/REGEXP:^[0-9]+$', 'fields' => array());
		$GLOBALS['benchmark_walks'] = array('.1.2.1' => array());
		foreach (array('ifName', 'ifDescr', 'ifAlias', 'ifLabel') as $field_number => $field) {
			$oid = '.1.3.' . ($field_number + 1);
			$GLOBALS['xml']['fields'][$field] = array('direction' => 'input', 'method' => 'walk', 'source' => 'value', 'oid' => $oid);
			$GLOBALS['benchmark_walks'][$oid] = array();
			for ($i = 1; $i <= $size; $i++) $GLOBALS['benchmark_walks'][$oid][$oid . '.' . $i] = $field . $i;
		}
		for ($i = 1; $i <= $size; $i++) $GLOBALS['benchmark_walks']['.1.2.1']['.1.2.1.' . $i] = (string) $i;
	}
	gc_collect_cycles();
	memory_reset_peak_usage();
	$started = hrtime(true);
	$ok = $mode === 'unchanged' ? run_data_query(1, 1) : query_snmp_host(1, 1);
	$elapsed = (hrtime(true) - $started) / 1e6;
	$peak = memory_get_peak_usage(false);
	if (!$ok) throw new \RuntimeException('Profile run failed: ' . implode('; ', $GLOBALS['dq_logs']));
	$expected_cache_rows = $size * ($mode === 'unchanged' ? 2 : 4);
	if ((int) sql('SELECT COUNT(*) FROM host_snmp_cache WHERE present=1')->fetchColumn() !== $expected_cache_rows) {
		throw new \RuntimeException('Profile lost cache rows');
	}
	if ($mode === 'unchanged') {
		if ((int) sql('SELECT COUNT(*) FROM data_local WHERE orphan=0 AND snmp_index=CAST(id AS CHAR)')->fetchColumn() !== $size
			|| (int) sql('SELECT COUNT(*) FROM poller_item')->fetchColumn() !== $size) {
			throw new \RuntimeException('Profile changed source identities or polling membership');
		}
	} elseif ((int) sql('SELECT COUNT(*) FROM host_snmp_cache WHERE field_value <> CONCAT(field_name,snmp_index)')->fetchColumn() !== 0) {
		throw new \RuntimeException('Profile changed discovered values');
	}
	$hash = hash_init('sha256');
	foreach (array(
		'SELECT field_name,field_value,snmp_index,oid,present FROM host_snmp_cache ORDER BY field_name,snmp_index',
		'SELECT id,snmp_index,orphan FROM data_local ORDER BY id',
		'SELECT data_template_data_id,data_input_field_id,value FROM data_input_data ORDER BY 1,2',
		'SELECT local_data_id,poller_id,rrd_name FROM poller_item ORDER BY 1,2,3'
	) as $query) {
		$rows = sql($query);
		while ($row = $rows->fetch(\PDO::FETCH_NUM)) hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR) . "\n");
	}
	$results[] = array('wall_ms' => round($elapsed, 3), 'database_calls' => $GLOBALS['database_total_queries'],
		'peak_mib' => round($peak / 1048576, 3), 'state_sha256' => hash_final($hash));
}
print json_encode(array('mode' => $mode, 'size' => $size, 'php' => PHP_VERSION,
	'server' => $GLOBALS['db']->getAttribute(\PDO::ATTR_SERVER_VERSION), 'samples' => $results), JSON_THROW_ON_ERROR) . "\n";
