<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Runs every CsrfPostGuardBatch3Probe scenario in its own process. Each child
 * inherits the request-coverage environment and writes its own report, since
 * csrf_require_post() exits and cannot be exercised twice in one process.
 */
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require dirname(__DIR__, 2) . '/Helpers/IsolatedProbe.php';
$probe     = __DIR__ . '/CsrfPostGuardBatch3Probe.php';
$scenarios = [];
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe) . ' --list', $scenarios, $status);

if ($status !== 0 || $scenarios === []) {
	throw new RuntimeException('Could not list CSRF guard scenarios');
}

foreach ($scenarios as $scenario) {
	$verdict = cacti_test_isolated_probe($probe, explode(' ', $scenario));

	if (($verdict['ok'] ?? false) !== true) {
		throw new RuntimeException('CSRF guard decision mismatch: ' . $scenario);
	}
}
print 'PASS: ' . count($scenarios) . " CSRF POST guard decisions\n";
