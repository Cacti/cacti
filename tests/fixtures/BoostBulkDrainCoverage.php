<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Request-coverage probe for the bulk Boost drain in poller_boost.php. The drain
// is a function of a CLI entry point that cannot be included under the unit
// driver, so BoostBulkDrainFailureProbe.php compiles it at its original line
// numbers in a child process. Run both scenarios here, with the same
// expectations as BoostBulkDrainFailureTest, so each child publishes its
// remapped evidence for the changed-line gate.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require dirname(__DIR__) . '/Helpers/IsolatedProbe.php';

$probe  = __DIR__ . '/BoostBulkDrainFailureProbe.php';
$middle = cacti_test_isolated_probe($probe, ['middle']);
$none   = cacti_test_isolated_probe($probe, ['none']);

// A rejected mid-stream flush keeps the rest of data source 1 for the retry,
// parks data source 1 for the parent to carry forward, leaves data source 2
// alone, and acknowledges the rest of the shard.
$retained = ['1@1700000000:42', '1@1700000060:42', '2@1700000000:42', '2@1700000060:42', '2@1700000120:42', '2@1700000180:42'];

if ($middle['result'] !== 8 || $middle['writes'] !== $retained || $middle['parked'] !== [1] || $middle['deletes'] !== 1) {
	throw new RuntimeException('A rejected Boost flush did not stop its data source.');
}

if ($none['result'] !== 8 || count($none['writes']) !== 8 || $none['deletes'] !== 1) {
	throw new RuntimeException('A clean Boost drain did not write and acknowledge every row.');
}
print "PASS: the bulk Boost drain stops a data source at its rejected flush\n";
