<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.
test('RRD update capability checks are reused within batches and refreshed between calls', function () {
	$fixture = dirname(__DIR__, 3) . '/fixtures/Core/Rrd/UpdateCapabilities.php';
	exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr ' . escapeshellarg($fixture), $output, $status);
	expect($status)->toBe(0);
	$result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

	foreach ($result['cases'] as $case) {
		expect($case['count'])->toBe(6)->and($case['comparisons'])->toBe(1);

		foreach ($case['commands'] as $command) {
			expect(str_contains($command, '--skip-past-updates'))->toBe(version_compare($case['version'], '1.5.0', '>='));
		}
	}
	expect($result['empty_comparisons'])->toBe(0);
});
