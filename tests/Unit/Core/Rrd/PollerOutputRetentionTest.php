<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('poller output retains samples until their selected write is acknowledged', function ($scenario) {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/Rrd/PollerOutputRetention.php', [$scenario]);
	expect($result['ok'])->toBeTrue();
})->with(['batch', 'drain', 'write-failure', 'empty-response', 'false-response', 'mixed-response', 'concurrent', 'incomplete', 'housekeeping', 'boost', 'delete-failure', 'batch-delete-failure']);
