<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.
use Symfony\Component\Process\Process;

test('native authentication and package boundaries handle hostile text and paths', function () {
	$fixture = dirname(__DIR__, 3) . '/fixtures/Core/Auth/BoundaryContracts.php';
	$process = new Process([PHP_BINARY, $fixture]);
	$process->mustRun();
	expect($process->getOutput())->toBe("Native authentication, repository, metadata, and theme contracts passed.\n");
	expect($process->getErrorOutput())->toBe('');
});
