<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';
test('report history and items enforce the same owner/admin policy and actual parent (#7773)', function () {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/ReportOwnership.php');
	expect($result['cases'])->toBe(30)->and($result['foreign_history_preserved'])->toBeTrue();
});
