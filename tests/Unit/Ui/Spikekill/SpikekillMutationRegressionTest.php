<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

test('SpikeKill mutations preserve budgets, gaps, filters and failure outcomes', function () {
	$fixture = dirname(__DIR__, 3) . '/fixtures/Core/SpikekillMutationMatrix.php';
	$process = proc_open([PHP_BINARY, $fixture], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	expect(is_resource($process))->toBeTrue();
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	expect(proc_close($process))->toBe(0)->and($errors)->toBe('');
	$passed = json_decode($output, true, 512, JSON_THROW_ON_ERROR)['passed'];
	expect($passed)->toContain('rra-budget', 'nan-budget', 'preserve-gaps', 'zero-last', 'unknown-bounds',
		'dispatch-write', 'dispatch-backup', 'dispatch-restore', 'combined-results', 'dump-empty',
		'dump-failure', 'full-mutation-success', 'full-mutation-restore-failure');
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/spikekill.php');
	if (str_contains($source, 'selectedDataSources')) {
		expect($passed)->toContain('filter-traffic_in', 'filter-0', 'filter-1', 'filter-missing',
			'filter-statistics', 'absolute-gaps', 'gap-fill-zero');
	} else {
		expect($passed)->toContain('variance-nan-budget');
	}
});
