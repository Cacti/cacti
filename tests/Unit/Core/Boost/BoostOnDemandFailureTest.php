<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('on-demand Boost stops before later timestamps after a rejected write', function ($scenario, $writes) {
	$r = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/BoostOnDemandFailureProbe.php', [$scenario]);
	expect($r['result'])->toBe(-1)->and($r['writes'])->toBe($writes)->and($r['deletes'])->toBe(0);
})->with([['first', 1], ['middle', 2]]);
