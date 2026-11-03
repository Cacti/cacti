<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli' || getenv('CACTI_E2E_DISPOSABLE') !== '1' || ($argv[1] ?? '') !== 'catalog') {
	throw new RuntimeException('This worker requires a disposable catalog scenario');
}
$GLOBALS['cacti_fixture_contract'] = ['name' => 'library-worker', 'complete' => false, 'assertions' => 0,
	'producers'                                 => ['tests/fixtures/Core/LibraryWorker.php', 'tests/fixtures/Core/LibraryPluginCatalog.php',
		'tests/Helpers/RequestCoverage.php', 'tests/Helpers/SourceSlice.php', 'tests/Helpers/PhpSource.php', 'cli/fetch_plugins.php']];
require __DIR__ . '/../../Helpers/RequestCoverage.php';
require __DIR__ . '/../../../include/global.php';
date_default_timezone_set('UTC');
$_SESSION['sess_user_id'] = 1;
$assertions               = 0;
function library_expect(mixed $actual, mixed $expected, string $message): void {
	global $assertions;
	$assertions++;

	if ($actual !== $expected) {
		throw new RuntimeException($message . ': ' . var_export($actual, true) . ' != ' . var_export($expected, true));
	}
}
require __DIR__ . '/LibraryPluginCatalog.php';
library_plugin_catalog();
$GLOBALS['cacti_fixture_contract']['complete']   = true;
$GLOBALS['cacti_fixture_contract']['assertions'] = $assertions;
print json_encode(['scenario' => 'catalog', 'assertions' => $assertions], JSON_THROW_ON_ERROR) . PHP_EOL;
