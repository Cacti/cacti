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

// A rejection of the flush that closes data source 1, or of the last flush of
// the shard, parks that data source and still acknowledges the shard.
foreach (['boundary' => [1], 'tail' => [2]] as $scenario => $parked) {
	$result = cacti_test_isolated_probe($probe, [$scenario]);

	if ($result['result'] !== 8 || count($result['writes']) !== 8 || $result['parked'] !== $parked || $result['deletes'] !== 1) {
		throw new RuntimeException('A rejected ' . $scenario . ' Boost flush did not park its data source.');
	}
}
// The on-demand reader and the key acknowledgement in lib/boost.php: each
// failing step keeps the rows for the retry, and a clean run acknowledges them.
$demand = __DIR__ . '/BoostOnDemandFailureProbe.php';

foreach (['first' => 1, 'middle' => 2, 'ok-substring' => 1, 'archive-copy' => 0, 'temp-create' => 0, 'tail' => 4, 'ack-create' => 4, 'ack-insert' => 4, 'ack-delete' => 4] as $scenario => $writes) {
	$result = cacti_test_isolated_probe($demand, [$scenario]);

	if ($result['result'] !== -1 || $result['writes'] !== $writes || $result['deletes'] !== 0) {
		throw new RuntimeException('A failed on-demand Boost ' . $scenario . ' step did not keep its rows.');
	}
}
$clean = cacti_test_isolated_probe($demand, ['archive-ok']);
$empty = cacti_test_isolated_probe($demand, ['ack-empty']);

if ($clean['result'] !== 4 || $clean['deletes'] !== 2 || $empty['result'] !== 1 || $empty['deletes'] !== 0) {
	throw new RuntimeException('A clean on-demand Boost run did not acknowledge its rows.');
}

foreach (['deleted' => 'OK', 'query-failed' => 'ERROR: Unable to verify data source'] as $scenario => $expected) {
	$result = cacti_test_isolated_probe(__DIR__ . '/BoostSourceExistenceProbe.php', [$scenario]);

	if ($result['result'] !== $expected || $result['queries'] !== 1) {
		throw new RuntimeException('Boost did not distinguish a deleted data source from a failed read.');
	}
}
print "PASS: the bulk Boost drain stops a data source at its rejected flush\n";
