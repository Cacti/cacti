<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 2) . '/Helpers/IsolatedProbe.php';

test('device and report callers store plain text once and retain trusted report formatting', function ($scenario) {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 2) . '/fixtures/Core/MessageCallers.php', [$scenario]);
	expect($result['ok'])->toBeTrue()->and($result['checks'])->toBeGreaterThan(0);
})->with(['api', 'color', 'templates', 'graphs', 'automation', 'pollers', 'query', 'regex', 'report'])->skip(!getenv('BOOST_DB_HOST'), 'Requires the disposable MariaDB coverage service.');
