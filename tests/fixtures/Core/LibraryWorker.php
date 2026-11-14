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
/** Count one assertion and throw when the actual value is not identical to the expected value. */
function library_expect(mixed $actual, mixed $expected, string $message): void {
	global $assertions;
	$assertions++;

	if ($actual !== $expected) {
		throw new RuntimeException($message . ': ' . var_export($actual, true) . ' != ' . var_export($expected, true));
	}
}
/** Import completed child request coverage whose source still matches. */
function library_import_coverage(array $reports): void {
	foreach ($reports as $path) {
		$report = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

		foreach ($report['coverage'] as $file => $lines) {
			if (hash_file('sha256', $file) !== $report['hashes'][$file]) {
				throw new RuntimeException('Child coverage does not match ' . $file);
			}

			foreach ($lines as $line => $hits) {
				$GLOBALS['cacti_fixture_request_coverage'][$file][$line] = max($hits, $GLOBALS['cacti_fixture_request_coverage'][$file][$line] ?? -2);
			}
		}
	}
}
require __DIR__ . '/LibraryPluginCatalog.php';
library_plugin_catalog();
$GLOBALS['cacti_fixture_contract']['complete']   = true;
$GLOBALS['cacti_fixture_contract']['assertions'] = $assertions;
print json_encode(['scenario' => 'catalog', 'assertions' => $assertions], JSON_THROW_ON_ERROR) . PHP_EOL;
