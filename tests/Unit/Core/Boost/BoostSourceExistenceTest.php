<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('Boost distinguishes deleted sources from failed metadata reads', function ($scenario, $result) {
	$r = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/BoostSourceExistenceProbe.php', [$scenario]);
	expect($r['result'])->toBe($result)->and($r['queries'])->toBe(1);
})->with([['deleted', 'OK'], ['query-failed', 'ERROR: Unable to verify data source']]);
