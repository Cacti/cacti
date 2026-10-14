<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

test('empty mappings are cached and failed mapping reads are retried', function ($scenario, $reads) {
	$fixture = dirname(__DIR__) . '/fixtures/PollerMappingCache.php';
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture) . ' ' . escapeshellarg($scenario), $output, $status);
	expect($status)->toBe(0);
	$result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
	expect($result)->toBe(['reads' => $reads, 'passes' => 5]);
})->with([['empty', 1], ['retry', 2]]);
