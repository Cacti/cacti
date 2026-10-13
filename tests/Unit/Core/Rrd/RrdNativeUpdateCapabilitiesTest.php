<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

test('native update batches retain timestamp order and version options', function ($version) {
	$fixture = dirname(__DIR__, 3) . '/fixtures/Core/Rrd/NativeUpdateCapabilities.php';
	exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr ' . escapeshellarg($fixture) . ' ' . escapeshellarg($version), $output, $status);
	expect($status)->toBe(0);
	$result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
	expect($result['count'])->toBe(6)->and($result['empty'])->toBe(0)->and($result['commands'])->toHaveCount(6);
})->with(['1.3.0', '1.4.0', '1.5.0', '1.8.0']);
