<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('on-demand Boost stops before later timestamps after a rejected write', function ($scenario, $writes) {
	$r = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/BoostOnDemandFailureProbe.php', [$scenario]);
	expect($r['result'])->toBe(-1)->and($r['writes'])->toBe($writes)->and($r['deletes'])->toBe(0);
})->with([['first', 1], ['middle', 2], ['ok-substring', 1], ['archive-copy', 0], ['temp-create', 0],
	['tail', 4], ['ack-create', 4], ['ack-insert', 4], ['ack-delete', 4]]);

test('on-demand Boost acknowledges the fetched keys once every write and archive copy succeeds', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/BoostOnDemandFailureProbe.php', ['archive-ok']);
	expect($r['result'])->toBe(4)->and($r['writes'])->toBe(4)->and($r['deletes'])->toBe(2);
});

test('acknowledging no fetched keys succeeds without a database round trip', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/BoostOnDemandFailureProbe.php', ['ack-empty']);
	expect($r['result'])->toBe(1)->and($r['writes'])->toBe(0)->and($r['deletes'])->toBe(0);
});
