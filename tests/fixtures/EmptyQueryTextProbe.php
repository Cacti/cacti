<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiEmptyQueryTextProbe;

require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';

const SQL_NO_CACHE = '';

final class Cache {
	public static array $rows  = [];
	public static array $calls = [];
}

// Bind only the database boundary in this isolated fixture namespace.
function db_fetch_assoc_prepared($sql, $params) {
	Cache::$calls[] = $params;

	return Cache::$rows;
}

$root = dirname(__DIR__, 2);

foreach (['cacti_sizeof', 'stri_replace'] as $function) {
	\cacti_fixture_source_function($root . '/lib/functions.php', $function, __NAMESPACE__);
}

foreach (['substitute_host_data', 'substitute_snmp_query_data'] as $function) {
	\cacti_fixture_source_function($root . '/lib/variables.php', $function, __NAMESPACE__);
}
\cacti_fixture_source_function($root . '/lib/rrd.php', 'rrd_substitute_host_query_data', __NAMESPACE__);

Cache::$rows = [
	['field_name' => 'IfAlias', 'field_value' => ''],
	['field_name' => 'ifDescr', 'field_value' => 'Ethernet 1'],
	['field_name' => 'ifSpeed', 'field_value' => '0'],
	['field_name' => 'ifBlank', 'field_value' => '   '],
];
$graph   = ['host_id' => 0, 'snmp_query_id' => 1, 'snmp_index' => '1'];
$results = [];

foreach ([
	['Interface Description: |query_IfAlias|', 'Interface Description: '],
	['|query_IfAlias| / |query_ifDescr|', ' / Ethernet 1'],
	['|query_IfAlias| |query_IfAlias|', ' '],
	['|query_ifalias|', ''],
	['|query_ifSpeed|', '0'],
	['|query_ifBlank|', ''],
	['|query_unknown|', '|query_unknown|'],
] as [$input, $expected]) {
	$actual = rrd_substitute_host_query_data($input, $graph, []);

	if ($actual !== $expected) {
		throw new \RuntimeException('Unexpected graph text for ' . $input);
	}
	$results[] = $actual;
}
$item      = ['snmp_query_id' => 2, 'snmp_index' => '2'];
$results[] = rrd_substitute_host_query_data('|query_IfAlias|', $graph, $item);

if (end(Cache::$calls) !== [2, '2'] || end($results) !== '') {
	throw new \RuntimeException('Graph item query identity was lost');
}
$results[] = substitute_snmp_query_data('|query_IfAlias|', 0, 1, '1');
$results[] = substitute_snmp_query_data('|query_ifDescr|', 0, 1, '1', 4);

if (array_slice($results, -2) !== ['|query_IfAlias|', 'Ethe']) {
	throw new \RuntimeException('Suggested value substitution changed');
}
print json_encode($results, JSON_THROW_ON_ERROR) . PHP_EOL;
