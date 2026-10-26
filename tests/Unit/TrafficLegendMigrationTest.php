<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

test('traffic migration preserves calculations and links across repeated imports', function () {
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/traffic_legend_migration.php'), $output, $status);
	expect($status)->toBe(0);
	expect(implode("\n", $output))->toStartWith('PASS: five templates');
});
