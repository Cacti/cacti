<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiPollerMappingFixture;
use RuntimeException;

require dirname(__DIR__) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 2);
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';
$config = ['library_path' => $root . '/lib', 'is_web' => false, 'config_options_array' => ['storage_location' => '']];
$debug  = false;
$reads  = 0;
$retry  = ($argv[1] ?? '') === 'retry';

// Limit the controlled boundary to database reads and log output.
function db_fetch_assoc($sql) {
	return [];
}
function db_fetch_assoc_prepared($sql) {
	global $reads, $retry;
	$reads++;

	return $retry && $reads === 1 ? false : [];
}
function cacti_log(...$arguments) {
}

cacti_fixture_source_function($root . '/lib/poller.php', 'process_poller_output', __NAMESPACE__);
$pipe = null;

for ($pass = 0; $pass < 5; $pass++) {
	if (process_poller_output($pipe) !== 0) {
		throw new RuntimeException('Empty output unexpectedly wrote an RRD');
	}
	$expected = $retry && $pass > 0 ? 2 : 1;

	if ($reads !== $expected) {
		throw new RuntimeException('A failed read was cached or an empty map was repeatedly loaded');
	}
}
print json_encode(['reads' => $reads, 'passes' => 5], JSON_THROW_ON_ERROR) . "\n";
