<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;

$source = dirname(__DIR__, 3);
require $source . '/include/vendor/autoload.php';

$coverage = null;
$output   = getenv('CACTI_PR_RAW_COVERAGE');

if ($output !== false && $output !== '') {
	$filter = new Filter();
	$filter->includeFile($source . '/install/functions.php');
	$coverage = new CodeCoverage((new Selector())->forLineCoverage($filter), $filter);
	$coverage->start('retired-theme-cleanup');
}
$root   = sys_get_temp_dir() . '/cacti-retired-themes-' . bin2hex(random_bytes(8));
mkdir($root, 0700);

// Run the original cleanup against a disposable installation, never the checkout.
define('CACTI_PATH_BASE', $root);
define('CACTI_PATH_LIBRARY', $source . '/lib');
define('CACTI_PATH_LOG', $root);
define('CACTI_WEB', false);
require $source . '/include/global_constants.php';
require $source . '/lib/functions.php';
require $source . '/lib/database.php';
require $source . '/install/functions.php';

try {
	foreach (['carrot', 'hollyberry', 'modern'] as $theme) {
		mkdir($root . '/include/themes/' . $theme . '/nested', 0700, true);
		file_put_contents($root . '/include/themes/' . $theme . '/nested/main.css', 'fixture');
	}

	prune_deprecated_files();
	clearstatcache();

	foreach (['carrot', 'hollyberry'] as $theme) {
		if (file_exists($root . '/include/themes/' . $theme)) {
			throw new RuntimeException('Retired theme survived cleanup: ' . $theme);
		}
	}

	if (file_get_contents($root . '/include/themes/modern/nested/main.css') !== 'fixture') {
		throw new RuntimeException('Cleanup changed the active theme.');
	}

	// Repeating the upgrade cleanup must also tolerate already removed themes.
	prune_deprecated_files();
	print "PASS: retired theme trees removed, active theme retained, repeated cleanup succeeds\n";
} finally {
	install_rmdir_recursive($root . '/include', true);

	foreach (glob($root . '/*.log') as $log) {
		unlink($log);
	}
	rmdir($root);
}

if ($coverage !== null) {
	$coverage->stop();
	$lines  = [];
	$hashes = [];

	// Use the same statement normalization as Pest's Clover report. Xdebug's
	// raw offsets alone do not map constant array items to their source lines.
	foreach ($coverage->getData()->lineCoverage() as $file => $hits) {
		$lines[$file]  = array_map(static fn ($tests) => $tests === null ? -2 : count($tests), $hits);
		$hashes[$file] = hash_file('sha256', $file);
	}
	$report = ['root' => $source, 'coverage' => $lines, 'hashes' => $hashes];
	$path   = $output . '/retired-theme-cleanup-' . bin2hex(random_bytes(8)) . '.json';

	if (file_put_contents($path, json_encode($report, JSON_THROW_ON_ERROR)) === false) {
		throw new RuntimeException('Could not preserve retired theme cleanup coverage.');
	}
}
