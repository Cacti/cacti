<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiManagementInvalidationFixture;
use ReflectionFunction;
use RuntimeException;

require dirname(__DIR__) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 2);
$config = ['library_path' => $root . '/lib'];
$database_default = 'management-lock-fixture';
$reads = $writes = 0;

// The publication helper is native. Only its database boundary reports a
// competing management request holding the lock; no writes may follow.
function db_table_exists($table) {
	return true;
}
function db_fetch_cell_prepared($sql, $arguments) {
	global $reads;

	if (!str_contains($sql, 'GET_LOCK')) {
		throw new RuntimeException('Unexpected read before publication lock');
	}
	$reads++;

	return 0;
}
function db_execute_prepared(...$arguments) {
	$GLOBALS['writes']++;
	throw new RuntimeException('Management mutation ran without its publication lock');
}

foreach (['api_data_source_cache_unused_lock', 'api_data_source_cache_unused_invalidate'] as $name) {
	cacti_fixture_source_function($root . '/lib/api_data_source.php', $name, __NAMESPACE__);
}
$file = $argv[1];
$name = $argv[2];
cacti_fixture_source_function($root . '/' . $file, $name, __NAMESPACE__);
$function = new ReflectionFunction(__NAMESPACE__ . '\\' . $name);
$values = $arguments = [];

foreach ($function->getParameters() as $index => $parameter) {
	$type = $parameter->getType();
	$values[$index] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : match ((string) $type) {
		'array', '?array' => [],
		'string', '?string' => '',
		'bool' => false,
		default => 0,
	};
	$arguments[$index] = &$values[$index];
}

try {
	$function->invokeArgs($arguments);
	throw new RuntimeException('Management entry point bypassed a busy publication lock');
} catch (RuntimeException $error) {
	if ($error->getMessage() !== 'Polling metadata is being edited by another request.' || $reads !== 1 || $writes !== 0) {
		throw $error;
	}
}
print json_encode(['lock_reads' => $reads, 'writes' => $writes], JSON_THROW_ON_ERROR) . "\n";
