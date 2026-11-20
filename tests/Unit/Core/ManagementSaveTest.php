<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 2) . '/Helpers/IsolatedProbe.php';

test('management saves preserve device, collector and site dependencies', function ($scenario) {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 2) . '/fixtures/Core/ManagementSave.php', [$scenario]);
	expect($result['ok'])->toBeTrue();
})->with(['device-invalid', 'device-save-failure', 'device-disable', 'device-migrate', 'device-unchanged', 'limited-titles', 'site-rename', 'site-notes', 'site-save-failure', 'site-delete', 'site-delete-failure']);
