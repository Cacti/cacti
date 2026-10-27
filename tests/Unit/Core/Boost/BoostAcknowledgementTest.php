<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';
test('Boost requires RRDtool acknowledgement before advancing cursors or deleting queued samples (#8342)', function () {
    $result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/BoostAcknowledgement.php');
    expect($result['refused'])->toBeTrue()
        ->and($result['updates'])->toBe(1000)
        ->and($result['worker_processes'])->toBe(1)
        ->and($result['timeout_closed'])->toBeTrue();
});
