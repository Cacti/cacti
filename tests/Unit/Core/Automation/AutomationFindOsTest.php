<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

it('matches discovery rules against complete and partial evidence using native SQL', function () {
	if (!getenv('CACTI_MATCH_DB_DSN')) {
		$this->markTestSkipped('Set CACTI_MATCH_DB_DSN for the isolated MariaDB matcher regression.');
	}
	$results = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/automation_matching_probe.php');
	expect($results)->toHaveCount(16);

	foreach ($results as $name => $result) {
		expect($result['actual'], $name)->toBe($result['expected']);
		expect($result['queries'], $name)->toBe(1);
	}
});
