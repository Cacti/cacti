<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

test('quoted script fields remain literal across parser and POSIX shell boundary cases', function () {
	$fixture = dirname(__DIR__, 3) . '/fixtures/Core/ScriptSubstitutionMatrix.php';
	$pipes   = [];
	$process = proc_open([PHP_BINARY, $fixture], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	expect(is_resource($process))->toBeTrue();
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	expect(proc_close($process))->toBe(0)->and($errors)->toBe('');
	expect(json_decode($output, true, 512, JSON_THROW_ON_ERROR)['passed'])->toBe(1310);
})->skip(PHP_OS_FAMILY === 'Windows', 'POSIX shell required');
