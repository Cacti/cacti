<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

test('library coverage fixtures pass every assertion', function (string $fixture, array $arguments, int $passed) {
	$pipes   = [];
	$process = proc_open(array_merge([PHP_BINARY, dirname(__DIR__, 2) . '/fixtures/Core/' . $fixture], $arguments), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	expect(is_resource($process))->toBeTrue();
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	expect(proc_close($process))->toBe(0)->and($errors)->toBe('');
	$lines = explode("\n", trim($output));
	expect(json_decode(end($lines), true, 512, JSON_THROW_ON_ERROR))->toBe(['passed' => $passed]);
})->with([
	'helpers'           => ['LibraryHelpersCoverage.php', [], 41],
	'helpers no vendor' => ['LibraryHelpersCoverage.php', ['no-vendor'], 1],
	'aggregate'         => ['AggregateLibraryCoverage.php', [], 40],
	'scheduler'         => ['SchedulerLibraryCoverage.php', [], 23],
	'export'            => ['ExportLibraryCoverage.php', [], 75],
	'import'            => ['ImportLibraryCoverage.php', [], 64],
	'utility'           => ['UtilityLibraryCoverage.php', [], 94],
]);
