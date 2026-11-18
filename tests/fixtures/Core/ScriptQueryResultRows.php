<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root    = dirname(__DIR__, 3);
$scripts = ['query_akcp_airflow', 'query_akcp_fourtwenty', 'query_akcp_humidity', 'query_akcp_temperature',
	'query_baytech_circuits', 'query_host_cpu', 'query_host_partitions'];
$rows = [['value' => "rack\r\nsensor"], ['value' => 0], ['value' => null]];

foreach ($scripts as $index => $script) {
	$namespace = 'CactiScriptQueryRows' . $index;
	cacti_fixture_source_function($root . '/lib/functions.php', 'cacti_sizeof', $namespace);
	cacti_fixture_source_function($root . '/scripts/' . $script . '.php', 'reindex', $namespace);
	$reindex = $namespace . '\\reindex';

	if ($reindex($rows) !== ['racksensor', '0', ''] || $reindex([]) !== []) {
		throw new RuntimeException('Query rows must remain one line, preserve numeric zero, and accept an empty result: ' . $script);
	}
}

printf("PASS: %d script query result row contracts\n", count($scripts) * 2);
