<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('Boost acknowledgement batches preserve complete timestamp groups', function ($scenario, $ok, $remaining) {
	$r = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/BoostAcknowledgementProbe.php', [$scenario]);
	expect($r['result'])->toBe($ok)->and($r['batch_sizes'])->toBe([999, 2])->and($r['remaining'])->toHaveCount($remaining);

	if ($remaining) {
		expect(array_column($r['remaining'],'rrd_name'))->toBe(['in', 'out']);
	}
})->with([['success', true, 0], ['failed-delete', false, 2]]);
