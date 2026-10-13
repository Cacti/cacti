<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

test('float debug guards preserve enabled messages and keep normal runs silent', function () {
	$fixture = dirname(__DIR__, 3) . '/fixtures/Core/SpikekillFloatDebug.php';
	$process = proc_open([PHP_BINARY, $fixture], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	expect(is_resource($process))->toBeTrue();
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	expect(proc_close($process))->toBe(0)->and($errors)->toBe('');
	expect(json_decode($output, true, 512, JSON_THROW_ON_ERROR))->toBe(['passed' => 4]);
});
