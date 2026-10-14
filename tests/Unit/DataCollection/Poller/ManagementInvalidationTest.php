<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('management changes fail before mutation when another publisher owns the lock', function ($file, $function) {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/ManagementInvalidationProbe.php', [$file, $function]);
	expect($result)->toBe(['lock_reads' => 1, 'writes' => 0]);
})->with(json_decode(file_get_contents(dirname(__DIR__, 3) . '/fixtures/management-invalidation.json'), true, 512, JSON_THROW_ON_ERROR));
