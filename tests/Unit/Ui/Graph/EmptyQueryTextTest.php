<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 4) . '/lib/variables.php';
require_once dirname(__DIR__, 4) . '/lib/rrd.php';

beforeEach(function () {
	$this->queryTextSessions = $GLOBALS['database_sessions'] ?? [];
	$key                     = $GLOBALS['database_hostname'] . ':' . $GLOBALS['database_port'] . ':' . $GLOBALS['database_default'];
	$this->queryTextDb       = new class extends FakeMySQLPDO {
		public function prepare(string $query, array $options = []): PDOStatement|false {
			return parent::prepare(str_replace('SELECT DISTINCT SQL_NO_CACHE ', 'SELECT DISTINCT ', $query), $options);
		}
	};
	$GLOBALS['database_sessions'][$key] = $this->queryTextDb;
	$this->queryTextDb->exec('CREATE TABLE host_snmp_cache (host_id INTEGER, snmp_query_id INTEGER, snmp_index TEXT, field_name TEXT, field_value TEXT)');
	$this->queryTextDb->exec("INSERT INTO host_snmp_cache VALUES
		(0, 1, '1', 'IfAlias', ''),
		(0, 1, '1', 'ifDescr', 'Ethernet 1'),
		(0, 1, '1', 'ifSpeed', '0'),
		(0, 2, '2', 'IfAlias', 'Uplink'),
		(0, 3, '3', 'IfAlias', '   ')");
});

afterEach(function () {
	$GLOBALS['database_sessions'] = $this->queryTextSessions;
});

test('graph text replaces a known empty query field (#2815)', function ($queryId, $index, $input, $expected) {
	$graph = ['host_id' => 0, 'snmp_query_id' => $queryId, 'snmp_index' => $index];
	expect(rrd_substitute_host_query_data($input, $graph, []))->toBe($expected);
})->with([
	'empty comment'          => [1, '1', 'Interface Description: |query_IfAlias|', 'Interface Description: '],
	'mixed known values'     => [1, '1', '|query_IfAlias| / |query_ifDescr|', ' / Ethernet 1'],
	'repeated empty value'   => [1, '1', '|query_IfAlias| |query_IfAlias|', ' '],
	'case-insensitive field' => [1, '1', '|query_ifalias|', ''],
	'numeric zero'           => [1, '1', '|query_ifSpeed|', '0'],
	'nonempty value'         => [2, '2', '|query_IfAlias|', 'Uplink'],
	'whitespace-only field'  => [3, '3', '|query_IfAlias|', ''],
	'unknown field'          => [1, '1', '|query_unknown|', '|query_unknown|'],
	'missing index'          => [1, 'missing', '|query_IfAlias|', '|query_IfAlias|'],
]);

test('graph item query identity takes precedence over graph query identity', function () {
	$graph = ['host_id' => 0, 'snmp_query_id' => 2, 'snmp_index' => '2'];
	$item  = ['snmp_query_id' => 1, 'snmp_index' => '1'];
	expect(rrd_substitute_host_query_data('|query_IfAlias|', $graph, $item))->toBe('');
});

test('default query substitution preserves empty tokens for suggested-value fallback', function () {
	expect(substitute_snmp_query_data('|query_IfAlias|', 0, 1, '1'))->toBe('|query_IfAlias|')
		->and(substitute_snmp_query_data('|query_IfAlias|', 0, 3, '3'))->toBe('|query_IfAlias|')
		->and(substitute_snmp_query_data('|query_ifDescr|', 0, 1, '1', 4))->toBe('Ethe');
});
