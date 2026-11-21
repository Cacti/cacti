<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Runs a Pest suite inside this process so the changed-line gate can measure
// what it executes. The suite's source slices (cacti_fixture_source_function())
// are remapped to their original files when the process ends, which Pest's own
// Clover report cannot do for code compiled from a slice. Usage:
// php PestSuiteCoverage.php tests/phpunit-<name>.xml
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);

// Pest and its dependencies load thousands of files whose unexecuted lines would
// exhaust the probe's memory; only Cacti's own sources are of interest.
if (function_exists('xdebug_set_filter') && getenv('CACTI_COVERAGE_FILE')) {
	xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_EXCLUDE, [$root . '/include/vendor', $root . '/tests']);
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';

$configuration = $argv[1] ?? '';

if ($configuration === '' || !is_file($root . '/' . $configuration)) {
	fwrite(STDERR, "A Pest configuration under the repository is required.\n");
	exit(2);
}

$_SERVER['argv'] = ['pest', '--configuration=' . $configuration, '--colors=never', '--no-progress'];
$_SERVER['argc'] = count($_SERVER['argv']);
chdir($root);
require $root . '/include/vendor/pestphp/pest/bin/pest';
